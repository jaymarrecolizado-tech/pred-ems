# Changelog

All notable changes to the DICT RO2 HRIS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased] — 2026-08-11

### Security
- Extracted 26 Form Request classes for all POST routes (replaces inline `$request->validate()`)
- Added `SecurityHeaders` middleware (CSP, X-Frame-Options, X-Content-Type-Options, HSTS)
- Published `config/session.php` with hardened settings: `secure=true`, `http_only=true`, `same_site=lax`
- Added `Search::escape()` helper to prevent LIKE wildcard injection in search queries
- Applied LIKE escaping to all 4 controllers with user search input (Employee, Leave, Monetization, AuditLog)
- Added rate limiting to sensitive POST routes (leave filing, document requests: 10/min; login: 5/min)
- Strengthened password policy to require mixed case, numbers, and symbols
- Added soft deletes to Employee model (government data retention compliance)

### Architecture
- Extracted business operations into an `app/Actions/` layer (16 classes: `ApproveLeave`, `FileLeaveApplication`, `RejectLeave`, `CancelLeaveApplication`, `ProcessMonetization`, `SubmitDocumentRequest`, `IssueDocumentRequest`, `RejectDocumentRequest`, `CancelDocumentRequest`, `GeneratePayrollItems`, `AdjustPayrollItem`, `FinalizePayroll`, `MarkRemittanceRemitted`, `ImportEmployees`, `ImportAttendance` + `ActionResult` value object)
- Slimmed LeaveController, MonetizationController, DocumentRequestController, PayrollController, and ImportController to validation + flash handling; business rules now testable in isolation
- Extracted the bulk-import preview/commit logic out of `ImportController` (~470 → 233 lines) into `App\Actions\ImportEmployees` and `App\Actions\ImportAttendance`, with shared date/time parsing in `App\Support\ImportParser`
- Extracted document PDF rendering into `App\Support\DocumentRenderer` (shared by the issuance action and the download path) and day-count formatting into `App\Support\Format`
- Created 17 database factory classes with HasFactory trait on 15 models
- Created 3 PHP enums: `LeaveStatus`, `PayrollPeriodStatus`, `DocumentRequestStatus`
- Published `config/services.php` for SMS gateway configuration (moved all `env()` calls out of service classes)
- Added DB transactions to multi-step operations (leave approval, document issuance, payroll finalize, monetization)

### Testing & CI
- Fixed `phpunit.xml` to set the Laravel 12 config keys (`CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`) so rate-limiter counters no longer persist across tests (the old `CACHE_DRIVER` key was ignored by Laravel 12, silently using the database cache)
- Added the missing `App\Http\Controllers\Controller` base class (partial repo copy lacked the scaffold file)
- Restored the standard Laravel `artisan` CLI entrypoint and the 3 scaffold migrations (`create_users_table`, `create_cache_table`, `create_jobs_table`) — the feature suite is MySQL smoke tests, and a fresh CI database previously had no schema to migrate
- Made the test suite database-agnostic: `tests/TestCase.php` now reads `DB_CONNECTION`/`DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` from the environment instead of 16 duplicated per-file `setUp()` blocks hardcoding the dev `hris` database
- Dropped the real-person data dependency from `PayrollAdjustmentTest` (uses the dev `juan@dictro2.gov.ph` account) and made `DocumentVerificationTest` self-contained (creates its own document) so the suite passes on a fresh seeded database
- CI workflow now prepares the MySQL service: `migrate --seed` + `leave:accrue` before running Unit + Feature suites, and passes DB credentials via env
- Ran a full-repo Pint pass (210 files) so the CI code-style gate is green
- Added missing `.gitignore` entries for `storage/app/private/imports/*` (employee PII uploads), `storage/fonts/*` (dompdf cache), and `bootstrap/cache/*`
- Made `add_performance_indexes` migration idempotent (`Schema::hasIndex` column checks) so upgrades on an existing database don't collide with pre-existing indexes
- Fixed `attendance_logs.source` CHECK constraint to include `hr_correction` (the value written by the correction-approval workflow)
- Fixed `ImportTest::tearDown()` to `forceDelete()` imported test employees (soft deletes left unique employee numbers occupied between runs)
- Added GitHub Actions CI workflow (PHP 8.2/8.3/8.4 matrix, MySQL 8.0)
- CI runs Pint code-style check, syntax lint, and unit + feature test suites
- Created `phpunit.xml`, `tests/TestCase.php`, `tests/CreatesApplication.php` (were missing)
- Added 337-line `PayrollComputationTest` with parametric tests for GSIS, PhilHealth, PAG-IBIG, BIR
- Added `ImportActionsTest` — direct tests for the import actions' per-row semantics (within-file duplicates, employment-type alias resolution, create/update/upsert modes, attendance punch validation)

### Performance
- Added performance indexes on employees (employee_number, last_name, status), leave_applications (status), attendance_logs (employee_id + log_date)
- Fixed N+1 query in `Employee::leaveBalances()` (single grouped query instead of N per-type SUM queries)
- Added `Cache::remember()` to dashboard counts (60-second TTL)
- Cache reference data (contribution rates, leave types, employment types, divisions)

### Frontend & Infrastructure
- Self-hosted 11 Google Fonts TTF files (Inter + Be Vietnam Pro) for offline/intranet readiness
- Created comprehensive `.env.example` with all required environment variables
- Updated README to reflect actual stack (removed stale Tailwind/Livewire references)

## [Previous] — Pre-audit baseline

- Phase 1-6 application: employee management, leave (CSC-compliant), payroll with PH statutory deductions,
  attendance with geofencing, official document generation (COE, Service Record, DTR), notifications with SMS,
  audit trails, VL monetization, flexible work scheduling, and official DICT document templates.
