<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5.2 — Flexible work scheduling (AOM No. 2026-020).
     *
     * work_schedules : effective-dated schedule registry. Each schedule stores
     *   a per-day-of-week definition (work/rest + AM/PM times) as JSON under
     *   `days`, keyed 1 (Mon) .. 7 (Sun). `starts_on`/`ends_on` make schedules
     *   effective-dated so past DTRs always resolve the schedule that applied
     *   that day. `revert_schedule_id` is the fallback schedule used for a
     *   whole week when a holiday/work suspension falls on this schedule's
     *   designated rest day (CSC Resolution No. 2600838 rule in the AOM).
     * holidays      : calendar of holidays / work suspensions used to apply
     *   the CSC revert rule and to mark DTR rows. `is_repeating` marks
     *   fixed-date holidays (e.g. Dec 25) that recur every year.
     */
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->json('days'); // {"1": {"work":true,"am_start":"07:00",...}, "5": {"work":false}, ...}
            $table->date('starts_on')->nullable(); // effective from
            $table->date('ends_on')->nullable();   // effective until (null = open-ended)
            $table->boolean('is_active')->default(true);
            $table->foreignId('revert_schedule_id')->nullable()->constrained('work_schedules')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->date('date');
            $table->string('type', 25)->default('regular_holiday'); // regular_holiday, special_nonworking, work_suspension
            $table->boolean('is_repeating')->default(false); // fixed-date annual holiday
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('work_schedules');
    }
};
