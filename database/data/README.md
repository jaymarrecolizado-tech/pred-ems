# database/data

`employees_directory.json` (the normalized real personnel directory, ~130 records)
is **not committed** to the repository because it contains employee personal data
(names, birth dates, salaries, addresses, contact details).

Generate it locally from the office tracker before seeding:

```bash
python data/normalize_to_json.py   # reads the tracker xlsx -> database/data/employees_directory.json
php artisan db:seed --class=RealDirectorySeeder
```

`RealDirectorySeeder` skips gracefully when the file is absent, so a fresh clone
still installs cleanly (demo data only).
