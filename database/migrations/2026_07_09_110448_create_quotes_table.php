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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete()->comment('Invoice created from this quote');
            $table->string('quote_number')->unique()->comment('e.g., SH-Q-00001');
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('customer_name')->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tax_id')->nullable();
            $table->integer('total_amount')->nullable()->comment('Total in cents');
            $table->integer('tax_base_total')->nullable()->comment('Sum of line totals before tax, in cents');
            $table->integer('tax_total')->nullable()->comment('Sum of line tax amounts, in cents');
            $table->decimal('irpf_rate', 5, 2)->nullable();
            $table->integer('irpf_amount')->nullable()->comment('IRPF withholding in cents');
            $table->integer('amount_eur')->nullable()->comment('EUR equivalent in cents');
            $table->string('currency', 3);
            $table->text('legal_notes')->nullable();
            $table->string('pdf_path')->nullable()->comment('Path to generated Spanish PDF');
            $table->string('pdf_path_en')->nullable()->comment('Path to generated English PDF');
            $table->timestamp('generated_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
