<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class DocumentRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Smoke-test against the real (seeded) MySQL database, not :memory:.
        config(['database.default' => 'mysql']);
        config([
            'database.connections.mysql.database' => 'hris',
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    private function cleanupRequests(int $employeeId): void
    {
        DocumentRequest::where('employee_id', $employeeId)->delete();
    }

    public function test_employee_requests_and_hr_issues_leave_balances_certificate(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            // Employee submits the request.
            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'leave_balances',
                'purpose' => 'Loan application with GSIS',
            ])->assertRedirect(route('documents.requests'));

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'leave_balances')
                ->latest('id')->firstOrFail();

            $this->assertEquals(DocumentRequest::STATUS_PENDING, $request->status);
            $this->assertDatabaseHas('audit_logs', [
                'model_type' => DocumentRequest::class,
                'model_id' => $request->id,
                'action' => 'document_requested',
            ]);

            // HR issues it: reference minted, document ledger entry created.
            $this->actingAs($this->admin())
                ->post("/documents/requests/{$request->id}/issue")
                ->assertRedirect();

            $request->refresh();
            $this->assertEquals(DocumentRequest::STATUS_ISSUED, $request->status);
            $this->assertNotNull($request->reference_no);
            $this->assertStringStartsWith('LB-', $request->reference_no);

            $this->assertDatabaseHas('documents', [
                'employee_id' => $employee->id,
                'document_type' => 'leave_balances',
                'reference_no' => $request->reference_no,
            ]);

            // Employee can download the official copy.
            $response = $this->actingAs($this->employeeUser())
                ->get("/documents/requests/{$request->id}/download");
            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
            $this->assertStringContainsString('%PDF', $response->getContent());

            // Re-issuing is refused once processed.
            $this->actingAs($this->admin())
                ->post("/documents/requests/{$request->id}/issue")
                ->assertStatus(409);
        } finally {
            $this->cleanupRequests($employee->id);
            Document::where('employee_id', $employee->id)->where('document_type', 'leave_balances')->delete();
        }
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Bank requirement',
            ])->assertRedirect();

            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Another bank requirement',
            ])->assertSessionHasErrors('document_type');

            $this->assertSame(1, DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'certificate_of_employment')
                ->count());
        } finally {
            $this->cleanupRequests($employee->id);
        }
    }

    public function test_dtr_request_requires_period(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'dtr',
                'purpose' => 'Court attendance',
            ])->assertSessionHasErrors('period');
        } finally {
            $this->cleanupRequests($employee->id);
        }
    }

    public function test_hr_can_reject_request_with_reason(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'no_pending_case',
                'purpose' => 'Travel authority application',
            ])->assertRedirect();

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'no_pending_case')->latest('id')->firstOrFail();

            $this->actingAs($this->admin())->post("/documents/requests/{$request->id}/reject", [
                'rejection_reason' => 'Verification pending with the Legal Office.',
            ])->assertRedirect();

            $request->refresh();
            $this->assertEquals(DocumentRequest::STATUS_REJECTED, $request->status);
            $this->assertSame('Verification pending with the Legal Office.', $request->rejection_reason);
            $this->assertNotNull($request->processed_at);
        } finally {
            $this->cleanupRequests($employee->id);
        }
    }

    public function test_employee_can_cancel_pending_request(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'service_record',
                'purpose' => 'Retirement application',
            ])->assertRedirect();

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'service_record')->latest('id')->firstOrFail();

            $this->actingAs($this->employeeUser())
                ->post("/documents/requests/{$request->id}/cancel")
                ->assertRedirect();

            $this->assertEquals(DocumentRequest::STATUS_CANCELED, $request->fresh()->status);
        } finally {
            $this->cleanupRequests($employee->id);
        }
    }

    public function test_employee_cannot_access_hr_queue_or_issue(): void
    {
        $employee = $this->employeeUser()->employee;

        try {
            $this->actingAs($this->employeeUser())->get('/documents/requests/queue')->assertForbidden();

            $this->actingAs($this->employeeUser())->post('/documents/requests', [
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Personal use',
            ])->assertRedirect();

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'certificate_of_employment')->latest('id')->firstOrFail();

            $this->actingAs($this->employeeUser())
                ->post("/documents/requests/{$request->id}/issue")
                ->assertForbidden();
        } finally {
            $this->cleanupRequests($employee->id);
        }
    }
}
