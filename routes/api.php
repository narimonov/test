<?php

use App\Http\Controllers\Api\Admin\AdminModerationController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminOverviewController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BlacklistAppealController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\CarrierReputationController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DriverDocumentController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\JobSearchController;
use App\Http\Controllers\Api\MatchingController;
use App\Http\Controllers\Api\MvrController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\RecruitingRequestController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TalentPoolController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\TravelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes — everything the SPA talks to
|--------------------------------------------------------------------------
*/

/*
| Public: the privacy notice has to be readable before signing up, and the
| gateway callbacks authenticate themselves by signature.
*/
Route::get('privacy', [PrivacyController::class, 'show']);
Route::post('payments/{provider}/callback', [BillingController::class, 'callback']);
Route::post('telegram/webhook', TelegramWebhookController::class);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Drivers verify with their own phone or email.
        Route::post('send-code', [AuthController::class, 'sendCode']);
        Route::post('verify-code', [AuthController::class, 'verifyCode']);

        /*
        | Carriers verify with a code sent to their FMCSA contact. These sit
        | outside the carrier.fmcsa middleware, or verification itself would
        | be blocked.
        */
        Route::middleware('role:carrier')->prefix('carrier')->group(function () {
            Route::get('channels', [AuthController::class, 'carrierChannels']);
            Route::post('send-code', [AuthController::class, 'sendCarrierCode']);
            Route::post('verify-code', [AuthController::class, 'verifyCarrierCode']);
        });
    });
});

