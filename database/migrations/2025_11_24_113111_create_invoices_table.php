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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique()->comment('e.g., SH-00001');
            $table->date('invoice_date');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('customer_name');
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->integer('total_amount')->comment('Total in cents');
            $table->string('currency', 3);
            $table->string('pdf_path')->nullable()->comment('Path to generated PDF');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'period_year', 'period_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
