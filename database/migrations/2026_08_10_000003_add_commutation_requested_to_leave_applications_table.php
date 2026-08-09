<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CSC Form No. 6 asks whether the employee requests commutation of the
     * leave (conversion of leave days to cash). Captured at filing time so the
     * printed application form reflects the employee's choice.
     */
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->boolean('commutation_requested')->default(false)->after('contact_during_leave');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('commutation_requested');
        });
    }
};
