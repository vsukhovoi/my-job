<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Enums\UserRole;
use App\Models\Resume;
use App\Models\User;
use App\Services\ResumeFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeFileAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function candidate(): User
    {
        return User::factory()->create(['role' => UserRole::Candidate]);
    }

    private function resumeFor(User $user): Resume
    {
        return Resume::factory()->create(['user_id' => $user->id]);
    }

    #[Test]
    public function candidate_can_upload_pdf_file_to_resume(): void
    {
        $user   = $this->candidate();
        $resume = $this->resumeFor($user);
        $file   = UploadedFile::fake()->create('cv.pdf', 500, 'application/pdf');

        $service = app(ResumeFileService::class);
        $result  = $service->upload($resume, $file);

        $this->assertTrue($result->hasAttachedFile());
        $this->assertSame('cv.pdf', $result->attached_file_original_name);
        $this->assertSame('application/pdf', $result->attached_file_mime_type);
        Storage::disk('private')->assertExists($result->attached_file_path);
    }

    #[Test]
    public function candidate_can_upload_docx_file_to_resume(): void
    {
        $user   = $this->candidate();
        $resume = $this->resumeFor($user);
        $mime   = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $file   = UploadedFile::fake()->create('cv.docx', 300, $mime);

        $service = app(ResumeFileService::class);
        $result  = $service->upload($resume, $file);

        $this->assertTrue($result->hasAttachedFile());
        $this->assertSame('cv.docx', $result->attached_file_original_name);
        Storage::disk('private')->assertExists($result->attached_file_path);
    }

    #[Test]
    public function candidate_cannot_upload_file_larger_than_5mb(): void
    {
        $user   = $this->candidate();
        $resume = $this->resumeFor($user);
        $file   = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'); // 6 MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Максимальний розмір файлу — 5 МБ');

        app(ResumeFileService::class)->upload($resume, $file);
    }

    #[Test]
    public function candidate_cannot_upload_unsupported_file_type(): void
    {
        $user   = $this->candidate();
        $resume = $this->resumeFor($user);
        $file   = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Дозволені формати: PDF, DOC, DOCX');

        app(ResumeFileService::class)->upload($resume, $file);
    }

    #[Test]
    public function uploading_new_file_replaces_old_one(): void
    {
        $user    = $this->candidate();
        $resume  = $this->resumeFor($user);
        $service = app(ResumeFileService::class);

        $first  = UploadedFile::fake()->create('first.pdf', 100, 'application/pdf');
        $resume = $service->upload($resume, $first);
        $oldPath = $resume->attached_file_path;

        $second = UploadedFile::fake()->create('second.pdf', 200, 'application/pdf');
        $resume = $service->upload($resume, $second);

        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($resume->attached_file_path);
        $this->assertSame('second.pdf', $resume->attached_file_original_name);
    }

    #[Test]
    public function candidate_can_delete_attached_file(): void
    {
        $user    = $this->candidate();
        $resume  = $this->resumeFor($user);
        $service = app(ResumeFileService::class);

        $file   = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
        $resume = $service->upload($resume, $file);
        $path   = $resume->attached_file_path;

        $resume = $service->delete($resume);

        $this->assertFalse($resume->hasAttachedFile());
        Storage::disk('private')->assertMissing($path);
    }

    #[Test]
    public function employer_without_cv_access_cannot_download_file(): void
    {
        $candidate = $this->candidate();
        $resume    = $this->resumeFor($candidate);

        $file    = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
        $service = app(ResumeFileService::class);
        $resume  = $service->upload($resume, $file);

        $employer = User::factory()->create(['role' => UserRole::Employer]);

        $this->actingAs($employer)
            ->get(route('resumes.file.download', $resume))
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_download_their_attached_file(): void
    {
        $user    = $this->candidate();
        $resume  = $this->resumeFor($user);
        $service = app(ResumeFileService::class);

        $file   = UploadedFile::fake()->create('my-cv.pdf', 100, 'application/pdf');
        $resume = $service->upload($resume, $file);

        $this->actingAs($user)
            ->get(route('resumes.file.download', $resume))
            ->assertOk()
            ->assertDownload('my-cv.pdf');
    }
}
