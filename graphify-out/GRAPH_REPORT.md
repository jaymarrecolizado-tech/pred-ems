# Graph Report - .  (2026-08-10)

## Corpus Check
- 226 files · ~80,447 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1219 nodes · 2448 edges · 166 communities (87 shown, 79 thin omitted)
- Extraction: 85% EXTRACTED · 15% INFERRED · 0% AMBIGUOUS · INFERRED: 357 edges (avg confidence: 0.8)
- Token cost: 28,000 input · 12,000 output

## Community Hubs (Navigation)
- Attendance Controllers
- Leave Credits SMS Jobs
- Employee Leave Models
- Attendance Admin Actions
- CSV Import Pipeline
- Roles Permissions Seeders
- Allowance Audit Models
- Employee CRUD Controller
- Holiday Work Schedules
- Appointment Controller
- Directory JSON Normalizer
- Attendance Feature Tests
- HRIS Architecture Blueprint
- Document Verification
- Payroll Core Engine
- Payslips Document Requests
- User Auth Roles
- Frontend App Shell JS
- eGovPay UI Redesign
- Composer Package Metadata
- Composer Scripts
- Feature Test Suite Files
- Checkpoint Work Schedule
- SMS Gateway Architecture
- Appointment COE Tests
- Import Feature Tests
- Profile Self-Service Tests
- Reports Feature Tests
- COE Service Record Controllers
- Payroll Item Adjustments
- Remittances SMS Queue
- AOM Compressed Workweek
- Error Pages Tests
- Payroll Adjustment Tests
- Payroll Period Model
- Division Position Seeders
- Settings Notifications
- Statutory Compliance Engine
- Employee Pages Smoke Tests
- Attendance Corrections
- Composer Dev Dependencies
- Project Setup Scripts
- Document Verification Tests
- Contribution Rates Payroll
- Attendance Log Model
- Composer Plugin Config
- Composer Runtime Dependencies
- Flexible Work Scheduling Policy
- Role Middleware
- Real Directory Seeder
- Dashboard View Toggle UI
- Composer Autoload PSR-4
- Notification Bell Partial
- Dashboard Demo Charts
- post create project cmd php
- appointments create blade php appointmen
- appointments edit blade php appointments
- employees index blade php employees
- employees show blade php employees
- profile show blade php employees
- forced leave blade php partials
- StatCard jsx CHIP TONES StatCard
- extra laravel dont discover
- cleanup directory py main norm
- dashboard blade php partials avatar
- employees create blade php employees
- employees edit blade php employees
- imports index blade php partials
- approvals blade php partials avatar
- monetization blade php partials avatar
- attendance summary blade php partials
- attrition blade php partials breadcrumbs
- documents blade php partials breadcrumbs
- headcount blade php partials breadcrumbs
- reports index blade php partials
- reports leave balances blade php
- leave utilization blade php partials
- checkpoints blade php partials breadcrum
- corrections blade php partials breadcrum
- attendance index blade php partials
- logs blade php partials breadcrumbs
- attendance settings blade php partials
- audit logs index blade php
- login blade php partials toast
- coe blade php partials doc
- dtr blade php partials doc
- documents leave balances blade php
- no pending case blade php
- requests create blade php partials
- requests index blade php partials
- queue blade php partials breadcrumbs
- service record blade php partials
- employees partials form blade php
- 403 blade php errors frame
- 404 blade php errors frame
- 409 blade php errors frame
- 419 blade php errors frame
- 422 blade php errors frame
- 429 blade php errors frame
- 500 blade php errors frame
- 503 blade php errors frame
- frame blade php partials error
- attendance blade php partials breadcrumb
- employees blade php partials breadcrumbs
- leave create blade php partials
- leave index blade php partials
- notifications index blade php partials
- notifications settings blade php partial
- error cat blade php partials
- icon blade php partials icon
- toast blade php partials toast
- adjust blade php partials breadcrumbs
- payroll index blade php partials
- my blade php partials breadcrumbs
- remittances blade php partials breadcrum
- payroll show blade php partials
- profile edit blade php partials
- password blade php partials breadcrumbs
- setup sh setup sh script

## God Nodes (most connected - your core abstractions)
1. `Employee` - 114 edges
2. `User` - 63 edges
3. `Audit` - 43 edges
4. `DocumentRequest` - 38 edges
5. `LeaveApplication` - 35 edges
6. `LeaveType` - 32 edges
7. `ImportController` - 25 edges
8. `AttendanceTest` - 25 edges
9. `Holiday` - 23 edges
10. `AttendanceAdminController` - 22 edges

