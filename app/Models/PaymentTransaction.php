<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AddonType;
use App\Payments\CheckoutService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Readonly-модель для таблиці payment_processed_events.
 *
 * @property string           $event_id
 * @property string           $gateway
 * @property string           $order_id
 * @property int|null         $amount_kopecks
 * @property \Carbon\Carbon   $processed_at
 * @property-read int|null    $vacancy_id
 * @property-read int|null    $days
 * @property-read float|null  $amount_uah
 * @property-read string      $gateway_label
 * @property-read string      $purpose
 */
class PaymentTransaction extends Model
{
    use HasFactory;
    protected $table      = 'payment_processed_events';
    protected $primaryKey = 'event_id';
    public $incrementing  = false;
    public $keyType       = 'string';
    public $timestamps    = false;
    protected $guarded    = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn () => false);
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /** @return array{0: int|null, 1: int|null} */
    protected function vacancyId(): Attribute
    {
        return Attribute::get(function (): ?int {
            [$vacancyId] = CheckoutService::parseOrderId($this->order_id);
            return $vacancyId;
        });
    }

    protected function days(): Attribute
    {
        return Attribute::get(function (): ?int {
            [, $days] = CheckoutService::parseOrderId($this->order_id);
            return $days;
        });
    }

    protected function amountUah(): Attribute
    {
        return Attribute::get(function (): ?float {
            if ($this->amount_kopecks !== null) {
                return $this->amount_kopecks / 100;
            }
            // Записи до появи amount_kopecks: сума лише для vac_ за поточним прайсом
            if (! $this->days) {
                return null;
            }
            $kopecks = config("payments.prices.{$this->days}");
            return $kopecks ? $kopecks / 100 : null;
        });
    }

    /** Людський опис того, за що оплачено, за форматом order_id. */
    protected function purpose(): Attribute
    {
        return Attribute::get(function (): string {
            $orderId = $this->order_id;

            if ($this->days) {
                return "Вакансія #{$this->vacancy_id}, {$this->days} дн.";
            }

            [, $planId] = CheckoutService::parseSubscriptionOrderId($orderId);
            if ($planId) {
                $plan = SubscriptionPlan::find($planId);
                return $plan ? "Тариф «{$plan->name}», 1 міс." : "Тариф #{$planId}, 1 міс.";
            }

            if ($vacancyId = CheckoutService::parseAnonymousOrderId($orderId)) {
                return "Анонімна публікація, вакансія #{$vacancyId}";
            }

            if (preg_match('/^addon_([a-z_]+)_\d+_\d+$/', $orderId, $m)) {
                return AddonType::tryFrom($m[1])?->label() ?? 'Додаткова послуга';
            }

            return '—';
        });
    }

    protected function gatewayLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->gateway) {
            'mono'      => 'MonoPay',
            'wayforpay' => 'WayForPay',
            'liqpay'    => 'LiqPay',
            'stripe'    => 'Stripe',
            default     => ucfirst($this->gateway),
        });
    }

    public function getVacancyAttribute(): ?Vacancy
    {
        if (! $this->vacancy_id) {
            return null;
        }
        return Vacancy::find($this->vacancy_id);
    }
}
