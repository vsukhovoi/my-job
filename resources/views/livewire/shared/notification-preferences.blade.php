<?php

use Livewire\Volt\Component;

new class extends Component {

    public bool $notifyEmail    = true;
    public bool $notifyTelegram = false;
    public bool $saved          = false;

    public function mount(): void
    {
        $this->notifyEmail    = (bool) auth()->user()->notify_via_email;
        $this->notifyTelegram = (bool) auth()->user()->notify_via_telegram;
    }

    public function toggle(string $channel): void
    {
        if ($channel === 'email') {
            // Якщо Telegram не підключений або вимкнений — email не можна вимкнути
            if ($this->notifyEmail && ! $this->notifyTelegram) {
                return;
            }
            $this->notifyEmail = ! $this->notifyEmail;
        }

        if ($channel === 'telegram') {
            $user = auth()->user();
            if (empty($user->telegram_id)) {
                return;
            }
            // Якщо email вимкнений — telegram не можна вимкнути
            if ($this->notifyTelegram && ! $this->notifyEmail) {
                return;
            }
            $this->notifyTelegram = ! $this->notifyTelegram;
        }
    }

    public function save(): void
    {
        auth()->user()->update([
            'notify_via_email'    => $this->notifyEmail,
            'notify_via_telegram' => $this->notifyTelegram,
        ]);

        $this->saved = true;
    }
};
?>

<div class="space-y-4">

    @php $hasTelegram = ! empty(auth()->user()->telegram_id); @endphp

    <div class="space-y-2">

        {{-- Email --}}
        <button type="button" wire:click="toggle('email')"
                class="flex items-center gap-3 w-full px-4 py-3 rounded-xl border transition text-left
                       {{ $notifyEmail
                           ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                           : 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500' }}">
            {{-- Checkbox --}}
            <span class="shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition
                         {{ $notifyEmail ? 'border-green-500 bg-green-500' : 'border-gray-300 dark:border-gray-500' }}">
                @if($notifyEmail)
                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                @endif
            </span>
            {{-- Icon --}}
            <svg class="w-4 h-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            {{-- Label --}}
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Email</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ auth()->user()->email }}</p>
            </div>
        </button>

        {{-- Telegram --}}
        <button type="button"
                class="flex items-center gap-3 w-full px-4 py-3 rounded-xl border transition text-left
                       {{ $notifyTelegram
                           ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                           : ($hasTelegram
                               ? 'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500'
                               : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-50 cursor-not-allowed') }}"
                @if($hasTelegram) wire:click="toggle('telegram')" @endif
                @disabled(! $hasTelegram)>
            {{-- Checkbox --}}
            <span class="shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition
                         {{ $notifyTelegram ? 'border-green-500 bg-green-500' : 'border-gray-300 dark:border-gray-500' }}">
                @if($notifyTelegram)
                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                @endif
            </span>
            {{-- Icon --}}
            <svg class="w-4 h-4 shrink-0 text-[#2AABEE]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
            </svg>
            {{-- Label --}}
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Telegram</p>
                @if($hasTelegram)
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ auth()->user()->telegram_id }}</p>
                @else
                    <a href="{{ auth()->user()->role === \App\Enums\UserRole::Employer ? route('employer.my-profile') : route('seeker.profile') }}"
                       class="text-xs text-orange-500 hover:underline">
                        підключити →
                    </a>
                @endif
            </div>
        </button>

    </div>

    <button wire:click="save"
            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
        Зберегти
    </button>

    @if($saved)
        <p class="text-xs text-green-600 dark:text-green-400" wire:key="saved-ok">✓ Налаштування збережено</p>
    @endif
</div>
