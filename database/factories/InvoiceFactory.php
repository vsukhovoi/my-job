<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $number = 'INV-' . now()->year . '-' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);

        return [
            'user_id'         => User::factory(),
            'invoice_number'  => $number,
            'amount'          => fake()->numberBetween(10000, 500000),
            'status'          => InvoiceStatus::Pending,
            'recipient_name'  => 'ТОВ «ФЛАГМАН СВ»',
            'iban'            => 'UA000000000000000000000000000',
            'edrpou'          => '37490783',
            'bank_name'       => 'АТ «УНІВЕРСАЛ БАНК»',
            'payment_purpose' => fn (array $attrs) => "Оплата послуг My Job, рахунок {$attrs['invoice_number']}",
            'expires_at'      => now()->addDays(30),
        ];
    }
}
