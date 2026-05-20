<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(User $user, int $amountKopecks, ?int $orderId = null, array $payerData = []): Invoice
    {
        return DB::transaction(function () use ($user, $amountKopecks, $orderId, $payerData) {
            $number = $this->generateNumber();

            return Invoice::create([
                'user_id'         => $user->id,
                'order_id'        => $orderId,
                'invoice_number'  => $number,
                'amount'          => $amountKopecks,
                'status'          => InvoiceStatus::Pending,
                'payer_name'      => $payerData['payer_name'] ?? $user->company?->name,
                'payer_edrpou'    => $payerData['payer_edrpou'] ?? null,
                'recipient_name'  => config('invoice.recipient_name'),
                'iban'            => config('invoice.iban'),
                'edrpou'          => config('invoice.edrpou'),
                'bank_name'       => config('invoice.bank_name'),
                'mfo'             => config('invoice.mfo'),
                'payment_purpose' => "Оплата послуг My Job, рахунок {$number}",
                'expires_at'      => now()->addDays(30),
            ]);
        });
    }

    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4', 'portrait');
    }

    public function markAsPaid(Invoice $invoice, ?string $statementId = null): void
    {
        $invoice->update([
            'status'                => InvoiceStatus::Paid,
            'paid_at'               => now(),
            'monobank_statement_id' => $statementId,
        ]);

        event(new InvoicePaid($invoice));
    }

    private function generateNumber(): string
    {
        $year   = now()->year;
        $lastId = Invoice::whereYear('created_at', $year)->max('id') ?? 0;
        $seq    = str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);

        return "INV-{$year}-{$seq}";
    }
}
