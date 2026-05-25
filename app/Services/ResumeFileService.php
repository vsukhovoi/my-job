<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resume;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ResumeFileService
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

        if ($resume->hasAttachedFile()) {
            $this->deleteFromDisk($resume);
        }

        $path = $file->store("resumes/{$resume->user_id}", 'private');

        $resume->update([
            'attached_file_path'          => $path,
            'attached_file_original_name' => $file->getClientOriginalName(),
            'attached_file_size'          => $file->getSize(),
            'attached_file_mime_type'     => $file->getMimeType(),
            'attached_file_uploaded_at'   => now(),
        ]);

        return $resume->fresh();
    }

    public function delete(Resume $resume): Resume
    {
        if ($resume->hasAttachedFile()) {
            $this->deleteFromDisk($resume);
        }

        $resume->update([
            'attached_file_path'          => null,
            'attached_file_original_name' => null,
            'attached_file_size'          => null,
            'attached_file_mime_type'     => null,
            'attached_file_uploaded_at'   => null,
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
            $resume->attached_file_original_name,
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

    private function deleteFromDisk(Resume $resume): void
    {
        Storage::disk('private')->delete($resume->attached_file_path);
    }
}