Route::middleware(['auth:sanctum', 'not.blocked'])->group(function () {
    Route::get('scoring/criteria', [ScoringController::class, 'index']);

    // Left open so a blacklisted party can still appeal.
    Route::get('appeals', [BlacklistAppealController::class, 'index']);
    Route::post('appeals', [BlacklistAppealController::class, 'store']);

    Route::get('reviews', [ReviewController::class, 'index']);
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::get('reviews/mine', [ReviewController::class, 'mine']);
    Route::get('review-proofs/{reviewProof}', [ReviewController::class, 'downloadProof']);

    Route::post('privacy/accept', [PrivacyController::class, 'accept']);

    /*
    | Support widget — available on every page, for every role.
    */
    Route::prefix('support')->group(function () {
        Route::get('/', [SupportController::class, 'show']);
        Route::post('/', [SupportController::class, 'store']);
        Route::get('poll', [SupportController::class, 'poll']);
        Route::post('escalate', [SupportController::class, 'escalate']);
    });

    /*
    | Carrier <-> driver chat.
    */
    /*
    | Onboarding checklist — carriers see everyone they hired, drivers see
    | their own.
    */
    Route::get('onboarding', [OnboardingController::class, 'index']);
    Route::get('onboarding/{onboarding}', [OnboardingController::class, 'show']);
    Route::put('onboarding/{onboarding}/steps/{step}', [OnboardingController::class, 'updateStep']);

    // A driver should be able to look a company up before applying.
    Route::get('carriers/{carrier}/reputation', [CarrierReputationController::class, 'show']);
    Route::post('carriers/{carrier}/reputation/refresh', [CarrierReputationController::class, 'refresh']);

    Route::get('conversations', [ConversationController::class, 'index']);
    Route::get('conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'storeMessage']);
    Route::get('attachments/{attachment}', [ConversationController::class, 'downloadAttachment']);

    /*
    | Driver side
    */
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('profile', [DriverProfileController::class, 'show']);
        Route::put('profile', [DriverProfileController::class, 'update']);
        Route::get('applications', [JobSearchController::class, 'applications']);

        // CDL and medical card.
        Route::get('documents', [DriverDocumentController::class, 'index']);
        Route::post('documents', [DriverDocumentController::class, 'store']);
        Route::delete('documents/{driverDocument}', [DriverDocumentController::class, 'destroy']);

        Route::get('jobs', [JobSearchController::class, 'index']);
        Route::get('jobs/{jobPost}', [JobSearchController::class, 'show']);

        // Applying needs a verified account that is not blacklisted.
        Route::middleware(['account.verified', 'driver.allowed'])
            ->post('jobs/{jobPost}/apply', [JobSearchController::class, 'apply']);
    });

    /*
    | Carrier side
    */
    Route::middleware('role:carrier,admin')->prefix('carrier')->group(function () {
        // The only thing visible before FMCSA verification.
        Route::get('profile', [CarrierController::class, 'show']);

        Route::middleware('carrier.fmcsa')->group(function () {
            Route::put('profile', [CarrierController::class, 'update']);
            Route::get('dashboard', [CarrierController::class, 'dashboard']);

            Route::get('plans', [BillingController::class, 'plans']);
            Route::post('checkout', [BillingController::class, 'checkout']);
            Route::post('checkout/confirm', [BillingController::class, 'confirmFake']);
            Route::delete('subscription', [CarrierController::class, 'cancelSubscription']);

            Route::post('conversations', [ConversationController::class, 'store']);

            Route::get('recruiting-requests', [RecruitingRequestController::class, 'index']);
            Route::post('recruiting-requests', [RecruitingRequestController::class, 'store']);

            Route::put('scoring/overrides', [ScoringController::class, 'updateOverrides']);

            Route::apiResource('jobs', JobPostController::class)->parameters(['jobs' => 'jobPost']);

            /*
            | Paid: applicants and the driver pool need an active plan.
            */
            Route::middleware(['account.verified', 'carrier.subscribed'])->group(function () {
                Route::get('jobs/{jobPost}/applicants', [ApplicantController::class, 'index']);
                Route::get('jobs/{jobPost}/matches', MatchingController::class);
                Route::put('applications/{application}/status', [ApplicantController::class, 'updateStatus']);

                /*
                | Motor vehicle records. Ordering needs the driver's recorded
                | authorisation; a record pulled recently is reused instead.
                */
                Route::get('mvr/rates', [MvrController::class, 'rates']);
                Route::get('drivers/{driverProfile}/mvr', [MvrController::class, 'index']);
                Route::get('drivers/{driverProfile}/mvr/quote', [MvrController::class, 'quote']);
                Route::post('drivers/{driverProfile}/mvr', [MvrController::class, 'store']);
                Route::post('mvr/{mvrReport}/refresh', [MvrController::class, 'refresh']);

                /*
                | Getting a hired driver to orientation.
                */
                Route::get('travel', [TravelController::class, 'index']);
                Route::post('travel/search', [TravelController::class, 'search']);
                Route::post('drivers/{driverProfile}/travel', [TravelController::class, 'book']);
                Route::post('drivers/{driverProfile}/travel/record', [TravelController::class, 'record']);

                Route::get('drivers', [TalentPoolController::class, 'index']);
                Route::post('drivers', [TalentPoolController::class, 'store']);
                Route::get('drivers/{driverProfile}', [TalentPoolController::class, 'show']);
                Route::put('drivers/{driverProfile}', [TalentPoolController::class, 'update']);
                Route::put('drivers/{driverProfile}/status', [TalentPoolController::class, 'updateStatus']);
            });
        });
    });

    // The redacted PDF: drivers see their own, carriers see applicants'.
    Route::get('documents/{driverDocument}/pdf', [DriverDocumentController::class, 'download']);

    /*
    | Admin side
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('overview', AdminOverviewController::class);

        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users/{user}/block', [AdminUserController::class, 'block']);
        Route::delete('users/{user}/block', [AdminUserController::class, 'unblock']);

        Route::get('appeals', [AdminModerationController::class, 'appeals']);
        Route::post('appeals/{appeal}/decision', [AdminModerationController::class, 'decideAppeal']);

        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::post('reviews/{review}/contacted', [AdminReviewController::class, 'markContacted']);
        Route::post('reviews/{review}/decision', [AdminReviewController::class, 'decide']);

        Route::post('blacklist', [AdminModerationController::class, 'setBlacklist']);
        Route::delete('reviews/{review}', [AdminModerationController::class, 'removeReview']);
        Route::post('carriers/{carrier}/recheck', [AdminModerationController::class, 'recheckCarrier']);
    });
});
