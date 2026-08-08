<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound SMS queue — the recommended LOKA-style architecture: enqueue on
     * notify, send asynchronously via `php artisan sms:send` (scheduled every
     * minute). The gateway is an Android phone with a SIM, so web requests are
     * never blocked on it and failures never break business actions.
     */
    public function up(): void
    {
        Schema::create('sms_queue', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);            // E.164, e.g. +639171234567
            $table->string('message', 320);         // SMS_MAX_MESSAGE_LENGTH
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_queue');
    }
};
