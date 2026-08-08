<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee document requests and the HR fulfillment trail.
     *
     * lifecycle: pending -> issued (reference number minted, PDF generated,
     * issuance recorded in `documents`) | rejected (with reason).
     */
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);   // service_record | certificate_of_employment | leave_balances | no_pending_case | dtr
            $table->text('purpose')->nullable();   // why the document is needed
            $table->string('period', 7)->nullable(); // e.g. "2026-08" — DTR month
            $table->string('status', 20)->default('pending'); // pending | issued | rejected
            $table->string('reference_no', 40)->nullable();   // minted on issue
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests');
    }
};
