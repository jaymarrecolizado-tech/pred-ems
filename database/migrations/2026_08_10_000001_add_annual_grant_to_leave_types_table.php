<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks leave types that receive a flat annual grant instead of a monthly
     * accrual — e.g. SLP (3 days/year, non-cumulative, per CSC Omnibus Rules
     * on Leave). Each January 1 the `leave:accrue` command resets any unused
     * balance from the previous year and grants `annual_max_credit` afresh.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('annual_grant')->default(false)->after('annual_max_credit');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn('annual_grant');
        });
    }
};
