"""Normalize the DICT RO2 Employees Directory xlsx into clean JSON.

Sources (priority order, dedup by normalized full name):
  1. 'Region 02-SG (2026)-'          -> Plantilla/Regular + COS sections (~97)
  2. 'Region 02-SG (2026)- 2nd Sem'  -> gap-filler for position/SG/salary
  3. 'Employees Address'             -> residential addresses
  4. 'JO employees PER PROJECT (as of' + 'Sheet49' -> Job Order (~43)
  5. 'GIP 2026'                      -> interns (~10)
  6. 'Accession and Separation'      -> separated personnel
Place-of-assignment lookups: 'DICT Personnel' (organic), 'Project (COS)' (COS).

Output: database/data/employees_directory.json (clean, idempotent import payload)
"""
import json
import os
import re
from collections import OrderedDict

XLSX = r"C:\Users\jayre\Desktop\upgrade\data\REGION2 EMPLOYEES DIRECTORY.xlsx"
OUT = r"C:\Users\jayre\Desktop\upgrade\database\data\employees_directory.json"

import openpyxl
wb = openpyxl.load_workbook(XLSX, data_only=True, read_only=True)

def rows(name):
    return list(wb[name].iter_rows(values_only=True))

def clean(v):
    if v is None:
        return ""
    if isinstance(v, float) and v.is_integer():
        return str(int(v))
    return str(v).strip()

def norm(s):
    """Normalized key for matching: uppercase, collapse whitespace, fix mojibake."""
    s = s.replace("\ufffd", "Ñ")
    return re.sub(r"\s+", " ", s.upper()).strip()

def to_date(v):
    """Excel serial / datetime / string -> 'YYYY-MM-DD' or ''."""
    from datetime import datetime, timedelta
    if v is None:
        return ""
    if isinstance(v, datetime):
        return v.strftime("%Y-%m-%d")
    if isinstance(v, float) or isinstance(v, int):
        try:
            return (datetime(1899, 12, 30) + timedelta(days=int(v))).strftime("%Y-%m-%d")
        except Exception:
            return ""
    s = clean(v)
    m = re.match(r"(\d{4})-(\d{2})-(\d{2})", s)
    if m:
        return f"{m.group(1)}-{m.group(2)}-{m.group(3)}"
    m = re.match(r"(\d{1,2})/(\d{1,2})/(\d{4})", s)
    if m:
        return f"{m.group(3)}-{int(m.group(1)):02d}-{int(m.group(2)):02d}"
    return ""

def parse_sg(s):
    """'22-1' -> (22, 1); garbage/dates -> (None, None)."""
    m = re.match(r"^\s*(\d{1,2})\s*[-–]\s*(\d{1,2})\s*$", clean(s))
    if not m:
        return None, None
    g, st = int(m.group(1)), int(m.group(2))
    return (g, st) if 1 <= g <= 33 and 1 <= st <= 8 else (None, None)

def parse_salary(v):
    s = clean(v).replace(",", "")
    try:
        f = float(s)
        return round(f, 2) if f > 0 else None
    except ValueError:
        return None

SUFFIXES = ("JR", "SR", "II", "III", "IV")

def split_name(last, first, middle=""):
    """Title-case names, extract suffix from first name, fix mojibake."""
    def title(s):
        s = s.replace("\ufffd", "Ñ")
        words = s.strip().split()
        out = []
        for w in words:
            w = w.lower()
            if w in {"ma.", "de", "del", "san", "santo", "sta.", "mc", "van", "von", "doña", "doña"}:
                out.append(w.capitalize())
            elif w in {"delos", "dela"}:
                out.append(w.capitalize())
            else:
                out.append(w.capitalize())
        return " ".join(out)

    last = title(clean(last))
    first = title(clean(first))
    middle = title(clean(middle))
    suffix = ""

    # Extract suffix from the END of the first name (e.g. "CIRILO JR.")
    m = re.match(r"^(.*?)\s+(" + "|".join(SUFFIXES) + r")\.?$", first.upper())
    if m:
        first = title(m.group(1))
        suffix = m.group(2).capitalize()
    # Suffix at the END of the last name field (e.g. "ATAL JR." in full names)
    m = re.match(r"^(.*?)\s+(" + "|".join(SUFFIXES) + r")\.?$", last.upper())
    if m and not suffix:
        last = title(m.group(1))
        suffix = m.group(2).capitalize()

    # If the middle name itself is a bare suffix, treat as suffix
    if middle.upper() in SUFFIXES and not suffix:
        suffix = middle.capitalize()
        middle = ""

    return first, middle, last, suffix

