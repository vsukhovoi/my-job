<?php

declare(strict_types=1);

namespace App\Livewire\Employer;

use App\Models\EmailVerification;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

final class EmailSetupModal extends Component
{
    public bool   $show          = false;
    public string $step          = 'email';
    public string $email         = '';
    public string $verifiedEmail = ''; // встановлюється тільки після валідації
    public string $code          = '';

    public function mount(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        $hasFakeEmail = str_ends_with((string) $user->email, '@telegram.local')
            || str_ends_with((string) $user->email, '@phone.local');

        $dismissed = session()->has('email_setup_dismissed_' . $user->id);

        if ($hasFakeEmail && ! $dismissed) {
            $this->show = true;
        }
    }

    public function sendCode(): void
    {
        $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . auth()->id(),
            ],
        ], [
            'email.required' => 'Введіть адресу електронної пошти',
            'email.email'    => 'Невірний формат email',
            'email.unique'   => 'Цей email вже використовується іншим акаунтом',
        ]);

        $code = EmailVerification::generateCode();

        $this->verifiedEmail = $this->email;

        EmailVerification::updateOrCreate(
            ['email' => $this->verifiedEmail],
            [
                'code'            => $code,
                'code_expires_at' => now()->addMinutes(10),
                'is_verified'     => false,
                'verified_at'     => null,
            ]
        );

        Mail::raw(
            "Ваш код підтвердження для MyJob: {$code}\n\nКод дійсний 10 хвилин.",
            fn ($m) => $m->to($this->verifiedEmail)->subject('Підтвердження email — MyJob')
        );

        $this->step = 'code';
        $this->resetErrorBag();
    }

    public function verify(): void
    {
        $this->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Введіть код',
            'code.size'     => 'Код складається з 6 цифр',
        ]);

        $verification = EmailVerification::where('email', $this->verifiedEmail)
            ->where('code', trim($this->code))
            ->where('code_expires_at', '>', now())
            ->where('is_verified', false)
            ->first();

        if (! $verification || ! $verification->verifyCode(trim($this->code))) {
            $this->addError('code', 'Невірний або прострочений код');
            return;
        }

        $user = auth()->user();
        $user->email             = $this->verifiedEmail;
        $user->email_verified_at = now();
        $user->save();

        $this->show = false;
    }

    public function resend(): void
    {
        $this->step = 'email';
        $this->code = '';
        $this->resetErrorBag();
    }

    public function skip(): void
    {
        session()->put('email_setup_dismissed_' . auth()->id(), true);
        $this->show = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.employer.email-setup-modal');
    }
}
