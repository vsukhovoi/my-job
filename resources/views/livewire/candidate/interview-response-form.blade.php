<?php

declare(strict_types=1);

use App\Models\InterviewRequest;
use App\Models\InterviewResponse;
use App\Services\AsyncInterviewService;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $interviewRequestId;

    public int $step = 0;

    public bool $reviewing = false;

    /** @var array<int, string> */
    public array $answers = [];

    public string $submitted = '';

    public function mount(int $interviewRequestId): void
    {
        $this->interviewRequestId = $interviewRequestId;

        $request = InterviewRequest::with('response')->findOrFail($interviewRequestId);

        abort_unless(
            $request->application->user_id === auth()->id(),
            403
        );

        if ($request->response) {
            foreach ($request->response->answers as $item) {
                $this->answers[$item['question_index']] = $item['text'];
            }

            if ($request->response->isSubmitted()) {
                $this->submitted = 'done';
            }
        }
    }

    #[Computed]
    public function request(): InterviewRequest
    {
        return InterviewRequest::with(['application', 'response'])->findOrFail($this->interviewRequestId);
    }

    public function daysLeft(): ?int
    {
        $deadline = $this->request->deadline_at;

        if (! $deadline) {
            return null;
        }

        return max(0, (int) now()->diffInDays($deadline, false));
    }

    public function next(AsyncInterviewService $service): void
    {
        $total = count($this->request->questions);

        $this->validate([
            "answers.{$this->step}" => 'required|string|max:1000',
        ]);

        $this->saveDraft($service);

        if ($this->step < $total - 1) {
            $this->step++;
        } else {
            $this->reviewing = true;
        }
    }

    public function back(): void
    {
        if ($this->reviewing) {
            $this->reviewing = false;
        } elseif ($this->step > 0) {
            $this->step--;
        }
    }

    public function editStep(int $index): void
    {
        $this->step      = $index;
        $this->reviewing = false;
    }

    public function saveDraft(AsyncInterviewService $service): void
    {
        $answers = [];
        foreach ($this->answers as $i => $text) {
            $answers[] = ['question_index' => $i, 'text' => $text];
        }

        $service->saveResponse($this->request, auth()->user(), $answers, submit: false);
        unset($this->request);
    }

    public function submit(AsyncInterviewService $service): void
    {
        $this->validate([
            'answers.*' => 'required|string|max:1000',
        ]);

        $answers = [];
        foreach ($this->answers as $i => $text) {
            $answers[] = ['question_index' => $i, 'text' => $text];
        }

        try {
            $service->saveResponse($this->request, auth()->user(), $answers, submit: true);
            $this->submitted = 'done';
            unset($this->request);
        } catch (\RuntimeException $e) {
            $this->addError('answers', $e->getMessage());
        }
    }
}; ?>

<div class="space-y-5">

    @if($submitted === 'done')
        {{-- Відповіді надіслано або read-only --}}
        <div class="rounded-xl border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20 p-4 mb-4">
            <p class="text-sm font-medium text-green-700 dark:text-green-400">
                Ваші відповіді надіслано роботодавцю.
            </p>
        </div>

        @php $response = $this->request->response; @endphp

        @if($response)
            <div class="space-y-4">
                @foreach($this->request->questions as $i => $question)
                    @php $answer = collect($response->answers)->firstWhere('question_index', $i); @endphp
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                            Питання {{ $i + 1 }}
                        </p>
                        <p class="text-sm text-gray-800 dark:text-gray-200 mb-3">{{ $question }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-wrap">
                            {{ $answer['text'] ?? '—' }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

    @elseif($reviewing)
        {{-- Екран підтвердження --}}
        <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">Перевірте відповіді</h3>

        <div class="space-y-4">
            @foreach($this->request->questions as $i => $question)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <div class="flex items-start justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                            Питання {{ $i + 1 }}: {{ $question }}
                        </p>
                        <button type="button"
                                wire:click="editStep({{ $i }})"
                                class="text-xs text-orange-500 hover:text-orange-600 font-medium ml-2 shrink-0">
                            Змінити
                        </button>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                        {{ $answers[$i] ?? '—' }}
                    </p>
                </div>
            @endforeach
        </div>

        @error('answers') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror

        <div class="flex items-center gap-3 pt-1">
            <button type="button"
                    wire:click="back"
                    class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 transition">
                ← Назад
            </button>
            <button type="button"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600
                           text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                Зберегти чернетку
            </button>
            <button type="button"
                    wire:click="submit"
                    wire:loading.attr="disabled"
                    class="px-5 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-60
                           text-white text-sm font-bold rounded-lg transition">
                <span wire:loading.remove wire:target="submit">Надіслати ✓</span>
                <span wire:loading wire:target="submit">Надсилання...</span>
            </button>
        </div>

    @else
        {{-- Крок N/Total --}}
        @php
            $total   = count($this->request->questions);
            $question = $this->request->questions[$step];
            $daysLeft = $this->daysLeft();
        @endphp

        @if($daysLeft !== null)
            <div class="text-sm text-amber-600 dark:text-amber-400">
                ⏳ Залишилось {{ $daysLeft }} {{ $daysLeft === 1 ? 'день' : ($daysLeft < 5 ? 'дні' : 'днів') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-1">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                Крок {{ $step + 1 }} з {{ $total }}
            </h3>
            <span class="text-xs text-gray-400">{{ $step + 1 }}/{{ $total }}</span>
        </div>

        {{-- Progress bar --}}
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
            <div class="bg-orange-500 h-1.5 rounded-full transition-all"
                 style="width: {{ (($step + 1) / $total) * 100 }}%"></div>
        </div>

        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $question }}</p>

        <div>
            <textarea wire:model="answers.{{ $step }}"
                      rows="5"
                      maxlength="1000"
                      placeholder="Ваша відповідь..."
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg
                             bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm
                             focus:ring-2 focus:ring-orange-400 focus:border-transparent transition resize-none">
            </textarea>
            <div class="flex justify-between mt-1">
                @error("answers.{$step}")
                    <p class="text-red-500 text-xs">{{ $message }}</p>
                @else
                    <span></span>
                @enderror
                <span class="text-xs text-gray-400">
                    {{ strlen($answers[$step] ?? '') }}/1000
                </span>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            @if($step > 0)
                <button type="button"
                        wire:click="back"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 transition">
                    ← Назад
                </button>
            @endif

            <button type="button"
                    wire:click="next"
                    wire:loading.attr="disabled"
                    class="px-5 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-60
                           text-white text-sm font-bold rounded-lg transition ml-auto">
                <span wire:loading.remove wire:target="next">
                    {{ $step < $total - 1 ? 'Далі →' : 'Переглянути відповіді' }}
                </span>
                <span wire:loading wire:target="next">Збереження...</span>
            </button>
        </div>
    @endif

</div>