def norm_contact(v):
    parts = []
    for chunk in re.split(r"[/,]", clean(v)):
        c = re.sub(r"\D", "", chunk)
        if len(c) == 10 and c.startswith("9"):
            c = "0" + c
        if len(c) >= 7:
            parts.append(c)
    return " / ".join(parts) if parts else clean(v)

def classify_email(email):
    e = clean(email).lower()
    if not e:
        return "", ""
    if "dict.gov.ph" in e:
        return e, ""
    return "", e

POSITION_NORMALIZE = {
    "ITO II": "Information Technology Officer II",
    "ITO I": "Information Technology Officer I",
    "ITO III": "Information Technology Officer III",
    "ENGR III": "Engineer III",
    "CAO": "Chief Administrative Officer",
    "ISA I": "Information Systems Analyst I",
    "ECET I": "Electronic Communication Equipment Technician I",
    "CEO III": "Communication Equipment Operator III",
    "CEO II": "Communication Equipment Operator II",
    "MPO II": "Media Production Officer II",
    "HRMO II": "Human Resource Management Officer II",
    "Engineeer I": "Engineer I",
    "Compr Maint. Technologist II": "Computer Maintenance Technologist II",
    "PDO 1": "Project Development Officer I",
    "PDO 2": "Project Development Officer II",
    "PLO 1": "Planning Officer I",
    "PLO 2": "Planning Officer II",
    "PLA": "Project Assistant I",
    "PLA I": "Project Assistant I",
    "Admin Aide IV/Driver II": "Administrative Aide IV (Driver II)",
    "ECET I (detailed)": "Electronic Communication Equipment Technician I",
}

DETAILED_POSITIONS = {"ECET I (detailed)"}

def norm_position(pos):
    p = clean(pos)
    if p in POSITION_NORMALIZE:
        return POSITION_NORMALIZE[p]
    p = re.sub(r"\s+", " ", p).strip()
    p = p.replace("Information System Analyst", "Information Systems Analyst").replace("Information Sys Analyst", "Information Systems Analyst")
    p = p.replace("Admin. Assistant", "Administrative Assistant")
    # strip designations in parentheses from admin titles (keep simple title)
    if p.startswith("Administrative Assistant"):
        p = re.sub(r"\s*\(.*?\)", "", p).strip()
    return p

# ---------------------------------------------------------------- master sheet
master = rows("Region 02-SG (2026)-")
second_sem = rows("Region 02-SG (2026)- 2nd Sem")
addresses = rows("Employees Address")

# gap-filler map: normname -> (position, sg, salary) from 2nd Sem sheet
gap = {}
for r in second_sem[5:]:
    key = norm(clean(r[4]) + "|" + clean(r[5]) + "|" + clean(r[6]))
    if not key or key == "|":
        continue
    gap[key] = (norm_position(r[1]), parse_sg(r[2]), parse_salary(r[3]))

# address map: normname -> address
addr = {}
for r in addresses[5:]:
    key = norm(clean(r[4]) + "|" + clean(r[5]) + "|" + clean(r[6]))
    if key and key != "|":
        addr[key] = clean(r[9])

people = OrderedDict()  # normname -> record

