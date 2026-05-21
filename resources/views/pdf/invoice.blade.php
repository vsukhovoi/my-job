<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
    .page { padding: 30px 40px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid #2563eb; padding-bottom: 16px; }
    .logo { height: 120px; width: auto; display: block; }
    .invoice-meta { text-align: right; margin-top: -50px; }
    .invoice-meta .number { font-size: 16px; font-weight: 700; }
    .invoice-meta .date { color: #6b7280; margin-top: 4px; font-size: 13px; }
    .section { margin-bottom: 20px; }
    .section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; margin-bottom: 6px; }
    .parties { display: flex; gap: 40px; margin-bottom: 24px; }
    .party { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
    .party-name { font-weight: 700; font-size: 16px; margin-bottom: 2px; color: #000; line-height: 0.9; }
    .party-detail { color: #000; margin-top: 2px; font-size: 16px; line-height: 0.9; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #000; font-size: 14px; }
    table thead tr { background: #f3f4f6; color: #111827; }
    table thead th { padding: 8px 10px; text-align: left; font-weight: 700; border: 1px solid #000; }
    table tbody tr:nth-child(even) { background: #f9fafb; }
    table tbody td { padding: 8px 10px; border: 1px solid #000; }
    .total-row { background: #eff6ff !important; font-weight: 700; }
    .total-row td { border: 1px solid #000; font-size: 14px; }
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
            <img src="{{ public_path('img/logo/mj-logo.png') }}" alt="My Job" style="height:120px; width:auto; display:block;">
        </div>
        <div class="invoice-meta">
            <div class="number">Рахунок-фактура № {{ $invoice->invoice_number }}</div>
            <div class="date">від {{ $invoice->created_at->locale('uk')->isoFormat('D MMMM YYYY') }} р.</div>
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
                <th style="width:1%; white-space:nowrap;">№</th>
                <th style="width:auto;">Найменування послуги</th>
                <th style="width:1%; white-space:nowrap; text-align:center;">К-сть</th>
                <th style="width:1%; white-space:nowrap; text-align:center;">Од.</th>
                <th style="width:1%; white-space:nowrap; text-align:right;">Ціна (грн)</th>
                <th style="width:1%; white-space:nowrap; text-align:right;">Сума (грн)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $invoice->payment_purpose }}</td>
                <td style="text-align:center;">1</td>
                <td style="text-align:center;">посл.</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
            </tr>
            <tr class="total-row">
                <td colspan="5" style="text-align:right; padding-right:20px;">Разом до сплати:</td>
                <td style="text-align:right;">{{ number_format($invoice->amount / 100, 2, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div style="font-size:13px; margin-bottom:16px; line-height:1.6;">
        <span style="color:#6b7280;">Всього на суму:</span><br>
        <strong>{{ $invoice->amountInWords() }}</strong><br>
        <span style="color:#6b7280;">Без ПДВ</span>
    </div>

@if($invoice->expires_at)
    <div style="color:#6b7280; margin-bottom:12px; font-size:10px;">
        Рахунок дійсний до {{ $invoice->expires_at->format('d.m.Y') }}
    </div>
    <div style="color:#374151; margin-bottom:8px; font-size:10px; line-height:1.5;">
        На підставі статей 634 та 642 Цивільного кодексу України, оплата цього рахунку є повним і безумовним прийняттям (акцептом) умов Договору публічної оферти про надання послуг з оброблення даних та розміщення інформації, розміщеного за посиланням: myjob.co.ua/offer, та чинних Тарифів Виконавця, розміщених за посиланням: myjob.co.ua/pricing. Надання послуг за цим рахунком не потребує підписання двосторонніх паперових Актів приймання-передачі наданих послуг.
    </div>
    <div style="width:100%; height:1px; background-color:#000; font-size:0; line-height:0; margin-bottom:8px;"></div>
    <div style="color:#374151; font-size:10px; line-height:1.5; margin-bottom:20px;">
        <span style="font-weight:700;">Увага! У призначені платежу обов'язково необхідно вказувати (так як зазначено у рахунку): "Розміщення інформації на веб-сайті My Job, тариф (Ваш тариф), рахунок № ХХ-ХХХХХ від дд.мм.рррр. без ПДВ" (де ХХ-ХХХХХ — номер Вашого рахунку).
        В іншому випадку платіж може бути не зараховано.</span>
    </div>
    @endif

    <div class="footer">
        Цей документ сформовано автоматично на платформі My Job (myjob.co.ua).
        Підпис та печатка не потрібні. {{ $invoice->recipient_name }} · ЄДРПОУ {{ $invoice->edrpou }}
    </div>
</div>
</body>
</html>
