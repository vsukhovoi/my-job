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
        Schema::createIfNotExists('addon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('addon_type')->unique();
            $table->unsignedInteger('price');
            $table->timestamps();
        });

        DB::table('addon_settings')->insertOrIgnore([
            ['addon_type' => 'hot',                   'price' => 199, 'created_at' => now(), 'updated_at' => now()],
            ['addon_type' => 'top',                   'price' => 299, 'created_at' => now(), 'updated_at' => now()],
            ['addon_type' => 'anonymous_publication', 'price' => 599, 'created_at' => now(), 'updated_at' => now()],
            ['addon_type' => 'cv_access',             'price' => 990, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_settings');
    }
};
