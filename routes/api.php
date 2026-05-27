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

Route::post('/telegram/link', [\App\Http\Controllers\Api\TelegramWebhookController::class, 'link'])
    ->name('telegram.link')
    ->middleware('throttle:30,1');

Route::prefix('telegram/auth')->group(function (): void {
    Route::post('/init', [TelegramAuthController::class, 'init'])->middleware('throttle:20,1');
    Route::get('/status/{token}', [TelegramAuthController::class, 'status'])->middleware('throttle:120,1');
    Route::post('/contact', [TelegramAuthController::class, 'contact'])->middleware('throttle:60,1');
});

Route::get('/vacancies/{id}', function (int $id) {
    $vacancy = \App\Models\Vacancy::with('company')
        ->where('is_active', true)
        ->find($id);

    if (! $vacancy) {
        return response()->json(['error' => 'Not found'], 404);
    }

    $types = collect((array) $vacancy->employment_type)
        ->map(fn($t) => \App\Enums\EmploymentType::tryFrom($t)?->label() ?? $t)
        ->join(', ');

    $salary = match (true) {
        (bool) $vacancy->salary_from && (bool) $vacancy->salary_to =>
            number_format((int) $vacancy->salary_from, 0, '.', ' ') . ' – ' .
            number_format((int) $vacancy->salary_to, 0, '.', ' ') . ' ' . $vacancy->currency,
        (bool) $vacancy->salary_from =>
            'від ' . number_format((int) $vacancy->salary_from, 0, '.', ' ') . ' ' . $vacancy->currency,
        default => null,
    };

    return response()->json([
        'id'              => $vacancy->id,
        'title'           => $vacancy->title,
        'company'         => $vacancy->company->name,
        'salary'          => $salary,
        'employment_type' => $types,
        'url'             => url('/jobs/' . $vacancy->slug),
    ]);
})->name('api.vacancies.show');

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
