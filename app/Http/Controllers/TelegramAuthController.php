<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PendingVacancyService;
use App\Services\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramAuthService $authService,
    ) {}

    /**
     * POST /api/telegram/auth/init
     * Генерує сесію та повертає deep link.
     */
    public function init(Request $request): JsonResponse
    {
        $role = in_array($request->input('role'), ['candidate', 'employer'], true)
            ? $request->input('role')
            : 'candidate';

        $data = $this->authService->generateSession($role);

        return response()->json($data);
    }

    /**
     * GET /api/telegram/auth/status/{token}
     * Polling — перевірка статусу сесії.
     */
    public function status(string $token): JsonResponse
    {
        return response()->json($this->authService->getStatus($token));
    }

    /**
     * POST /api/telegram/auth/contact
     * Отримує контакт від бота та авторизує Telegram-сесію.
     * Захищений через X-Telegram-Webhook-Token.
     *
     * @return JsonResponse{matched: bool, user_id?: int, error?: string}
     */
    public function contact(Request $request): JsonResponse
    {
        $token = $request->header('X-Telegram-Webhook-Token');

        if ($token !== config('services.telegram.webhook_token')) {
            Log::warning('TelegramAuthController@contact: invalid webhook token', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'auth_token'       => 'required|string',
            'telegram_user_id' => 'required|integer',
            'phone'            => 'required|string',
            'first_name'       => 'nullable|string|max:255',
            'last_name'        => 'nullable|string|max:255',
            'username'         => 'nullable|string|max:255',
        ]);

        $matched = $this->authService->processContact(
            $validated['auth_token'],
            (int) $validated['telegram_user_id'],
            $validated['phone'],
        );

        if (! $matched) {
            return response()->json(['matched' => false]);
        }

        $userId = User::where('telegram_id', $validated['telegram_user_id'])->value('id');

        return response()->json(array_filter([
            'matched' => true,
            'user_id' => $userId,
        ], fn($v) => $v !== null));
    }

    /**
     * GET /telegram/auth/login/{token}
     * Одноразовий вхід після підтвердження через бота.
     */
    public function login(string $token): RedirectResponse
    {
        $user = $this->authService->loginWithToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'telegram' => 'Посилання для входу недійсне або прострочене.',
            ]);
        }

        Auth::login($user, remember: true);
        Session::regenerate();

        $vacancy = app(PendingVacancyService::class)->createFromSession($user);

        if ($vacancy) {
            return redirect()->route('employer.dashboard')
                ->with('vacancy_published_id', $vacancy->id);
        }

        // Якщо прийшли з resume wizard — прив'язуємо резюме і повертаємо на крок 3
        if (request()->query('resume_redirect')) {
            $resumeId = session()->pull('pending_resume_id');
            if ($resumeId) {
                $resume = \App\Models\Resume::find($resumeId);
                if ($resume && $resume->user_id === null) {
                    $resume->update(['user_id' => $user->id]);
                }
                session(['resume_wizard_step' => 3]);
                return redirect()->route('resumes.edit', ['resume' => $resumeId]);
            }
        }

        $redirect = match($user->role->value) {
            'employer'  => route('employer.dashboard'),
            'candidate' => route('seeker.dashboard'),
            default     => route('home'),
        };

        return redirect()->intended($redirect);
    }
}
