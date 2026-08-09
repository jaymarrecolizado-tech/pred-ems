<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveCreditLedger;
use App\Models\LeaveType;
use App\Models\Setting;
use App\Models\SmsQueue;
use App\Models\User;
use App\Notifications\DocumentRequestIssuedNotification;
use App\Support\Notifier;
use App\Support\Sms;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

        // CAUTION: these tables are wiped — safe only because notifications,
        // sms_queue and document_requests hold purely test-generated rows in
        // this dev database. Never run this suite against a populated DB.
        DB::table('notifications')->delete();
        DB::table('sms_queue')->delete();
        DB::table('document_requests')->delete();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@dictro2.gov.ph')->firstOrFail();
    }

    private function employeeUser(): User
    {
        // The leave-approval test files SLP, which now requires a credit
        // balance — so pick a leave-entitled employee (JO/COS have none).
        $employee = Employee::whereHas('user.roles', fn ($q) => $q->where('name', 'employee'))
            ->whereHas('employmentType', fn ($q) => $q->where('has_leave_credits', true))
            ->orderBy('id')
            ->firstOrFail();

        return $employee->user;
    }

    private function cleanupNotifications(User $user): void
    {
        $user->notifications()->delete();
    }

    private function cleanupDocuments(int $employeeId): void
    {
        Document::where('employee_id', $employeeId)->where('document_type', 'certificate_of_employment')->delete();
        DocumentRequest::where('employee_id', $employeeId)->delete();
    }

    /* ------------------------------------------------------------------ */
    /*  SMS helpers                                                        */
    /* ------------------------------------------------------------------ */

    public function test_phone_normalization_to_e164(): void
    {
        $this->assertSame('+639171234567', Sms::normalizePhone('09171234567'));
        $this->assertSame('+639171234567', Sms::normalizePhone('639171234567'));
        $this->assertSame('+639171234567', Sms::normalizePhone('+639171234567'));
        $this->assertNull(Sms::normalizePhone(''));
        $this->assertNull(Sms::normalizePhone(null));
    }

    public function test_sms_enqueued_when_enabled_and_phone_present(): void
    {
        $employee = $this->employeeUser()->employee;
        $originalPhone = $employee->contact_number;

        try {
            $employee->forceFill(['contact_number' => '09171234567'])->save();
            config(['services.sms.enabled' => true]);
            config(['services.sms.country_code' => 63]);

            $request = DocumentRequest::create([
                'employee_id' => $employee->id,
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Notification test',
                'status' => DocumentRequest::STATUS_ISSUED,
                'reference_no' => 'COE-2026-TEST',
            ]);

            Notifier::send($this->employeeUser(), new DocumentRequestIssuedNotification($request));

            $row = SmsQueue::where('phone', '+639171234567')->latest('id')->first();
            $this->assertNotNull($row);
            $this->assertEquals(SmsQueue::STATUS_PENDING, $row->status);
            $this->assertStringContainsString('COE-2026-TEST', $row->message);

            $row->delete();
            $request->delete();
        } finally {
            $employee->forceFill(['contact_number' => $originalPhone])->save();
            config(['services.sms.enabled' => false]);
            SmsQueue::where('phone', '+639171234567')->delete();
        }
    }

    public function test_sms_not_enqueued_when_disabled(): void
    {
        // services.sms.enabled defaults to false — nothing must be enqueued.
        config(['services.sms.enabled' => false]);

        $employee = $this->employeeUser()->employee;
        $originalPhone = $employee->contact_number;

        try {
            $employee->forceFill(['contact_number' => '09171234567'])->save();

            $request = DocumentRequest::create([
                'employee_id' => $employee->id,
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Disabled test',
                'status' => DocumentRequest::STATUS_ISSUED,
            ]);

            Notifier::send($this->employeeUser(), new DocumentRequestIssuedNotification($request));

            $this->assertSame(0, SmsQueue::where('phone', 'like', '%9171234567')->count());

            $request->delete();
        } finally {
            $employee->forceFill(['contact_number' => $originalPhone])->save();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Admin channel switches                                             */
    /* ------------------------------------------------------------------ */

    public function test_admin_can_toggle_email_and_sms_channels(): void
    {
        $admin = $this->admin();

        try {
            // Page renders with the current switches.
            $this->actingAs($admin)->get('/notifications/settings')->assertOk()->assertSee('Email notifications');

            // Turn both off.
            $this->actingAs($admin)->post('/notifications/settings', [
                'email' => '0',
                'sms' => '0',
            ])->assertRedirect();

            $this->assertFalse(Setting::emailNotificationsEnabled());
            $this->assertFalse(Setting::smsNotificationsEnabled());

            // Turn both back on.
            $this->actingAs($admin)->post('/notifications/settings', [
                'email' => '1',
                'sms' => '1',
            ])->assertRedirect();

            $this->assertTrue(Setting::emailNotificationsEnabled());
            $this->assertTrue(Setting::smsNotificationsEnabled());
        } finally {
            // Reset to a clean state so later tests see defaults (deleting the
            // keys makes sms fall back to the gateway config again).
            Setting::where('key', 'notifications.email_enabled')->delete();
            Setting::where('key', 'notifications.sms_enabled')->delete();
        }
    }

    public function test_employee_cannot_access_notification_settings(): void
    {
        $this->actingAs($this->employeeUser())
            ->get('/notifications/settings')
            ->assertStatus(403);

        $this->actingAs($this->employeeUser())
            ->post('/notifications/settings', ['email' => '0', 'sms' => '0'])
            ->assertStatus(403);
    }

    public function test_email_channel_omitted_when_disabled(): void
    {
        $employee = $this->employeeUser()->employee;
        $request = DocumentRequest::create([
            'employee_id' => $employee->id,
            'document_type' => 'certificate_of_employment',
            'purpose' => 'Channel toggle test',
            'status' => DocumentRequest::STATUS_ISSUED,
        ]);

        try {
            Setting::set('notifications.email_enabled', false);
            $notification = new DocumentRequestIssuedNotification($request);
            $this->assertNotContains('mail', $notification->via($this->employeeUser()));

            Setting::set('notifications.email_enabled', true);
            $this->assertContains('mail', $notification->via($this->employeeUser()));
        } finally {
            Setting::where('key', 'notifications.email_enabled')->delete();
            $request->delete();
        }
    }

    public function test_sms_not_enqueued_when_admin_toggle_off(): void
    {
        $employee = $this->employeeUser()->employee;
        $originalPhone = $employee->contact_number;

        try {
            $employee->forceFill(['contact_number' => '09171234567'])->save();
            config(['services.sms.enabled' => true]);
            Setting::set('notifications.sms_enabled', false);

            $request = DocumentRequest::create([
                'employee_id' => $employee->id,
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Toggle-off test',
                'status' => DocumentRequest::STATUS_ISSUED,
            ]);

            Notifier::send($this->employeeUser(), new DocumentRequestIssuedNotification($request));

            $this->assertSame(0, SmsQueue::where('phone', 'like', '%9171234567')->count());

            $request->delete();
        } finally {
            $employee->forceFill(['contact_number' => $originalPhone])->save();
            config(['services.sms.enabled' => false]);
            Setting::where('key', 'notifications.sms_enabled')->delete();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  In-system notifications                                            */
    /* ------------------------------------------------------------------ */

    public function test_document_request_issued_notifies_employee(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        try {
            $this->actingAs($user)->post('/documents/requests', [
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Bank requirement',
            ])->assertRedirect();

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'certificate_of_employment')->latest('id')->firstOrFail();

            $this->actingAs($this->admin())->post("/documents/requests/{$request->id}/issue")->assertRedirect();
            $request->refresh();

            $notification = $user->notifications()
                ->where('type', DocumentRequestIssuedNotification::class)
                ->latest()->first();
            $this->assertNotNull($notification);
            $this->assertNull($notification->read_at);
            $this->assertSame('Document issued', $notification->data['title']);
            $this->assertStringContainsString($request->reference_no, $notification->data['body']);
        } finally {
            $this->cleanupNotifications($user);
            $this->cleanupDocuments($employee->id);
        }
    }

    public function test_document_request_rejected_notifies_employee(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;

        try {
            $this->actingAs($user)->post('/documents/requests', [
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Loan',
            ])->assertRedirect();

            $request = DocumentRequest::where('employee_id', $employee->id)
                ->where('document_type', 'certificate_of_employment')->latest('id')->firstOrFail();

            $this->actingAs($this->admin())->post("/documents/requests/{$request->id}/reject", [
                'rejection_reason' => 'Incomplete records.',
            ])->assertRedirect();

            $notification = $user->notifications()
                ->where('type', \App\Notifications\DocumentRequestRejectedNotification::class)
                ->latest()->first();
            $this->assertNotNull($notification);
            $this->assertSame('Document request rejected', $notification->data['title']);
            $this->assertStringContainsString('Incomplete records.', $notification->data['body']);
        } finally {
            $this->cleanupNotifications($user);
            $this->cleanupDocuments($employee->id);
        }
    }

    public function test_document_request_submitted_alerts_hr(): void
    {
        $user = $this->employeeUser();
        $employee = $user->employee;
        $admin = $this->admin();

        try {
            $this->actingAs($user)->post('/documents/requests', [
                'document_type' => 'service_record',
                'purpose' => 'Retirement application',
            ])->assertRedirect();

            $notification = $admin->notifications()
                ->where('type', \App\Notifications\DocumentRequestSubmittedNotification::class)
                ->latest()->first();
            $this->assertNotNull($notification);
            $this->assertSame('New document request', $notification->data['title']);
            $this->assertSame(route('documents.requests.queue'), $notification->data['url']);
        } finally {
            $this->cleanupNotifications($user);
            $this->cleanupNotifications($admin);
            $this->cleanupDocuments($employee->id);
        }
    }

    public function test_leave_approved_notifies_employee(): void
    {
        $admin = $this->admin();
        $user = $this->employeeUser();
        $employee = $user->employee;

        $type = LeaveType::where('is_active', true)->where('accrual_per_month', 0)->first();
        $application = null;

        try {
            $this->actingAs($user)->post('/leave', [
                'leave_type_id' => $type->id,
                'date_from' => '2026-08-21',
                'date_to' => '2026-08-21',
                'reason' => 'Personal errand',
            ])->assertRedirect();

            $application = LeaveApplication::where('employee_id', $employee->id)->latest('id')->firstOrFail();

            $this->actingAs($admin)->post("/leave/{$application->id}/approve")->assertRedirect();

            $notification = $user->notifications()
                ->where('type', \App\Notifications\LeaveApprovedNotification::class)
                ->latest()->first();
            $this->assertNotNull($notification);
            $this->assertSame('Leave approved', $notification->data['title']);
        } finally {
            // The approved SLP application debited the ledger — remove that row
            // too so repeated runs never drain the employee's SLP credit.
            if ($application) {
                LeaveCreditLedger::where('source_type', LeaveApplication::class)
                    ->where('source_id', $application->id)
                    ->delete();
                $application->delete();
            }
            $this->cleanupNotifications($user);
        }
    }

    public function test_mark_read_actions(): void
    {
        $user = $this->employeeUser();

        try {
            // Seed an unread notification directly through the real flow.
            $request = DocumentRequest::create([
                'employee_id' => $user->employee->id,
                'document_type' => 'certificate_of_employment',
                'purpose' => 'Mark-read test',
                'status' => DocumentRequest::STATUS_ISSUED,
                'reference_no' => 'COE-2026-MRK',
            ]);

            Notifier::send($user, new DocumentRequestIssuedNotification($request));

            $this->actingAs($user)->get('/notifications')->assertOk();
            $this->actingAs($user)->get('/notifications?filter=unread')->assertOk();

            $notification = $user->notifications()
                ->where('type', DocumentRequestIssuedNotification::class)
                ->latest()->first();
            $this->assertNotNull($notification);

            // Mark one as read.
            $this->actingAs($user)->post("/notifications/{$notification->id}/read")->assertRedirect();
            $this->assertNotNull($notification->fresh()->read_at);

            $request->delete();
            $this->cleanupNotifications($user);

            // Mark-all on a fresh set.
            $request2 = DocumentRequest::create([
                'employee_id' => $user->employee->id,
                'document_type' => 'service_record',
                'purpose' => 'Mark-all test',
                'status' => DocumentRequest::STATUS_ISSUED,
                'reference_no' => 'SR-2026-MRK',
            ]);
            Notifier::send($user, new DocumentRequestIssuedNotification($request2));

            $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
            $this->assertSame(0, $user->unreadNotifications()->count());

            $request2->delete();
            $this->cleanupNotifications($user);
        } finally {
            $this->cleanupNotifications($user);
            DocumentRequest::where('employee_id', $user->employee->id)->delete();
        }
    }
}