def add_person(last, first, middle, position, sg, salary, gender, birth, contact,
               email, project, item, bp, date_app, kind, remarks=""):
    fn, mn, ln, suffix = split_name(last, first, middle)
    key = norm(f"{ln}|{fn}|{mn}")
    if not ln:
        return
    grade, step = parse_sg(sg)
    if grade is None and key in gap:
        gpos, gsg, gsal = gap[key]
        position = position or gpos
        if grade is None:
            grade, step = gsg
        if salary is None:
            salary = gsal

    gov, pers = classify_email(email)
    row = {
        "first_name": fn, "middle_name": mn, "last_name": ln, "suffix": suffix,
        "gender": clean(gender).title() or None,
        "birth_date": to_date(birth) or None,
        "contact_number": norm_contact(contact) or None,
        "gov_email": gov or None,
        "personal_email": pers or None,
        "residential_address": addr.get(key) or None,
        "position_title": norm_position(position) or None,
        "salary_grade": grade, "step": step,
        "monthly_salary": salary,
        "plantilla_item_no": None if clean(item).upper() in ("COTERMINOUS", "COTERMINUS") else (clean(item) or None),
        "bp_number": clean(bp) or None,
        "date_original_appointment": to_date(date_app) or None,
        "source_of_fund": clean(project) or None,
        "employment_type_code": "PERMANENT",
        "division_code": None,
        "status": "active",
        "remarks": None,
        "education": None,
        "eligibilities": [],
    }
    # employment type from project / item
    proj = clean(project).upper()
    item_u = clean(item).upper()
    if kind == "cos":
        row["employment_type_code"] = "CONTRACT_OF_SERVICE"
    elif kind == "jo":
        row["employment_type_code"] = "JOB_ORDER"
    elif kind == "gip":
        row["employment_type_code"] = "GIP"
    elif kind == "sep":
        row["employment_type_code"] = "PERMANENT"  # refined below by section
        row["employment_type_code"] = "JOB_ORDER"
    elif "COTERMINOUS" in item_u or "CTI" in proj or "COTERMINOUS" in proj:
        row["employment_type_code"] = "CO_TERMINUS"
    elif proj == "PLANTILLA":
        row["employment_type_code"] = "PERMANENT"
    else:
        row["employment_type_code"] = "PERMANENT"

    if remarks:
        row["remarks"] = remarks
    if clean(position) in DETAILED_POSITIONS:
        note = "Detailed to another unit"
        row["remarks"] = f"{row['remarks']} · {note}" if row["remarks"] else note

    # only take the highest-priority record per name
    if key not in people:
        people[key] = row

# --- Section 1: REGULAR / PLANTILLA (rows 7..48) ---
for i, r in enumerate(master[6:49], start=7):
    pos, sg, sal, sur, first, mid = r[1], r[2], r[3], r[4], r[5], r[6]
    if not clean(pos) and not clean(sur) and not clean(first):
        continue
    if not clean(sur) and not clean(first):  # vacant item rows
        continue
    add_person(sur, first, mid, pos, sg, sal, r[7], r[8], r[10], r[13],
               r[14], r[15], r[16], r[17], "organic")

# --- Section 2: COS (rows 51..104) ---
for i, r in enumerate(master[50:104], start=51):
    pos, sg, sal, sur, first, mid = r[1], r[2], r[3], r[4], r[5], r[6]
    if not clean(pos) and not clean(sur) and not clean(first):
        continue
    if not clean(sur) and not clean(first):
        continue
    total = parse_salary(sal)
    basic = round(total / 1.2, 2) if total else None
    premium = round(total - basic, 2) if total else None
    note = f"Contract of Service - basic {basic} + 20% premium {premium}" if total else "Contract of Service"
    add_person(sur, first, mid, pos, sg, sal, r[7], r[8], r[10], r[13],
               r[14], r[15], r[16], r[17], "cos", remarks=note)

# ---------------------------------------------------------------- place lookups
place_organic = {}   # normname -> division code
for r in rows("DICT Personnel")[3:]:
    key = norm(clean(r[1]) + "|" + clean(r[2]) + "|" + clean(r[3]))
    if not key or key == "|":
        continue
    place_organic[key] = clean(r[5]).upper()

