<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Position titles with plantilla salary grade. Covers both plantilla
     * positions (Permanent/Casual/Temporary) and non-plantilla ones
     * (JO/COS/GIP), where the grade may be informational only.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->string('level', 50)->nullable();          // e.g. I, II, III / Chief / Director
            $table->boolean('is_plantilla')->default(false);
            $table->string('plantilla_item_no')->nullable();   // existing plantilla item number
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['title', 'salary_grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