## Surprising Connections (you probably didn't know these)
- `CSC Res. 2600838 Friday-Revert Rule` --semantically_similar_to--> `Friday Holiday Revert to Standard 8-Hour Week`  [INFERRED] [semantically similar]
  README.md → Reference/AOM NO. 2026-020 - Resumption of the 4-Day Compressed Workweek Schedule.pdf
- `4-Day Compressed Workweek Schedule` --semantically_similar_to--> `Four-Day Compressed Workweek Mon-Thu 7AM-6PM`  [INFERRED] [semantically similar]
  docs/PLAN.md → Reference/AOM NO. 2026-020 - Resumption of the 4-Day Compressed Workweek Schedule.pdf
- `AOM 2026-020 Flexible Work Scheduling` --references--> `AOM No. 2026-020 Resumption of 4-Day CWW`  [INFERRED]
  README.md → Reference/AOM NO. 2026-020 - Resumption of the 4-Day Compressed Workweek Schedule.pdf
- `eGovPay-style Design System` --semantically_similar_to--> `eGovPay-style Design System Redesign`  [INFERRED] [semantically similar]
  README.md → docs/UI_REDESIGN.md
- `SMS Queue Async Delivery sms:send` --semantically_similar_to--> `HRMIS Event to sms_queue to Gateway Architecture`  [INFERRED] [semantically similar]
  README.md → Reference/sms.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Personnel Directory Generation Pipeline** — database_data_readme_normalize_to_json, database_data_readme_cleanup_directory, database_data_readme_employees_directory_json, database_data_readme_realdirectoryseeder [EXTRACTED 1.00]
- **AOM 2026-020 CWW Holiday Policy Bundle** — reference_aom_no_2026_020_resumption_of_the_4_day_compressed_workweek_schedule_four_day_cww, reference_aom_no_2026_020_resumption_of_the_4_day_compressed_workweek_schedule_friday_revert_rule, reference_aom_no_2026_020_resumption_of_the_4_day_compressed_workweek_schedule_deemed_complied_rule, reference_aom_no_2026_020_resumption_of_the_4_day_compressed_workweek_schedule_csc_resolution_2600838 [EXTRACTED 1.00]
- **SMS Async Delivery Architecture Flow** — reference_sms_async_queue_architecture, reference_sms_http_send_contract, reference_sms_capcom6_product, reference_sms_soft_fail_delivery [EXTRACTED 1.00]

## Communities (166 total, 79 thin omitted)

### Community 0 - "Attendance Controllers"
Cohesion: 0.05
Nodes (17): AttendanceController, AuditLogController, LoginController, App\Http\Controllers\Controller, DashboardController, MonetizationController, Collection, ReportController (+9 more)

### Community 1 - "Leave Credits SMS Jobs"
Cohesion: 0.05
Nodes (16): AccrueLeaveCredits, SendSmsCommand, DocumentRequest, SmsQueue, SmsChannel, DocumentRequestIssuedNotification, DocumentRequestRejectedNotification, DocumentRequestSubmittedNotification (+8 more)

### Community 2 - "Employee Leave Models"
Cohesion: 0.05
Nodes (10): Collection, EmploymentType, LeaveApplication, LeaveCreditLedger, LeaveType, LeaveApprovedNotification, LeaveFiledNotification, LeaveRejectedNotification (+2 more)

### Community 3 - "Attendance Admin Actions"
Cohesion: 0.07
Nodes (13): AttendanceAdminController, DocumentRequestController, LeaveController, NotificationController, PayrollController, ProfileController, AttendanceCheckpoint, Audit (+5 more)

### Community 4 - "CSV Import Pipeline"
Cohesion: 0.11
Nodes (4): ImportController, Collection, Response, ImportReader

### Community 5 - "Roles Permissions Seeders"
Cohesion: 0.08
Nodes (11): Role, ContributionRateSeeder, DatabaseSeeder, DevEmployeeSeeder, DivisionsAndPositionsSeeder, EmployeeUserSeeder, EmploymentTypeSeeder, LeaveTypeSeeder (+3 more)

### Community 6 - "Allowance Audit Models"
Cohesion: 0.09
Nodes (8): Allowance, AuditLog, EmployeeAllowance, EmployeeCivilServiceEligibility, EmployeeEducation, Permission, SalaryScale, Illuminate\Database\Eloquent\Model

