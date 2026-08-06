<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Salary Standardization Law (SSL) grade × step table.
     * Config data — replaced/updated when a new SSL is issued.
     * unique(grade, step) guards against duplicate entries.
     */
    public function up(): void
    {
        Schema::create('salary_scales', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('salary_grade');
            $table->unsignedTinyInteger('step');
            $table->decimal('amount', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['salary_grade', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_scales');
    }
};
