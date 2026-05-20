<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InvoiceMatcherService;
use App\Services\MonobankService;
use Illuminate\Console\Command;

class CheckInvoicePayments extends Command
{
    protected $signature   = 'invoices:check-payments';
    protected $description = 'Перевірити нові надходження по IBAN та підтвердити рахунки';

    public function handle(MonobankService $monobank, InvoiceMatcherService $matcher): int
    {
        $to   = now()->timestamp;
        $from = now()->subMinutes(10)->timestamp;

        $statements = $monobank->getStatements($from, $to);
        $matcher->match($statements);

        $this->info('Перевірку завершено. Транзакцій отримано: ' . count($statements));

        return self::SUCCESS;
    }
}