### Community 8 - "Holiday Work Schedules"
Cohesion: 0.16
Nodes (6): self, Holiday, CarbonInterface, Schedule, Carbon\CarbonInterface, Illuminate\Support\Collection

### Community 10 - "Directory JSON Normalizer"
Cohesion: 0.19
Nodes (15): add_person(), classify_email(), clean(), norm(), norm_contact(), norm_position(), parse_salary(), parse_sg() (+7 more)

### Community 12 - "HRIS Architecture Blueprint"
Cohesion: 0.12
Nodes (18): Append-Only Ledgers Pattern, CSC Omnibus Leave Rules MC 41 s.1998, Effective-Dated Records Pattern, Hostinger VPS KVM2 Production Stack, DICT RO2 HRIS Approved Blueprint v1.0, CSC Service Record Form 212, Single Monolith Laravel Architecture, XAMPP Local Development (+10 more)

### Community 13 - "Document Verification"
Cohesion: 0.16
Nodes (4): DocumentVerificationController, Document, DateTimeInterface, ServiceRecordTest

### Community 14 - "Payroll Core Engine"
Cohesion: 0.23
Nodes (4): Carbon, Payroll, Collection, PayrollTest

### Community 16 - "User Auth Roles"
Cohesion: 0.17
Nodes (5): User, Collection, Illuminate\Database\Eloquent\Factories\HasFactory, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable

### Community 17 - "Frontend App Shell JS"
Cohesion: 0.23
Nodes (13): bindToastLifecycle(), closeSidebar(), dismissToast(), el(), fmt(), isDesktop(), openSidebar(), renderArea() (+5 more)

### Community 18 - "eGovPay UI Redesign"
Cohesion: 0.16
Nodes (15): Dashboard Hero Stat Row Chart Layout, UI Design Tokens Navy Brand Canvas, eGovPay-style Design System Redesign, Deliberately Omitted YearStepper and ModeToggle, Pass I Mobile Responsive UX Retained, Zero Build Step Plain CSS Vanilla JS, eGovPay-style Design System, eGovPay Kit Design Tokens (+7 more)

### Community 19 - "Composer Package Metadata"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 20 - "Composer Scripts"
Cohesion: 0.14
Nodes (14): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+6 more)

### Community 22 - "Checkpoint Work Schedule"
Cohesion: 0.20
Nodes (3): self, WorkSchedule, AttendanceSeeder

### Community 23 - "SMS Gateway Architecture"
Cohesion: 0.20
Nodes (12): In-System Email SMS Notifications, SMS Queue Async Delivery sms:send, HRMIS Event to sms_queue to Gateway Architecture, capcom6 Android SMS Gateway Product, E.164 Phone Normalization Country Code 63, SMS HTTP Send Contract POST JSON, LOKA SmsGateway Reference Client, SMS Mode A Local Phone LAN (+4 more)

### Community 28 - "COE Service Record Controllers"
Cohesion: 0.29
Nodes (3): CoeController, ServiceRecordController, DocumentIssuer

### Community 31 - "AOM Compressed Workweek"
Cohesion: 0.22
Nodes (10): AOM 2026-020 Work Schedule Registry, CTO-Eligible Rest-Day Holiday Punches, 4-Day Compressed Workweek Schedule, AOM No. 2026-007 Supplemental CWW Guidelines, AOM No. 2026-019 Temporary CWW Suspension, AOM No. 2026-020 Resumption of 4-Day CWW, DICT Region II Tuguegarao, Engr. Pinky T. Jimenez Regional Director (+2 more)

### Community 35 - "Division Position Seeders"
Cohesion: 0.22
Nodes (3): Division, Position, RealDirectorySeeder

### Community 37 - "Statutory Compliance Engine"
Cohesion: 0.22
Nodes (9): BIR TRAIN Withholding Brackets, Config-Driven Compliance Engine, GSIS RA 8291 Contribution Rules, PAG-IBIG Circular 460 Rules, Payroll computation_json Audit Trace, PhilHealth UHC Premium Rules, Philippine Statutory Compliance Engine, Phase 6 Payroll Engine (+1 more)

### Community 40 - "Composer Dev Dependencies"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 41 - "Project Setup Scripts"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 45 - "Composer Plugin Config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 46 - "Composer Runtime Dependencies"
Cohesion: 0.29
Nodes (7): require, barryvdh/laravel-dompdf, chillerlan/php-qrcode, laravel/framework, laravel/tinker, php, phpoffice/phpspreadsheet

