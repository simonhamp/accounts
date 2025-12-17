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
            $table->bigInteger('amount_eur')->nullable()->after('total_amount');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->bigInteger('amount_eur')->nullable()->after('total_amount');
        });

        Schema::table('other_incomes', function (Blueprint $table) {
            $table->bigInteger('amount_eur')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_eur');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('amount_eur');
        });

        Schema::table('other_incomes', function (Blueprint $table) {
            $table->dropColumn('amount_eur');
        });
    }
};
