#!/usr/bin/env python3
"""
cleanup_directory.py — post-extraction hygiene for employees_directory.json.

Applies the decisions made during the HRIS data cleanup (August 2026):

1. FIX malformed emails copied from the tracker (missing @, missing dot, typo).
2. ADD convention emails (first.last@dict.gov.ph) for active staff with no
   email on file, so every active employee can get a login account.
3. MERGE duplicate records: for people listed in BOTH the directory (active)
   and the Accession & Separation sheet (separated), the separation sheet is
   the formal HR record — we keep the separated record (which carries the
   gov email) and drop the stale active copy. Any position/salary details on
   the dropped copy are copied onto the kept record so history is preserved.
4. DROP simple double-active duplicates (same person, two directory rows).

Run AFTER data/normalize_to_json.py and BEFORE php artisan db:seed:

    python data/normalize_to_json.py   # regenerate from the tracker xlsx
    python data/cleanup_directory.py   # dedupe + fix emails (this script)
    php artisan db:seed --class=RealDirectorySeeder
"""
from __future__ import annotations

import json
import re
import sys
from collections import defaultdict
from pathlib import Path

JSON_PATH = Path(__file__).resolve().parent.parent / "database" / "data" / "employees_directory.json"

# --------------------------------------------------------------------------
# 1. Malformed emails: employee_number -> (field, old, new)
# --------------------------------------------------------------------------
EMAIL_FIXES = {
    "RO2-0184": ("gov_email", "jayson.guisando.dict.gov.ph", "jayson.guisando@dict.gov.ph"),      # missing @
    "RO2-0137": ("gov_email", "oseph.rosal@dict.gov.ph", "joseph.rosal@dict.gov.ph"),              # typo, missing "j"
    "RO2-0170": ("personal_email", "christopher.capili@dictgov.ph", "christopher.capili@dict.gov.ph"),  # missing dot
}

# --------------------------------------------------------------------------
# 2. Convention emails for active staff with no email on file.
#    first.last@dict.gov.ph — VERIFY with HR before rollout.
# --------------------------------------------------------------------------
CONVENTION_EMAILS = {
    "RO2-0136": "jeoy.laverinto@dict.gov.ph",
    "RO2-0191": "rica.carungi@dict.gov.ph",
    "RO2-0196": "edward.argonza@dict.gov.ph",
    "RO2-0201": "jonathan.delrosario@dict.gov.ph",
    "RO2-0216": "ivan.santos@dict.gov.ph",
}

# --------------------------------------------------------------------------
# 3. Merge pairs: keep separated record, absorb position/salary details from
#    the stale active copy/copies, then drop the active copies.
# --------------------------------------------------------------------------
MERGES = {
    "RO2-0192": ["RO2-0215"],  # kept record absorbs position from the active copy
    "RO2-0193": ["RO2-0198"],
    "RO2-0194": ["RO2-0202"],
    "RO2-0206": ["RO2-0205"],
    "RO2-0209": ["RO2-0208"],
    "RO2-0213": ["RO2-0211", "RO2-0212"],  # two stale active copies
    "RO2-0218": ["RO2-0217"],
    "RO2-0220": ["RO2-0219"],
}

# --------------------------------------------------------------------------
# 4. Simple double-active duplicates: keep the record listed here, drop the
#    other(s). RO2-0149 Catinoy (Engineer III, COS, full 201 data + email)
#    wins over RO2-0200 (stale Job Order copy, Planning Officer II).
# --------------------------------------------------------------------------
KEEP_SINGLES = {
    "RO2-0149": ["RO2-0200"],  # keeps the complete COS record with email/201 data
}

# Fields worth carrying over from a dropped copy onto the kept record.
MERGE_FIELDS = [
    "middle_name", "position_title", "salary_grade", "step", "monthly_salary",
    "division_code", "source_of_fund", "date_original_appointment",
    "gender", "birth_date",
]


def norm_name(value: str | None) -> str:
    return re.sub(r"\s+", " ", (value or "").strip()).lower()


def main() -> int:
    data = json.loads(JSON_PATH.read_text(encoding="utf-8"))
    by_number = {r["employee_number"]: r for r in data}
    changes: list[str] = []

    # --- 1. email fixes ----------------------------------------------------
    for number, (field, old, new) in EMAIL_FIXES.items():
        rec = by_number.get(number)
        if rec is None:
            changes.append(f"  ! EMAIL FIX target {number} not found")
            continue
        if rec.get(field) == new:
            changes.append(f"  email {number}: already {new}")
        elif rec.get(field) == old:
            rec[field] = new
            changes.append(f"  email {number}: '{old}' -> '{new}'")
        else:
            changes.append(f"  ! email {number}: unexpected value '{rec.get(field)}' (expected '{old}')")

    # --- 2. convention emails ----------------------------------------------
    for number, email in CONVENTION_EMAILS.items():
        rec = by_number.get(number)
        if rec is None:
            changes.append(f"  ! CONVENTION target {number} not found")
            continue
        if rec.get("gov_email") == email:
            changes.append(f"  email {number}: already {email}")
        else:
            rec["gov_email"] = email
            changes.append(f"  email {number}: +{email} (convention — verify)")

    # --- 3. merge pairs -----------------------------------------------------
    for keep_number, drop_numbers in MERGES.items():
        keep = by_number.get(keep_number)
        if keep is None:
            changes.append(f"  ! MERGE keep {keep_number} not found")
            continue
        drops = [by_number[n] for n in drop_numbers if by_number.get(n)]
        for field in MERGE_FIELDS:
            if not keep.get(field):
                for drop in drops:
                    if drop.get(field):
                        keep[field] = drop[field]
                        break
        for n in drop_numbers:
            if by_number.pop(n, None):
                changes.append(f"  merge {n} -> {keep_number} (kept separated record, absorbed position details)")
            else:
                changes.append(f"  ! merge {n} not found (nothing to drop)")

    # --- 4. simple duplicates ----------------------------------------------
    for keep_number, drop_numbers in KEEP_SINGLES.items():
        for n in drop_numbers:
            if by_number.pop(n, None):
                changes.append(f"  drop {n} (duplicate of {keep_number})")
            else:
                changes.append(f"  ! drop {n} not found")

    # --- save (rebuild the list from the lookup dict so dropped records are gone) ---
    data = list(by_number.values())
    JSON_PATH.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    # --- verify: no duplicate (last|first) pairs remain -----------------------
    groups: dict[str, list[str]] = defaultdict(list)
    for r in data:
        groups[f"{norm_name(r.get('last_name'))}|{norm_name(r.get('first_name'))}"].append(r["employee_number"])

    print("=== applied changes ===")
    for line in changes:
        print(line)

    print(f"\n=== result: {len(data)} records ===")
    leftovers = {k: v for k, v in groups.items() if len(v) > 1}
    if leftovers:
        print("REMAINING duplicate last|first groups (review manually):")
        for k, v in sorted(leftovers.items()):
            print(f"  {k}: {v}")
    else:
        print("No duplicate last|first groups remain.")

    print(f"\nWrote {JSON_PATH}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
