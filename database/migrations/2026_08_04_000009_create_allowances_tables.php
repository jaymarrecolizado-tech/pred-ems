<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allowance definitions (e.g., PERA) and per-employee effective-dated grants.
     */
    public function up(): void
    {
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();   // PERA, RA, etc.
            $table->string('name');
            $table->string('computation', 20)->default('fixed'); // fixed | percentage
            $table->decimal('amount', 12, 2)->nullable();        // for fixed
            $table->decimal('percentage', 5, 2)->nullable();     // for percentage
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowance_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable(); // override base definition
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'allowance_id', 'effective_from'], 'emp_allowance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_allowances');
        Schema::dropIfExists('allowances');
    }
};
