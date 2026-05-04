<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('tax_type')->nullable()->after('total');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_type');
            $table->bigInteger('tax_amount')->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['tax_type', 'tax_rate', 'tax_amount']);
        });
    }
};
