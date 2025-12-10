<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any 'invoiced' status to 'ready' since we now check via relationship
        DB::table('stripe_transactions')
            ->where('status', 'invoiced')
            ->update(['status' => 'ready']);

        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->dropColumn('is_complete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_transactions', function (Blueprint $table) {
            $table->boolean('is_complete')->default(false)->after('status');
        });
    }
};
