<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\InterviewType;
use App\Enums\MessageType;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\Interview;
use App\Models\MessageTemplate;
use App\Services\ApplicationStatusService;
use App\Services\CommunicationService;
use App\Services\InterviewService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Application $application;

    // Notes
    public string $newNoteText    = '';
    public ?int   $editingNoteId  = null;
    public string $editingNoteText = '';

    // Interview
    public bool   $showInterviewForm   = false;
    public string $ivDate              = '';
    public string $ivTime              = '';
    public string $ivDuration          = '60';
    public string $ivType              = '';
    public string $ivMeetingLink       = '';
    public string $ivOfficeAddress     = '';
    public string $ivNotes             = '';
    public string $ivInternalNotes     = '';
    public string $ivScheduled         = '';

    // Message
    public bool   $showMessageForm  = false;
    public string $msgType          = '';
    public string $msgTemplateId    = '';
    public string $msgSubject       = '';
    public string $msgBody          = '';
    public bool   $msgCopyToSender  = false;
    public string $msgSent          = '';

    public function mount(int $applicationId): void
    {
        $this->application = Application::with(['user', 'vacancy'])
            ->whereHas('vacancy', fn ($q) => $q->where('company_id', auth()->user()->company->id))
            ->findOrFail($applicationId);
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function updateStatus(string $status): void
    {
        try {
            app(ApplicationStatusService::class)->changeStatus(
                $this->application,
                ApplicationStatus::from($status),
                auth()->user(),
                'employer',
            );
            $this->application->refresh();
        } catch (\Throwable) {
            // Невалідний статус або заборонена дія
        }
    }

    // ── Rating ────────────────────────────────────────────────────────────────

    public function updateRating(int $rating): void
    {
        $this->application->update(['rating' => $rating]);
        $this->application->refresh();
    }

    // ── Notes CRUD ────────────────────────────────────────────────────────────

    public function addNote(): void
    {
        $text = trim($this->newNoteText);

        if ($text === '') {
            return;
        }

        ApplicationNote::create([
            'application_id' => $this->application->id,
            'author_id'      => auth()->id(),
            'text'           => $text,
        ]);

        $this->newNoteText = '';
    }

    public function startEdit(int $noteId): void
    {
        $note = $this->resolveOwnNote($noteId);
        if (!$note) {
            return;
        }

        $this->editingNoteId   = $noteId;
        $this->editingNoteText = $note->text;
    }

    public function cancelEdit(): void
    {
        $this->editingNoteId   = null;
        $this->editingNoteText = '';
    }

    public function saveEdit(): void
    {
        $text = trim($this->editingNoteText);

        if ($text === '' || !$this->editingNoteId) {
            return;
        }

        $note = $this->resolveOwnNote($this->editingNoteId);
        if (!$note) {
            return;
        }

        $note->update([
            'text'      => $text,
            'is_edited' => true,
        ]);

        $this->editingNoteId   = null;
        $this->editingNoteText = '';
    }

    public function deleteNote(int $noteId): void
    {
        $note = $this->resolveOwnNote($noteId);
        $note?->delete();
    }

    // ── Interview ─────────────────────────────────────────────────────────────

    public function scheduleInterview(): void
    {
        $this->validate([
            'ivDate'     => 'required|date|after:today',
            'ivTime'     => 'required|date_format:H:i',
            'ivDuration' => 'required|integer|in:30,60,90,120',
            'ivType'     => 'required|in:' . implode(',', array_column(InterviewType::cases(), 'value')),
        ]);

        $scheduledAt = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $this->ivDate . ' ' . $this->ivTime);

        app(InterviewService::class)->schedule(
            application:   $this->application,
            creator:       auth()->user(),
            scheduledAt:   $scheduledAt,
            duration:      (int) $this->ivDuration,
            type:          InterviewType::from($this->ivType),
            meetingLink:   $this->ivMeetingLink,
            officeAddress: $this->ivOfficeAddress,
            notes:         $this->ivNotes,
            internalNotes: $this->ivInternalNotes,
        );

        $this->reset('ivDate', 'ivTime', 'ivDuration', 'ivType', 'ivMeetingLink', 'ivOfficeAddress', 'ivNotes', 'ivInternalNotes');
        $this->showInterviewForm = false;
        $this->ivScheduled       = 'ok';
    }

    public function cancelInterview(int $interviewId): void
    {
        $interview = Interview::whereHas('application', fn ($q) => $q->whereHas('vacancy', fn ($v) => $v->where('company_id', auth()->user()->company->id)))
            ->findOrFail($interviewId);

        app(InterviewService::class)->cancel($interview, 'Скасовано роботодавцем');
    }

    // ── Communication ─────────────────────────────────────────────────────────

    public function updatedMsgTemplateId(string $value): void
    {
        if (!$value) {
            return;
        }

        $tpl = MessageTemplate::find((int) $value);
        if (!$tpl) {
            return;
        }

        $vars = app(CommunicationService::class)->buildVars($this->application, auth()->user());

        $this->msgSubject = MessageTemplate::replaceVars($tpl->subject, $vars);
        $this->msgBody    = MessageTemplate::replaceVars($tpl->body, $vars);
        $this->msgType    = $tpl->type->value;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'msgType'    => 'required|in:' . implode(',', array_column(MessageType::cases(), 'value')),
            'msgSubject' => 'required|string|max:255',
            'msgBody'    => 'required|string',
        ]);

        app(CommunicationService::class)->send(
            application:   $this->application,
            sender:        auth()->user(),
            type:          MessageType::from($this->msgType),
            subject:       $this->msgSubject,
            body:          $this->msgBody,
            copyToSender:  $this->msgCopyToSender,
            templateId:    $this->msgTemplateId ? (int) $this->msgTemplateId : null,
        );

        $this->reset('msgType', 'msgTemplateId', 'msgSubject', 'msgBody', 'msgCopyToSender');
        $this->showMessageForm = false;
        $this->msgSent         = 'ok';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveOwnNote(int $noteId): ?ApplicationNote
    {
        return ApplicationNote::where('application_id', $this->application->id)
            ->where('author_id', auth()->id())
            ->find($noteId);
    }

    #[Computed]
    public function notes(): \Illuminate\Database\Eloquent\Collection
    {
        return ApplicationNote::with('author')
            ->where('application_id', $this->application->id)
            ->latest()
            ->get();
    }

    #[Computed]
    public function statuses(): array
    {
        return array_values(array_filter(
            ApplicationStatus::cases(),
            fn (ApplicationStatus $s) => in_array('employer', $s->allowedActors(), true),
        ));
    }

    #[Computed]
    public function messageTypes(): array
    {
        return MessageType::cases();
    }

    #[Computed]
    public function templates(): \Illuminate\Database\Eloquent\Collection
    {
        return MessageTemplate::where('company_id', auth()->user()->company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->application->messages()->with('sender')->get();
    }

    #[Computed]
    public function interviews(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->application->interviews()->with('creator')->get();
    }

    #[Computed]
    public function interviewTypes(): array
    {
        return InterviewType::cases();
    }

    #[Computed]
    public function timeSlots(): array
    {
        $slots = [];
        for ($h = 9; $h <= 18; $h++) {
            $slots[] = sprintf('%02d:00', $h);
            if ($h < 18) {
                $slots[] = sprintf('%02d:30', $h);
            }
        }
        return $slots;
    }
}; ?>

