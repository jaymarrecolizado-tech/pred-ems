# DICT RO2 — Employee Management System (HRIS)

Full-blown HR information system for **DICT Regional Office 2**: employee 201-file profiles,
appointments, leave management (CSC rules), payroll with Philippine statutory deductions
(GSIS/PhilHealth/PAG-IBIG/BIR), official documents (Service Record, COE), and remittance reports.

- **Framework:** Laravel 11/12 (PHP 8.2+)
- **Database:** MySQL 8 / MariaDB
- **Frontend:** Blade + Tailwind + Alpine.js (+ Livewire 3 for interactive forms)
- See [`docs/PLAN.md`](docs/PLAN.md) for the full blueprint (architecture, ERD, roadmap).

---

## 🧱 Repository layoutThis repository ships the **Phase 1 application + data layer + blueprint** for a fresh Laravel project:

```
docs/PLAN.md                            ← full technical blueprint (ERD, roadmap, deployment)
database/migrations/                    ← 21 migrations: RBAC → employees → leave → payroll
database/seeders/                       ← roles, employment types, leave types, contribution rates, dev data, real directory
database/data/README.md                 ← how to regenerate the personnel directory locally
data/normalize_to_json.py               ← tracker xlsx → JSON converter (Python, run locally)
data/cleanup_directory.py               ← dedupe + email hygiene pass on the JSON (Python, run locally)

> 🔒 Employee personal data (tracker xlsx + generated `employees_directory.json`) is
> **not committed** — generate it locally before seeding (see `database/data/README.md`).
app/Models/                             ← Eloquent models (User, Role, Employee, …) + RBAC helpers
app/Http/Controllers/Auth/              ← custom session login/logout (no Breeze dependency)
app/Http/Controllers/                   ← DashboardController, EmployeeController
app/Http/Middleware/RoleMiddleware.php  ← role-guard middleware
routes/web.php · bootstrap/app.php      ← Phase 1 routes + role middleware alias
resources/views/                        ← Blade views: login, dashboard, employee CRUD
public/css/app.css                      ← zero-build-step stylesheet
scripts/setup.sh                        ← one-command XAMPP setup
```

**Phase 1 scope (implemented):** login, RBAC (admin/hr/payroll/unit_head/employee), dashboard with headcount stats, and full employee profile management — list with search + employment-type/status filters, add/edit, profile view with appointment history and leave balances, auto employee-number generation (`RO2-XXXX`).

**Self-service (implemented):**

- **My Profile** — every logged-in user gets a personal profile area (sidebar + topbar avatar). Employees linked to a 201-file record can **edit their own personal information** (name, birthdate, civil status, contact details, gov ID numbers). Employment data (position, salary, grade, status) stays HR-managed.
- **Profile photos** — upload/remove your photo (JPG/PNG/WebP, max 2 MB) from the profile page; HR can also set photos on the employee edit form. Photos are stored on the public disk (`storage/app/public/photos/`) — run `php artisan storage:link` so they're served at `/storage/…` (the setup script does this automatically). Avatars appear everywhere: sidebar, topbar, employee list, dashboard, and profile headers.
- **Change own password** — self-service password update with current-password verification (min 8 chars).
- **Audit trail viewer** — every create/update/delete of an employee record, profile self-edit, photo change, and password change is appended to `audit_logs` with actor, IP, and old→new value diffs. Admin/HR can browse and filter the trail at `/audit-logs` (searchable by actor, record id, IP, and action type).

**Documents & service history — Phase 3 (implemented):**

