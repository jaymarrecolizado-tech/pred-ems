<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maiden name — required on the CSC Service Record (CS Form 212) for
     * married women.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('maiden_name', 100)->nullable()->after('middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('maiden_name');
        });
    }
};
