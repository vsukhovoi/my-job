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
    <div>
        <h3 class="text-sm font-medium text-gray-700">Канал сповіщень підтримки</h3>
        <p class="text-xs text-gray-400 mt-0.5">
            Отримуйте сповіщення коли надходить відповідь на ваше звернення.
        </p>
    </div>

    @error('channel')
        <p class="text-xs text-red-500">{{ $message }}</p>
    @enderror

    <div class="flex gap-3">

        {{-- Email --}}
        <label class="flex-1 cursor-pointer">
            <input type="radio"
                   wire:model="channel"
                   value="email"
                   class="sr-only peer" />
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-200
                        peer-checked:border-green-500 peer-checked:bg-green-50 transition">
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-gray-700">Email</p>
                    <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
        </label>

        {{-- Telegram --}}
        @php $hasTelegram = ! empty(auth()->user()->telegram_id); @endphp
        <label @class(['flex-1', 'cursor-pointer' => $hasTelegram, 'cursor-not-allowed' => ! $hasTelegram])>
            <input type="radio"
                   wire:model="channel"
                   value="telegram"
                   @disabled(! $hasTelegram)
                   class="sr-only peer" />
            <div @class([
                'flex items-center gap-3 px-4 py-3 rounded-xl border transition',
                'border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50' => $hasTelegram,
                'border-gray-100 bg-gray-50 opacity-60' => ! $hasTelegram,
            ])>
                <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-gray-700">Telegram</p>
                    @if($hasTelegram)
                        <p class="text-xs text-green-600">підключено ✓</p>
                    @else
                        <a href="{{ auth()->user()->role === \App\Enums\UserRole::Employer ? route('employer.my-profile') : route('seeker.profile') }}"
                           class="text-xs text-orange-500 hover:underline">
                            підключити →
                        </a>
                    @endif
                </div>
            </div>
        </label>

    </div>

    <button wire:click="save"
            class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
        Зберегти
    </button>

    @if($saved)
        <p class="text-xs text-green-600" wire:key="saved-ok">✓ Налаштування збережено</p>
    @endif
</div>
