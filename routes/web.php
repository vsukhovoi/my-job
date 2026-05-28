<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\WebhookController as PaymentWebhookController;
use App\Http\Controllers\Payments\WfpFormController;
// use App\Http\Controllers\StripeWebhookController; // Stripe вимкнено
use App\Http\Controllers\TelegramAuthController;
use App\Models\Category;
use App\Models\Interview;
use App\Models\Resume;
use App\Models\Vacancy;
use App\Services\InterviewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ── Public ─────────────────────────────────────────────────────────────────
Volt::route('/', 'pages.jobs.index')->name('home');
Route::view('/for-employers', 'pages.for-employers')->name('for-employers');
Volt::route('/pricing', 'pages.pricing')->name('pricing');
Route::view('/about', 'pages.about')->name('about');
Route::view('/offer', 'pages.offer')->name('offer');
Route::view('/contacts', 'pages.contacts')->name('contacts');

// ── Interview confirmation (public, token-based) ────────────────────────────
Route::get('/interview/{token}/confirm', function (string $token, InterviewService $service) {
    try {
        $interview = $service->confirm($token);
        return view('interview.response', [
            'message' => 'Участь підтверджено! Чекаємо вас ' . $interview->scheduled_at->format('d.m.Y о H:i') . '.',
            'type'    => 'success',
        ]);
    } catch (\Throwable) {
        abort(404);
    }
})->name('interview.confirm');

Route::get('/interview/{token}/cancel', function (string $token, InterviewService $service) {
    $interview = Interview::where('confirm_token', $token)->firstOrFail();
    $service->cancel($interview, 'Скасовано кандидатом');
    return view('interview.response', [
        'message' => 'Ваша участь скасована. Якщо це помилка — зв\'яжіться з нами.',
        'type'    => 'warning',
    ]);
})->name('interview.cancel');
Volt::route('/jobs/{vacancy:slug}', 'pages.jobs.show')->name('jobs.show');

// ── Async Interview (deep links для Telegram-кнопок) ────────────────────────
Volt::route('/interview-request/{interviewRequestId}/respond', 'candidate.interview-response-form')
    ->middleware(['auth'])
    ->name('interview.respond');

Volt::route('/interview-request/{interviewRequestId}/view', 'employer.interview-response-view')
    ->middleware(['auth'])
    ->name('interview.view');

// Sitemap (cached 24 h)
Route::get('/sitemap.xml', function () {
    $xml = Cache::remember('sitemap', 86400, function () {
        $vacancies = Vacancy::where('is_active', true)->get(['slug', 'updated_at']);

        return response()->view('sitemap', compact('vacancies'))->getContent();
    });

    return response($xml)->header('Content-Type', 'application/xml');
})->name('sitemap');

// ── Telegram Auth ───────────────────────────────────────────────────────────
Route::get('/telegram/auth/login/{token}', [TelegramAuthController::class, 'login'])
    ->name('telegram.auth.login')
    ->middleware('throttle:10,1');

// ── Social Auth ─────────────────────────────────────────────────────────────
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\SocialAuthController::class, 'callback'])
    ->name('social.callback');

// ── Auth ────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->get('dashboard', function () {
    return match (auth()->user()->role) {
        \App\Enums\UserRole::Employer  => redirect()->route('employer.dashboard'),
        \App\Enums\UserRole::Candidate => redirect()->route('seeker.dashboard'),
        default                        => redirect('/admin'),
    };
})->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// ── Employer Dashboard ──────────────────────────────────────────────────────
Route::middleware(['auth', 'role:employer'])
    ->prefix('dashboard/employer')
    ->name('employer.')
    ->group(function () {
        Volt::route('/', 'pages.employer.dashboard')->name('dashboard');
        Volt::route('/profile', 'pages.employer.profile')->name('profile');
        Volt::route('/applicants/{vacancyId}', 'pages.employer.applicants')->name('applicants');
        Volt::route('/candidates', 'pages.employer.candidates')->name('candidates');
        Volt::route('/candidates/{applicationId}', 'pages.employer.candidate-detail')->name('candidate.detail');
        Volt::route('/templates', 'pages.employer.message-templates')->name('message.templates');
        Volt::route('/analytics', 'pages.employer.analytics')->name('analytics');
        Volt::route('/vacancies/create', 'pages.employer.vacancies.edit')
            ->middleware('subscription.job_limit')
            ->name('vacancies.create');
        Volt::route('/vacancies/{vacancyId}/edit', 'pages.employer.vacancies.edit')->name('vacancies.edit');

        // Stripe Checkout redirect (тимчасово відключено)
        Route::get('/vacancies/{vacancy}/promote', function (Vacancy $vacancy) {
            return redirect()->route('jobs.show', $vacancy->slug)
                ->with('info', 'Платне просування вакансій буде доступне найближчим часом.');
        })->name('vacancies.promote');

        Volt::route('/vacancies/{vacancy}/payment/success', 'pages.employer.vacancies.payment-success')
            ->name('vacancies.payment.success');

        Route::get('/vacancies/{vacancy}/payment/cancel', function (Vacancy $vacancy) {
            abort_unless($vacancy->company_id === auth()->user()->company?->id, 403);
            return redirect()
                ->route('employer.billing')
                ->with('warning', 'Оплату скасовано. Виберіть тариф та спробуйте ще раз.');
        })->name('vacancies.payment.cancel');

        // ── Білінг ────────────────────────────────────────────────────────
        Volt::route('/billing', 'pages.employer.billing')->name('billing');
        Volt::route('/billing/checkout/addon/{addon}', 'pages.employer.billing-checkout-addon')->name('billing.checkout.addon');
        Volt::route('/billing/checkout/{plan}', 'pages.employer.billing-checkout')->name('billing.checkout');
        Volt::route('/billing/invoice/{invoiceNumber}', 'billing.invoice')->name('billing.invoice.show');
        Route::match(['GET', 'POST'], '/billing/success', function () {
            return view('employer.billing-success');
        })->name('billing.success');
        Volt::route('/payments', 'pages.employer.payments')->name('payments');
        Volt::route('/my-profile', 'pages.employer.my-profile')->name('my-profile');
        Volt::route('/support', 'pages.employer.support')->name('support');
        Volt::route('/telegram-guide', 'pages.employer.telegram-guide')->name('telegram-guide');
    });

