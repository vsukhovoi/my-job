<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Models\InterviewRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $interviewRequestId;

    public string $actionDone = '';

    public function mount(int $interviewRequestId): void
    {
        $this->interviewRequestId = $interviewRequestId;

        $request = InterviewRequest::with('application.vacancy.company')->findOrFail($interviewRequestId);

        abort_unless(
            $request->application->vacancy->company->user_id === auth()->id(),
            403
        );
    }

    #[Computed]
    public function request(): InterviewRequest
    {
        return InterviewRequest::with(['response.candidate', 'application.vacancy'])->findOrFail($this->interviewRequestId);
    }

    public function reject(): void
    {
        $application = $this->request->application;
        $oldStatus   = $application->status;

        $application->logStatus(ApplicationStatus::Rejected, auth()->user());

        event(new ApplicationStatusChanged($application, $oldStatus, ApplicationStatus::Rejected, auth()->user()));

        $this->actionDone = 'rejected';
        unset($this->request);
    }

    public function moveToInterview(): void
    {
        $application = $this->request->application;
        $oldStatus   = $application->status;

        $application->logStatus(ApplicationStatus::Interview, auth()->user());

        event(new ApplicationStatusChanged($application, $oldStatus, ApplicationStatus::Interview, auth()->user()));

        $this->actionDone = 'interview';
        unset($this->request);
    }
}; ?>

<div class="space-y-5">
    @if($actionDone === 'rejected')
        <div class="rounded-xl border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/20 p-4">
            <p class="text-sm text-red-700 dark:text-red-400">Заявку відхилено.</p>
        </div>
    @elseif($actionDone === 'interview')
        <div class="rounded-xl border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20 p-4">
            <p class="text-sm text-green-700 dark:text-green-400">Кандидата переведено на інтерв'ю.</p>
        </div>
    @else
        @if($this->request->response)
            @php $response = $this->request->response; @endphp

            {{-- Заголовок --}}
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $response->candidate->name ?? '—' }}
                    </p>
                    @if($response->submitted_at)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Відповів {{ $response->submitted_at->format('d.m.Y о H:i') }}
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                    {{ $this->request->status->label() }}
                </span>
            </div>

            {{-- Відповіді --}}
            <div class="space-y-4">
                @foreach($this->request->questions as $i => $question)
                    @php
                        $answer = collect($response->answers)->firstWhere('question_index', $i);
                    @endphp
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                            Питання {{ $i + 1 }}
                        </p>
                        <p class="text-sm text-gray-800 dark:text-gray-200 mb-3">
                            {{ $question }}
                        </p>
                        <p class="text-xs font-semibold text-orange-500 mb-1">Відповідь</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                            {{ $answer['text'] ?? '—' }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Дії --}}
            <div class="flex items-center gap-3 pt-1">
                <button type="button"
                        wire:click="reject"
                        wire:confirm="Відхилити заявку кандидата?"
                        class="px-4 py-2 text-sm border border-red-300 dark:border-red-600
                               text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20
                               rounded-lg font-medium transition">
                    Відхилити
                </button>
                <button type="button"
                        wire:click="moveToInterview"
                        class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold rounded-lg transition">
                    Перевести на інтерв'ю
                </button>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Кандидат ще не надав відповіді.
            </p>
        @endif
    @endif
</div>
