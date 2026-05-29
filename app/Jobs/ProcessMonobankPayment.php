<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\InvoiceMatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMonobankPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected array $statement) {}

    public function handle(InvoiceMatcherService $matcher): void
    {
        $matcher->match([$this->statement]);
    }
}