- **Appointment Manager** — admin/HR maintain each employee's chronological, effective-dated appointment history (`/employees/{id}/appointments/create`): original appointment, promotions, transfers, re-appointments, step increments. Every change re-syncs the employee's current-position snapshot; the timeline is the source of truth for the Service Record.
- **Service Record (CS Form 212)** — official CSC-format PDF/HTML preview (`Service Record` button on any profile): DICT letterhead + seal, personal info grid (incl. maiden name + birth date/place), certification paragraph, appointment table (service / record of appointment / separation / remarks), "Nothing Follows" row, EO 54 footer, and prepared-by/certifier signature blocks resolved from the actual plantilla. Sequential `SR-2026-XXXX` reference numbers.
- **Certificate of Employment (COE)** — letter-style PDF/HTML preview (`COE` button on any profile) certifying employment period, position, and division, with `COE-2026-XXXX` reference numbers.
- **Issuance ledger** — every generated document is recorded in the `documents` table (employee, type, reference no., issued by, timestamp) for a clean audit trail; PDFs are dompdf-safe (table-only layout).

**Document requests — Phase 3.5 (implemented):**

- **My Documents** (`/documents/requests`) — every employee with a 201-file can request an official document: **Certificate of Employment**, **Service Record (CSC Form 212)**, **Certificate of Leave Balances** (live VL/SL/other balances as of issuance), **Certification of No Pending Case**, or **Daily Time Record (CSC Form 48)** (per month). A purpose is required; duplicate pending requests are blocked.
- **HR fulfillment queue** (`/documents/requests/queue`, admin/HR) — review pending requests, **Issue** (mints the reference number — `COE-`/`SR-`/`LB-`/`NPC-`/`DTR-` — generates the PDF, and records the issuance in the `documents` ledger) or **Reject** with a reason. Status filter across pending / issued / rejected / canceled.
- **Official copies** — once issued, the employee downloads the PDF with its reference number (regenerated deterministically); the documents-issued report now filters all document types.

**Attendance & DTR — Phase 5 (implemented):**

- **Geofenced time logging** — admin/HR plot attendance **checkpoints** on a Leaflet + OpenStreetMap map (HQ + the 5 provincial offices seeded), each with a punch radius. Employees punch via their phone/desktop browser; the system verifies the device GPS position **server-side** against the nearest checkpoint and rejects punches outside the radius (with a friendly message naming the checkpoint).
- **AM/PM in/out** — the punch button follows the physical bundy-clock sequence (AM in → AM out → PM in → PM out) and even skips to PM in when someone arrives after lunch.
- **Corrections must go to HR** — timelogs are **append-only**; any alteration is submitted as a correction request and approved/rejected by HR (the only path that changes a punch). HR can also enter **manual punches** for field duty, forgotten punches, or GPS failure.
- **CSC Form 48 DTR** — auto-generated monthly Daily Time Record: full day grid with AM/PM in-out times, hours rendered, late/undertime vs. the schedule in effect for each day, and totals. HTML preview + dompdf PDF with `DTR-2026-XXXX` reference numbers tracked in `documents`.
- **Flexible scheduling (AOM No. 2026-020)** — Attendance Settings is now an effective-dated **work-schedule registry** + **holiday calendar**: named schedules with per-day-of-week work/rest and AM/PM times (seeded: **4-Day Compressed Workweek** Mon–Thu 7AM–6PM / Friday rest, and **Standard 8-Hour** Mon–Fri 8AM–5PM). The **CSC Res. 2600838 Friday-revert rule** is enforced — a holiday on a weekday rest day reverts the whole week to standard hours; holidays on working days are flagged deemed-complied. Rest-day/holiday/weekend punches are **never blocked** and their hours count as **overtime (CTO-eligible)** — the timelog evidence employees need to claim Compensatory Time-Off.
- **Audited** — punches, corrections, approvals, checkpoint changes, schedule/holiday changes, and manual entries all land in `audit_logs`.

**Payroll — Phase 6 (implemented):**