### Community 47 - "Flexible Work Scheduling Policy"
Cohesion: 0.29
Nodes (7): AOM 2026-020 Flexible Work Scheduling, CSC Res. 2600838 Friday-Revert Rule, Geofenced Time Logging, Phase 5 Attendance and DTR, CSC Resolution No. 2600838 Flexible Work Arrangements, Holiday on Working Day Deemed Complied Rule, Friday Holiday Revert to Standard 8-Hour Week

### Community 48 - "Role Middleware"
Cohesion: 0.47
Nodes (3): RoleMiddleware, Closure, Symfony\Component\HttpFoundation\Response

### Community 49 - "Real Directory Seeder"
Cohesion: 0.40
Nodes (6): cleanup_directory.py Dedupe Email Hygiene, employees_directory.json Uncommitted PII, normalize_to_json.py Tracker Converter, RealDirectorySeeder Graceful Skip, Real DICT RO2 Personnel Directory, RealDirectorySeeder

### Community 51 - "Composer Autoload PSR-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 52 - "Notification Bell Partial"
Cohesion: 0.40
Nodes (4): partials.notification-bell, partials.avatar, partials.icon, partials.toast

### Community 54 - "post create project cmd php"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 55 - "appointments create blade php appointmen"
Cohesion: 0.50
Nodes (3): appointments.partials.form, partials.avatar, partials.breadcrumbs

### Community 56 - "appointments edit blade php appointments"
Cohesion: 0.50
Nodes (3): appointments.partials.form, partials.avatar, partials.breadcrumbs

### Community 57 - "employees index blade php employees"
Cohesion: 0.50
Nodes (3): employees.partials.type_badge, partials.avatar, partials.breadcrumbs

### Community 58 - "employees show blade php employees"
Cohesion: 0.50
Nodes (3): employees.partials.type_badge, partials.avatar, partials.breadcrumbs

### Community 59 - "profile show blade php employees"
Cohesion: 0.50
Nodes (3): employees.partials.type_badge, partials.avatar, partials.breadcrumbs

### Community 60 - "forced leave blade php partials"
Cohesion: 0.50
Nodes (3): partials.avatar, partials.breadcrumbs, partials.report-exports

### Community 63 - "extra laravel dont discover"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **176 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+171 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **79 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Employee` connect `Employee CRUD Controller` to `Attendance Controllers`, `Leave Credits SMS Jobs`, `Employee Leave Models`, `Attendance Admin Actions`, `CSV Import Pipeline`, `Roles Permissions Seeders`, `Allowance Audit Models`, `Appointment Controller`, `Attendance Feature Tests`, `Document Verification`, `Payroll Core Engine`, `Feature Test Suite Files`, `Appointment COE Tests`, `Import Feature Tests`, `Profile Self-Service Tests`, `Reports Feature Tests`, `COE Service Record Controllers`, `Error Pages Tests`, `Division Position Seeders`, `Settings Notifications`, `Employee Pages Smoke Tests`, `Document Verification Tests`, `Contribution Rates Payroll`, `Attendance Log Model`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._
- **Why does `DocumentRequest` connect `Leave Credits SMS Jobs` to `Attendance Controllers`, `Attendance Admin Actions`, `Allowance Audit Models`, `Payslips Document Requests`?**
  _High betweenness centrality (0.025) - this node is a cross-community bridge._
- **Why does `User` connect `User Auth Roles` to `Attendance Controllers`, `Leave Credits SMS Jobs`, `Error Pages Tests`, `Employee Leave Models`, `Payroll Adjustment Tests`, `Roles Permissions Seeders`, `Employee Pages Smoke Tests`, `Document Verification Tests`, `Attendance Feature Tests`, `Contribution Rates Payroll`, `Document Verification`, `Payroll Core Engine`, `Feature Test Suite Files`, `Appointment COE Tests`, `Import Feature Tests`, `Profile Self-Service Tests`, `Reports Feature Tests`, `Remittances SMS Queue`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Are the 48 inferred relationships involving `Employee` (e.g. with `.handle()` and `.index()`) actually correct?**
  _`Employee` has 48 INFERRED edges - model-reasoned connections that need verification._
- **Are the 5 inferred relationships involving `User` (e.g. with `.hrUsers()` and `.run()`) actually correct?**
  _`User` has 5 INFERRED edges - model-reasoned connections that need verification._
- **What connects `$schema`, `name`, `type` to the rest of the system?**
  _176 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Attendance Controllers` be split into smaller, more focused modules?**
  _Cohesion score 0.05476190476190476 - nodes in this community are weakly interconnected._