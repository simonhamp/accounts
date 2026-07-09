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
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->integer('unit_price')->comment('Price in cents');
            $table->integer('total')->comment('Total in cents');
            $table->string('tax_type')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('tax_amount')->default(0)->comment('Tax in cents');
            $table->timestamps();

            $table->index('quote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
