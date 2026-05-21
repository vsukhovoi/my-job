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

    public function amountInWords(): string
    {
        $total    = (int) $this->amount;
        $hryvnias = intdiv($total, 100);
        $kopecks  = $total % 100;

        $hryvniaWord = self::pluralUk($hryvnias, 'гривня', 'гривні', 'гривень');
        $kopeckWord  = self::pluralUk($kopecks, 'копійка', 'копійки', 'копійок');

        $words = self::numberToWordsUk($hryvnias, true);
        $words = mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);

        return $words
            . ' ' . $hryvniaWord . ', '
            . sprintf('%02d', $kopecks) . ' ' . $kopeckWord . '.';
    }

    private static function pluralUk(int $n, string $one, string $few, string $many): string
    {
        $mod10  = $n % 10;
        $mod100 = $n % 100;

        if ($mod100 >= 11 && $mod100 <= 19) {
            return $many;
        }
        if ($mod10 === 1) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }
        return $many;
    }

    private static function numberToWordsUk(int $n, bool $feminine = false): string
    {
        if ($n === 0) {
            return 'нуль';
        }

        $hundreds = ['', 'сто', 'двісті', 'триста', 'чотириста', 'п\'ятсот', 'шістсот', 'сімсот', 'вісімсот', 'дев\'ятсот'];
        $tens     = ['', 'десять', 'двадцять', 'тридцять', 'сорок', 'п\'ятдесят', 'шістдесят', 'сімдесят', 'вісімдесят', 'дев\'яносто'];
        $teens    = ['десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять', 'п\'ятнадцять', 'шістнадцять', 'сімнадцять', 'вісімнадцять', 'дев\'ятнадцять'];
        $unitsM   = ['', 'один', 'два', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять'];
        $unitsF   = ['', 'одна', 'дві', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять'];

        $parts = [];

        if ($n >= 1_000_000) {
            $millions = intdiv($n, 1_000_000);
            $parts[]  = self::numberToWordsUk($millions, false) . ' ' . self::pluralUk($millions, 'мільйон', 'мільйони', 'мільйонів');
            $n        %= 1_000_000;
        }

        if ($n >= 1_000) {
            $thousands = intdiv($n, 1_000);
            $parts[]   = self::numberToWordsUk($thousands, true) . ' ' . self::pluralUk($thousands, 'тисяча', 'тисячі', 'тисяч');
            $n         %= 1_000;
        }

        if ($n >= 100) {
            $parts[] = $hundreds[intdiv($n, 100)];
            $n       %= 100;
        }

        if ($n >= 10 && $n <= 19) {
            $parts[] = $teens[$n - 10];
            $n       = 0;
        } elseif ($n >= 20) {
            $parts[] = $tens[intdiv($n, 10)];
            $n       %= 10;
        }

        if ($n > 0) {
            $parts[] = $feminine ? $unitsF[$n] : $unitsM[$n];
        }

        return implode(' ', array_filter($parts));
    }
}
