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
- Created 17 database factory classes with HasFactory trait on 15 models
- Created 3 PHP enums: `LeaveStatus`, `PayrollPeriodStatus`, `DocumentRequestStatus`
- Published `config/services.php` for SMS gateway configuration (moved all `env()` calls out of service classes)
- Added DB transactions to multi-step operations (leave approval, document issuance, payroll finalize, monetization)

### Testing & CI
- Added GitHub Actions CI workflow (PHP 8.2/8.3/8.4 matrix, MySQL 8.0)
- CI runs Pint code-style check, syntax lint, and unit + feature test suites
- Created `phpunit.xml`, `tests/TestCase.php`, `tests/CreatesApplication.php` (were missing)
- Added 337-line `PayrollComputationTest` with parametric tests for GSIS, PhilHealth, PAG-IBIG, BIR

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
