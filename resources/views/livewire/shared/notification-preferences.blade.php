<?php

use App\Enums\NotificationChannel;
use Livewire\Volt\Component;

new class extends Component {

    public string $channel = 'email';
    public bool   $saved   = false;

    public function mount(): void
    {
        $this->channel = auth()->user()->notification_channel->value;
    }

    public function save(): void
    {
        $this->validate([
            'channel' => ['required', 'in:email,telegram'],
        ]);

        $user = auth()->user();

        if ($this->channel === 'telegram' && empty($user->telegram_id)) {
            $this->addError('channel', 'Спочатку підключіть Telegram у налаштуваннях акаунту.');
            return;
        }

        $user->update([
            'notification_channel' => $this->channel,
        ]);

        $this->saved = true;
    }
};
?>

<div class="space-y-4">
    @error('channel')
        <p class="text-xs text-red-500">{{ $message }}</p>
    @enderror

    @php $hasTelegram = ! empty(auth()->user()->telegram_id); @endphp

    <div class="grid grid-cols-2 gap-3">

        {{-- Email --}}
        <button type="button" wire:click="$set('channel', 'email')"
                @class([
                    'flex items-center gap-2 px-3 py-3 rounded-xl border transition text-left w-full',
                    'border-green-500 bg-green-50 dark:bg-green-900/20' => $channel === 'email',
                    'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500' => $channel !== 'email',
                ])>
            <svg class="w-4 h-4 shrink-0 {{ $channel === 'email' ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-sm font-medium {{ $channel === 'email' ? 'text-green-700 dark:text-green-300' : 'text-gray-700 dark:text-gray-200' }}">Email</p>
                <p class="text-xs truncate {{ $channel === 'email' ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                    {{ $channel === 'email' ? 'активний ✓' : auth()->user()->email }}
                </p>
            </div>
        </button>

        {{-- Telegram --}}
        <button type="button"
                @class([
                    'flex items-center gap-2 px-3 py-3 rounded-xl border transition text-left w-full',
                    'border-green-500 bg-green-50 dark:bg-green-900/20' => $channel === 'telegram',
                    'border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-500' => $channel !== 'telegram' && $hasTelegram,
                    'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 opacity-50 cursor-not-allowed' => ! $hasTelegram,
                ])
                @if($hasTelegram) wire:click="$set('channel', 'telegram')" @endif
                @disabled(! $hasTelegram)>
            <svg class="w-4 h-4 shrink-0 text-[#2AABEE]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-sm font-medium {{ $channel === 'telegram' ? 'text-green-700 dark:text-green-300' : 'text-gray-700 dark:text-gray-200' }}">Telegram</p>
                @if($hasTelegram)
                    <p class="text-xs {{ $channel === 'telegram' ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                        {{ $channel === 'telegram' ? 'активний ✓' : 'підключено' }}
                    </p>
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
