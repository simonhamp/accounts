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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number')->nullable();
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->integer('total_amount')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending');
            $table->string('original_file_path')->nullable();
            $table->json('extracted_data')->nullable();
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'bill_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