place_cos = {}       # normname -> division code
for r in rows("Project (COS)")[6:]:
    key = norm(clean(r[6]) + "|" + clean(r[7]) + "|" + clean(r[8]))
    if not key or key == "|":
        continue
    place_cos[key] = clean(r[13]).upper()

def division_from_place(place):
    p = place.upper()
    if "TECHNICAL OPERATIONS" in p or p == "TOD":
        return "TOD"
    if "ADMIN" in p or p == "AFD":
        return "AFD"
    if "REGIONAL DIRECTOR" in p or "OAR" in p:
        return "OAR"
    if "CAGAYAN" in p or p == "CPO":
        return "CPO"
    if "ISABELA" in p or p == "IPO":
        return "IPO"
    if "NUEVA VIZCAYA" in p or p == "NVPO":
        return "NVPO"
    if "QUIRINO" in p or p == "QPO":
        return "QPO"
    if "BATANES" in p or p == "BPO":
        return "BPO"
    return "RO"

for key, row in people.items():
    ln = norm(row["last_name"]) + "|" + norm(row["first_name"]) + "|" + norm(row["middle_name"])
    if row["employment_type_code"] == "CONTRACT_OF_SERVICE":
        div = place_cos.get(key) or place_organic.get(key)
        row["division_code"] = division_from_place(div) if div else "RO"
    elif row["employment_type_code"] == "PERMANENT" or row["employment_type_code"] == "CO_TERMINUS":
        div = place_organic.get(key)
        row["division_code"] = division_from_place(div) if div else "RO"
    else:
        row["division_code"] = "RO"

# ---------------------------------------------------------------- JO employees
jo_seen = 0
for sheet in ["JO employees PER PROJECT (as of", "Sheet49"]:
    for r in rows(sheet)[4:]:
        pos, sg, basic, prem, total, sur, first, mid, proj = r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9]
        place = r[12] if len(r) > 12 else ""
        if not clean(sur) and not clean(first):
            continue
        key = norm(clean(sur) + "|" + clean(first) + "|" + clean(mid))
        if key in people:
            continue
        t = parse_salary(total)
        b = parse_salary(basic)
        p = parse_salary(prem)
        note = f"Job Order - basic {b} + 20% premium {p}" if (b or p) else "Job Order"
        add_person(sur, first, mid, pos, sg, total, None, None, None, None,
                   proj, None, None, None, "jo", remarks=note)
        people[key]["division_code"] = division_from_place(clean(place))
        jo_seen += 1

# ---------------------------------------------------------------- GIP interns
gip = 0
for r in rows("GIP 2026")[1:]:
    place, name, educ, elig, contact, email, assum, end, rem, renew = r[0], r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9]
    if not clean(name):
        continue
    # full name "Hennessi Mae S. Pedro" -> split (peel trailing suffix first)
    parts = clean(name).split()
    if len(parts) < 2:
        continue
    suffix = ""
    if parts[-1].upper().rstrip(".") in SUFFIXES and len(parts) > 2:
        suffix = parts[-1].rstrip(".").capitalize()
        parts = parts[:-1]
    last = parts[-1]
    first = parts[0]
    middle = " ".join(parts[1:-1])
    fn, mn, ln, suffix = split_name(last + (" " + suffix if suffix else ""), first, middle)
    key = norm(f"{ln}|{fn}|{mn}")
    if key in people:
        continue
    edu = None
    e = clean(educ)
    if e:
        lines = [x.strip() for x in e.replace("\\n", "\n").split("\n") if x.strip()]
        level = lines[0] if lines else ""
        course = " ".join(lines[1:]) if len(lines) > 1 else None
        if "GRADUATE" in level.upper():
            lvl = "College"
        elif "UNDERGRADUATE" in level.upper():
            lvl = "Undergraduate"
        elif level.upper() == "K-12":
            lvl = "Secondary"
        else:
            lvl = level
        edu = {"level": lvl, "course": course}
    eligs = [x.strip() for x in clean(elig).replace("\\n", "\n").split("\n") if x.strip() and x.strip().upper() not in ("NONE", "N/A")]
    rem_note = []
    if clean(end):
        rem_note.append(f"Contract ends {to_date(end)}")
    if clean(renew) and clean(renew) not in ("0",):
        rem_note.append(f"Renewal count: {clean(renew)}")
    add_person(last, first, middle, "GIP - Intern", None, None, None, None, contact, email,
               "GIP", None, None, assum, "gip", remarks="; ".join(rem_note) or "Government Internship Program")
    # add_person re-split the raw parts; restore the correctly-split name (suffix peeling)
    people[key].update({"first_name": fn, "middle_name": mn, "last_name": ln, "suffix": suffix})
    people[key]["education"] = edu
    people[key]["eligibilities"] = eligs
    people[key]["division_code"] = division_from_place(clean(place))
    gip += 1

