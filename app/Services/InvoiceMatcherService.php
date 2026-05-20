<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class InvoiceMatcherService
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    /**
     * Матчинг транзакцій з pending-рахунками.
     * Шукає номер MJ-XXXXX у полі description транзакції.
     *
     * @param array<int, array{id: string, description: string, amount: int}> $statements
     */
    public function match(array $statements): void
    {
        if (empty($statements)) {
            return;
        }

        $pendingInvoices = Invoice::where('status', InvoiceStatus::Pending)
            ->whereNull('monobank_statement_id')
            ->get()
            ->keyBy('invoice_number');

        foreach ($statements as $statement) {
            $description = $statement['description'] ?? '';
            $statementId = $statement['id'] ?? null;

            if (!preg_match('/(MJ-\d{5})/', $description, $matches)) {
                continue;
            }

            $invoiceNumber = $matches[1];

            if (!isset($pendingInvoices[$invoiceNumber])) {
                continue;
            }

            /** @var Invoice $invoice */
            $invoice     = $pendingInvoices[$invoiceNumber];
            $paidAmount  = abs($statement['amount'] ?? 0);

            if ($paidAmount !== $invoice->amount) {
                Log::warning('InvoiceMatcher: сума не збігається', [
                    'invoice'      => $invoiceNumber,
                    'expected'     => $invoice->amount,
                    'got'          => $paidAmount,
                    'statement_id' => $statementId,
                ]);
                continue;
            }

            $this->invoiceService->markAsPaid($invoice, $statementId);

            Log::info('InvoiceMatcher: рахунок підтверджено автоматично', [
                'invoice_number' => $invoiceNumber,
                'statement_id'   => $statementId,
            ]);
        }
    }
}
