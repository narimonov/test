<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\BlacklistAppeal;
use App\Models\Carrier;
use App\Models\DriverProfile;
use App\Models\JobPost;
use App\Models\Review;
use App\Models\User;

class AdminOverviewController extends Controller
{
    /** Platform-wide counters. */
    public function __invoke()
    {
        return response()->json([
            'users' => [
                'total'    => User::count(),
                'drivers'  => User::where('role', User::ROLE_DRIVER)->count(),
                'carriers' => User::where('role', User::ROLE_CARRIER)->count(),
                'blocked'  => User::whereNotNull('blocked_at')->count(),
                'new_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            ],
            'carriers' => [
                'total'          => Carrier::count(),
                'fmcsa_verified' => Carrier::whereNotNull('fmcsa_verified_at')->count(),
                'subscribed'     => Carrier::where('subscription_status', 'active')->count(),
                'blacklisted'    => Carrier::whereNotNull('blacklisted_at')->count(),
            ],
            'drivers' => [
                'total'       => DriverProfile::count(),
                'hired'       => DriverProfile::whereNotNull('hired_carrier_id')->count(),
                'blacklisted' => DriverProfile::whereNotNull('blacklisted_at')->count(),
            ],
            'activity' => [
                'open_jobs'       => JobPost::where('is_open', true)->count(),
                'applications'    => Application::count(),
                'reviews'         => Review::published()->count(),
                'negative_reviews' => Review::published()->where('is_negative', true)->count(),
                'pending_appeals' => BlacklistAppeal::pending()->count(),
            ],
        ]);
    }
}
