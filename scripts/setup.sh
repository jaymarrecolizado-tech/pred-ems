#!/usr/bin/env bash
# ============================================================
# DICT RO2 HRIS — Phase 1 setup script
# Runs in Git Bash (Windows) or Linux/macOS.
#
# Usage:
#   bash scripts/setup.sh [app-name]        # default: hris
#
# Prerequisites:
#   - PHP 8.2+ and Composer on PATH (XAMPP php + composer)
#   - MySQL running (XAMPP) — database is created automatically
# ============================================================
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_NAME="${1:-hris}"
TARGET="$(pwd)/${APP_NAME}"

command -v composer >/dev/null 2>&1 || { echo "✖ Composer not found. Install from https://getcomposer.org"; exit 1; }
command -v php >/dev/null 2>&1 || { echo "✖ PHP not found. Ensure XAMPP PHP (8.2+) is on your PATH."; exit 1; }
PHP_MAJOR="$(php -r 'echo PHP_MAJOR_VERSION;')"
PHP_MINOR="$(php -r 'echo PHP_MINOR_VERSION;')"
if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]; }; then
    echo "✖ PHP 8.2+ required (found $PHP_MAJOR.$PHP_MINOR)."
    exit 1
fi

[ -d "$TARGET" ] && { echo "✖ Target directory '$TARGET' already exists. Choose another name."; exit 1; }

echo "==> 1/5 Scaffolding Laravel → $TARGET"
composer create-project laravel/laravel "$TARGET" --no-interaction

echo "==> 2/5 Copying HRIS layers (models, HTTP, routes, views, database, docs)"
# Merge-copy contents (src/. -> dest/) so existing scaffold dirs are never
# nested (portable across Git Bash / Linux cp variants).
mkdir -p "$TARGET/app/Models" "$TARGET/app/Http" "$TARGET/resources/views" \
         "$TARGET/public/css"  "$TARGET/database" "$TARGET/docs"
cp -r "$REPO_DIR/app/Models/."      "$TARGET/app/Models/"
cp -r "$REPO_DIR/app/Http/."        "$TARGET/app/Http/"
cp    "$REPO_DIR/routes/web.php"    "$TARGET/routes/web.php"
cp    "$REPO_DIR/bootstrap/app.php" "$TARGET/bootstrap/app.php"
cp -r "$REPO_DIR/resources/views/." "$TARGET/resources/views/"
cp -r "$REPO_DIR/public/css/."      "$TARGET/public/css/"
cp -r "$REPO_DIR/database/."        "$TARGET/database/"
cp -r "$REPO_DIR/docs/."            "$TARGET/docs/"

echo "==> 3/5 Configuring .env (database: $APP_NAME)"
cd "$TARGET"
sed -i "s/^APP_NAME=.*/APP_NAME=\"DICT RO2 HRIS\"/" .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$APP_NAME/" .env
[ -f .env.example ] && cp .env.example .env.bak.example

echo "==> 4/5 Creating database and running migrations + seeders"
# Try to create the DB via MySQL CLI (XAMPP default: root, no password)
if command -v mysql >/dev/null 2>&1; then
    mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`${APP_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || echo "  (could not auto-create DB — create '${APP_NAME}' manually in phpMyAdmin)"
else
    echo "  (mysql CLI not found — create database '${APP_NAME}' manually in phpMyAdmin)"
fi

php artisan migrate --seed --force

# Link the public storage disk so uploaded profile photos are served at /storage/*
php artisan storage:link || echo "  (storage link exists or could not be created — run 'php artisan storage:link' manually)"

echo "==> 5/5 Done!"
echo ""
echo "  Start the app:  cd $(pwd) && php artisan serve"
echo "  Open:           http://localhost:8000"
echo ""
echo "  Demo accounts (password: 'password'):"
echo "    admin@dictro2.gov.ph   hr@dictro2.gov.ph"
echo "    payroll@dictro2.gov.ph head@dictro2.gov.ph  juan@dictro2.gov.ph"
