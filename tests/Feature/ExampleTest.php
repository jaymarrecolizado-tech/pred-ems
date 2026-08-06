<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic application test: guests hitting the root are redirected to the
     * login page, which renders successfully.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $this->get('/')->assertStatus(302)->assertRedirect('/dashboard');
        // The dashboard is auth-protected, so guests are bounced to login.
        $this->get('/dashboard')->assertStatus(302)->assertRedirect('/login');

        $this->get('/login')->assertOk();
    }
}
