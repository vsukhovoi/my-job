<?php

declare(strict_types=1);

use App\Livewire\Forms\LoginForm;
use App\Services\PendingVacancyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Крок: 'main' | 'email'
    public string $step = 'main';

    // Роль: 'candidate' | 'employer'
    public string $role = 'candidate';

    // Email flow
    public LoginForm $form;

    public function mount(): void
    {
        if (session()->has('pending_vacancy')) {
            $this->role = 'employer';
        }
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
        $this->resetErrorBag();
    }

    public function showEmailLogin(): void
    {
        $this->step = 'email';
        $this->resetErrorBag();
    }

    public function backToMain(): void
    {
        $this->step = 'main';
        $this->resetErrorBag();
    }

    public function login(): void
    {
        $this->validate();
        $this->form->authenticate();
        Session::regenerate();

        $user = Auth::user();

        $vacancy = app(PendingVacancyService::class)->createFromSession($user);

        if ($vacancy) {
            session()->flash('vacancy_published_id', $vacancy->id);
            $this->redirect(route('employer.dashboard'), navigate: true);
            return;
        }

        $default = match($user->role->value) {
            'employer'  => route('employer.dashboard', absolute: false),
            'candidate' => route('seeker.dashboard', absolute: false),
            default     => route('home', absolute: false),
        };

        $this->redirectIntended(default: $default, navigate: true);
    }
}; ?>

