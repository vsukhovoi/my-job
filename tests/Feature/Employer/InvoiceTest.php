<?php

declare(strict_types=1);

namespace Tests\Feature\Employer;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceMatcherService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_is_created_with_correct_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::Employer]);

        $invoice = app(InvoiceService::class)->create($user, 50000);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'amount'  => 50000,
            'status'  => InvoiceStatus::Pending->value,
            'edrpou'  => config('invoice.edrpou'),
        ]);
        $this->assertStringStartsWith('MJ-', $invoice->invoice_number);
        $this->assertStringContainsString($invoice->invoice_number, $invoice->payment_purpose);
    }

    #[Test]
    public function invoice_numbers_are_unique_and_sequential(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $service = app(InvoiceService::class);

        $inv1 = $service->create($user, 10000);
        $inv2 = $service->create($user, 20000);

        $this->assertNotEquals($inv1->invoice_number, $inv2->invoice_number);
    }

    #[Test]
    public function mark_as_paid_fires_event_and_sets_status(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        app(InvoiceService::class)->markAsPaid($invoice, 'test-statement-id');

        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
        Event::assertDispatched(InvoicePaid::class);
    }

    #[Test]
    public function matcher_finds_invoice_by_payment_purpose(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        $statements = [
            [
                'id'          => 'mono-stmt-001',
                'description' => "Оплата послуг My Job, рахунок {$invoice->invoice_number}",
                'amount'      => 50000,
            ],
        ];

        app(InvoiceMatcherService::class)->match($statements);

        $this->assertEquals(InvoiceStatus::Paid, $invoice->fresh()->status);
        Event::assertDispatched(InvoicePaid::class);
    }

    #[Test]
    public function matcher_skips_wrong_amount(): void
    {
        Event::fake([InvoicePaid::class]);

        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = app(InvoiceService::class)->create($user, 50000);

        $statements = [
            [
                'id'          => 'mono-stmt-002',
                'description' => "Рахунок {$invoice->invoice_number}",
                'amount'      => 10000,
            ],
        ];

        app(InvoiceMatcherService::class)->match($statements);

        $this->assertEquals(InvoiceStatus::Pending, $invoice->fresh()->status);
        Event::assertNotDispatched(InvoicePaid::class);
    }

    #[Test]
    public function invoice_page_renders_for_owner(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    #[Test]
    public function invoice_page_forbidden_for_another_user(): void
    {
        $owner   = User::factory()->create(['role' => UserRole::Employer]);
        $other   = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertNotFound();
    }

    #[Test]
    public function employer_can_cancel_pending_invoice(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Pending,
        ]);

        app(InvoiceService::class)->cancel($invoice, $user);

        $this->assertEquals(InvoiceStatus::Cancelled, $invoice->fresh()->status);
    }

    #[Test]
    public function employer_cannot_cancel_paid_invoice(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Paid,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(InvoiceService::class)->cancel($invoice, $user);
    }

    #[Test]
    public function employer_cannot_cancel_another_users_invoice(): void
    {
        $owner   = User::factory()->create(['role' => UserRole::Employer]);
        $other   = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'status'  => InvoiceStatus::Pending,
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(InvoiceService::class)->cancel($invoice, $other);
    }

    #[Test]
    public function cancel_button_visible_for_pending_invoice_in_volt(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Pending,
        ]);

        $this->actingAs($user);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertSee('Скасувати рахунок');
    }

    #[Test]
    public function cancel_button_hidden_for_paid_invoice_in_volt(): void
    {
        $user    = User::factory()->create(['role' => UserRole::Employer]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status'  => InvoiceStatus::Paid,
        ]);

        $this->actingAs($user);
        Volt::test('billing.invoice', ['invoiceNumber' => $invoice->invoice_number])
            ->assertDontSee('Скасувати рахунок');
    }
}
