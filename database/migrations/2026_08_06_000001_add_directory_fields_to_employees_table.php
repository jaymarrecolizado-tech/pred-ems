<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Directory fields harvested from the "Region 02 Employees Directory" tracker:
     * gov.ph email, plantilla item number, GSIS BP number, and source of fund
     * (PLANTILLA / MOOE / PNPKI / e-LGU ...). These power the employee profile
     * and later payroll/remittance modules.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('gov_email', 150)->nullable()->after('personal_email');
            $table->string('plantilla_item_no', 100)->nullable()->after('position_id');
            $table->string('bp_number', 50)->nullable()->after('plantilla_item_no');
            $table->string('source_of_fund', 80)->nullable()->after('bp_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['gov_email', 'plantilla_item_no', 'bp_number', 'source_of_fund']);
        });
    }
};
