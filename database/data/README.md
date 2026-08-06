# database/data

`employees_directory.json` (the normalized real personnel directory, ~130 records)
is **not committed** to the repository because it contains employee personal data
(names, birth dates, salaries, addresses, contact details).

Generate it locally from the office tracker before seeding:

```bash
python data/normalize_to_json.py    # reads the tracker xlsx -> database/data/employees_directory.json
python data/cleanup_directory.py    # dedupe + fix/add emails (idempotent)
php artisan db:seed --class=RealDirectorySeeder
```

`cleanup_directory.py` merges records where the same person appears in both the
directory and the Accession & Separation sheet (the formal separation record
wins, absorbing the last position/salary details), drops double-active
duplicates, repairs malformed emails, and adds convention emails
(`first.last@dict.gov.ph`) so every active employee can log in.

`RealDirectorySeeder` skips gracefully when the file is absent, so a fresh clone
still installs cleanly (demo data only).
