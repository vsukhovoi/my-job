<?php

declare(strict_types=1);

use App\Http\Controllers\CityController;
use App\Http\Controllers\ResumeWizardController;
use App\Http\Controllers\TelegramAuthController;
use Illuminate\Support\Facades\Route;

Route::post('/mono/webhook', \App\Http\Controllers\Api\MonobankWebhookController::class)
    ->name('mono.webhook');

Route::post('/telegram/webhook/callback', [\App\Http\Controllers\Api\TelegramWebhookController::class, 'callback'])
    ->name('telegram.webhook.callback')
    ->middleware('throttle:60,1');

Route::prefix('telegram/auth')->group(function (): void {
    Route::post('/init', [TelegramAuthController::class, 'init'])->middleware('throttle:20,1');
    Route::get('/status/{token}', [TelegramAuthController::class, 'status'])->middleware('throttle:120,1');
});

Route::prefix('cities')->group(function (): void {
    Route::get('/', [CityController::class, 'index']);
    Route::get('/search', [CityController::class, 'search']);
    Route::post('/nearest', [CityController::class, 'nearest']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/resumes', [ResumeWizardController::class, 'index']);
    Route::post('/resumes', [ResumeWizardController::class, 'store']);

    Route::get('/resumes/{resume}', [ResumeWizardController::class, 'show']);
    Route::patch('/resumes/{resume}', [ResumeWizardController::class, 'update']);
    Route::delete('/resumes/{resume}', [ResumeWizardController::class, 'destroy']);

    Route::post('/resumes/{resume}/send-verification-code', [ResumeWizardController::class, 'sendVerificationCode']);
    Route::post('/resumes/{resume}/verify-email', [ResumeWizardController::class, 'verifyEmail']);

    Route::post('/resumes/{resume}/experiences', [ResumeWizardController::class, 'storeExperience']);
    Route::patch('/resumes/{resume}/experiences/{experience}', [ResumeWizardController::class, 'updateExperience']);
    Route::delete('/resumes/{resume}/experiences/{experience}', [ResumeWizardController::class, 'destroyExperience']);

    Route::post('/resumes/{resume}/skills', [ResumeWizardController::class, 'storeSkill']);
    Route::delete('/resumes/{resume}/skills/{skill}', [ResumeWizardController::class, 'destroySkill']);

    Route::post('/resumes/{resume}/publish', [ResumeWizardController::class, 'publish']);

    Route::get('/resumes/{resume}/stepper-status', [ResumeWizardController::class, 'stepperStatus']);
});
