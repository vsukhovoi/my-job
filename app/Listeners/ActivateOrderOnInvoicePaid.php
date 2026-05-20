<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\InvoicePaid;
use Illuminate\Support\Facades\Log;

class ActivateOrderOnInvoicePaid
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->order_id) {
            Log::info('InvoicePaid: потрібно активувати order', [
                'invoice_id' => $invoice->id,
                'order_id'   => $invoice->order_id,
            ]);
        }
    }
}
