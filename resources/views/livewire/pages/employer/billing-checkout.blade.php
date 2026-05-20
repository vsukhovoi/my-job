<?php

declare(strict_types=1);

use App\Enums\PlanType;
use App\Http\Controllers\Payments\PaymentGatewayRegistry;
use App\Models\SubscriptionPlan;
use App\Payments\CheckoutService;
use App\Payments\Gateways\LiqPayGateway;
use App\Payments\Gateways\MonoPayGateway;
use App\Payments\Gateways\WayForPayGateway;
use App\Services\InvoiceService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public SubscriptionPlan $plan;

    public function mount(SubscriptionPlan $plan): void
    {
        abort_unless($plan->is_active, 404);

        if ($plan->type === PlanType::Free || $plan->price_monthly <= 0) {
            $this->redirect(route('employer.billing'), navigate: false);
        }

        $this->plan = $plan;
    }

    public function payByIban(): void
    {
        $invoice = app(InvoiceService::class)->create(
            auth()->user(),
            (int) ($this->plan->price_monthly * 100),
            planName: $this->plan->name
        );

        $this->redirect(route('employer.billing.invoice.show', $invoice->invoice_number), navigate: false);
    }

    public function pay(string $gateway): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gw = $registry->get($gateway);

        abort_if($gw === null, 422, "Невідомий шлюз: {$gateway}");

        $checkout = new CheckoutService($gw);
        $url = $checkout->createPlanSubscriptionCheckout(auth()->user(), $this->plan);

        $this->redirect($url, navigate: false);
    }
}
?>

<div class="min-h-screen mj-billing-bg">
    <x-employer-tabs />

    <div class="max-w-lg mx-auto px-4 py-12">

        {{-- Plan summary --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 mb-6 text-center">
            <p class="text-sm text-gray-500 mb-1">Ви обрали тариф</p>
            <h1 class="text-2xl font-extrabold text-gray-900">{{ $plan->name }}</h1>
            <p class="text-3xl font-bold text-blue-600 mt-2">
                {{ number_format($plan->price_monthly, 0, '.', ' ') }} ₴
                <span class="text-base font-normal text-gray-500">/міс</span>
            </p>
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

            {{-- MonoPay --}}
            <button wire:click="pay('mono')" wire:loading.attr="disabled"
                    class="flex items-center gap-4 w-full px-5 py-4 bg-white border border-gray-200 rounded-2xl hover:border-blue-500 hover:shadow-md transition-all text-left">
                <div class="w-10 h-10 rounded-xl bg-[#1A1A1A] flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-black">M</span>
                </div>
                <div>
                    <p class="font-bold text-gray-900">MonoPay</p>
                    <p class="text-xs text-gray-500">monobank, Apple Pay, Google Pay</p>
                </div>
                <svg class="ml-auto w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
