<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('entity_type')->default('individual')->after('country');
            $table->string('cif')->nullable()->after('dni_nie');
            $table->text('registro_mercantil')->nullable()->after('cif');
            $table->string('tax_regime')->default('peninsula_baleares')->after('registro_mercantil');
        });

        Schema::table('people', function (Blueprint $table) {
            $table->string('dni_nie')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('dni_nie')->nullable(false)->change();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn([
                'entity_type',
                'cif',
                'registro_mercantil',
                'tax_regime',
            ]);
        });
    }
};
