<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seeker;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use App\Services\ResumeFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeFileController extends Controller
{
    public function __construct(
        protected ResumeFileService $service,
    ) {}

    public function download(Resume $resume): StreamedResponse
    {
        $this->authorize('downloadFile', $resume);

        return $this->service->download($resume);
    }
}
