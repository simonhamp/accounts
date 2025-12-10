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
        Schema::table('other_incomes', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('currency');
            $table->unsignedBigInteger('amount_paid')->nullable()->after('status');
            $table->foreignId('bank_account_id')->nullable()->after('amount_paid')->constrained()->nullOnDelete();
            $table->date('paid_at')->nullable()->after('bank_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('other_incomes', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['status', 'amount_paid', 'bank_account_id', 'paid_at']);
        });
    }
};
