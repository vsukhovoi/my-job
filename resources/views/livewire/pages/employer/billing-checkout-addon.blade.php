<?php

declare(strict_types=1);

use App\Enums\AddonType;
use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Payments\CheckoutService;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public AddonType $addon;

    public function mount(AddonType $addon): void
    {
        $this->addon = $addon;
    }

    public function pay(string $gateway): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gw = $registry->get($gateway);

        abort_if($gw === null, 422, "Невідомий шлюз: {$gateway}");

        $checkout = new CheckoutService($gw);
        $url = $checkout->createAddonCheckout($this->addon, auth()->user());

        $this->redirect($url, navigate: false);
    }

    public function payByIban(): void
    {
        $invoice = app(InvoiceService::class)->create(
            auth()->user(),
            (int) ($this->addon->price() * 100),
            planName: $this->addon->label(),
        );

        $this->redirect(route('employer.billing.invoice.show', $invoice->invoice_number), navigate: false);
    }
}
?>

<div class="min-h-screen mj-billing-bg">
    <x-employer-tabs />

    <div class="max-w-lg mx-auto px-4 py-12">

        {{-- Addon summary --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6 text-center">
            <p class="text-sm text-gray-500 mb-1">Ви обрали послугу</p>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $addon->label() }}</h1>
            <p class="text-3xl font-bold text-blue-600 mt-2">
                {{ number_format($addon->price(), 0, '.', ' ') }} ₴
            </p>
            <p class="text-sm text-gray-500 mt-1">Термін дії: {{ $addon->durationDays() }} днів</p>
        </div>

        {{-- Gateway selection --}}
        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 text-center">Оберіть спосіб оплати</p>

        <div class="flex flex-col gap-3">

            {{-- LiqPay --}}
            <button wire:click="pay('liqpay')" wire:loading.attr="disabled"
                    class="flex items-center gap-4 w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl hover:border-blue-500 hover:shadow-md transition-all text-left">
                <div class="w-10 h-10 rounded-xl bg-[#FF6600] flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-black">LP</span>
                </div>
                <div>
                    <p class="font-bold text-gray-900">LiqPay</p>
                    <p class="text-xs text-gray-500">Картка, Apple Pay, Google Pay</p>
                </div>
                <svg class="ml-auto w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- WayForPay --}}
            <button wire:click="pay('wayforpay')" wire:loading.attr="disabled"
                    class="flex items-center gap-4 w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl hover:border-blue-500 hover:shadow-md transition-all text-left">
                <div class="w-10 h-10 rounded-xl bg-[#0066CC] flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-black">WP</span>
                </div>
                <div>
                    <p class="font-bold text-gray-900">WayForPay</p>
                    <p class="text-xs text-gray-500">Картка, частинами, QR</p>
                </div>
                <svg class="ml-auto w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            {{-- Plata by Mono --}}
            <button wire:click="pay('mono')" wire:loading.attr="disabled"
                    class="flex items-center gap-4 w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl hover:border-gray-800 hover:shadow-md transition-all text-left">
                <div class="w-10 h-10 rounded-xl bg-[#1A1A1A] flex items-center justify-center shrink-0">
                    <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-7 h-7">
                        <rect width="36" height="36" rx="8" fill="#1A1A1A"/>
                        <text x="5" y="23" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="white">plata</text>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-900">Plata by Mono</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 41 17" class="h-4" aria-label="Google Pay">
                            <path d="M19.8 8.4v4.9h-1.6V1h4.1c1 0 1.9.3 2.6 1 .7.6 1.1 1.5 1.1 2.5s-.4 1.8-1.1 2.5c-.7.6-1.6 1-2.6.9h-2.5zm0-5.9v4.4h2.6c.6 0 1.2-.2 1.6-.6.9-.8.9-2.2.1-3-.4-.4-1-.7-1.7-.7h-2.6z" fill="#3C4043"/>
                            <path d="M29.2 5.2c1.1 0 2 .3 2.6.9.6.6 1 1.4 1 2.5v5h-1.5v-1.1h-.1c-.6.9-1.5 1.4-2.5 1.4-.9 0-1.7-.3-2.3-.8-.6-.5-.9-1.2-.9-2 0-.8.3-1.5.9-2s1.4-.7 2.5-.7c.9 0 1.6.2 2.2.5v-.3c0-.6-.2-1.1-.7-1.5-.5-.4-1-.6-1.6-.6-.9 0-1.7.4-2.2 1.1l-1.4-.9c.8-1.1 2-1.5 3.5-1.5zm-2 6.3c0 .4.2.7.5 1 .3.2.7.4 1.1.4.6 0 1.2-.2 1.7-.7.5-.5.7-1 .7-1.7-.5-.4-1.1-.6-2-.6-.6 0-1.1.1-1.5.4-.3.3-.5.7-.5 1.2z" fill="#3C4043"/>
                            <path d="M40.1 5.5l-5.3 12.2H33l2-4.3-3.5-7.9h1.7l2.5 6.1h.1l2.5-6.1h1.8z" fill="#3C4043"/>
                            <path d="M13.3 7.4c0-.5 0-.9-.1-1.4H6.8v2.7h3.7c-.2.9-.6 1.6-1.4 2.1v1.7h2.3c1.3-1.2 2-3 2-5.1z" fill="#4285F4"/>
                            <path d="M6.8 14.2c1.8 0 3.4-.6 4.5-1.7l-2.2-1.7c-.6.4-1.4.7-2.3.7-1.8 0-3.3-1.2-3.8-2.8H.7v1.7c1.2 2.3 3.5 3.8 6.1 3.8z" fill="#34A853"/>
                            <path d="M3 8.7c-.3-.8-.3-1.6 0-2.4V4.6H.7C-.2 6.4-.2 8.6.7 10.4L3 8.7z" fill="#FBBC04"/>
                            <path d="M6.8 3.5c1 0 2 .4 2.7 1.1l2-2C10.2 1.4 8.6.8 6.8.8 4.2.8 1.9 2.3.7 4.6L3 6.3C3.5 4.7 5 3.5 6.8 3.5z" fill="#EA4335"/>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 20" class="h-4" aria-label="Apple Pay">
                            <path d="M9.8 3.3c-.5.6-1.3 1.1-2.1 1-.1-.8.3-1.6.8-2.2C9 1.5 9.9 1 10.6 1c.1.9-.2 1.7-.8 2.3zm.7 1.2c-1.1-.1-2.1.6-2.7.6-.6 0-1.5-.6-2.4-.6C4 4.6 2.6 5.6 1.9 7.1c-1.5 2.5-.4 6.3 1.1 8.3.7 1 1.6 2.1 2.7 2.1.9 0 1.3-.6 2.4-.6 1.1 0 1.4.6 2.4.6 1.1 0 1.9-1 2.6-2 .8-1.1 1.1-2.2 1.1-2.3-.1 0-2.1-.8-2.1-3.1 0-1.9 1.6-2.8 1.6-2.9-.9-1.3-2.3-1.5-2.7-1.5h-.5z" fill="#000"/>
                            <path d="M19.3 2.1c2.3 0 3.9 1.5 3.9 3.8s-1.6 3.9-3.9 3.9h-2.5v4h-1.9V2.1h4.4zm-2.5 6.2h2.1c1.6 0 2.5-.9 2.5-2.3 0-1.5-.9-2.3-2.5-2.3h-2.1v4.6zm7.5 3.8c0-1.5 1.1-2.4 3.1-2.5l2.3-.1V9c0-.9-.6-1.5-1.7-1.5-.9 0-1.5.5-1.7 1.2h-1.7c.1-1.6 1.5-2.7 3.4-2.7 2 0 3.3 1.1 3.3 2.8v5.9h-1.7v-1.4h-.1c-.5.9-1.5 1.5-2.5 1.5-1.6 0-2.7-1-2.7-2.7zm5.4-.8v-.6l-2.1.1c-1 .1-1.5.5-1.5 1.2 0 .7.6 1.1 1.4 1.1 1.2 0 2.2-.7 2.2-1.8zm3.9 5.6v-1.5c.2 0 .4.1.6.1.8 0 1.2-.4 1.5-1.3l.1-.5-3.2-8.8h2l2.2 7h.1l2.2-7h1.9l-3.3 9.3c-.8 2.1-1.6 2.8-3.4 2.8-.2-.1-.5-.1-.7-.1z" fill="#000"/>
                        </svg>
                    </div>
                </div>
                <svg class="ml-auto w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

            <div class="flex items-center gap-3 my-1">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-xs text-gray-400">або</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>

            {{-- IBAN --}}
            <button wire:click="payByIban" wire:loading.attr="disabled"
                    class="flex items-center gap-4 w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl hover:border-green-500 hover:shadow-md transition-all text-left">
                <div class="w-10 h-10 rounded-xl bg-green-600 flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-black">UA</span>
                </div>
                <div>
                    <p class="font-bold text-gray-900">Банківський переказ (IBAN)</p>
                    <p class="text-xs text-gray-500">Рахунок-фактура · оплата протягом 30 днів</p>
                </div>
                <svg class="ml-auto w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>

        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('employer.billing') }}" class="text-sm text-gray-500 hover:text-gray-700">← Повернутись до тарифів</a>
        </div>

        <div wire:loading class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white border border-gray-200 rounded-2xl px-8 py-6 text-center shadow-xl">
                <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-sm font-medium text-gray-600">Перенаправлення на оплату...</p>
            </div>
        </div>

    </div>
</div>
