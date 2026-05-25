# Резюме: Вкладення файлу (PDF/DOC/DOCX) — Фаза 1

## Контекст

Кандидат заповнює структуроване резюме на платформі (Resume + personal_info JSON + experiences + skills). Тепер потрібно додати можливість **опційно прикріпити файл резюме** (PDF/DOC/DOCX) до існуючого структурованого резюме.

**Це Фаза 1.** AI-парсинг файлу буде окремою фазою після лончу.

**Принцип:** Файл — це **вкладення** до структурованого резюме, не окрема сутність. Кандидат може завантажити файл лише після створення структурованого резюме.

## Крок 1 — Рекогносцировка (ОБОВ'ЯЗКОВО ПЕРШИМ)

Перед будь-якими змінами **прочитай** і **повідом мені структуру**:

1. `app/Models/Resume.php` — поточні поля, fillable, casts, relationships
2. `database/migrations/*_create_resumes_table.php` — структура таблиці
3. `resources/views/livewire/seeker/resume/` — Volt-компоненти редагування резюме (особливо edit/form)
4. `app/Services/ProfileCompletenessService.php` — як рахується completeness для кандидата
5. `config/filesystems.php` — налаштування дисків (особливо чи є `private` диск)
6. Усі існуючі тести в `tests/Feature/` що стосуються Resume (для розуміння паттерну)

**STOP. Покажи мені результат рекогносцировки і дочекайся підтвердження перед Кроком 2.**

## Крок 2 — Міграція та модель

### Міграція

Створи міграцію `add_attached_file_to_resumes_table`:

```php
Schema::table('resumes', function (Blueprint $table) {
    $table->string('attached_file_path')->nullable()->after('personal_info');
    $table->string('attached_file_original_name')->nullable()->after('attached_file_path');
    $table->unsignedInteger('attached_file_size')->nullable()->after('attached_file_original_name'); // bytes
    $table->string('attached_file_mime_type', 100)->nullable()->after('attached_file_size');
    $table->timestamp('attached_file_uploaded_at')->nullable()->after('attached_file_mime_type');
});
```

### Модель Resume

Додай до `fillable`:
- `attached_file_path`
- `attached_file_original_name`
- `attached_file_size`
- `attached_file_mime_type`
- `attached_file_uploaded_at`

Додай casts:
- `attached_file_uploaded_at` => `datetime`

Додай методи:
```php
public function hasAttachedFile(): bool
{
    return !empty($this->attached_file_path);
}

public function getAttachedFileSizeFormatted(): ?string
{
    if (!$this->attached_file_size) return null;
    
    $bytes = $this->attached_file_size;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
```

**STOP. Покажи результат і дочекайся підтвердження перед Кроком 3.**

## Крок 3 — Сервіс ResumeFileService

Створи `app/Services/ResumeFileService.php`:

```php
class ResumeFileService
{
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    
    public const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    
    public function upload(Resume $resume, UploadedFile $file): Resume
    {
        $this->validateFile($file);
        
        // Видалити попередній файл, якщо є
        if ($resume->hasAttachedFile()) {
            $this->delete($resume);
        }
        
        $path = $file->store("resumes/{$resume->user_id}", 'private');
        
        $resume->update([
            'attached_file_path' => $path,
            'attached_file_original_name' => $file->getClientOriginalName(),
            'attached_file_size' => $file->getSize(),
            'attached_file_mime_type' => $file->getMimeType(),
            'attached_file_uploaded_at' => now(),
        ]);
        
        return $resume->fresh();
    }
    
    public function delete(Resume $resume): Resume
    {
        if ($resume->hasAttachedFile()) {
            Storage::disk('private')->delete($resume->attached_file_path);
        }
        
        $resume->update([
            'attached_file_path' => null,
            'attached_file_original_name' => null,
            'attached_file_size' => null,
            'attached_file_mime_type' => null,
            'attached_file_uploaded_at' => null,
        ]);
        
        return $resume->fresh();
    }
    
    public function download(Resume $resume): StreamedResponse
    {
        if (!$resume->hasAttachedFile()) {
            throw new \RuntimeException('No attached file');
        }
        
        return Storage::disk('private')->download(
            $resume->attached_file_path,
            $resume->attached_file_original_name
        );
    }
    
    protected function validateFile(UploadedFile $file): void
    {
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Дозволені формати: PDF, DOC, DOCX');
        }
        
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Максимальний розмір файлу — 5 МБ');
        }
    }
}
```