<div class="login-wrapper" style="text-align: center;">

    {{-- Title --}}
    <h1 style="font-size: 28px; font-weight: 800; color: #1a1a1a; margin-bottom: 12px; line-height: 1.2;">
        @if($role === 'employer')
            Вхід або реєстрація роботодавця
        @else
            Вхід або реєстрація кандидата
        @endif
    </h1>

    {{-- Subtitle --}}
    <p style="font-size: 15px; color: #555; margin-bottom: 20px; line-height: 1.6;">
        @if($role === 'employer')
            Увійдіть, щоб розміщувати вакансії<br>та знаходити кращих кандидатів.
        @else
            Увійдіть, щоб керувати резюме,<br>відгукуватися на вакансії та отримувати пропозиції.
        @endif
    </p>

    {{-- OAuth role mismatch error --}}
    @if($errors->has('email') || $errors->has('telegram'))
        <div style="margin-bottom:16px; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca;
                    border-radius:10px; color:#b91c1c; font-size:14px; line-height:1.5; text-align:left;">
            {{ $errors->first('email') ?: $errors->first('telegram') }}
        </div>
    @endif

    {{-- Role switcher --}}
    <div class="role-switcher" style="display: inline-flex; gap: 12px; width: 340px; background: #e8eaed;
         border-radius: 10px; padding: 4px; margin-bottom: 20px;">
        <button wire:click="setRole('candidate')"
                style="flex: 1; padding: 10px; font-size: 16px; font-weight: 600; border-radius: 7px; cursor: pointer; transition: all 0.3s ease;
                       border: 1px solid {{ $role === 'candidate' ? '#2d323b' : 'transparent' }};
                       background: {{ $role === 'candidate' ? '#2d323b' : 'transparent' }};
                       color: {{ $role === 'candidate' ? '#ffffff' : '#5f6368' }};
                       box-shadow: {{ $role === 'candidate' ? '0 4px 8px rgba(45,50,59,0.2)' : 'none' }};">
            Кандидат
        </button>
        <button wire:click="setRole('employer')"
                style="flex: 1; padding: 10px; font-size: 16px; font-weight: 600; border-radius: 7px; cursor: pointer; transition: all 0.3s ease;
                       border: 1px solid {{ $role === 'employer' ? '#2d323b' : 'transparent' }};
                       background: {{ $role === 'employer' ? '#2d323b' : 'transparent' }};
                       color: {{ $role === 'employer' ? '#ffffff' : '#5f6368' }};
                       box-shadow: {{ $role === 'employer' ? '0 4px 8px rgba(45,50,59,0.2)' : 'none' }};">
            Роботодавець
        </button>
    </div>

    {{-- ======= ГОЛОВНИЙ КРОК ======= --}}
    @if($step === 'main')
        <div class="login-card" style="background:#fff; border:1px solid #a7a7a7; border-radius:12px;
                    box-shadow:0 2px 12px rgba(0,0,0,0.08); padding:24px;">

            <div style="display:flex; flex-direction:column; gap:10px;">

                {{-- Google (перший) --}}
                <a href="{{ route('social.redirect', ['provider' => 'google', 'role' => $role]) }}"
                   class="google-btn"
                   style="display:flex; align-items:center; justify-content:center; gap:12px;
                          height:52px; border:1px solid #a7a7a7; border-radius:8px; font-size:15px;
                          font-weight:600; color:#3c4043; background:#fff; text-decoration:none; transition:background 0.2s;"
                   onmouseover="this.style.background='#f8f9fa'"
                   onmouseout="this.style.background='#fff'">
                    <svg width="20" height="20" viewBox="0 0 48 48">
                        <path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.2l6.8-6.8C35.8 2.2 30.2 0 24 0 14.7 0 6.7 5.4 2.7 13.3l7.9 6.1C12.5 13 17.8 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v8.5h12.4c-.5 2.8-2.1 5.1-4.4 6.7l7 5.4C43.2 36.8 46.1 31.1 46.1 24.5z"/>
                        <path fill="#FBBC05" d="M10.6 28.6A14.8 14.8 0 0 1 9.5 24c0-1.6.3-3.2.8-4.6l-7.9-6.1A23.9 23.9 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.9-6.1z"/>
                        <path fill="#34A853" d="M24 48c6.2 0 11.4-2 15.2-5.5l-7-5.4c-2 1.4-4.6 2.2-8.2 2.2-6.2 0-11.5-4.2-13.4-9.8l-7.9 6.1C6.7 42.6 14.7 48 24 48z"/>
                    </svg>
                    Продовжити з Google
                </a>

                {{-- Telegram (другий) --}}
                <button type="button" id="tg-login-btn"
                        onclick="telegramLogin('{{ $role }}')"
                        style="display:flex; align-items:center; justify-content:center; gap:12px;
                               height:52px; border:none; border-radius:8px; font-size:15px; font-weight:600;
                               color:#fff; background:#2AABEE; cursor:pointer; width:100%; transition:background 0.2s;"
                        onmouseover="this.style.background='#1a9bde'"
                        onmouseout="this.style.background='#2AABEE'">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/>
                    </svg>
                    Увійти через Telegram
                </button>

            </div>

            <hr style="border:none; border-top:1px solid #a7a7a7; margin:16px 0;">

            <button wire:click="showEmailLogin"
                    class="login-email-btn"
                    style="display:flex; align-items:center; justify-content:center; gap:10px;
                           padding:12px; font-size:15px; font-weight:600; color:#1a1a1a;
                           background:transparent; border:none; cursor:pointer; border-radius:8px;
                           transition:background 0.2s; width:100%;"
                    onmouseover="this.style.background='#e8eaed'"
                    onmouseout="this.style.background='transparent'">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="color:#555; flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Увійти за допомогою ел. пошти
            </button>

            <p style="font-size:14px; color:#888; margin-top:8px; text-align:center;">
                Немає акаунту?
                <a href="{{ route('register') }}" wire:navigate
                   style="color:#1a73e8; font-weight:600; text-decoration:none;">
                    Зареєструватись
                </a>
            </p>
        </div>
    @endif

    {{-- ======= EMAIL ЛОГІН ======= --}}
    @if($step === 'email')
        <div class="login-card" style="background:#fff; border:1px solid #a7a7a7; border-radius:12px;
                    box-shadow:0 2px 12px rgba(0,0,0,0.08); padding:24px;">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form wire:submit="login">
                <input
                    class="login-input"
                    wire:model="form.email"
                    type="email"
                    placeholder="Електронна пошта"
                    required autofocus autocomplete="username"
                    style="width:100%; height:48px; padding:0 16px; font-size:15px;
                           border:1px solid #d0d5dd; border-radius:8px; outline:none;
                           color:#1a1a1a; background:#fff; margin-bottom:12px;
                           box-sizing:border-box; transition:border-color 0.2s;"
                    onfocus="this.style.borderColor='#1a73e8'"
                    onblur="this.style.borderColor='#d0d5dd'"
                />
                <x-input-error :messages="$errors->get('form.email')" class="mb-2" style="text-align:left;" />

                <input
                    class="login-input"
                    wire:model="form.password"
                    type="password"
                    placeholder="Пароль"
                    required autocomplete="current-password"
                    style="width:100%; height:48px; padding:0 16px; font-size:15px;
                           border:1px solid #d0d5dd; border-radius:8px; outline:none;
                           color:#1a1a1a; background:#fff; margin-bottom:16px;
                           box-sizing:border-box; transition:border-color 0.2s;"
                    onfocus="this.style.borderColor='#1a73e8'"
                    onblur="this.style.borderColor='#d0d5dd'"
                />
                <x-input-error :messages="$errors->get('form.password')" class="mb-2" style="text-align:left;" />

                <button type="submit"
                        style="width:100%; height:48px; font-size:16px; font-weight:600;
                               background-color:#1a73e8; color:#fff; border:none;
                               border-radius:8px; cursor:pointer; transition:background 0.2s;"
                        onmouseover="this.style.background='#1557b0'"
                        onmouseout="this.style.background='#1a73e8'">
                    Увійти
                </button>

                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate
                       style="display:block; margin-top:12px; font-size:13px; color:#1a73e8; text-decoration:underline;">
                        Забули пароль?
                    </a>
                @endif
            </form>

            <p style="margin-top:12px; font-size:14px; color:#888;">
                Немає акаунту?
                <a href="{{ route('register') }}" wire:navigate
                   style="color:#1a73e8; font-weight:600; text-decoration:none;">
                    Зареєструватись
                </a>
            </p>
        </div>

        <button wire:click="backToMain"
                class="login-back-btn"
                style="margin-top:12px; display:flex; align-items:center; justify-content:center;
                       gap:6px; font-size:14px; color:#555; background:transparent;
                       border:none; cursor:pointer; width:100%; padding:10px;"
                onmouseover="this.style.color='#1a1a1a'"
                onmouseout="this.style.color='#555'">
            ← Назад
        </button>
    @endif

    {{-- Telegram Auth Modal --}}
    <div id="tg-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5);
         z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:32px 24px; max-width:360px; width:90%;
                    text-align:center; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <button onclick="closeTgModal()"
                    style="position:absolute; top:12px; right:16px; background:none; border:none;
                           font-size:20px; color:#888; cursor:pointer;">✕</button>

            <div style="width:56px; height:56px; background:#2AABEE; border-radius:50%;
                 display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/>
                </svg>
            </div>

            <h3 id="tg-modal-title" style="font-size:18px; font-weight:700; color:#1a1a1a; margin:0 0 8px;">
                Відкрийте Telegram
            </h3>
            <p id="tg-modal-text" style="font-size:14px; color:#666; margin:0 0 20px; line-height:1.6;">
                Натисніть "Start" у боті та поділіться своїм номером телефону. Сторінка оновиться автоматично.
            </p>

            <a id="tg-deep-link" href="#" target="_blank"
               style="display:block; background:#2AABEE; color:#fff; font-weight:700; font-size:15px;
                      padding:12px; border-radius:8px; text-decoration:none; margin-bottom:12px;">
                Відкрити бота →
            </a>

            <div id="tg-spinner" style="font-size:13px; color:#888;">
                <span style="display:inline-block; animation:tg-spin 1s linear infinite;">⟳</span>
                Очікую підтвердження...
            </div>
        </div>
    </div>

    <style>
    @keyframes tg-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>

    <script>
    let _tgPollInterval  = null;
    let _tgToken         = null;
    let _tgPolling       = false;
    let _visibilityTimer = null;

    async function telegramLogin(role) {
        const btn = document.getElementById('tg-login-btn');
        try {
            if (btn) { btn.disabled = true; btn.textContent = 'Завантаження...'; }

            const res = await fetch('/api/telegram/auth/init', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ role }),
            });

            if (!res.ok) throw new Error('init failed');
            const { token, deep_link } = await res.json();

            _tgToken = token;
            document.getElementById('tg-deep-link').href = deep_link;
            document.getElementById('tg-modal').style.display = 'flex';
            document.getElementById('tg-modal-title').textContent = 'Відкрийте Telegram';
            document.getElementById('tg-modal-text').textContent = 'Натисніть "Start" у боті та поділіться своїм номером телефону. Сторінка оновиться автоматично.';
            document.getElementById('tg-spinner').style.display = 'block';

            startPolling(token);
            setTimeout(() => { stopPolling(); closeTgModal(); }, 300000);

        } catch {
            alert('Помилка. Спробуйте ще раз.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = `<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L8.32 13.617l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.828.942z"/></svg> Увійти через Telegram`;
            }
        }
    }

    function startPolling(token) {
        stopPolling();
        _tgPollInterval = setInterval(() => pollTgStatus(token), 3000);
    }

    function stopPolling() {
        clearInterval(_tgPollInterval);
        _tgPollInterval = null;
    }

    async function pollTgStatus(token) {
        if (_tgPolling) return;
        _tgPolling = true;
        try {
            const res = await fetch(`/api/telegram/auth/status/${token}`);

            if (res.status === 429) {
                stopPolling();
                setTimeout(() => startPolling(token), 10000);
                return;
            }

            if (!res.ok) return;
            const data = await res.json();

            if (data.status === 'authorized' && data.login_url) {
                stopPolling();
                _tgToken = null;
                document.getElementById('tg-modal-title').textContent = '✅ Авторизовано!';
                document.getElementById('tg-modal-text').textContent = 'Перенаправляємо...';
                document.getElementById('tg-spinner').style.display = 'none';
                setTimeout(() => { window.location.href = data.login_url; }, 500);
            } else if (data.status === 'expired' || data.status === 'not_found') {
                stopPolling();
                _tgToken = null;
                closeTgModal();
                alert('Сесія прострочена. Спробуйте ще раз.');
            }
        } catch {
        } finally {
            _tgPolling = false;
        }
    }

    function closeTgModal() {
        stopPolling();
        _tgToken = null;
        document.getElementById('tg-modal').style.display = 'none';
    }

    // На мобільних: debounce щоб уникнути множинних спрацювань
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && _tgToken) {
            clearTimeout(_visibilityTimer);
            _visibilityTimer = setTimeout(() => {
                startPolling(_tgToken);
            }, 300);
        } else if (document.visibilityState === 'hidden') {
            stopPolling();
        }
    });
    </script>

</div>
