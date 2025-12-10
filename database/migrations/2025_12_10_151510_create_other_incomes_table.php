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
        Schema::create('other_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('income_source_id')->nullable()->constrained()->nullOnDelete();
            $table->date('income_date');
            $table->string('description');
            $table->integer('amount'); // in cents
            $table->string('currency')->default('EUR');
            $table->string('reference')->nullable(); // transaction ID, payout ID, etc.
            $table->string('original_file_path')->nullable(); // for PDFs
            $table->string('source_filename')->nullable(); // for noting CSV filename
            $table->json('extracted_data')->nullable(); // AI-extracted metadata
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_incomes');
    }
};