- **Computation engine** — `App\Support\Payroll` computes each employee's run from **effective-dated rate rows** (`contribution_rates`): GSIS 9%/12% on basic salary, PhilHealth 5% premium (₱500 floor / ₱5,000 cap, split 50/50), PAG-IBIG 2%/2% capped at ₱200, and BIR TRAIN brackets annualized (PERA exempt). Annual rate changes are data edits — never code changes.
- **Payroll periods** (`/payroll`, admin/HR/payroll) — create a period (duplicate dates rejected), **Compute Payroll** (one item per active employee with PERA from their allowance rows), then **Finalize & Issue Payslips** (locks the period against edits, issues a `PS-YYYY-NNNN` payslip per item, and generates GSIS/PhilHealth/PAG-IBIG/BIR remittance summaries).
- **Full audit trace** — every item persists its computation trace (bases, rates, brackets, notes) in `computation_json`, so any payslip can be explained years later.
- **Payslip PDFs** — dompdf-safe, DICT letterhead, earnings/deductions tables, net pay, and the computation-trace table; `PS-2026-XXXX` reference numbers.
- **Remittance register** (`/payroll/remittances`) — per-agency summaries per period with pending → remitted lifecycle (record the OR/batch reference number).
- **Self-service payslips** — every employee sees **My Payslips** (`/my/payslips`): their own finalized payslips with PDF download; accessing anyone else's payslip is forbidden.
- **RBAC** — the payroll role can run payroll but cannot see the audit trail or manage employees.

> ⚠️ Known limits: honoraria/overtime/LWOP lines exist in the schema and engine but have no per-item entry UI yet; `salary_scales` holds sample placeholder amounts until the official SSL table is imported.

**Reports & Audit — Phase 4 (implemented):**

- **Reports hub** (`/reports`, admin/HR) — a stat overview (total/active/separated, leave cardholders, documents issued) plus five reports, each with **CSV export** (`?format=csv`, UTF-8 BOM, formula-injection safe):
  - **Headcount** — employees grouped by employment type, division, status, or source of fund, with a status filter and percentage bars
  - **Leave balances** — VL/SL balances for every active employee (one grouped ledger query)
  - **Leave utilization** — approved applications, employees, and days taken per leave type per year
  - **Documents issued** — every Service Record / COE with reference number, issuer, and timestamp
  - **Attrition & onboarding** — separations and new hires per year

**Leave management — Phase 2 (implemented):**

- **My Leave** — every employee linked to a 201-file record gets a personal leave page (`/leave`): a live **leave card** of balances (VL/SL and all CSC types, derived from the append-only ledger), plus their full application history.
- **File leave** — working-day (Mon–Fri) computation between selected dates, per-type balance hints, and an insufficient-balance guard for accrual leaves (VL/SL).
- **Approval workflow** — admin/HR review the queue at `/leave/approvals` (filter by status/search), with **Approve** (auto-debits the ledger via a `used` entry, keeping the balance derived and immutable) and **Reject** (records a reason).
- **Monthly accruals** — `php artisan leave:accrue` accrues VL/SL at 1.25 days/month from each employee's original appointment date (idempotent, append-only), scheduled via `routes/console.php` for the 1st of each month. **15,196 credit entries** seeded for the 33 leave-entitled active staff.
- **Audited** — filing, approval, rejection, and cancellation are all appended to `audit_logs`.

**UI & UX (implemented):**

- **eGovPay-style design system** — dark navy sidebar (`#1B2A4A`), blue-600 primary actions, light-gray canvas, white rounded cards, pill badges, tracked uppercase table headers, Inter + Be Vietnam Pro type, circular deterministic avatars, and a rebuilt dashboard (hero banner, stat-row panel, vanilla-SVG headcount chart with table/area/bar view toggle). See [`docs/UI_REDESIGN.md`](docs/UI_REDESIGN.md). Delivered on the `ui-improvements` branch.
- **Mobile responsive + modern app UX** — off-canvas sidebar drawer (hamburger toggle, backdrop, Escape close) via `public/js/app.js`, breakpoints for tablet/phone/small-phone, 44px touch targets, 16px mobile inputs (no iOS zoom), `:focus-visible` rings, skip-link, `aria-current`/`aria-expanded`, `theme-color`, styled scrollbars, `prefers-reduced-motion`, a print stylesheet, and "swipe to see more" hints on overflowing tables.

