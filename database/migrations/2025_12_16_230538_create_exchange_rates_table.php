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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('from_currency', 3);
            $table->string('to_currency', 3)->default('EUR');
            $table->decimal('rate', 10, 6);
            $table->timestamps();

            $table->unique(['date', 'from_currency', 'to_currency']);
            $table->index(['from_currency', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