// ── Resume Wizard ───────────────────────────────────────────────────────────
Route::prefix('resumes')
    ->name('resumes.')
    ->group(function () {
        Route::get('/create', function () {
            $resumeId = session('pending_resume_id');

            if ($resumeId) {
                $resume = \App\Models\Resume::find($resumeId);
                if ($resume && ($resume->user_id === null || $resume->user_id === auth()->id())) {
                    return view('resumes.create', compact('resume'));
                }
            }

            $resume = \App\Models\Resume::create([
                'user_id' => auth()->id(),
                'title'   => 'Нове резюме',
                'status'  => 'draft',
            ]);
            session(['pending_resume_id' => $resume->id]);

            return view('resumes.create', compact('resume'));
        })->name('create');

        Route::get('/{resume}/edit', function (Resume $resume) {
            abort_unless(auth()->id() === $resume->user_id, 403);
            return view('resumes.edit', compact('resume'));
        })->name('edit');

        Route::get('/{resume}/export/pdf', function (Resume $resume) {
            abort_unless(auth()->id() === $resume->user_id, 403);
            // PDF export — підключити бібліотеку (наприклад, barryvdh/laravel-dompdf) у майбутньому
            abort(501, 'Експорт PDF ще не реалізовано');
        })->name('export.pdf');

        Route::get('/{resume}/file/download', [\App\Http\Controllers\Seeker\ResumeFileController::class, 'download'])
            ->middleware('auth')
            ->name('file.download');
    });

Volt::route('/resumes/{resume}', 'pages.resumes.show')->name('resumes.show');

// ── Seeker Dashboard ────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:candidate'])
    ->prefix('dashboard/seeker')
    ->name('seeker.')
    ->group(function () {
        Volt::route('/', 'pages.seeker.dashboard')->name('dashboard');
        Volt::route('/resumes', 'pages.seeker.resumes')->name('resumes');
        Volt::route('/applications', 'pages.seeker.applications')->name('applications');
        Volt::route('/applications/{applicationId}', 'pages.seeker.application-detail')->name('application.detail');
        Volt::route('/interviews', 'pages.seeker.interviews')->name('interviews');
        Volt::route('/offers', 'pages.seeker.offers')->name('offers');
        Volt::route('/saved', 'pages.seeker.saved-vacancies')->name('saved');
        Volt::route('/recommended', 'pages.seeker.recommended')->name('recommended');
        Volt::route('/profile', 'pages.seeker.profile')->name('profile');
        Volt::route('/messages', 'pages.seeker.support-threads')->name('messages');
        Volt::route('/messages/{threadId}', 'pages.seeker.support-thread-detail')->name('message.detail');
        Volt::route('/telegram-guide', 'pages.seeker.telegram-guide')->name('telegram-guide');
    });

// ── Payment callbacks ───────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/payment/success', fn() => view('payment.success'))->name('payment.success');
    Route::get('/payment/cancel', fn() => view('payment.cancel'))->name('payment.cancel');
});

// Stripe вимкнено — маршрут деактивовано
// Route::post('/stripe/webhook', StripeWebhookController::class)
//     ->name('stripe.webhook')
//     ->middleware('throttle:60,1');

// ── Unified Payment Webhooks (всі провайдери) ────────────────────────────────
Route::post('/webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payments')
    ->middleware('throttle:60,1');

// ── WayForPay: auto-submit form page (form checkout mode) ────────────────────
Route::get('/payments/wfp/form/{orderId}', [WfpFormController::class, 'show'])
    ->name('payments.wfp.form')
    ->middleware('auth');

// ── Health Check ────────────────────────────────────────────────────────────
Route::get('/health', function () {
    // Check DB
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = 'ok';
    } catch (\Throwable) {
        $db = 'error';
    }

    // Check cache (database driver)
    try {
        \Illuminate\Support\Facades\Cache::put('_health', 1, 5);
        $cache = \Illuminate\Support\Facades\Cache::get('_health') === 1 ? 'ok' : 'error';
    } catch (\Throwable) {
        $cache = 'error';
    }

    $status = ($db === 'ok' && $cache === 'ok') ? 200 : 503;

    return response()->json([
        'status' => $status === 200 ? 'ok' : 'degraded',
        'db'     => $db,
        'cache'  => $cache,
    ], $status);
})->name('health');


require __DIR__ . '/auth.php';
