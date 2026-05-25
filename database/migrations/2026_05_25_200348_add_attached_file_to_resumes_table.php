<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->string('attached_file_path')->nullable()->after('personal_info');
            $table->string('attached_file_original_name')->nullable()->after('attached_file_path');
            $table->unsignedInteger('attached_file_size')->nullable()->after('attached_file_original_name');
            $table->string('attached_file_mime_type', 100)->nullable()->after('attached_file_size');
            $table->timestamp('attached_file_uploaded_at')->nullable()->after('attached_file_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn([
                'attached_file_path',
                'attached_file_original_name',
                'attached_file_size',
                'attached_file_mime_type',
                'attached_file_uploaded_at',
            ]);
        });
    }
};
