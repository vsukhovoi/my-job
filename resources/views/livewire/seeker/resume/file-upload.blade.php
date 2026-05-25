<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Services\ResumeFileService;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Resume $resume;

    #[Validate('nullable|file|mimes:pdf,doc,docx|max:5120')]
    public $file = null;

    public bool $showUploader = false;

    public function uploadFile(ResumeFileService $service): void
    {
        $this->validate();

        if (!$this->file) {
            return;
        }

        try {
            $this->resume = $service->upload($this->resume, $this->file);
            $this->file = null;
            $this->showUploader = false;
            $this->dispatch('file-uploaded');
        } catch (\Exception $e) {
            $this->addError('file', $e->getMessage());
        }
    }

    public function deleteFile(ResumeFileService $service): void
    {
        try {
            $this->resume = $service->delete($this->resume);
        } catch (\Exception $e) {
            $this->addError('file', $e->getMessage());
        }
    }
}; ?>

<div>
    @if ($resume->hasAttachedFile())
        {{-- File attached --}}
        <div class="flex items-center gap-3 px-3 py-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
            <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-indigo-700 dark:text-indigo-300 truncate">
                    {{ $resume->attached_file_original_name }}
                </p>
                <p class="text-xs text-indigo-500 dark:text-indigo-400">
                    {{ $resume->getAttachedFileSizeFormatted() }}
                </p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <a href="{{ route('resumes.file.download', $resume) }}"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:text-indigo-300 dark:hover:bg-indigo-800 transition">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Завантажити
                </a>
                <button wire:click="deleteFile"
                        wire:confirm="Видалити прикріплений файл?"
                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30 transition">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    @elseif ($showUploader)
        {{-- Upload form --}}
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <label class="flex-1">
                    <input type="file"
                           wire:model="file"
                           accept=".pdf,.doc,.docx"
                           class="block w-full text-xs text-gray-600 dark:text-gray-400
                                  file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                                  file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700
                                  dark:file:bg-blue-900/40 dark:file:text-blue-300
                                  hover:file:bg-blue-100 cursor-pointer">
                </label>
                <button wire:click="$set('showUploader', false)"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div wire:loading wire:target="file" class="text-xs text-gray-500 dark:text-gray-400">
                Завантаження файлу...
            </div>

            @error('file')
                <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @if ($file)
                <button wire:click="uploadFile"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 disabled:opacity-60 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                    Прикріпити
                </button>
            @endif

            <p class="text-xs text-gray-400 dark:text-gray-500">PDF, DOC або DOCX · до 5 МБ</p>
        </div>
    @else
        {{-- Trigger button --}}
        <button wire:click="$set('showUploader', true)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50 transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            Прикріпити файл
        </button>
    @endif
</div>