# ------------------------------------------------- Accession & Separation (former)
sep = 0
section = "PLANTILLA"
for r in rows("Accession and Separation")[1:]:
    s, name, acc, sep_date, gov = r[0], r[1], r[2], r[3], r[4]
    if clean(s) and not clean(name):
        section = clean(s).upper()
    if not clean(name):
        continue
    parts = clean(name).split()
    if len(parts) < 2:
        continue
    last = parts[-1]
    first = parts[0]
    middle = " ".join(parts[1:-1])
    fn, mn, ln, suffix = split_name(last, first, middle)
    key = norm(f"{ln}|{fn}|{mn}")
    if key in people:
        continue
    typ = "PERMANENT"
    if "JOB" in section:
        typ = "JOB_ORDER"
    elif "CONTRACT" in section:
        typ = "CONTRACT_OF_SERVICE"
    add_person(last, first, middle, None, None, None, None, None, None, gov,
               None, None, None, acc, "sep",
               remarks=f"Separated {to_date(sep_date) or 'n/a'}")
    people[key]["employment_type_code"] = typ
    people[key]["status"] = "separated"
    people[key]["division_code"] = "RO"
    sep += 1

# ------------------------------------------------------ assign employee numbers
ORDER = {"PERMANENT": 0, "CO_TERMINUS": 1, "CONTRACT_OF_SERVICE": 2, "JOB_ORDER": 3, "GIP": 4}

def sort_key(item):
    row = item[1]
    return (ORDER.get(row["employment_type_code"], 9),
            row["date_original_appointment"] or "9999",
            row["last_name"])

records = []
num = 100
for key, row in sorted(people.items(), key=sort_key):
    num += 1
    row["employee_number"] = f"RO2-{num:04d}"
    # compact: drop empty values
    row = {k: v for k, v in row.items() if v not in (None, "", [], {})}
    records.append(row)

os.makedirs(os.path.dirname(OUT), exist_ok=True)
with open(OUT, "w", encoding="utf-8") as f:
    json.dump(records, f, ensure_ascii=False, indent=1)

# ---------------------------------------------------------------- summary
from collections import Counter
types = Counter(r["employment_type_code"] for r in records)
divs = Counter(r.get("division_code") for r in records)
print(f"TOTAL RECORDS: {len(records)}  (JO added: {jo_seen}, GIP: {gip}, separated: {sep})")
print("BY TYPE:", dict(types))
print("BY DIVISION:", dict(divs))
print("\nDISTINCT POSITIONS:")
for p in sorted({r["position_title"] for r in records if r.get("position_title")}):
    print("  -", p)
print("\nDISTINCT SOURCE OF FUND:")
for s in sorted({r["source_of_fund"] for r in records if r.get("source_of_fund")}):
    print("  -", s)
print("\nSample records:")
for r in records[:3] + records[-2:]:
    print(" ", r.get("employee_number"), r.get("last_name"), r.get("position_title"), r.get("employment_type_code"))
