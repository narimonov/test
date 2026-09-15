<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Carrier;
use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CarrierController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['carrier' => $this->carrierFor($request)]);
    }

    public function update(Request $request)
    {
        $carrier = $this->carrierFor($request);

        $carrier->fill($request->validate([
            'company_name'  => ['sometimes', 'string', 'max:255'],
            'mc_number'     => ['nullable', 'string', 'max:50'],
            'dot_number'    => ['nullable', 'string', 'max:50'],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'website'       => ['nullable', 'string', 'max:255'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state'         => ['nullable', 'string', 'size:2'],
            'zip'           => ['nullable', 'string', 'max:10'],
            'about'         => ['nullable', 'string', 'max:5000'],
            'fleet_size'    => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]))->save();

        return response()->json(['carrier' => $carrier->fresh()]);
    }

    public function dashboard(Request $request)
    {
        $carrier = $this->carrierFor($request);
        $jobIds = JobPost::where('carrier_id', $carrier->id)->pluck('id');

        $byStatus = Application::whereIn('job_post_id', $jobIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byTier = Application::whereIn('job_post_id', $jobIds)
            ->selectRaw('tier, count(*) as total')
            ->groupBy('tier')
            ->pluck('total', 'tier');

        return response()->json([
            'carrier' => $carrier,
            'stats'   => [
                'open_jobs'      => JobPost::where('carrier_id', $carrier->id)->where('is_open', true)->count(),
                'total_jobs'     => $jobIds->count(),
                'applications'   => Application::whereIn('job_post_id', $jobIds)->count(),
                'new_this_week'  => Application::whereIn('job_post_id', $jobIds)
                    ->where('created_at', '>=', now()->subWeek())->count(),
                'by_status'      => $byStatus,
                'by_tier'        => $byTier,
            ],
        ]);
    }

    /**
     * Obunani aktivlashtirish.
     *
     * To'lov provayderi (Stripe va h.k.) hali ulanmagan — bu yerda obuna
     * qo'lda aktivlashtiriladi. Stripe ulanganda webhook shu metodni almashtiradi.
     */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(['starter', 'pro', 'enterprise'])],
        ]);

        $user = $request->user();

        if (! $user->is_verified) {
            return response()->json([
                'message' => 'Obuna ochishdan oldin telefon yoki emailni tasdiqlang.',
                'code'    => 'verification_required',
            ], 403);
        }

        $carrier = $this->carrierFor($request);

        $carrier->forceFill([
            'subscription_plan'       => $data['plan'],
            'subscription_status'     => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return response()->json([
            'message' => 'Obuna aktivlashtirildi.',
            'carrier' => $carrier->fresh(),
        ]);
    }

    public function cancelSubscription(Request $request)
    {
        $carrier = $this->carrierFor($request);

        $carrier->forceFill(['subscription_status' => 'cancelled'])->save();

        return response()->json([
            'message' => 'Obuna bekor qilindi.',
            'carrier' => $carrier->fresh(),
        ]);
    }

    protected function carrierFor(Request $request): Carrier
    {
        return Carrier::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['company_name' => $request->user()->name]
        );
    }
}
