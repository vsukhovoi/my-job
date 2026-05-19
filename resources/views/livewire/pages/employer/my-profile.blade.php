<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('required|email|max:100')]
    public string $email = '';

    public string $phone = '';

    public string $telegram_id = '';

    #[Validate('nullable|string|min:8')]
    public string $password = '';

    #[Validate('nullable|string|same:password')]
    public string $password_confirmation = '';

    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name        = $user->name;
        $this->email       = $user->email;
        $this->phone       = $user->phone ?? '';
        $this->telegram_id = $user->telegram_id ? (string) $user->telegram_id : '';
    }

    public function save(): void
    {
        $userId = auth()->id();

        $this->validate([
            'name'                  => 'required|string|min:2|max:100',
            'email'                 => 'required|email|max:100|unique:users,email,' . $userId,
            'phone'                 => 'nullable|string|max:20|unique:users,phone,' . $userId,
            'telegram_id'           => 'nullable|numeric|min:1000000000|max:9999999999|unique:users,telegram_id,' . $userId,
            'password'              => 'nullable|string|min:8',
            'password_confirmation' => 'nullable|string|same:password',
        ]);

        $data = [
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone ?: null,
            'telegram_id' => $this->telegram_id ?: null,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        auth()->user()->update($data);

        $this->password              = '';
        $this->password_confirmation = '';
        $this->saved                 = true;

        $this->dispatch('profile-saved');
    }
}; ?>

<div class="min-h-screen seeker-dashboard-bg dark:bg-gray-900">
    <x-employer-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="bg-white dark:bg-gray-800 rounded-2xl border employer-card-border dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Особисті дані</h2>
            </div>

            <form wire:submit="save" class="p-6 space-y-5">

                {{-- Name --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Ім'я</label>
                    <input wire:model="name" type="text"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                    @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Електронна пошта</label>
                    <input wire:model="email" type="email"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                    @error('email')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Телефон</label>
                    <input wire:model="phone" type="tel" placeholder="+380XXXXXXXXX"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                    @error('phone')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                {{-- Telegram ID --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Telegram ID</label>
                    <input wire:model="telegram_id" type="text" placeholder="123456789"
                           class="w-full px-4 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                    <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                        Дізнатись свій ID можна через бот
                        <a href="https://t.me/userinfobot" target="_blank" class="text-blue-500 hover:underline">@userinfobot</a>
                    </p>
                    @error('telegram_id')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <hr class="border-gray-100 dark:border-gray-700">

                {{-- Password --}}
                <div x-data="{
                    showNew: false,
                    showConfirm: false,
                    copied: false,
                    generate() {
                        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
                        const arr = new Uint8Array(16);
                        crypto.getRandomValues(arr);
                        const pwd = Array.from(arr).map(b => chars[b % chars.length]).join('');
                        this.showNew = true;
                        this.showConfirm = true;
                        this.copied = false;
                        $wire.set('password', pwd);
                        $wire.set('password_confirmation', pwd);
                    },
                    async copy() {
                        const val = document.getElementById('emp-pwd-new').value;
                        if (!val) return;
                        await navigator.clipboard.writeText(val);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                }">
                    {{-- New password --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Новий пароль
                                <span class="text-gray-400 dark:text-gray-500 font-normal">(залиште порожнім, щоб не змінювати)</span>
                            </label>
                            <div class="flex items-center gap-1.5 ml-2 shrink-0">
                                <button type="button" @click="copy()" title="Скопіювати пароль"
                                        class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                    <svg x-show="!copied" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <svg x-show="copied" class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span x-text="copied ? 'Скопійовано' : 'Копіювати'"></span>
                                </button>
                                <button type="button" @click="generate()" title="Згенерувати надійний пароль"
                                        class="flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg border border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-purple-400 hover:text-purple-600 dark:hover:text-purple-400 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Згенерувати
                                </button>
                            </div>
                        </div>
                        <div class="relative">
                            <input id="emp-pwd-new" wire:model="password"
                                   :type="showNew ? 'text' : 'password'"
                                   placeholder="Мінімум 8 символів"
                                   class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                            <button type="button" @click="showNew = !showNew"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                                <svg x-show="!showNew" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showNew" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Confirm password --}}
                    <div class="mt-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Підтвердження пароля</label>
                        <div class="relative">
                            <input wire:model="password_confirmation"
                                   :type="showConfirm ? 'text' : 'password'"
                                   class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700/50 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">
                            <button type="button" @click="showConfirm = !showConfirm"
                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                                <svg x-show="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="pt-2 space-y-3">
                    <button type="submit"
                            class="w-full py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 dark:hover:bg-blue-500 transition-colors">
                        <span wire:loading.remove wire:target="save">Зберегти зміни</span>
                        <span wire:loading wire:target="save">Збереження...</span>
                    </button>

                    @if($saved)
                        <p class="text-center text-sm text-green-600 dark:text-green-400 font-medium">
                            ✅ Збережено
                        </p>
                    @endif
                </div>

            </form>
        </div>

    </div>
</div>
