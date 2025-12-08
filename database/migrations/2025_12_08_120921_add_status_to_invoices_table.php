<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status')->default('finalized')->after('generated_at');
            $table->string('original_file_path')->nullable()->after('status');
            $table->text('extracted_data')->nullable()->after('original_file_path');
            $table->text('error_message')->nullable()->after('extracted_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['status', 'original_file_path', 'extracted_data', 'error_message']);
        });
    }
};
