<?php

declare(strict_types=1);

use App\Models\Resume;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?string $flashMessage = null;

    public function mount(): void
    {
        if (session()->has('resume_published')) {
            $this->flashMessage = session('resume_published');
        }
    }

    #[Computed]
    public function resumes(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()
            ->resumes()
            ->withCount('experiences', 'skills')
            ->latest()
            ->get();
    }

    public function toggleStatus(int $resumeId): void
    {
        $resume = $this->findOwned($resumeId);

        $resume->update([
            'status' => $resume->status === 'published' ? 'draft' : 'published',
        ]);

        unset($this->resumes);
    }

    public function deleteResume(int $resumeId): void
    {
        $resume = $this->findOwned($resumeId);
        $resume->delete();
        unset($this->resumes);
    }

    private function findOwned(int $resumeId): Resume
    {
        return Resume::where('id', $resumeId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    }
}; ?>

<div class="min-h-screen seeker-dashboard-bg dark:bg-gray-900">
    <x-seeker-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Flash --}}
        @if ($flashMessage)
            <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-2xl px-5 py-4 text-sm font-medium">
                <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                {{ $flashMessage }}
            </div>
        @endif

        {{-- Header row --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Мої резюме</h2>
            <a href="{{ route('resumes.create') }}"
               class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                Створити резюме
            </a>
        </div>

        {{-- Resume list --}}
        @forelse($this->resumes as $resume)
            @php
                $isPublished = $resume->status === 'published';
                $name = trim(($resume->personal_info['first_name'] ?? '') . ' ' . ($resume->personal_info['last_name'] ?? ''));
                $displayTitle = $name ?: ($resume->title ?: 'Без назви');
                $position = $resume->personal_info['position'] ?? null;
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                {{-- Card header --}}
                <div class="px-6 py-5 flex flex-wrap items-start gap-4">

                    {{-- Avatar --}}
                    <div class="shrink-0 w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300 font-bold text-lg">
                        {{ mb_strtoupper(mb_substr($displayTitle, 0, 1)) }}
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">{{ $displayTitle }}</h3>
                            @if ($isPublished)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                    Активне
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span>
                                    Чернетка
                                </span>
                            @endif
                        </div>
                        @if ($position)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $position }}</p>
                        @endif
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            Оновлено {{ $resume->updated_at->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Stats --}}
                    <div class="w-full sm:w-auto flex items-center gap-5 text-center pt-2 sm:pt-0 border-t border-gray-100 dark:border-gray-700 sm:border-0">
                        <div class="flex-1 sm:flex-none">
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($resume->views_count) }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">переглядів</p>
                        </div>
                        <div class="flex-1 sm:flex-none">
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $resume->experiences_count }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">досвід</p>
                        </div>
                        <div class="flex-1 sm:flex-none">
                            <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $resume->skills_count }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">навичок</p>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-2">

                    {{-- View --}}
                    @if ($isPublished)
                        <a href="{{ route('resumes.show', $resume->id) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Переглянути
                        </a>
                    @endif

                    {{-- Toggle Active/Draft --}}
                    <button
                        wire:click="toggleStatus({{ $resume->id }})"
                        wire:confirm="{{ $isPublished ? 'Перевести резюме в чернетку?' : 'Опублікувати резюме?' }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition
                               {{ $isPublished
                                   ? 'bg-amber-100 text-amber-700 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300'
                                   : 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300' }}">
                        @if ($isPublished)
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                            В чернетку
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Активувати
                        @endif
                    </button>

                    {{-- Edit --}}
                    <a href="{{ route('resumes.edit', $resume->id) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300 transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Редагувати
                    </a>

                    {{-- Export PDF --}}
                    <a href="{{ route('resumes.export.pdf', $resume->id) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-purple-100 text-purple-700 hover:bg-purple-200 dark:bg-purple-900/40 dark:text-purple-300 transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Експорт PDF
                    </a>

                    {{-- File attachment --}}
                    @livewire('seeker.resume.file-upload', ['resume' => $resume], key('file-upload-' . $resume->id))

                    {{-- Delete --}}
                    <button
                        wire:click="deleteResume({{ $resume->id }})"
                        wire:confirm="Видалити резюме «{{ $displayTitle }}»? Цю дію не можна відмінити."
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300 transition ml-auto">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Видалити
                    </button>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm px-6 py-16 text-center">
                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-400 mb-4">У вас ще немає резюме</p>
                <a href="{{ route('resumes.create') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition">
                    Створити перше резюме
                </a>
            </div>
        @endforelse

    </div>
</div>
