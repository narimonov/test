<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverDocument;
use App\Models\DriverProfile;
use App\Services\DocumentRedactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Driver CDL va medical card'ni rasmga olib yuklaydi, maxfiy joylarni
 * belgilaydi. Tizim ularni qaytarib bo'lmaydigan qilib berkitadi, watermark
 * qo'yadi va PDF saqlaydi. Carrier faqat shu PDF'ni ko'radi.
 */
class DriverDocumentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'documents' => DriverDocument::where('driver_profile_id', $this->profileFor($request)->id)
                ->latest()
                ->get(),
            'types'          => config('documents.types'),
            'watermark_text' => config('documents.watermark_text'),
        ]);
    }

    public function store(Request $request, DocumentRedactionService $redaction)
    {
        $data = $request->validate([
            'type'                => ['required', Rule::in(array_keys(config('documents.types')))],
            'file'                => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . config('documents.max_upload_kb')],
            'document_expires_at' => ['nullable', 'date'],

            // Berkitiladigan joylar — rasm o'lchamiga nisbatan 0..1 koordinatalar.
            'redactions'          => ['nullable', 'array', 'max:20'],
            'redactions.*.x'      => ['required', 'numeric', 'min:0', 'max:1'],
            'redactions.*.y'      => ['required', 'numeric', 'min:0', 'max:1'],
            'redactions.*.w'      => ['required', 'numeric', 'min:0', 'max:1'],
            'redactions.*.h'      => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $profile = $this->profileFor($request);
        $disk = Storage::disk(config('documents.disk'));

        // Asl rasm private diskda qoladi — hech qachon tarqatilmaydi.
        $originalPath = $request->file('file')->store("documents/{$profile->id}/original", config('documents.disk'));

        $document = DriverDocument::create([
            'driver_profile_id'   => $profile->id,
            'type'                => $data['type'],
            'original_path'       => $originalPath,
            'redactions'          => $data['redactions'] ?? [],
            'watermark_text'      => config('documents.watermark_text'),
            'document_expires_at' => $data['document_expires_at'] ?? null,
            'status'              => 'processing',
        ]);

        $pdfRelative = "documents/{$profile->id}/{$document->id}-{$data['type']}.pdf";

        try {
            $redaction->process(
                $disk->path($originalPath),
                $disk->path($pdfRelative),
                $data['redactions'] ?? [],
                $document->watermark_text
            );

            $document->forceFill([
                'pdf_path'     => $pdfRelative,
                'status'       => 'ready',
                'processed_at' => now(),
            ])->save();
        } catch (RuntimeException $e) {
            $document->forceFill([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
            ])->save();

            return response()->json([
                'message'  => 'Hujjatni qayta ishlab bo\'lmadi: ' . $e->getMessage(),
                'document' => $document->fresh(),
            ], 422);
        }

        return response()->json(['document' => $document->fresh()], 201);
    }

    public function destroy(Request $request, DriverDocument $driverDocument)
    {
        abort_unless($driverDocument->driver_profile_id === $this->profileFor($request)->id, 403);

        $this->deleteFiles($driverDocument);
        $driverDocument->delete();

        return response()->json(['message' => 'Hujjat o\'chirildi.']);
    }

    /**
     * Berkitilgan PDF'ni ko'rsatadi. Driver o'zinikini, carrier esa faqat
     * o'ziga ariza bergan driverning hujjatini ko'ra oladi.
     */
    public function download(Request $request, DriverDocument $driverDocument)
    {
        abort_unless($this->canView($request, $driverDocument), 403, 'Bu hujjatni ko\'rishga ruxsat yo\'q.');
        abort_unless($driverDocument->status === 'ready' && $driverDocument->pdf_path, 404);

        $disk = Storage::disk(config('documents.disk'));

        abort_unless($disk->exists($driverDocument->pdf_path), 404);

        return response()->file($disk->path($driverDocument->pdf_path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $driverDocument->type . '.pdf"',
        ]);
    }

    // ------------------------------------------------------------------

    protected function canView(Request $request, DriverDocument $document): bool
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDriver()) {
            return $document->driver_profile_id === optional($user->driverProfile)->id;
        }

        if ($user->isCarrier() && $user->carrier) {
            // Kompaniya faqat o'ziga ariza bergan driverning hujjatini ko'radi.
            return $document->driverProfile
                ->applications()
                ->whereHas('jobPost', fn ($query) => $query->where('carrier_id', $user->carrier->id))
                ->exists();
        }

        return false;
    }

    protected function deleteFiles(DriverDocument $document): void
    {
        $disk = Storage::disk(config('documents.disk'));

        foreach ([$document->original_path, $document->pdf_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    protected function profileFor(Request $request): DriverProfile
    {
        $profile = DriverProfile::where('user_id', $request->user()->id)->first();

        abort_unless($profile, 404, 'Avval driver profilingizni yarating.');

        return $profile;
    }
}
