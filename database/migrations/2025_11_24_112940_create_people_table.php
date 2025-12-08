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
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('city');
            $table->string('postal_code');
            $table->string('country')->default('Spain');
            $table->string('dni_nie')->comment('Spanish tax ID');
            $table->string('invoice_prefix')->unique()->comment('Prefix for invoices, e.g., SH, WIFE');
            $table->unsignedInteger('next_invoice_number')->default(1)->comment('Auto-incrementing invoice number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