<div class="min-h-screen bg-gray-50">
    <x-employer-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Back --}}
        <a href="{{ route('employer.candidates') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 mb-6">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Мої кандидати
        </a>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ===== LEFT ===== --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- Candidate card --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">{{ $application->user->name }}</h1>
                            <p class="text-sm text-gray-400 mt-0.5">{{ $application->user->email }}</p>
                        </div>
                        <div class="flex items-center gap-0.5 shrink-0">
                            @for($star = 1; $star <= 5; $star++)
                                <button wire:click="updateRating({{ $star }})"
                                        class="text-2xl leading-none {{ $star <= ($application->rating ?? 0) ? 'text-amber-400' : 'text-gray-200' }} hover:text-amber-400 transition-colors">★</button>
                            @endfor
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Вакансія</p>
                            <p class="font-medium text-gray-800">{{ $application->vacancy->title }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Дата подачі</p>
                            <p class="font-medium text-gray-800">{{ $application->created_at->format('d.m.Y H:i') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Cover letter --}}
                @if($application->cover_letter)
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <h2 class="text-sm font-semibold text-gray-900 mb-3">Супровідний лист</h2>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $application->cover_letter }}</p>
                    </div>
                @endif

                {{-- Resume --}}
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Резюме</h2>
                    <a href="{{ $application->resume_url }}" target="_blank"
                       class="resume-btn inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-xl text-sm font-medium hover:bg-blue-100 transition-colors dark:bg-[#1F2937] dark:text-gray-200 dark:border dark:border-[#4B5563] dark:hover:bg-gray-700">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        Завантажити
                    </a>
                </div>

                {{-- ===== NOTES ===== --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">
                        Нотатки
                        @if($this->notes->count())
                            <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $this->notes->count() }})</span>
                        @endif
                    </h2>

                    {{-- Existing notes --}}
                    @forelse($this->notes as $note)
                        <div class="mb-4 last:mb-0" wire:key="note-{{ $note->id }}">
                            <div class="flex items-start justify-between gap-2 mb-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-xs font-bold text-blue-700">
                                        {{ mb_strtoupper(mb_substr($note->author->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">{{ $note->author->name }}</span>
                                        <span class="text-xs text-gray-400 ml-2">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                                        @if($note->is_edited)
                                            <span class="text-xs text-gray-400 ml-1">(ред.)</span>
                                        @endif
                                    </div>
                                </div>

                                @if($note->author_id === auth()->id())
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button wire:click="startEdit({{ $note->id }})"
                                                class="text-xs text-gray-400 hover:text-blue-600 transition-colors">
                                            Редагувати
                                        </button>
                                        <button wire:click="deleteNote({{ $note->id }})"
                                                wire:confirm="Видалити цю нотатку?"
                                                class="text-xs text-gray-400 hover:text-red-600 transition-colors">
                                            Видалити
                                        </button>
                                    </div>
                                @endif
                            </div>

                            {{-- Edit mode --}}
                            @if($editingNoteId === $note->id)
                                <div class="ml-9">
                                    <textarea wire:model="editingNoteText"
                                              rows="3"
                                              class="w-full border border-blue-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                                    <div class="flex gap-2 mt-2">
                                        <button wire:click="saveEdit"
                                                class="px-4 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg hover:bg-gray-700 transition-colors">
                                            Зберегти
                                        </button>
                                        <button wire:click="cancelEdit"
                                                class="px-4 py-1.5 text-gray-500 text-xs font-medium hover:text-gray-700 transition-colors">
                                            Скасувати
                                        </button>
                                    </div>
                                </div>
                            @else
                                {{-- View mode --}}
                                <div class="ml-9 bg-gray-50 rounded-xl px-4 py-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                                    {{ $note->text }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 mb-4">Нотаток ще немає. Будьте першим!</p>
                    @endforelse

                    {{-- Add new note --}}
                    <div class="mt-5 pt-5 border-t border-gray-100">
                        <textarea wire:model="newNoteText"
                                  rows="3"
                                  placeholder="Додайте нотатку про кандидата..."
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        <div class="flex justify-end mt-2">
                            <button wire:click="addNote"
                                    wire:loading.attr="disabled"
                                    class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                <span wire:loading.remove wire:target="addNote">+ Додати нотатку</span>
                                <span wire:loading wire:target="addNote">Збереження...</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ===== INTERVIEWS ===== --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-900">
                            Співбесіди
                            @if($this->interviews->count())
                                <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $this->interviews->count() }})</span>
                            @endif
                        </h2>
                        <button wire:click="$set('showInterviewForm', true)"
                                class="text-xs font-medium text-blue-600 hover:text-blue-800">
                            + Запланувати
                        </button>
                    </div>

                    @if($ivScheduled === 'ok')
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-xs text-green-700 font-medium">
                            Запрошення надіслано. Нагадування заплановані автоматично.
                        </div>
                    @endif

                    @if($showInterviewForm)
                        <div class="mb-5 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Дата <span class="text-red-500">*</span></label>
                                    <input type="date" wire:model="ivDate"
                                           min="{{ now()->addDay()->format('Y-m-d') }}"
                                           max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                    @error('ivDate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Час <span class="text-red-500">*</span></label>
                                    <select wire:model="ivTime"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Оберіть час...</option>
                                        @foreach($this->timeSlots as $slot)
                                            <option value="{{ $slot }}">{{ $slot }}</option>
                                        @endforeach
                                    </select>
                                    @error('ivTime') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Тип <span class="text-red-500">*</span></label>
                                    <select wire:model="ivType"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Оберіть...</option>
                                        @foreach($this->interviewTypes as $t)
                                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('ivType') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Тривалість</label>
                                    <select wire:model="ivDuration"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="30">30 хв</option>
                                        <option value="60">1 год</option>
                                        <option value="90">1.5 год</option>
                                        <option value="120">2 год</option>
                                    </select>
                                </div>
                            </div>

                            @if($ivType === 'video')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Посилання на зустріч</label>
                                    <input type="url" wire:model="ivMeetingLink" placeholder="https://meet.google.com/..."
                                           class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                </div>
                            @endif

                            @if($ivType === 'in_person')
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Адреса офісу</label>
                                    <input type="text" wire:model="ivOfficeAddress"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                </div>
                            @endif

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Примітки для кандидата</label>
                                <textarea wire:model="ivNotes" rows="2"
                                          placeholder="Будь ласка, підготуйтеся до..."
                                          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Внутрішні нотатки (видно тільки команді)</label>
                                <textarea wire:model="ivInternalNotes" rows="2"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                            </div>

                            <div class="flex gap-2 pt-1">
                                <button wire:click="scheduleInterview"
                                        wire:loading.attr="disabled"
                                        class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    <span wire:loading.remove wire:target="scheduleInterview">Запланувати та надіслати</span>
                                    <span wire:loading wire:target="scheduleInterview">Надсилання...</span>
                                </button>
                                <button wire:click="$set('showInterviewForm', false)"
                                        class="px-5 py-2 text-gray-500 text-sm font-medium hover:text-gray-700">
                                    Скасувати
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Interviews list --}}
                    @if($this->interviews->isEmpty() && !$showInterviewForm)
                        <p class="text-sm text-gray-400">Співбесід ще не заплановано.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($this->interviews as $iv)
                                @php
                                    $ivColor = match($iv->status->color()) {
                                        'blue'   => 'bg-blue-50 border-blue-100 text-blue-700',
                                        'green'  => 'bg-green-50 border-green-100 text-green-700',
                                        'yellow' => 'bg-yellow-50 border-yellow-100 text-yellow-700',
                                        'red'    => 'bg-red-50 border-red-100 text-red-600',
                                        default  => 'bg-gray-50 border-gray-100 text-gray-600',
                                    };
                                @endphp
                                <div class="p-4 rounded-xl border {{ $ivColor }}" wire:key="iv-{{ $iv->id }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                                <span class="text-xs font-semibold">{{ $iv->type->label() }}</span>
                                                <span class="text-xs opacity-75">{{ $iv->scheduled_at->format('d.m.Y о H:i') }}</span>
                                                <span class="text-xs opacity-75">{{ $iv->duration }} хв</span>
                                            </div>
                                            <span class="text-xs font-medium">{{ $iv->status->label() }}</span>
                                            @if($iv->meeting_link)
                                                <a href="{{ $iv->meeting_link }}" target="_blank"
                                                   class="block text-xs underline mt-1">Посилання на зустріч</a>
                                            @endif
                                            @if($iv->office_address)
                                                <p class="text-xs mt-1">{{ $iv->office_address }}</p>
                                            @endif
                                        </div>
                                        @if($iv->status->value !== 'cancelled')
                                            <button wire:click="cancelInterview({{ $iv->id }})"
                                                    wire:confirm="Скасувати цю співбесіду?"
                                                    class="text-xs hover:underline opacity-70 hover:opacity-100 shrink-0">
                                                Скасувати
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ===== COMMUNICATION ===== --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-900">
                            Повідомлення
                            @if($this->messages->count())
                                <span class="ml-1.5 text-xs font-normal text-gray-400">({{ $this->messages->count() }})</span>
                            @endif
                        </h2>
                        <button wire:click="$set('showMessageForm', true)"
                                class="text-xs font-medium text-blue-600 hover:text-blue-800">
                            + Надіслати
                        </button>
                    </div>

                    {{-- Success banner --}}
                    @if($msgSent === 'ok')
                        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-xs text-green-700 font-medium">
                            Повідомлення надіслано та поставлено в чергу.
                        </div>
                    @endif

                    {{-- Message form --}}
                    @if($showMessageForm)
                        <div class="mb-5 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-3">

                            <div class="grid grid-cols-2 gap-3">
                                {{-- Template selector --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Шаблон</label>
                                    <select wire:model.live="msgTemplateId"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Без шаблону</option>
                                        @foreach($this->templates as $tpl)
                                            <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                {{-- Type --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Тип <span class="text-red-500">*</span></label>
                                    <select wire:model="msgType"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">Оберіть...</option>
                                        @foreach($this->messageTypes as $mt)
                                            <option value="{{ $mt->value }}">{{ $mt->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('msgType') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Subject --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Тема <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="msgSubject"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
                                @error('msgSubject') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>

                            {{-- Body --}}
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Текст <span class="text-red-500">*</span></label>
                                <textarea wire:model="msgBody" rows="6"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                                @error('msgBody') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>

                            {{-- Copy to sender --}}
                            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                <input type="checkbox" wire:model="msgCopyToSender" class="w-4 h-4 text-blue-600 rounded"/>
                                Відправити копію собі ({{ auth()->user()->email }})
                            </label>

                            {{-- Buttons --}}
                            <div class="flex gap-2 pt-1">
                                <button wire:click="sendMessage"
                                        wire:loading.attr="disabled"
                                        class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                    <span wire:loading.remove wire:target="sendMessage">Надіслати</span>
                                    <span wire:loading wire:target="sendMessage">Надсилання...</span>
                                </button>
                                <button wire:click="$set('showMessageForm', false)"
                                        class="px-5 py-2 text-gray-500 text-sm font-medium hover:text-gray-700 transition-colors">
                                    Скасувати
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- History --}}
                    @if($this->messages->isEmpty() && !$showMessageForm)
                        <p class="text-sm text-gray-400">Повідомлень ще не надсилалось.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($this->messages as $msg)
                                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl" wire:key="msg-{{ $msg->id }}">
                                    <div class="w-7 h-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-700 shrink-0">
                                        {{ mb_strtoupper(mb_substr($msg->sender->name, 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-xs font-semibold text-gray-700">{{ $msg->type->label() }}</span>
                                            <span class="text-xs text-gray-400">{{ $msg->sent_at?->format('d.m.Y H:i') }}</span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-600">
                                                Надіслано
                                            </span>
                                        </div>
                                        <p class="text-xs font-medium text-gray-800 mt-0.5">{{ $msg->subject }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $msg->body }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>

            {{-- ===== RIGHT ===== --}}
            <div class="space-y-5">

                {{-- Status --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Статус</h2>
                    @php
                        $statusColor = match($application->status->color()) {
                            'gray'   => 'bg-gray-100 text-gray-600',
                            'blue'   => 'bg-blue-100 text-blue-700',
                            'yellow' => 'bg-yellow-100 text-yellow-700',
                            'green'  => 'bg-green-100 text-green-700',
                            'red'    => 'bg-red-100 text-red-600',
                            default  => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <div class="mb-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
                            {{ $application->status->label() }}
                        </span>
                    </div>
                    <div class="space-y-1">
                        @foreach($this->statuses as $status)
                            @if($status !== $application->status)
                                <button wire:click="updateStatus('{{ $status->value }}')"
                                        class="w-full text-left px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                                    → {{ $status->label() }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Дії</h2>
                    <div class="space-y-2">
                        <a href="{{ $application->resume_url }}" target="_blank"
                           class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Завантажити резюме
                        </a>
                        <a href="mailto:{{ $application->user->email }}"
                           class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Написати email
                        </a>
                        <button wire:click="updateStatus('rejected')"
                                wire:confirm="Відхилити кандидата?"
                                class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Відхилити
                        </button>
                    </div>
                </div>

                {{-- Vacancy --}}
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-2">Вакансія</h2>
                    <p class="text-sm font-medium text-gray-800">{{ $application->vacancy->title }}</p>
                    <p class="text-xs text-gray-400 mt-1 capitalize">{{ implode(', ', array_map(fn($t) => \App\Enums\EmploymentType::from($t)->label(), (array) $application->vacancy->employment_type)) }}</p>
                    <a href="{{ route('employer.vacancies.edit', $application->vacancy->id) }}"
                       class="inline-block mt-3 text-xs text-blue-600 hover:underline">
                        Редагувати вакансію →
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
