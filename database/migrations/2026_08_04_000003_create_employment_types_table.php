<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employment classifications with entitlement flags.
     * Flags drive leave accrual, GSIS/PhilHealth/PAG-IBIG coverage and the
     * 20% premium rule for contractual workers (no leave credits as of right).
     */
    public function up(): void
    {
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();          // PERMANENT, TEMPORARY, CASUAL, CO_TERMINUS, CONTRACTUAL, JOB_ORDER, CONTRACT_OF_SERVICE, GIP
            $table->string('name');                          // e.g. "Job Order", "Contract of Service"
            $table->boolean('has_leave_credits')->default(false);
            $table->boolean('has_gsis')->default(false);
            $table->boolean('has_philhealth')->default(false);
            $table->boolean('has_pagibig')->default(false);
            $table->boolean('has_withholding_tax')->default(false);
            $table->boolean('requires_20pct_premium')->default(false); // contractual: 20% premium in lieu of leave credits
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_types');
    }
};
