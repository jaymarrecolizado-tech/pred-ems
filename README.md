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

1. **Phase 1 — Foundation:** auth/RBAC, employee profiles, employment types (this data layer)
2. **Phase 2 — Leave:** accruals, applications, approvals, leave cards
3. **Phase 3 — Documents:** Service Record, COE (PDF via dompdf)
4. **Phase 4 — Payroll:** salary scales, contribution engine, payslips
5. **Phase 5 — Reports & Audit:** dashboards, headcount/remittance reports, audit viewer
6. **Phase 6 — Extras:** attendance/DTR, spreadsheet imports, notifications

See `docs/PLAN.md` for details.