> To install Phase 1 in one command, see **Quick start** below.

---

## 📇 Real personnel directory (from the tracker)

The employee module is pre-loaded with the **real DICT RO2 personnel directory**
(120 employees after deduplication: plantilla, co-terminus, COS, job order, and
GIP) extracted from `data/REGION2 EMPLOYEES DIRECTORY.xlsx` by
`data/normalize_to_json.py`, deduped/cleaned by `data/cleanup_directory.py`, and
stored as clean JSON in `database/data/employees_directory.json`.

It runs automatically with `php artisan migrate --seed`, or standalone:

```bash
php artisan db:seed --class=RealDirectorySeeder
```

What it loads per employee: name, gender, birthdate, contact, gov/personal email,
address, position, SG/step, salary, plantilla item no., GSIS BP no., source of
fund, original appointment date (+ an appointment record that seeds the future
Service Record), and GIP education/eligibility (201-file qualifications).

It also seeds the **real divisions** (Regional Office 2 + Office of the Regional
Director, AFD, TOD, and the 5 provincial offices) and **40 real position titles**
with their plantilla grades, replacing the sample starter data.

> ⚠️ Re-running `RealDirectorySeeder` refreshes imported records from the JSON —
> the tracker is treated as the source of truth for imported data.

> 🧹 **Data hygiene:** the extraction pipeline is `normalize_to_json.py` →
> `cleanup_directory.py` → `RealDirectorySeeder`. The cleanup pass merges people
> listed in both the directory and the Accession & Separation sheet (the
> separation record wins), drops double-active duplicates, fixes malformed
> emails, and adds convention emails (`first.last@dict.gov.ph`) for the few
> active staff with no email on file — so every active employee has a login.

---

## 💻 Local development (XAMPP on Windows)

