<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class ServiceRecordTest extends TestCase
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

        $this->testStartedAt = now();
    }

    protected function tearDown(): void
    {
        // Remove documents minted by this test so the shared dev DB's
        // reference-number counter stays stable across runs.
        Document::where('document_type', 'service_record')
            ->where('generated_by', $this->adminUser()->id)
            ->where('generated_at', '>=', $this->testStartedAt)
            ->delete();

        parent::tearDown();
    }

    private \DateTimeInterface $testStartedAt;

    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))->firstOrFail();

        return $employee->user;
    }

    public function test_hr_can_view_service_record_html(): void
    {
        $employee = Employee::firstOrFail();

        $this->actingAs($this->adminUser())
            ->get("/employees/{$employee->id}/service-record")
            ->assertOk()
            ->assertSee('SERVICE RECORD')
            ->assertSee('THIS IS TO CERTIFY')
            ->assertSee('Nothing Follows');
    }

    public function test_hr_can_download_service_record_pdf_and_records_document(): void
    {
        $employee = Employee::firstOrFail();

        $response = $this->actingAs($this->adminUser())
            ->get("/employees/{$employee->id}/service-record/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?: '');
        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->assertDatabaseHas('documents', [
            'employee_id' => $employee->id,
            'document_type' => 'service_record',
            'generated_by' => $this->adminUser()->id,
        ]);
    }

    public function test_employee_can_view_own_service_record_but_not_others(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $other = Employee::where('id', '!=', $employee->id)->firstOrFail();

        $this->actingAs($user)->get("/employees/{$employee->id}/service-record")->assertOk();
        $this->actingAs($user)->get("/employees/{$other->id}/service-record")->assertForbidden();
    }

    public function test_reference_numbers_are_unique_and_sequentially_formatted(): void
    {
        $employee = Employee::firstOrFail();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->adminUser())
                ->get("/employees/{$employee->id}/service-record/pdf")
                ->assertOk();
        }

        $refs = Document::where('document_type', 'service_record')
            ->where('generated_at', '>=', $this->testStartedAt)
            ->orderBy('reference_no')
            ->pluck('reference_no')
            ->toArray();

        $this->assertCount(3, $refs);
        $this->assertCount(count($refs), array_unique($refs), 'Reference numbers must be unique');

        // Format: SR-2026-0001, SR-2026-0002, ... (current year, zero-padded).
        foreach ($refs as $i => $ref) {
            $this->assertMatchesRegularExpression('/^SR-\d{4}-\d{4}$/', $ref, "Reference {$ref} malformed");
            $this->assertSame((int) substr($refs[0], -4) + $i, (int) substr($ref, -4), 'References must be sequential');
        }
    }
}
