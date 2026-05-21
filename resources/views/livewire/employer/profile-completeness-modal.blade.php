<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Services\ProfileCompletenessService;
use Livewire\Volt\Component;

new class extends Component
{
    public bool   $show       = false;
    public int    $percentage = 0;
    public ?array $nextStep   = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::Employer) {
            return;
        }

        $data            = app(ProfileCompletenessService::class)->employerScore($user);
        $this->percentage = $data['score'];
        $this->nextStep   = $data['next_step'] ?? null;

        $shouldShow = $this->percentage < 100
            && (
                is_null($user->profile_completeness_modal_shown_at)
                || $user->profile_completeness_modal_shown_at->lt(now()->startOfDay())
            );

        if ($shouldShow) {
            $user->update(['profile_completeness_modal_shown_at' => now()]);
            $this->show = true;
        }
    }

    public function dismiss(): void
    {
        $this->show = false;
    }

    public function goFill(): void
    {
        $this->show = false;
        $this->redirect(route('employer.profile'));
    }
}; ?>

<div>
@if($show)
<div
    class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center"
    x-data
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    {{-- Sheet --}}
    <div
        class="w-full max-w-md mx-4 bg-white dark:bg-gray-800 rounded-2xl p-6 space-y-5"
        @click.stop
    >
        {{-- Заголовок + прогрес --}}
        <div class="space-y-3">
            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                Ваш профіль заповнено на {{ $percentage }}% 🎯
            </h2>
            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                <div
                    class="h-2.5 rounded-full transition-all duration-500"
                    style="width: {{ $percentage }}%; background-color: {{ $percentage >= 75 ? '#16a34a' : ($percentage >= 40 ? '#d97706' : '#dc2626') }};"
                ></div>
            </div>
        </div>

        {{-- Переваги --}}
        <ul class="space-y-4">
            <li class="flex gap-3">
                <span class="text-2xl leading-none">🚀</span>
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Більше довіри</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Профілі зі 100% заповненням отримують на 40% більше відгуків від кандидатів.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="text-2xl leading-none">🔝</span>
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Вище у пошуку</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Повністю готові профілі відображаються першими у списках роботодавців.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="text-2xl leading-none">⏱</span>
                <div>
                    <p class="font-semibold text-sm text-gray-900 dark:text-gray-100">Економія часу</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Кандидати одразу бачать ваші переваги та умови, що відсіює нерелевантних кандидатів.</p>
                </div>
            </li>
        </ul>

        {{-- CTA --}}
        <div class="space-y-3 pt-1">
            <button
                wire:click="goFill"
                class="w-full py-3 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition"
            >
                Заповнити до 100%
            </button>
            <button
                wire:click="dismiss"
                class="w-full text-center text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition"
            >
                Нагадати пізніше
            </button>
        </div>
    </div>
</div>
@endif
</div>
