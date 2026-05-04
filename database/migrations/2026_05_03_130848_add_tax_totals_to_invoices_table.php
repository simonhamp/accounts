<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('tax_base_total')->default(0)->after('total_amount');
            $table->bigInteger('tax_total')->default(0)->after('tax_base_total');
            $table->decimal('irpf_rate', 5, 2)->nullable()->after('tax_total');
            $table->bigInteger('irpf_amount')->default(0)->after('irpf_rate');
            $table->text('legal_notes')->nullable()->after('irpf_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'tax_base_total',
                'tax_total',
                'irpf_rate',
                'irpf_amount',
                'legal_notes',
            ]);
        });
    }
};