**Перевір/створи диск `private` у `config/filesystems.php`:**

```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'serve' => false,
    'throw' => false,
],
```

**STOP.**

## Крок 4 — Роути та контролер для download

Файли зберігаються на `private` диску. Доступ — лише через захищений роут.

Створи контролер `app/Http/Controllers/Seeker/ResumeFileController.php`:

```php
class ResumeFileController extends Controller
{
    public function __construct(protected ResumeFileService $service) {}
    
    public function download(Resume $resume)
    {
        // Кандидат — свій файл
        // Працедавець — лише через куплений cv_access
        $this->authorize('downloadFile', $resume);
        
        return $this->service->download($resume);
    }
}
```

Створи `app/Policies/ResumePolicy.php` метод `downloadFile`:

```php
public function downloadFile(User $user, Resume $resume): bool
{
    // Власник резюме
    if ($user->id === $resume->user_id) {
        return true;
    }
    
    // Працедавець з активним cv_access
    if ($user->role === UserRole::Employer) {
        return $user->hasActiveCvAccess(); // використай існуючу логіку
    }
    
    return false;
}
```

**Зареєструй роут** у `routes/web.php` (всередині middleware `auth`):

```php
Route::get('/resumes/{resume}/file/download', [ResumeFileController::class, 'download'])
    ->name('resumes.file.download');
```

**STOP.**

## Крок 5 — Volt-компонент завантаження файлу

Створи `resources/views/livewire/seeker/resume/file-upload.blade.php` (Volt Class API):

```php
<?php

use App\Models\Resume;
use App\Services\ResumeFileService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;
    
    public Resume $resume;
    public $file = null;
    
    public function uploadFile(ResumeFileService $service): void
    {
        $this->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5 MB
        ]);
        
        try {
            $this->resume = $service->upload($this->resume, $this->file->getRealPath() 
                ? $this->file : throw new \RuntimeException('Файл не завантажено'));
            
            $this->file = null;
            $this->dispatch('file-uploaded');
            session()->flash('success', 'Файл успішно завантажено');
        } catch (\Exception $e) {
            $this->addError('file', $e->getMessage());
        }
    }
    
    public function deleteFile(ResumeFileService $service): void
    {
        $this->resume = $service->delete($this->resume);
        session()->flash('success', 'Файл видалено');
    }
};
?>

<div class="space-y-4">
    <h3 class="text-lg font-semibold">Файл резюме (опційно)</h3>
    <p class="text-sm text-gray-600">
        Прикріпіть PDF, DOC або DOCX. Максимум 5 МБ.
        Працедавці зможуть завантажити цей файл за наявності доступу до бази резюме.
    </p>
    
    @if ($resume->hasAttachedFile())
        <div class="border rounded-lg p-4 bg-gray-50">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium">{{ $resume->attached_file_original_name }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $resume->getAttachedFileSizeFormatted() }} ·
                        {{ $resume->attached_file_uploaded_at->format('d.m.Y H:i') }}
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('resumes.file.download', $resume) }}"
                       class="text-blue-600 hover:underline text-sm">
                        Завантажити
                    </a>
                    <button wire:click="deleteFile"
                            wire:confirm="Видалити прикріплений файл?"
                            class="text-red-600 hover:underline text-sm">
                        Видалити
                    </button>
                </div>
            </div>
        </div>
    @else
        <div>
            <input type="file" wire:model="file" accept=".pdf,.doc,.docx"
                   class="block w-full text-sm border rounded-lg p-2">
            
            @error('file')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
            
            <div wire:loading wire:target="file" class="text-sm text-gray-500 mt-1">
                Завантаження...
            </div>
            
            @if ($file)
                <button wire:click="uploadFile"
                        wire:loading.attr="disabled"
                        class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Прикріпити файл
                </button>
            @endif
        </div>
    @endif
</div>
```

