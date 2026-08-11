<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Support\DocumentIssuer;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    private function adminUser(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function someEmployee(): Employee
    {
        return Employee::query()
            ->with('appointments')
            ->firstOrFail();
    }

    public function test_qr_data_uri_renders_in_document_views(): void
    {
        $employee = $this->someEmployee();

        $html = view('documents.coe', [
            'employee' => $employee,
            'preparer' => DocumentIssuer::preparer(),
            'certifier' => DocumentIssuer::certifier(),
            'referenceNo' => 'COE-2026-9999',
            'qrDataUri' => 'data:image/png;base64,AAAA',
        ])->render();

        $this->assertStringContainsString('data:image/png;base64,AAAA', $html);
        $this->assertStringContainsString('/verify/COE-2026-9999', $html);
        $this->assertStringContainsString('VERIFY THIS DOCUMENT', $html);
    }

    public function test_no_qr_when_reference_missing(): void
    {
        $employee = $this->someEmployee();

        $html = view('documents.coe', [
            'employee' => $employee,
            'preparer' => DocumentIssuer::preparer(),
            'certifier' => DocumentIssuer::certifier(),
        ])->render();

        // The letterhead always embeds the real DICT/Bagong Pilipinas logos as
        // base64 data URIs, so assert on the QR-specific markers instead.
        $this->assertStringNotContainsString('/verify/', $html);
        $this->assertStringNotContainsString('VERIFY THIS DOCUMENT', $html);
    }

    public function test_public_verification_page_and_unknown_reference(): void
    {
        // Guest (no auth) must be able to verify. Self-contained so it also
        // works against a fresh (CI) database with no pre-existing documents.
        $document = Document::factory()->create();

        try {
            $this->get('/verify/'.$document->reference_no)
                ->assertOk()
                ->assertSee('DOCUMENT VERIFIED')
                ->assertSee($document->reference_no);

            $this->get('/verify/DOES-NOT-EXIST-0000')
                ->assertNotFound();
        } finally {
            // Remove the factory-created fixture (document + its employee) so
            // the shared dev DB doesn't accumulate stray rows per run.
            $document->forceDelete();
            $document->employee?->forceDelete();
        }
    }

    public function test_issued_coe_pdf_has_qr_and_verifies_end_to_end(): void
    {
        $employee = $this->someEmployee();

        $response = $this->actingAs($this->adminUser())
            ->get("/employees/{$employee->id}/coe/pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?: '');

        $document = Document::query()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($document, 'COE issuance should be recorded in the ledger.');

        $this->get('/verify/'.$document->reference_no)
            ->assertOk()
            ->assertSee('DOCUMENT VERIFIED')
            ->assertSee($document->reference_no);
    }
}
