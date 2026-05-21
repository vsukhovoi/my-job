<?php

declare(strict_types=1);

use App\Enums\PlanFeature;
use App\Enums\PlanType;
use App\Models\SubscriptionPlan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function plans(): \Illuminate\Database\Eloquent\Collection
    {
        return SubscriptionPlan::where('is_active', true)
            ->orderBy('price_monthly')
            ->get();
    }
}
?>

<div class="min-h-screen py-16 px-4">

    <div class="max-w-6xl mx-auto">

        {{-- Заголовок --}}
        <div class="text-center mb-12">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Тарифи для роботодавців</h1>
            <p class="text-gray-500 text-lg">Оберіть план, що підходить для вашого бізнесу</p>
        </div>

        {{-- Картки тарифів --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            @foreach($this->plans as $plan)
                @php
                    $isPro      = $plan->type === PlanType::Pro;
                    $hotCount   = (int)($plan->features['hot_per_month'] ?? 0);
                    $topCount   = (int)($plan->features['top_per_month'] ?? 0);
                    $hotDays    = (int)($plan->features['hot_days'] ?? 0);
                    $topDays    = (int)($plan->features['top_days'] ?? 0);
                    $jobLimit   = $plan->feature(PlanFeature::ActiveJobs);
                    $appLimit   = $plan->feature(PlanFeature::ApplicationsPerMonth);
                    $analytics  = (bool)($plan->features['analytics'] ?? false);
                    $templates  = (bool)($plan->features['message_templates'] ?? false);
                @endphp

                <div class="relative bg-white rounded-2xl flex flex-col
                    {{ $isPro ? 'border-2 border-orange-400 shadow-lg' : 'border border-gray-200' }}">

                    @if($isPro)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="bg-orange-400 text-white text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap">
                                Популярний
                            </span>
                        </div>
                    @endif

                    <div class="p-6 flex-1 flex flex-col">

                        <p class="text-lg font-bold text-gray-900">{{ $plan->name }}</p>

                        <p class="mt-2 mb-5">
                            @if($plan->price_monthly > 0)
                                <span class="text-3xl font-bold text-gray-900">{{ number_format($plan->price_monthly, 0, '.', ' ') }}</span>
                                <span class="text-gray-400 text-sm"> ₴/міс</span>
                            @else
                                <span class="text-3xl font-bold text-green-600">Безкоштовно</span>
                            @endif
                        </p>

                        <ul class="space-y-2 text-sm text-gray-600 flex-1 mb-6">

                            <li class="flex justify-between">
                                <span class="text-gray-500">Вакансій</span>
                                <span class="font-semibold text-gray-800">{{ $jobLimit === 0 ? '∞' : $jobLimit }}</span>
                            </li>

                            <li class="flex justify-between">
                                <span class="text-gray-500">Заявок / міс</span>
                                <span class="font-semibold text-gray-800">{{ $appLimit === 0 ? '∞' : $appLimit }}</span>
                            </li>

                            <li class="flex justify-between">
                                <span class="text-gray-500">Аналітика</span>
                                <span class="{{ $analytics ? 'text-green-600 font-semibold' : 'text-gray-300' }}">
                                    {{ $analytics ? '✓' : '—' }}
                                </span>
                            </li>

                            <li class="flex justify-between">
                                <span class="text-gray-500">Шаблони листів</span>
                                <span class="{{ $templates ? 'text-green-600 font-semibold' : 'text-gray-300' }}">
                                    {{ $templates ? '✓' : '—' }}
                                </span>
                            </li>

                            <li class="flex justify-between">
                                <span class="text-gray-500">HOT / міс</span>
                                @if($hotCount === 0)
                                    <span class="text-gray-300">—</span>
                                @else
                                    <span class="font-semibold text-orange-600">
                                        {{ $hotCount }} · {{ $hotDays === 0 ? '∞' : $hotDays . ' дн' }}
                                    </span>
                                @endif
                            </li>

                            <li class="flex justify-between">
                                <span class="text-gray-500">TOP / міс</span>
                                @if($topCount === 0)
                                    <span class="text-gray-300">—</span>
                                @else
                                    <span class="font-semibold text-blue-600">
                                        {{ $topCount }} · {{ $topDays === 0 ? '∞' : $topDays . ' дн' }}
                                    </span>
                                @endif
                            </li>

                        </ul>

                        @auth
                            @if(auth()->user()->role->value === 'employer')
                                @if($plan->type === PlanType::Free)
                                    <a href="{{ route('billing') }}"
                                       class="block text-center w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                                           bg-gray-100 hover:bg-gray-200 text-gray-700">
                                        Обрати
                                    </a>
                                @else
                                    <a href="{{ route('billing.checkout', $plan->type->value) }}"
                                       class="block text-center w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                                           {{ $isPro ? 'bg-orange-400 hover:bg-orange-500 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white' }}">
                                        Обрати
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('register') }}"
                                   class="block text-center w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                                       bg-blue-600 hover:bg-blue-700 text-white">
                                    Зареєструватись
                                </a>
                            @endif
                        @else
                            <a href="{{ route('register') }}"
                               class="block text-center w-full py-2.5 rounded-xl text-sm font-semibold transition-colors
                                   {{ $isPro ? 'bg-orange-400 hover:bg-orange-500 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white' }}">
                                Почати
                            </a>
                        @endauth

                    </div>
                </div>
            @endforeach
        </div>

        {{-- Додаткові послуги --}}
        <div class="max-w-3xl mx-auto">
            <h2 class="text-xl font-bold text-gray-900 text-center mb-6">Додаткові послуги</h2>
            <div class="bg-white border border-gray-200 rounded-2xl divide-y divide-gray-100">

                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">🔥</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">HOT — підняття вакансії вгору</p>
                            <p class="text-xs text-gray-500">З'являється першою у пошуку · 7 днів</p>
                        </div>
                    </div>
                    <span class="font-bold text-gray-800">199 ₴</span>
                </div>

                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">⭐</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">TOP — закріплення у топових позиціях</p>
                            <p class="text-xs text-gray-500">Завжди у верхній частині списку · 7 днів</p>
                        </div>
                    </div>
                    <span class="font-bold text-gray-800">299 ₴</span>
                </div>

                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="text-xl">📄</span>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">Доступ до бази CV</p>
                            <p class="text-xs text-gray-500">Перегляд резюме кандидатів · 30 днів</p>
                        </div>
                    </div>
                    <span class="font-bold text-gray-800">990 ₴</span>
                </div>

            </div>
        </div>

    </div>
</div>
