<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_processed_events', function (Blueprint $table) {
            // Фактична сума з webhook провайдера; null — для записів до цієї міграції
            $table->unsignedInteger('amount_kopecks')->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_processed_events', function (Blueprint $table) {
            $table->dropColumn('amount_kopecks');
        });
    }
};
