<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * These are smoke/integration tests against a real (migrated + seeded)
     * MySQL database — the local dev DB by default, or a scratch database in
     * CI. Connection settings come from the environment (DB_CONNECTION,
     * DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD) so CI can point
     * the whole suite at a fresh database without code changes.
     *
     * Note: env() falls back to the .env values (dev `hris` DB) when a variable
     * isn't set in the process environment. That is the intended local default;
     * the CI workflow always passes DB_* explicitly. Do not add a `config:cache`
     * step to CI — it would freeze these settings at build time.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => env('DB_CONNECTION', 'mysql'),
            'database.connections.mysql.host' => env('DB_HOST', '127.0.0.1'),
            'database.connections.mysql.port' => env('DB_PORT', '3306'),
            'database.connections.mysql.database' => env('DB_DATABASE', 'hris'),
            'database.connections.mysql.username' => env('DB_USERNAME', 'root'),
            'database.connections.mysql.password' => env('DB_PASSWORD', ''),
        ]);
    }
}
