<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Serves the privacy notice and records acceptance against the version the
 * user actually saw.
 */
class PrivacyController extends Controller
{
    public function show()
    {
        return response()->json([
            'version'        => config('privacy.version'),
            'effective_date' => config('privacy.effective_date'),
            'contact_email'  => config('privacy.contact_email'),
        ]);
    }

    public function accept(Request $request)
    {
        $data = $request->validate([
            'version'     => ['required', 'string'],
            'sms_consent' => ['nullable', 'boolean'],
            'mvr_consent' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'privacy_accepted_at' => now(),
            'privacy_version'     => $data['version'],
            'consent_ip'          => $request->ip(),
            // TCPA: SMS consent is separate from accepting the privacy notice.
            'sms_consent_at'      => ! empty($data['sms_consent']) ? now() : $user->sms_consent_at,
            // FCRA/DPPA: authorisation to pull a driving record for employment.
            'mvr_consent_at'      => ! empty($data['mvr_consent']) ? now() : $user->mvr_consent_at,
        ])->save();

        return response()->json([
            'message' => 'Thank you — your choices have been recorded.',
            'user'    => $user->fresh(),
        ]);
    }
}
