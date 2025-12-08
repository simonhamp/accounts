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
            $table->date('invoice_date')->nullable()->change();
            $table->unsignedTinyInteger('period_month')->nullable()->change();
            $table->unsignedSmallInteger('period_year')->nullable()->change();
            $table->string('customer_name')->nullable()->change();
            $table->integer('total_amount')->nullable()->change();
            $table->string('currency', 3)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('invoice_date')->nullable(false)->change();
            $table->unsignedTinyInteger('period_month')->nullable(false)->change();
            $table->unsignedSmallInteger('period_year')->nullable(false)->change();
            $table->string('customer_name')->nullable(false)->change();
            $table->integer('total_amount')->nullable(false)->change();
            $table->string('currency', 3)->nullable(false)->change();
        });
    }
};
