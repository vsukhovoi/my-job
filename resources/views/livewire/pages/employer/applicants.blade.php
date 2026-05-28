<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Vacancy;
use App\Services\ApplicationStatusService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Vacancy $vacancy;

    public string $filterStatus = '';

    /** @var array<int, string> */
    public array $notes = [];

    public function mount(int $vacancyId): void
    {
        $this->vacancy = Vacancy::where('company_id', auth()->user()->company->id)
            ->with('company')
            ->findOrFail($vacancyId);
    }

    #[On('application-status-updated')]
    public function handleStatusUpdate(int $applicationId, string $newStatus): void
    {
        unset($this->applications);
    }

    public function updateStatus(int $applicationId, string $status): void
    {
        $application = Application::where('vacancy_id', $this->vacancy->id)
            ->findOrFail($applicationId);

        try {
            app(ApplicationStatusService::class)->changeStatus(
                $application,
                ApplicationStatus::from($status),
                auth()->user(),
                'employer',
            );
        } catch (\Throwable) {
            // Невалідний статус або заборонена дія — ігноруємо
        }
    }

    public function saveNote(int $applicationId): void
    {
        $text = trim($this->notes[$applicationId] ?? '');

        if ($text === '') {
            return;
        }

        Application::where('vacancy_id', $this->vacancy->id)->findOrFail($applicationId);

        ApplicationNote::create([
            'application_id' => $applicationId,
            'author_id'      => auth()->id(),
            'text'           => $text,
        ]);

        $this->notes[$applicationId] = '';
    }

    #[Computed]
    public function applications(): \Illuminate\Database\Eloquent\Collection
    {
        return Application::with('user')
            ->where('vacancy_id', $this->vacancy->id)
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return ApplicationStatus::cases();
    }

    /** @return array<string, int> */
    #[Computed]
    public function countByStatus(): array
    {
        return Application::where('vacancy_id', $this->vacancy->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}; ?>

<div class="min-h-screen bg-gray-50">
    <x-employer-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Sub-header --}}
        <div class="mb-6">
            <a href="{{ route('employer.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 mb-3">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Вакансії
            </a>
            <h2 class="text-lg font-semibold text-gray-900">{{ $vacancy->title }}</h2>
            <p class="text-gray-500 text-sm mt-1">{{ $this->applications->count() }} відгуків</p>
        </div>

        {{-- Status filter pills --}}
        <div class="flex flex-wrap gap-2 mb-6">
            <button wire:click="$set('filterStatus', '')"
                    class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors
                           {{ $filterStatus === '' ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400' }}">
                Всі
            </button>
            @foreach($this->statuses as $status)
                <button wire:click="$set('filterStatus', '{{ $status->value }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors
                               {{ $filterStatus === $status->value ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400' }}">
                    {{ $status->label() }}
                    @if(isset($this->countByStatus[$status->value]))
                        <span class="ml-1 opacity-70">{{ $this->countByStatus[$status->value] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            @if($this->applications->isEmpty())
                <div class="p-12 text-center text-gray-400">
                    {{ $filterStatus ? 'Кандидатів з таким статусом немає.' : 'Відгуків ще немає.' }}
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($this->applications as $application)
                        <div class="p-6" x-data="{ showNotes: false }">
                            <div class="flex items-start justify-between gap-4">

                                {{-- Candidate info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 mb-1">
                                        <p class="font-semibold text-gray-900">{{ $application->user->name }}</p>
                                        @php
                                            $color = $application->status->color();
                                            $colorMap = [
                                                'gray'   => 'bg-gray-100 text-gray-600',
                                                'blue'   => 'bg-blue-100 text-blue-700',
                                                'yellow' => 'bg-yellow-100 text-yellow-700',
                                                'green'  => 'bg-green-100 text-green-700',
                                                'red'    => 'bg-red-100 text-red-600',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colorMap[$color] ?? 'bg-gray-100 text-gray-600' }}">
                                            {{ $application->status->label() }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-400">{{ $application->user->email }}</p>
                                    @if($application->cover_letter)
                                        <p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $application->cover_letter }}</p>
                                    @endif
                                    @php $latestNote = $application->notes()->latest()->first(); @endphp
                                    @if($latestNote)
                                        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-1.5 mt-2 inline-block">
                                            {{ $latestNote->author->name }}: {{ Str::limit($latestNote->text, 80) }}
                                        </p>
                                    @endif
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <p class="text-xs text-gray-400">{{ $application->created_at->format('d.m.Y') }}</p>

                                    <a href="{{ $application->resume_url }}" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline font-medium">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        </svg>
                                        Резюме
                                    </a>

                                    <select wire:change="updateStatus({{ $application->id }}, $event.target.value)"
                                            class="text-xs border border-gray-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        @foreach($this->statuses as $status)
                                            @php $employerAllowed = in_array('employer', $status->allowedActors(), true); @endphp
                                            <option value="{{ $status->value }}"
                                                {{ $application->status === $status ? 'selected' : '' }}
                                                {{ ! $employerAllowed && $application->status !== $status ? 'disabled' : '' }}>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <a href="{{ route('employer.candidate.detail', $application->id) }}"
                                       class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                        Деталі →
                                    </a>
                                </div>
                            </div>

                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
