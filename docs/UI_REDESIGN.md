# UI Redesign — GOV.PH Institutional

**Status:** Approved · **Rollout:** foundation-first (chrome → components → page sweeps)
**Goal:** Replace the generic "AI-generated SaaS" look with an authentic Philippine-government institutional design language.

---

## 1. Why

The current UI carries the classic AI-slop tells:

- Emoji as nav icons (`👤`, `👥`, `🧾`, `🏖`, `📄`, `💰`, `📊`)
- An 8-color badge rainbow (blue/green/red/amber/purple/teal/indigo/pink)
- Soft rounded cards + fuzzy drop shadows
- Navy *gradient* sidebar + glowing gradient logo tile
- `Segoe UI` system font with no typographic voice
- `P2/P3/P4/P5` pills in the nav (reads like a prototype)

## 2. Design direction — "GOV.PH Institutional"

A government HRIS should look like it belongs to a government office: flat, dense,
rule-bound, unmistakably Filipino. Reference: gov.ph, SSS, GSIS, DICT's own sites.

### Tokens

| Token | Old (AI-like) | New (Institutional) |
|---|---|---|
| Primary | `#1b5faa` muted blue | **Flag royal blue `#0038A8`** |
| Accent | gold gradient | **Flat flag gold `#FCD116`** — rules, active markers, brand borders only |
| Red | `#b91c1c` | **Flag red `#CE1126`** — destructive / separated |
| Surfaces | white cards + soft shadow | **Flat white + `#f4f6f9` panels, 1px hairlines, no shadows** |
| Radius | 8–20px pills | **2px (squared, official-form feel)** |
| Type | `Segoe UI` | **IBM Plex Sans** (text) + **IBM Plex Mono** (numbers, IDs, codes) |
| Palette | 8 badge colors | **1 badge shape + colored square dot** (stamp look), 4 status semantics |

Variable names are **preserved** (`--blue-600`, `--border`, `--text-muted`, `--gold-500`,
`--navy-900`, …) so Blade markup stays mostly untouched.

### Non-negotiables
1. **No emoji anywhere** — replaced by a small stroke-SVG icon set (`partials/icon.blade.php`).
2. **No gradients, no glass, no glow.**
3. **No pill shapes** — squared corners (2px) across buttons, badges, inputs, cards.
4. **Dense, official data tables** — full grid lines, uppercase micro-labels, mono numerals.
5. **Authentic masthead** — "Republic of the Philippines · Department of Information and
   Communications Technology · Regional Office 2" on a flag-blue bar with gold rule,
   on both the app shell and the login page.

## 3. Component map

| Component | Change |
|---|---|
| Masthead | Flag-blue bar, red top stripe, gold bottom rule, uppercase micro type |
| Sidebar | Flat `#0a1c3a`, squared links, active = blue block + gold left bar |
| Brand mark | Flat blue square, gold hairline border, white serif monogram (no glow) |
| Nav | SVG icons, upcoming modules = plain dimmed text `(P2)` — no pills |
| Topbar | White + hairline, uppercase page title with gold square bullet |
| Panels (cards) | 1px border, squared, header title = uppercase micro-label + rule |
| Tables | Full grid, gray uppercase header band, mono tabular numbers |
| Badges | White squared chips, 1px border, colored square dot, uppercase text |
| Buttons | Squared, solid blue primary, outline secondary, flag-red danger |
| Forms | Squared inputs, hairline borders, blue focus ring, bold labels + `*` |
| Avatars | Photos stay circular; initials = squared ID-photo placeholder (blue + gold hairline) |
| Alerts | Squared, 4px left accent bar |
| Stats | Flat panels, 3px top color rule, mono numerals |
| Login | Masthead + centered white squared card, gold top rule, "official website" footer |

## 4. Rollout order

- **Pass A — Tokens & base:** CSS variables, fonts, body/type/layout shell ✅
- **Pass B — Sidebar & brand:** masthead, flat sidebar, SVG icons, footer ✅
- **Pass C — Topbar, content, alerts** ✅
- **Pass D — Panels, tables, pagination** ✅
- **Pass E — Buttons, badges, forms, avatars, progress** ✅
- **Pass F — Login page** ✅
- **Pass G — Page sweeps:** dashboard, employee index/create/edit/show, profile pages,
  audit logs — fix any inline styles that fight the system (pending)
- **Pass H — QA:** `php artisan test`, browser check, review (pending)

**Out of scope:** no markup restructuring, no backend changes, no routes. Pure
`app.css` + Blade class work. Zero build step maintained.
