<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_via_email')->default(true)->after('notification_channel');
            $table->boolean('notify_via_telegram')->default(false)->after('notify_via_email');
        });

        DB::table('users')->where('notification_channel', 'telegram')->update([
            'notify_via_email'    => false,
            'notify_via_telegram' => true,
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_channel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('notification_channel')->default('email')->after('telegram_id');
        });

        DB::table('users')->where('notify_via_telegram', true)->update(['notification_channel' => 'telegram']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_via_email', 'notify_via_telegram']);
        });
    }
};
