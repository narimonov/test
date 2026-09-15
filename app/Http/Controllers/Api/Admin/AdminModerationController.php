<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlacklistAppeal;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\Review;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminModerationController extends Controller
{
    /** Ko'rib chiqilishi kerak bo'lgan apelyatsiyalar. */
    public function appeals(Request $request)
    {
        $status = $request->query('status', 'pending');

        $appeals = BlacklistAppeal::query()
            ->with([
                'driverProfile:id,first_name,last_name,blacklisted_at,blacklist_reason',
                'carrier:id,company_name,dot_number,blacklisted_at,blacklist_reason',
                'submittedBy:id,name,email,role',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25);

        return response()->json($appeals);
    }

    /** Apelyatsiya bo'yicha qaror. */
    public function decideAppeal(Request $request, BlacklistAppeal $appeal, ReputationService $reputation)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note'     => ['nullable', 'string', 'max:2000'],
        ]);

        if ($appeal->status !== 'pending') {
            return response()->json(['message' => 'Bu apelyatsiya bo\'yicha qaror allaqachon chiqarilgan.'], 422);
        }

        $subject = $appeal->subject_type === Review::SUBJECT_DRIVER
            ? $appeal->driverProfile
            : $appeal->carrier;

        if ($data['decision'] === 'approved' && $subject) {
            $reputation->liftBlacklist($subject, $data['note'] ?? 'Apelyatsiya qabul qilindi');
        }

        $appeal->forceFill([
            'status'              => $data['decision'],
            'decision_note'       => $data['note'] ?? null,
            'decided_by_user_id'  => $request->user()->id,
            'decided_at'          => now(),
        ])->save();

        return response()->json([
            'message' => $data['decision'] === 'approved'
                ? 'Apelyatsiya qabul qilindi, blacklist olib tashlandi.'
                : 'Apelyatsiya rad etildi.',
            'appeal'  => $appeal->fresh(),
        ]);
    }

    /** Admin qo'lda blacklist qo'yishi/olib tashlashi. */
    public function setBlacklist(Request $request, ReputationService $reputation)
    {
        $data = $request->validate([
            'subject_type' => ['required', Rule::in([Review::SUBJECT_DRIVER, Review::SUBJECT_CARRIER])],
            'subject_id'   => ['required', 'integer'],
            'action'       => ['required', Rule::in(['add', 'remove'])],
            'reason'       => ['required_if:action,add', 'nullable', 'string', 'max:255'],
        ]);

        $subject = $data['subject_type'] === Review::SUBJECT_DRIVER
            ? DriverProfile::findOrFail($data['subject_id'])
            : Carrier::findOrFail($data['subject_id']);

        $subject = $data['action'] === 'add'
            ? $reputation->blacklist($subject, $data['reason'])
            : $reputation->liftBlacklist($subject, $data['reason'] ?? 'Admin qarori');

        return response()->json([
            'message' => $data['action'] === 'add' ? 'Blacklist\'ga qo\'shildi.' : 'Blacklist\'dan olindi.',
            'subject' => $subject,
        ]);
    }

    /** Nomaqbul review'ni olib tashlash. */
    public function removeReview(Request $request, Review $review)
    {
        $review->update(['status' => 'removed', 'is_negative' => false]);

        return response()->json(['message' => 'Review olib tashlandi.', 'review' => $review->fresh()]);
    }

    /** Kompaniyani FMCSA bo'yicha qayta tekshirish. */
    public function recheckCarrier(Carrier $carrier, \App\Services\CarrierVerificationService $service)
    {
        return response()->json([
            'message' => 'FMCSA holati yangilandi.',
            'carrier' => $service->recheck($carrier),
        ]);
    }
}
