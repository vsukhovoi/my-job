<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function pendingInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('user_id', auth()->id())
            ->where('status', InvoiceStatus::Pending)
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function paidInvoices(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return Invoice::where('user_id', auth()->id())
            ->where('status', InvoiceStatus::Paid)
            ->orderByDesc('paid_at')
            ->paginate(10);
    }

    #[Computed]
    public function hasAnyInvoices(): bool
    {
        return Invoice::where('user_id', auth()->id())->exists();
    }
}; ?>

<div class="min-h-screen mj-billing-bg">
    <x-employer-tabs />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        {{-- ── Очікують оплати ── --}}
        @if($this->pendingInvoices->isNotEmpty())
        <div>
            <h2 class="text-base font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-yellow-400"></span>
                Очікують оплати
            </h2>
            <div class="space-y-3">
                @foreach($this->pendingInvoices as $invoice)
                <a href="{{ route('employer.billing.invoice.show', $invoice->invoice_number) }}"
                   class="flex items-center justify-between bg-white border border-yellow-200 rounded-2xl px-5 py-4 hover:border-yellow-400 hover:shadow-sm transition-all group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-yellow-50 border border-yellow-200 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm">{{ $invoice->invoice_number }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Виставлено {{ $invoice->created_at->format('d.m.Y') }}
                                @if($invoice->expires_at)
                                    · до {{ $invoice->expires_at->format('d.m.Y') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="font-bold text-gray-900">{{ $invoice->amountFormatted() }}</p>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                Очікує оплати
                            </span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Оплачені ── --}}
        @if($this->paidInvoices->isNotEmpty())
        <div>
            <h2 class="text-base font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                Оплачені рахунки
            </h2>
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="text-left px-5 py-3 font-medium text-gray-600">Рахунок</th>
                            <th class="text-left px-5 py-3 font-medium text-gray-600 hidden sm:table-cell">Дата оплати</th>
                            <th class="text-right px-5 py-3 font-medium text-gray-600">Сума</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($this->paidInvoices as $invoice)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $invoice->invoice_number }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $invoice->created_at->format('d.m.Y') }}</p>
                            </td>
                            <td class="px-5 py-3 text-gray-500 hidden sm:table-cell">
                                {{ $invoice->paid_at?->format('d.m.Y') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                {{ $invoice->amountFormatted() }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('employer.billing.invoice.show', $invoice->invoice_number) }}"
                                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    Переглянути
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($this->paidInvoices->hasPages())
                    <div class="px-5 py-3 border-t border-gray-100">
                        {{ $this->paidInvoices->links() }}
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Порожній стан ── --}}
        @if(!$this->hasAnyInvoices)
        <div class="text-center py-16">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-900 font-semibold mb-1">Рахунків ще немає</p>
            <p class="text-sm text-gray-500 mb-6">Оберіть тариф та оплатіть по IBAN — рахунок з'явиться тут</p>
            <a href="{{ route('employer.billing') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors">
                Обрати тариф
            </a>
        </div>
        @endif

    </div>
</div>
