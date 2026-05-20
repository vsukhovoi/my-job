<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');

            $table->string('payer_name')->nullable();
            $table->string('payer_edrpou')->nullable();

            $table->string('recipient_name');
            $table->string('iban');
            $table->string('edrpou');
            $table->string('bank_name');
            $table->string('mfo')->nullable();

            $table->string('payment_purpose');

            $table->string('monobank_statement_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
