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
        Schema::table('stripe_accounts', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('person_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('person_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('stripe_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
