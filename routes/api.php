<?php

use App\Http\Controllers\Api\Admin\AdminModerationController;
use App\Http\Controllers\Api\Admin\AdminOverviewController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlacklistAppealController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\DriverDocumentController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\JobSearchController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ScoringController;
use App\Http\Controllers\Api\TalentPoolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — SPA shu endpointlar bilan ishlaydi
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Driver: o'z telefoni/emaili orqali tasdiqlash.
        Route::post('send-code', [AuthController::class, 'sendCode']);
        Route::post('verify-code', [AuthController::class, 'verifyCode']);

        /*
        | Kompaniya: kod FMCSA'dagi rasmiy kontaktga yuboriladi.
        | Bu yo'llar carrier.fmcsa middleware'idan tashqarida — aks holda
        | tasdiqlashning o'zi ham bloklanib qolardi.
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

    // Blacklist'dagi tomon apelyatsiya berishi uchun bu yo'llar ochiq qoladi.
    Route::get('appeals', [BlacklistAppealController::class, 'index']);
    Route::post('appeals', [BlacklistAppealController::class, 'store']);

    Route::get('reviews', [ReviewController::class, 'index']);
    Route::post('reviews', [ReviewController::class, 'store']);

    /*
    | Driver tomoni
    */
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('profile', [DriverProfileController::class, 'show']);
        Route::put('profile', [DriverProfileController::class, 'update']);
        Route::get('applications', [JobSearchController::class, 'applications']);

        // Hujjatlar: CDL va medical card.
        Route::get('documents', [DriverDocumentController::class, 'index']);
        Route::post('documents', [DriverDocumentController::class, 'store']);
        Route::delete('documents/{driverDocument}', [DriverDocumentController::class, 'destroy']);

        Route::get('jobs', [JobSearchController::class, 'index']);
        Route::get('jobs/{jobPost}', [JobSearchController::class, 'show']);

        // Ariza berish: tasdiqlangan va blacklist'da bo'lmagan driver uchun.
        Route::middleware(['account.verified', 'driver.allowed'])
            ->post('jobs/{jobPost}/apply', [JobSearchController::class, 'apply']);
    });

    /*
    | Kompaniya tomoni
    */
    Route::middleware('role:carrier,admin')->prefix('carrier')->group(function () {
        // FMCSA tasdig'igacha ko'rinadigan yagona ma'lumot.
        Route::get('profile', [CarrierController::class, 'show']);

        Route::middleware('carrier.fmcsa')->group(function () {
            Route::put('profile', [CarrierController::class, 'update']);
            Route::get('dashboard', [CarrierController::class, 'dashboard']);

            Route::post('subscription', [CarrierController::class, 'subscribe']);
            Route::delete('subscription', [CarrierController::class, 'cancelSubscription']);

            Route::put('scoring/overrides', [ScoringController::class, 'updateOverrides']);

            Route::apiResource('jobs', JobPostController::class)->parameters(['jobs' => 'jobPost']);

            /*
            | Pullik qism: arizachilar va driver bazasi faqat aktiv obuna bilan.
            */
            Route::middleware(['account.verified', 'carrier.subscribed'])->group(function () {
                Route::get('jobs/{jobPost}/applicants', [ApplicantController::class, 'index']);
                Route::put('applications/{application}/status', [ApplicantController::class, 'updateStatus']);

                Route::get('drivers', [TalentPoolController::class, 'index']);
                Route::post('drivers', [TalentPoolController::class, 'store']);
                Route::get('drivers/{driverProfile}', [TalentPoolController::class, 'show']);
                Route::put('drivers/{driverProfile}', [TalentPoolController::class, 'update']);
                Route::put('drivers/{driverProfile}/status', [TalentPoolController::class, 'updateStatus']);
            });
        });
    });

    // Berkitilgan PDF: driver o'zinikini, carrier ariza bergan drivernikini.
    Route::get('documents/{driverDocument}/pdf', [DriverDocumentController::class, 'download']);

    /*
    | Admin tomoni
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('overview', AdminOverviewController::class);

        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users/{user}/block', [AdminUserController::class, 'block']);
        Route::delete('users/{user}/block', [AdminUserController::class, 'unblock']);

        Route::get('appeals', [AdminModerationController::class, 'appeals']);
        Route::post('appeals/{appeal}/decision', [AdminModerationController::class, 'decideAppeal']);

        Route::post('blacklist', [AdminModerationController::class, 'setBlacklist']);
        Route::delete('reviews/{review}', [AdminModerationController::class, 'removeReview']);
        Route::post('carriers/{carrier}/recheck', [AdminModerationController::class, 'recheckCarrier']);
    });
});
