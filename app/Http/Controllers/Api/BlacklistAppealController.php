<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlacklistAppeal;
use App\Models\Review;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Blacklist'ga tushgan driver yoki kompaniya apelyatsiya beradi.
 * Qarorni admin chiqaradi.
 */
class BlacklistAppealController extends Controller
{
    public function index(Request $request)
    {
        $subject = $this->subjectFor($request);

        return response()->json([
            'appeals' => BlacklistAppeal::where(
                $subject['type'] === Review::SUBJECT_DRIVER ? 'driver_profile_id' : 'carrier_id',
                $subject['model']->id
            )->latest()->get(),
            'is_blacklisted'   => $subject['model']->is_blacklisted,
            'blacklist_reason' => $subject['model']->blacklist_reason,
        ]);
    }

    public function store(Request $request, ReputationService $reputation)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:3000'],
        ]);

        $subject = $this->subjectFor($request);

        if (! $subject['model']->is_blacklisted) {
            throw ValidationException::withMessages([
                'reason' => ['Siz blacklist\'da emassiz.'],
            ]);
        }

        if ($reputation->hasPendingAppeal($subject['model'])) {
            throw ValidationException::withMessages([
                'reason' => ['Apelyatsiyangiz ko\'rib chiqilmoqda.'],
            ]);
        }

        $appeal = BlacklistAppeal::create([
            'subject_type'         => $subject['type'],
            'driver_profile_id'    => $subject['type'] === Review::SUBJECT_DRIVER ? $subject['model']->id : null,
            'carrier_id'           => $subject['type'] === Review::SUBJECT_CARRIER ? $subject['model']->id : null,
            'submitted_by_user_id' => $request->user()->id,
            'reason'               => $data['reason'],
        ]);

        return response()->json([
            'message' => 'Apelyatsiya yuborildi. Admin ko\'rib chiqadi.',
            'appeal'  => $appeal,
        ], 201);
    }

    protected function subjectFor(Request $request): array
    {
        $user = $request->user();

        if ($user->isCarrier() && $user->carrier) {
            return ['type' => Review::SUBJECT_CARRIER, 'model' => $user->carrier];
        }

        if ($user->isDriver() && $user->driverProfile) {
            return ['type' => Review::SUBJECT_DRIVER, 'model' => $user->driverProfile];
        }

        abort(404, 'Profil topilmadi.');
    }
}
