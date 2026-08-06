<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave type definitions with CSC accrual rules.
     * VL/SL accrue 1.25 days/month; SLP is 3 days/year non-cumulative;
     * statutory leaves (Maternity 60, Paternity 7, Solo Parent 7, VAWC 10) are
     * fixed grants.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();       // VL, SL, SLP, MATERNITY, PATERNITY, SOLO_PARENT, VAWC, STUDY...
            $table->string('name');
            $table->decimal('accrual_per_month', 5, 2)->default(0); // 1.25 for VL/SL
            $table->decimal('annual_max_credit', 6, 2)->nullable(); // cap on accumulated credits (e.g. 15 VL/SL balance per CSC rule)
            $table->boolean('is_cumulative')->default(true);        // SLP = false
            $table->boolean('is_commutable')->default(true);        // SLP = false
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