### Prerequisites
- **XAMPP** with **PHP 8.2+** and **MySQL** (Apache must be running)
- **Composer** (https://getcomposer.org)
- Git Bash (comes with Git for Windows)

### Quick start (one command)
From this repo's folder, in Git Bash (make sure `php` is on your PATH — add `C:\xampp\php` to PATH if needed):

```bash
bash scripts/setup.sh hris
```

This scaffolds Laravel, copies all Phase 1 files, creates the `hris` database, and runs `migrate --seed`. Then:

```bash
cd hris && php artisan serve   # → http://localhost:8000
```

### Manual setup (alternative)

1. **Scaffold** (from your `htdocs` folder):
   ```bash
   composer create-project laravel/laravel hris
   cd hris
   ```
2. **Copy this repo's files** into the project (git bash, from the repo root):
   ```bash
   cp -r app/Models ../hris/app/
   cp -r app/Http ../hris/app/
   cp routes/web.php ../hris/routes/web.php
   cp bootstrap/app.php ../hris/bootstrap/app.php
   cp -r resources/views ../hris/resources/views
   cp -r public/css ../hris/public/
   cp -r database ../hris/database
   cp -r docs ../hris/docs
   ```
3. **Configure `.env`**: `APP_NAME="DICT RO2 HRIS"`, `DB_DATABASE=hris` (create the `hris` database in phpMyAdmin).
4. **Migrate & seed**:
   ```bash
   composer install && php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```
5. Open **http://localhost:8000**
### Demo logins (dev seed data)
| Role | Email | Password |
|---|---|---|
| Admin | `admin@dictro2.gov.ph` | `!Password123` |
| HR | `hr@dictro2.gov.ph` | `!Password123` |
| Payroll | `payroll@dictro2.gov.ph` | `!Password123` |
| Unit Head | `head@dictro2.gov.ph` | `!Password123` |
| Employee | `juan@dictro2.gov.ph` | `!Password123` |

> ⚠️ Dev seeders (`DevEmployeeSeeder`, `EmployeeUserSeeder`) are automatically skipped in `production` environment.

#### Employee accounts (auto-provisioned)

`EmployeeUserSeeder` creates a login account for **every active employee that has
an email on file** (102 accounts): username = their email, default password
`!Password123`. Roles are derived from position: Director IV → `admin`,
Director III → `unit_head`, CAO / HRMO II / AO II (HRMO I) → `hr`, everyone else
→ `employee`. Employees without an email are skipped (reported during seeding).

RBAC is enforced on every route: staff roles (admin/hr/payroll/unit_head) see the
employee directory; the `employee` role can only view their own profile; create/
edit/delete is admin/HR only. Sidebar navigation adapts to the signed-in role.
All roles can access **My Profile** (self-service); **Audit Trail** is admin/HR only.

---

## ☁️ Production deployment (Hostinger VPS KVM2)

Specs used: Ubuntu 24.04 · Nginx · PHP 8.2-FPM · MariaDB · Let's Encrypt
(2 vCPU / 8 GB RAM / 100 GB NVMe — plenty of headroom for 100–500 users.)

1. **Provision VPS** → install Ubuntu 24.04 template.
2. **Install stack:**
   ```bash
   sudo apt update && sudo apt install -y nginx php8.3-fpm php8.3-mysql \
     php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-bcmath \
     mariadb-server composer unzip
   ```
3. **Set up MySQL** database + dedicated user (not root).
4. **Deploy code:** clone the private Git repo to `/var/www/hris`, run
   `composer install --no-dev`, copy `.env.example` → `.env` with production
   values, `php artisan key:generate`, `php artisan migrate --seed --force` (or
   `--class=` without dev seeders).
5. **Nginx site config** → point to `public/`, PHP-FPM socket, Let's Encrypt
   (`sudo certbot --nginx`).
6. **Scheduler & queue:**
   ```bash
   echo "* * * * * cd /var/www/hris && php artisan schedule:run >> /dev/null 2>&1" | crontab -
   ```
7. **Hardening:** UFW (allow 22, 80, 443), fail2ban, OPcache enabled,
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`.
8. **Backups (non-negotiable):** nightly `mysqldump` + `storage/` snapshot to
   off-server storage; test a restore at least once a month.

---

## 🧪 Validation commands

```bash
php artisan migrate:fresh --seed   # rebuild DB with seed data (dev only)
php artisan route:list             # confirm routes
php -l database/migrations/2026_08_04_000006_create_employees_table.php  # lint a file
```

---

## 🗺️ Roadmap

1. **Phase 1 — Foundation:** ✅ auth/RBAC, employee profiles, self-service, audit trail
2. **UI Redesign:** ✅ eGovPay-style design system + mobile responsive — on branch `ui-improvements`
3. **Phase 2 — Leave:** ✅ accruals (`leave:accrue`), filing, approvals, leave cards
4. **Phase 3 — Documents:** ✅ Service Record (CSC Form 212), Certificate of Employment, and the **Appointment Manager** (HR-managed service history that drives both PDFs)
5. **Phase 4 — Reports & Audit:** ✅ reporting hub — headcount, leave balances/utilization, documents issued, attrition & onboarding — with CSV export (audit viewer ✅ done)
6. **Phase 5 — Attendance & DTR:** ✅ geofenced punch-in/out with map-plotted checkpoints, HR-approved correction workflow, CSC Form 48 DTR PDFs, and flexible AOM 2026-020 work scheduling (CWW + holidays + CSC Friday-revert rule, CTO-ready punches) — imports/notifications still stretch
6.5. **Phase 3.5 — Document Requests:** ✅ self-service request workflow (COE, Service Record, Leave Balances, No Pending Case, DTR) with an HR fulfillment queue — issue mints references + ledger entries, reject with reason, status tracking, official PDF downloads
7. **Phase 6 — Payroll:** ✅ config-driven contribution/tax engine, payroll periods (create → compute → finalize), dompdf payslips with computation traces, remittance register, and employee self-service payslips — built last so the appointment/leave/document backbone was solid first (honoraria/overtime entry UI + official SSL table still open)

See `docs/PLAN.md` for details.
