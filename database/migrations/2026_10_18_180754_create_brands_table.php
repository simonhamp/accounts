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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            $table->boolean('email_invoices')->default(false)
                ->comment('Email invoices to customers, automatically for new ones and by hand');
            $table->string('invoice_sender_name')->nullable();
            $table->string('invoice_from_address')->nullable();
            $table->string('invoice_reply_to')->nullable();
            $table->string('invoice_bcc')->nullable();
            $table->string('invoice_language', 2)->default('en')
                ->comment('Language of the invoice email and its PDF: en or es');

            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_scheme')->nullable()
                ->comment('smtps or smtp; empty means smtps on port 465 and smtp otherwise');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable()->comment('Encrypted');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
