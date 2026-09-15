<?php

use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\JobSearchController;
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
        Route::post('send-code', [AuthController::class, 'sendCode']);
        Route::post('verify-code', [AuthController::class, 'verifyCode']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('scoring/criteria', [ScoringController::class, 'index']);

    /*
    | Driver tomoni — o'z profili, vakansiya qidirish, ariza berish.
    */
    Route::middleware('role:driver')->prefix('driver')->group(function () {
        Route::get('profile', [DriverProfileController::class, 'show']);
        Route::put('profile', [DriverProfileController::class, 'update']);
        Route::get('applications', [JobSearchController::class, 'applications']);

        Route::get('jobs', [JobSearchController::class, 'index']);
        Route::get('jobs/{jobPost}', [JobSearchController::class, 'show']);

        // Ariza berish uchun telefon yoki email tasdiqlangan bo'lishi shart.
        Route::middleware('account.verified')
            ->post('jobs/{jobPost}/apply', [JobSearchController::class, 'apply']);
    });

    /*
    | Carrier tomoni — kompaniya profili, obuna, vakansiyalar.
    */
    Route::middleware('role:carrier,admin')->prefix('carrier')->group(function () {
        Route::get('profile', [CarrierController::class, 'show']);
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
