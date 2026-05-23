<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\Vacancy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function stats(): array
    {
        $apps = auth()->user()->applications();

        return [
            'total'     => $apps->count(),
            'active'    => (clone $apps)->whereIn('status', [
                ApplicationStatus::Pending->value,
                ApplicationStatus::Screening->value,
                ApplicationStatus::Interview->value,
            ])->count(),
            'interviews' => Interview::whereHas('application', fn($q) =>
                $q->where('user_id', auth()->id())
            )->where('scheduled_at', '>=', now())
             ->where('status', InterviewStatus::Scheduled)
             ->count(),
            'hired'     => (clone $apps)->where('status', ApplicationStatus::Hired->value)->count(),
            'rejected'  => (clone $apps)->where('status', ApplicationStatus::Rejected->value)->count(),
            'unread_messages' => \App\Models\SupportThread::where('user_id', auth()->id())
                ->whereHas('messages', fn($q) => $q->where('is_read', false)
                    ->where('sender_id', '!=', auth()->id()))
                ->count(),
        ];
    }

    #[Computed]
    public function upcomingInterviews(): \Illuminate\Database\Eloquent\Collection
    {
        return Interview::with(['application.vacancy.company', 'application.vacancy.city'])
            ->whereHas('application', fn($q) => $q->where('user_id', auth()->id()))
            ->where('scheduled_at', '>=', now())
            ->where('status', InterviewStatus::Scheduled)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentApplications(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->applications()
            ->with(['vacancy.company', 'vacancy.city'])
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recommendedVacancies(): \Illuminate\Database\Eloquent\Collection
    {
        $appliedIds = auth()->user()->applications()->pluck('vacancy_id');

        return Vacancy::with(['company', 'city', 'category'])
            ->where('is_active', true)
            ->whereNotIn('id', $appliedIds)
            ->latest('published_at')
            ->limit(4)
            ->get();
    }
}; ?>

<div class="min-h-screen seeker-dashboard-bg dark:bg-gray-900">
    <x-seeker-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <livewire:shared.profile-completeness type="candidate" />

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @php
                $statCards = [
                    ['label' => 'Всього заявок',  'value' => $this->stats['total'],     'color' => '#2563eb', 'route' => null],
                    ['label' => 'На розгляді',    'value' => $this->stats['active'],    'color' => '#f59e0b', 'route' => null],
                    ['label' => 'Співбесід',      'value' => $this->stats['interviews'],'color' => '#7c3aed', 'route' => null],
                    ['label' => 'Прийнятий',      'value' => $this->stats['hired'],     'color' => '#16a34a', 'route' => null],
                    ['label' => 'Відмов',         'value' => $this->stats['rejected'],  'color' => '#dc2626', 'route' => null],
                    ['label' => 'Нових повідомлень', 'value' => $this->stats['unread_messages'], 'color' => '#0891b2', 'route' => 'seeker.messages'],
                ];
            @endphp
            @foreach($statCards as $card)
                @if($card['route'])
                    <a href="{{ route($card['route']) }}"
                       class="bg-white rounded-2xl border employer-card-border p-5 text-center shadow-sm hover:border-cyan-300 transition block">
                        <p style="font-size:2rem; font-weight:800; color:{{ $card['color'] }}; line-height:1;">{{ $card['value'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $card['label'] }}</p>
                    </a>
                @else
                    <div class="bg-white rounded-2xl border employer-card-border p-5 text-center shadow-sm">
                        <p style="font-size:2rem; font-weight:800; color:{{ $card['color'] }}; line-height:1;">{{ $card['value'] }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $card['label'] }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            {{-- Upcoming interviews --}}
            <div class="bg-white rounded-2xl border employer-card-border shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Найближчі співбесіди</h2>
                    <a href="{{ route('seeker.interviews') }}" class="text-sm text-blue-600 hover:underline">Всі →</a>
                </div>
                @forelse($this->upcomingInterviews as $interview)
                    <div class="px-6 py-4 border-b border-gray-50 last:border-b-0">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 truncate">{{ $interview->application->vacancy?->title ?? '(вакансія видалена)' }}</p>
                                <p class="text-sm text-gray-500">{{ $interview->application->vacancy?->company?->name ?? '' }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-gray-800">{{ $interview->scheduled_at->format('d.m.Y') }}</p>
                                <p class="text-xs text-gray-500">{{ $interview->scheduled_at->format('H:i') }}</p>
                            </div>
                        </div>
                        @if($interview->meeting_link)
                            <a href="{{ $interview->meeting_link }}" target="_blank"
                               class="mt-2 inline-flex items-center gap-1.5 text-xs text-blue-600 hover:underline">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Посилання на зустріч
                            </a>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-gray-400 text-sm">Немає запланованих співбесід</div>
                @endforelse
            </div>

            {{-- Recent applications --}}
            <div class="bg-white rounded-2xl border employer-card-border shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Останні заявки</h2>
                    <a href="{{ route('seeker.applications') }}" class="text-sm text-blue-600 hover:underline">Всі →</a>
                </div>
                @forelse($this->recentApplications as $app)
                    <a href="{{ route('seeker.application.detail', $app->id) }}"
                       class="block px-6 py-4 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900 truncate">{{ $app->vacancy?->title ?? '(вакансія видалена)' }}</p>
                                <p class="text-sm text-gray-500">{{ $app->vacancy?->company?->name ?? '' }}</p>
                            </div>
                            @php
                                $colors = [
                                    'pending'   => 'bg-gray-100 text-gray-700',
                                    'screening' => 'bg-blue-100 text-blue-700',
                                    'interview' => 'bg-yellow-100 text-yellow-700',
                                    'hired'     => 'bg-green-100 text-green-700',
                                    'rejected'  => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-semibold {{ $colors[$app->status->value] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $app->status->label() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $app->created_at->diffForHumans() }}</p>
                    </a>
                @empty
                    <div class="px-6 py-10 text-center text-gray-400 text-sm">
                        Ви ще не подавали заявок.
                        <a href="{{ route('home') }}" class="text-blue-600 hover:underline">Знайти вакансії →</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Recommended vacancies --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl border employer-card-border dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Рекомендовані вакансії</h2>
            </div>
            @if($this->recommendedVacancies->isEmpty())
                <div class="px-6 py-10 text-center text-gray-400 dark:text-gray-500 text-sm">Немає рекомендацій</div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-x divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($this->recommendedVacancies as $vacancy)
                        <a href="{{ route('jobs.show', $vacancy->slug) }}"
                           class="block p-5 transition-transform hover:-translate-y-1">
                            <p class="font-semibold text-gray-900 dark:text-gray-100 mb-1">{{ $vacancy->title }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">{{ $vacancy->company->name }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">
                                {{ $vacancy->city?->name ?? 'Вся Україна' }}
                                @if($vacancy->salary_from || $vacancy->salary_to)
                                    · {{ $vacancy->salary_from ? number_format($vacancy->salary_from, 0, '.', ' ') : '' }}
                                    @if($vacancy->salary_from && $vacancy->salary_to) – @endif
                                    {{ $vacancy->salary_to ? number_format($vacancy->salary_to, 0, '.', ' ') : '' }}
                                    {{ $vacancy->currency }}
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
