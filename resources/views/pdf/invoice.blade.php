<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    .page { padding: 30px 40px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
    .logo { height: 60px; width: auto; display: block; }
    .invoice-meta { text-align: right; }
    .invoice-meta .number { font-size: 16px; font-weight: 700; }
    .invoice-meta .date { color: #6b7280; margin-top: 4px; }
    .section { margin-bottom: 20px; }
    .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px; }
    .parties { display: flex; gap: 40px; margin-bottom: 24px; }
    .party { flex: 1; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
    .party-name { font-weight: 700; font-size: 12px; margin-bottom: 4px; }
    .party-detail { color: #374151; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table thead tr { background: #2563eb; color: #fff; }
    table thead th { padding: 8px 10px; text-align: left; font-size: 10px; font-weight: 700; }
    table tbody tr:nth-child(even) { background: #f9fafb; }
    table tbody td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    .total-row { background: #eff6ff !important; font-weight: 700; }
    .total-row td { border-top: 2px solid #2563eb; font-size: 13px; }
    .payment-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 16px; margin-bottom: 20px; }
    .payment-box-title { font-weight: 700; color: #166534; margin-bottom: 10px; }
    .payment-row { display: flex; margin-bottom: 5px; }
    .payment-label { color: #6b7280; width: 160px; flex-shrink: 0; }
    .payment-value { font-weight: 700; overflow-wrap: break-word; }
    .purpose-box { background: #fefce8; border: 1px solid #fde047; border-radius: 6px; padding: 12px; margin-bottom: 20px; }
    .purpose-text { font-weight: 700; font-size: 12px; }
    .footer { border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 9px; text-align: center; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-paid    { background: #dcfce7; color: #166534; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <img src="{{ public_path('img/logo/mj-logo.png') }}" alt="My Job" style="height:60px; width:auto; display:block;">
            <div style="color:#6b7280; margin-top:4px; font-size:11px;">myjob.co.ua</div>
        </div>
        <div class="invoice-meta">
            <div class="number">Рахунок-фактура № {{ $invoice->invoice_number }}</div>
            <div class="date">від {{ $invoice->created_at->locale('uk')->isoFormat('D MMMM YYYY') }} р.</div>
            <div style="margin-top:6px;">
                <span class="status-badge {{ $invoice->isPaid() ? 'status-paid' : 'status-pending' }}">
                    {{ $invoice->status->label() }}
                </span>
            </div>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <div class="section-title">Постачальник</div>
            <div class="party-name">{{ $invoice->recipient_name }}</div>
            <div class="party-detail">ЄДРПОУ: {{ $invoice->edrpou }}</div>
            <div class="party-detail">{{ $invoice->bank_name }}</div>
            @if($invoice->mfo)
            <div class="party-detail">МФО: {{ $invoice->mfo }}</div>
            @endif
            <div class="party-detail" style="word-break:break-all;">IBAN: {{ $invoice->iban }}</div>
        </div>
        <div class="party">
            <div class="section-title">Покупець</div>
            <div class="party-name">{{ $invoice->payer_name ?? '—' }}</div>
            @if($invoice->payer_edrpou)
            <div class="party-detail">ЄДРПОУ / ІПН: {{ $invoice->payer_edrpou }}</div>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>№</th>
                <th>Найменування послуги</th>
                <th style="text-align:right;">Сума (грн)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Послуги з розміщення інформації на веб-сайті My Job</td>
                <td style="text-align:right;">{{ $invoice->amountFormatted() }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2" style="text-align:right; padding-right:20px;">Разом до сплати:</td>
                <td style="text-align:right;">{{ $invoice->amountFormatted() }}</td>
            </tr>
        </tbody>
    </table>

    <div class="payment-box">
        <div class="payment-box-title">Реквізити для оплати</div>
        <div class="payment-row">
            <div class="payment-label">Отримувач:</div>
            <div class="payment-value">{{ $invoice->recipient_name }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">ЄДРПОУ:</div>
            <div class="payment-value">{{ $invoice->edrpou }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">IBAN:</div>
            <div class="payment-value">{{ $invoice->iban }}</div>
        </div>
        <div class="payment-row">
            <div class="payment-label">Банк:</div>
            <div class="payment-value">{{ $invoice->bank_name }}</div>
        </div>
        @if($invoice->mfo)
        <div class="payment-row">
            <div class="payment-label">МФО:</div>
            <div class="payment-value">{{ $invoice->mfo }}</div>
        </div>
        @endif
    </div>

    <div class="purpose-box">
        <div class="section-title">Призначення платежу (вказати точно)</div>
        <div class="purpose-text">{{ $invoice->payment_purpose }}</div>
    </div>

    @if($invoice->expires_at)
    <div style="color:#6b7280; margin-bottom:20px; font-size:10px;">
        Рахунок дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
    </div>
    @endif

    <div class="footer">
        Цей документ сформовано автоматично на платформі My Job (myjob.co.ua).
        Підпис та печатка не потрібні. {{ $invoice->recipient_name }} · ЄДРПОУ {{ $invoice->edrpou }}
    </div>
</div>
</body>
</html>
