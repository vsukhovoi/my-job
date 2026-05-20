<?php

use App\Models\Invoice;
use App\Services\InvoiceService;
use Livewire\Volt\Component;

new class extends Component {

    public Invoice $invoice;

    public function mount(string $invoiceNumber): void
    {
        $this->invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->where('user_id', auth()->id())
            ->first() ?? abort(404);
    }

    public function downloadPdf(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pdf = app(InvoiceService::class)->generatePdf($this->invoice);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "{$this->invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}; ?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">
            Рахунок {{ $invoice->invoice_number }}
        </h1>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
            {{ $invoice->status->color() === 'green' ? 'bg-green-100 text-green-800' : '' }}
            {{ $invoice->status->color() === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
            {{ $invoice->status->color() === 'gray' ? 'bg-gray-100 text-gray-800' : '' }}
            {{ $invoice->status->color() === 'red' ? 'bg-red-100 text-red-800' : '' }}
        ">
            {{ $invoice->status->label() }}
        </span>
    </div>

    @if($invoice->isPaid())
        <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
            <svg class="w-6 h-6 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <div>
                <p class="font-semibold text-green-800">Оплату підтверджено</p>
                <p class="text-sm text-green-600">{{ $invoice->paid_at?->format('d.m.Y о H:i') }}</p>
            </div>
        </div>
    @else
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
            <div class="text-sm text-blue-600 mb-1">До сплати</div>
            <div class="text-3xl font-bold text-blue-900">{{ $invoice->amountFormatted() }}</div>
            @if($invoice->expires_at)
                <div class="text-sm text-blue-500 mt-1">
                    Дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
                </div>
            @endif
        </div>

        <div class="mb-6 bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
            <div class="p-4">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                    Реквізити для оплати
                </h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Отримувач</span>
                        <span class="font-medium text-right">{{ $invoice->recipient_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">ЄДРПОУ</span>
                        <span class="font-medium">{{ $invoice->edrpou }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Банк</span>
                        <span class="font-medium text-right">{{ $invoice->bank_name }}</span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-gray-500">IBAN</span>
                        <span class="font-mono font-medium text-xs break-all">{{ $invoice->iban }}</span>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div class="text-sm text-gray-500 mb-1">Призначення платежу</div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="font-semibold text-yellow-900 text-sm">{{ $invoice->payment_purpose }}</p>
                    <p class="text-xs text-yellow-600 mt-1">
                        Вкажіть точно це призначення при переказі — інакше оплату не буде підтверджено автоматично
                    </p>
                </div>
            </div>
        </div>
    @endif

    <div class="flex gap-3">
        <button
            wire:click="downloadPdf"
            class="flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white rounded-xl text-sm font-medium hover:bg-gray-700 transition-colors"
        >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Завантажити PDF
        </button>
    </div>
</div>
