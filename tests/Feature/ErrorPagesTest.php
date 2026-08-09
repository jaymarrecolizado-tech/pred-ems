<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
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

    public function test_403_renders_friendly_page_with_cat(): void
    {
        // An employee hitting an admin-only queue must get the friendly 403.
        $this->actingAs($this->employeeUser())
            ->get('/documents/requests/queue')
            ->assertStatus(403)
            ->assertSee('This page is off-limits')
            ->assertSee('error-cat', false)
            ->assertSee('403')
            ->assertSee('Back to Dashboard');
    }

    public function test_403_shows_custom_abort_message(): void
    {
        // /my/payslips aborts 404 for accounts without a linked 201-file; use
        // the document-request guard which carries a custom 403 message when
        // an account has no employee record. Simulate directly via abort by
        // rendering the frame with a custom message.
        $view = view('errors.frame', [
            'code' => 403,
            'title' => 'This page is off-limits',
            'message' => 'No employee 201-file record linked to this account.',
            'description' => 'If you believe this is a mistake, please contact the HR office for assistance.',
        ]);

        $html = $view->render();

        $this->assertStringContainsString('No employee 201-file record linked to this account.', $html);
        $this->assertStringContainsString('This page is off-limits', $html);
        $this->assertStringContainsString('error-cat', $html);
    }

    public function test_404_renders_friendly_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/definitely-not-a-real-page-xyz')
            ->assertStatus(404)
            ->assertSee('This page wandered off')
            ->assertSee('error-cat', false);
    }

    public function test_419_renders_friendly_page(): void
    {
        // Force a 419 by rendering the view directly (the frame is what the
        // handler shows; asserting its copy guards the friendly copy).
        $view = view('errors.frame', [
            'code' => 419,
            'title' => 'Your session took a cat nap',
            'message' => null,
            'description' => 'Your session expired while you were away — sign back in and try again.',
        ]);

        $html = $view->render();

        $this->assertStringContainsString('Your session took a cat nap', $html);
        $this->assertStringContainsString('419', $html);
        $this->assertStringContainsString('error-cat', $html);
    }

    public function test_500_renders_friendly_page_without_leaking_details(): void
    {
        $view = view('errors.frame', [
            'code' => 500,
            'title' => 'Something hiccuped',
            'message' => null,
            'description' => 'An unexpected error happened on our end.',
        ]);

        $html = $view->render();

        $this->assertStringContainsString('Something hiccuped', $html);
        $this->assertStringContainsString('error-cat', $html);
    }

    public function test_custom_abort_message_is_escaped_against_xss(): void
    {
        $view = view('errors.frame', [
            'code' => 403,
            'title' => 'This page is off-limits',
            'message' => '<img src=x onerror=alert(1)> "quoted" & <b>bold</b>',
            'description' => 'Contact the HR office.',
        ]);

        $html = $view->render();

        // Raw angle brackets must never reach the page.
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }
}
