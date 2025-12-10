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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('notes');
            $table->string('billing_frequency')->default('none')->after('is_active');
            $table->unsignedTinyInteger('billing_month')->nullable()->after('billing_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'billing_frequency', 'billing_month']);
        });
    }
};
