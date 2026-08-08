# UI Redesign — eGovPay-style Design System

**Status:** Implemented (replaces the earlier GOV.PH Institutional pass) · **Zero build step** — plain CSS + vanilla JS, no Tailwind/React.

The design language in `ui_ref/` (an eGovPay merchant-dashboard kit: dark navy sidebar, blue-600 primary actions, light-gray canvas, white rounded cards, tracked uppercase table headers) was translated into the existing Blade + plain-CSS architecture.

---

## 1. Design tokens

| Token | Value | Used for |
|---|---|---|
| `--navy-950` | `#0B1120` | Sidebar background |
| `--navy-900` | `#0F172A` | Sidebar hover / active row |
| `--brand-600` | `#2563EB` | Primary buttons, focus rings, active states |
| `--brand-400` | `#60A5FA` | Active nav text/icon, accent bars |
| `--canvas` | `#F3F4F6` | Page background |
| `--surface` | `#FFFFFF` | Cards / panels |
| `--ink-900 / 700 / 500 / 400` | `#111827 → #9CA3AF` | Text hierarchy |
| `--line` / `--line-strong` | `#E5E7EB` / `#D1D5DB` | Hairline borders |
| Status pills | `#DCFCE7`/`#15803D` (green), `#FEE2E2`/`#B91C1C` (red), `#FEF3C7`/`#B45309` (amber), … | Badges |

Typography: **Inter** (body/UI, weights 300–700) + **Be Vietnam Pro** (display titles).
Radii: `--radius-card` 12px (panels), `--radius-control` 8px (buttons/inputs), `--radius-pill` (badges, progress).

## 2. Component map

| Component | Change |
|---|---|
| Masthead | Slim dark-navy strip (`navy-900`) with blue underline, uppercase micro type |
| Sidebar | Flat `#0B1120`, rounded links, active = `navy-900` row + `brand-400` left accent bar |
| Brand mark | Rounded blue tile, white serif monogram |
| Nav | Stroke-SVG icons (no emoji), upcoming modules = dimmed text `(P2)`… |
| Topbar | White + hairline, bold Be Vietnam Pro page title |
| Cards | White, 12px radius, hairline border, subtle shadow, bold title + actions header |
| Tables | Uppercase tracked headers, hairline row dividers, tabular mono numerals |
| Badges | **Pill shapes** with soft tinted backgrounds (green/red/amber/blue/purple/teal/indigo/pink/gray) |
| Buttons | Rounded 8px, solid blue primary, outline secondary, red danger, ghost |
| Forms | Rounded inputs, hairline borders, blue focus ring, bold labels |
| Avatars | **Circular** with deterministic per-name color (hash of the name) |
| Stats (dashboard) | Single white panel, 4 tiles divided by hairlines, icon chip + label + big value |
| Hero banner (dashboard) | Soft blue/lavender gradient + blurred pastel blobs, promo card on the right |
| Chart (dashboard) | Vanilla-SVG area/bar chart + table view, 3-button view toggle (blue active), peak-value bubble |
| Login | White rounded card, blue top rule, centered on gray canvas, navy masthead |

## 3. Dashboard layout (new)

1. **Hero banner** — gradient + blobs, headline + subtext, `promo-card` linking to the directory.
2. **Overview stat row** — one bordered panel, 4 tiles with vertical dividers (Total Employees, Active, Separated/Retired, Employment Types).
3. **Headcount by Employment Type** — card with header actions (view toggle: table / area / bar), vanilla-SVG chart with gradient fill + peak bubble.
4. **Recently Added Employees** — standard table.

## 4. JS (public/js/app.js)

- Vanilla SVG chart renderer: `JSON.parse(data-chart)` → area/bar/table views, peak-value bubble, gridlines, auto-thinned x labels.
- View toggle switches views and updates `aria-pressed`.
- Sidebar drawer (mobile), table overflow hints (unchanged from Pass I).

## 5. Rollout notes

- **Pass A–F** (GOV.PH Institutional): flat flag-palette chrome, SVG icons, masthead — shipped and later **replaced** by this system.
- **Pass I** (mobile responsive + modern UX): off-canvas drawer, breakpoints, touch targets, `:focus-visible`, skip link, `prefers-reduced-motion`, print stylesheet — **retained**.
- **Page sweeps:** dashboard rebuilt; stale inline references to old variables (`--border`, `--text-muted`, `--gold-500`, IBM Plex fonts) updated across profile/employee/audit views.

**Deliberately omitted from the kit:** `YearStepper` (no year dimension in current headcount data) and the Test/Live mode toggle (not meaningful for a gov HRIS). The view toggle covers the interactive chart controls.