**Підключи компонент** у сторінці редагування резюме (`resources/views/livewire/seeker/resume/edit.blade.php`):

```blade
@livewire('seeker.resume.file-upload', ['resume' => $resume], key('file-upload-' . $resume->id))
```

**STOP.**

## Крок 6 — Інтерфейс працедавця

У компоненті перегляду резюме працедавцем (знайди в `resources/views/livewire/employer/resumes/` — точне ім'я з'ясуй на Кроці 1) додай блок:

```blade
@if ($resume->hasAttachedFile())
    <div class="border rounded-lg p-4 bg-blue-50 my-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="font-medium">📎 Прикріплений файл резюме</div>
                <div class="text-sm text-gray-600">
                    {{ $resume->attached_file_original_name }} 
                    ({{ $resume->getAttachedFileSizeFormatted() }})
                </div>
            </div>
            @can('downloadFile', $resume)
                <a href="{{ route('resumes.file.download', $resume) }}"
                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Завантажити
                </a>
            @else
                <span class="text-sm text-gray-500">Доступ через CV-access</span>
            @endcan
        </div>
    </div>
@endif
```

**STOP.**

## Крок 7 — Тести (PHPUnit 12, #[Test])

Створи `tests/Feature/Resume/ResumeFileAttachmentTest.php`. Обов'язково:

- `actingAs()` ПЕРЕД `Volt::test()` (не chain)
- `#[Test]` attribute (не docblock)
- Enum `UserRole::Candidate`
- `user_id` (не `seeker_id`)

Тести (мінімум 7):

1. `candidate_can_upload_pdf_file_to_resume` — успішне завантаження PDF
2. `candidate_can_upload_docx_file_to_resume` — успішне завантаження DOCX
3. `candidate_cannot_upload_file_larger_than_5mb` — валідація розміру
4. `candidate_cannot_upload_unsupported_file_type` — валідація MIME (наприклад, .jpg)
5. `uploading_new_file_replaces_old_one` — заміна файлу видаляє попередній з диску
6. `candidate_can_delete_attached_file` — видалення файлу
7. `employer_without_cv_access_cannot_download_file` — авторизація працедавця
8. `employer_with_cv_access_can_download_file` — позитивний case для працедавця

Використовуй `Storage::fake('private')` для ізоляції тестів.

**Запусти тести: `php artisan test --filter=ResumeFileAttachmentTest`. Очікую 8/8 PASS.**

**STOP.**

## Крок 8 — Інтеграція з ProfileCompletenessService

**НЕ змінюй основну логіку completeness.** Файл — **бонус**, не критерій повноти. Структуроване резюме залишається основним джерелом completeness.

Але додай **інформаційний бейдж** у відображенні completeness:

Якщо `$resume->hasAttachedFile()` — показати маленький значок "📎 Файл прикріплено" поряд з прогрес-баром.

Точний spot — узгодимо після Кроку 1 (коли побачимо поточну структуру `shared.profile-completeness` Volt).

**STOP.**

## Крок 9 — Фінальна перевірка

1. Усі тести нової фічі: PASS
2. Регресійний прогін усього test suite: zero regressions
3. Linting/static analysis: clean
4. Перевірка вручну: завантаження PDF, DOC, DOCX → перегляд → видалення → завантаження знову

**Покажи фінальний звіт перед merge.**

---

## Важливі обмеження

- **НЕ парсити файл AI на цій фазі.** Це Фаза 2 (після лончу).
- **НЕ робити файл альтернативою структурованому резюме.** Файл — лише вкладення.
- **НЕ змінювати ProfileCompletenessService** в основній логіці. Файл не впливає на completeness.
- **НЕ робити файл публічно доступним.** Тільки через захищений роут з policy.
- Стрипи Volt-only, PHPUnit 12 з `#[Test]`, `actingAs()` перед `Volt::test()`, enum-roles, `user_id` — як завжди.
