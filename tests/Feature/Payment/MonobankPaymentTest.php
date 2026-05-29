<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Enums\InvoiceStatus;
use App\Jobs\ProcessMonobankPayment;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MonobankPaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'data' => [
                'statementItem' => [
                    'id'          => 'stmt_abc123',
                    'amount'      => 50000,
                    'description' => 'Оплата MJ-00001',
                    'status'      => 'DONE',
                    'time'        => now()->timestamp,
                ],
            ],
        ];

        $this->postJson(route('mono.webhook'), $payload)
             ->assertStatus(200);

        Queue::assertPushed(ProcessMonobankPayment::class);
    }

    #[Test]
    public function webhook_ignores_non_done_status(): void
    {
        Queue::fake();

        $payload = [
            'data' => [
                'statementItem' => [
                    'id'     => 'stmt_pending',
                    'amount' => 50000,
                    'status' => 'PENDING',
                ],
            ],
        ];

        $this->postJson(route('mono.webhook'), $payload)
             ->assertStatus(200)
             ->assertJson(['status' => 'ignored']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function job_marks_invoice_as_paid(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id'        => $user->id,
            'invoice_number' => 'MJ-00042',
            'amount'         => 50000,
            'status'         => InvoiceStatus::Pending,
        ]);

        $statement = [
            'id'          => 'stmt_mj_042',
            'amount'      => 50000,
            'status'      => 'DONE',
            'description' => 'Оплата послуг MJ-00042',
        ];

        app()->call([new ProcessMonobankPayment($statement), 'handle']);

        $this->assertDatabaseHas('invoices', [
            'id'                   => $invoice->id,
            'status'               => InvoiceStatus::Paid->value,
            'monobank_statement_id' => 'stmt_mj_042',
        ]);
    }

    #[Test]
    public function job_does_not_match_invoice_when_amount_differs(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id'        => $user->id,
            'invoice_number' => 'MJ-00043',
            'amount'         => 50000,
            'status'         => InvoiceStatus::Pending,
        ]);

        $statement = [
            'id'          => 'stmt_wrong_amount',
            'amount'      => 10000,
            'status'      => 'DONE',
            'description' => 'Оплата MJ-00043',
        ];

        app()->call([new ProcessMonobankPayment($statement), 'handle']);

        $this->assertDatabaseHas('invoices', [
            'id'     => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    #[Test]
    public function job_ignores_statement_without_invoice_number(): void
    {
        $user    = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Pending,
        ]);

        $statement = [
            'id'          => 'stmt_no_ref',
            'amount'      => $invoice->amount,
            'status'      => 'DONE',
            'description' => 'Переказ без призначення',
        ];

        app()->call([new ProcessMonobankPayment($statement), 'handle']);

        $this->assertDatabaseHas('invoices', [
            'id'     => $invoice->id,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }
}
