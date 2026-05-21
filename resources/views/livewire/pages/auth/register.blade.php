<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $role     = 'candidate';
    public string $name     = '';
    public string $email    = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function register(): void
    {
        $validated = $this->validate([
            'role'     => ['required', 'in:candidate,employer'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role']     = UserRole::from($validated['role']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">

        {{-- Role switcher --}}
        <div style="display: inline-flex; gap: 12px; width: 100%; background: #e8eaed;
             border-radius: 10px; padding: 4px; margin-bottom: 20px;">
            <button type="button" wire:click="setRole('candidate')"
                    style="flex: 1; padding: 10px; font-size: 16px; font-weight: 600; border-radius: 7px; cursor: pointer; transition: all 0.3s ease;
                           border: 1px solid {{ $role === 'candidate' ? '#2d323b' : 'transparent' }};
                           background: {{ $role === 'candidate' ? '#2d323b' : 'transparent' }};
                           color: {{ $role === 'candidate' ? '#ffffff' : '#5f6368' }};
                           box-shadow: {{ $role === 'candidate' ? '0 4px 8px rgba(45,50,59,0.2)' : 'none' }};">
                Кандидат
            </button>
            <button type="button" wire:click="setRole('employer')"
                    style="flex: 1; padding: 10px; font-size: 16px; font-weight: 600; border-radius: 7px; cursor: pointer; transition: all 0.3s ease;
                           border: 1px solid {{ $role === 'employer' ? '#2d323b' : 'transparent' }};
                           background: {{ $role === 'employer' ? '#2d323b' : 'transparent' }};
                           color: {{ $role === 'employer' ? '#ffffff' : '#5f6368' }};
                           box-shadow: {{ $role === 'employer' ? '0 4px 8px rgba(45,50,59,0.2)' : 'none' }};">
                Роботодавець
            </button>
        </div>

        <div>
            <x-input-label for="name" value="Ім'я" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Електронна пошта" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Пароль" />
            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                          type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Підтвердіть пароль" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                          type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
               href="{{ route('login') }}" wire:navigate>
                Вже зареєстровані?
            </a>

            <x-primary-button class="ms-4">
                Зареєструватися
            </x-primary-button>
        </div>
    </form>
</div>
