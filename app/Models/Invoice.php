<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'order_id', 'invoice_number', 'amount', 'status',
        'payer_name', 'payer_edrpou',
        'recipient_name', 'iban', 'edrpou', 'bank_name', 'mfo',
        'payment_purpose', 'monobank_statement_id', 'paid_at', 'expires_at',
    ];

    protected $casts = [
        'status'     => InvoiceStatus::class,
        'paid_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isExpired(): bool
    {
        return $this->status === InvoiceStatus::Expired
            || ($this->expires_at && $this->expires_at->isPast() && !$this->isPaid());
    }

    public function amountFormatted(): string
    {
        return number_format($this->amount / 100, 2, '.', ' ') . ' грн';
    }
}
