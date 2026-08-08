# eGovPay-style UI Kit

A Tailwind token set + React component library replicating the eGovPay
merchant dashboard visual style (dark navy sidebar, blue-600 primary
actions, light gray canvas, white cards, uppercase tracked table headers).

## 1. Setup

1. Copy `tailwind.config.js` into your project (merge the `theme.extend`
   block into your existing config if you already have one).
2. Load the fonts in your HTML `<head>` or via `@import` in your global CSS:

```html
<link
  href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap"
  rel="stylesheet"
/>
```

3. Copy the `src/components/` folder into your project.

## 2. Design tokens (extracted from the screenshot)

| Token | Value | Used for |
|---|---|---|
| `navy-950` | `#0B1120` | Sidebar background |
| `navy-900` | `#0F172A` | Sidebar hover / active row |
| `brand-600` | `#2563EB` | Primary buttons |
| `brand-400` | `#60A5FA` | Active nav link text/icon |
| `success.bg` / `success.text` | `#DCFCE7` / `#15803D` | Status badges |
| `canvas` | `#F3F4F6` | Page background |
| `surface` | `#FFFFFF` | Cards / panels |
| `ink-900 / 700 / 500 / 400` | `#111827 → #9CA3AF` | Text hierarchy |
| `line` | `#E5E7EB` | Hairline borders |

Typography: **Inter** (body/UI, weights 300–700) with **Be Vietnam Pro**
available for display/emphasis text — matches the Google Fonts requests
observed on the live site.

Radii: `rounded-card` (12px) for panels, `rounded-control` (8px) for
buttons/inputs, `rounded-pill` for badges and the mode toggle.

## 3. Components

- **`Sidebar`** — dark nav rail, logo, Test/Live mode toggle, nav items
  with active-state left accent bar, optional Settings section.
- **`ModeToggle`** — segmented Test Mode / Live Mode pill switch.
- **`TopBar`** — page header with bold title + right-side action slot.
- **`Card`** — white panel with optional title + actions header row.
- **`Table`** — uppercase tracked column headers, hairline row dividers.
- **`Badge`** — pill status label (`success` / `warning` / `danger` / `neutral`).
- **`Button`** — `primary` (solid blue), `outline` (white/border), `ghost`.
- **`Avatar`** — circular initials badge with deterministic color.
- **`HeroBanner`** — soft lavender/blue gradient section with blurred
  pastel blobs, for a top-of-dashboard promo. Accepts heading/subtext
  as children and a `card` slot (use with `PromoCard`).
- **`PromoCard`** — small white card with icon chip + title + description,
  meant to sit inside `HeroBanner` (e.g. "Get Paid").
- **`StatCard` / `StatRow`** — metric tiles (icon chip, label, value) laid
  out in a single bordered panel with vertical dividers, matching the
  "Overview" strip.
- **`YearStepper`** — "‹ 2026 ›" control for chart panel headers.
- **`ViewToggle`** — 3-way icon button group (table / area / bar) with
  active blue state, matching the chart view switcher.
- **`TrendAreaChart`** — blue gradient-fill area chart (Recharts) with
  a peak-value bubble label, matching "By Transaction".

## 4. Examples

- `src/App.example.jsx` — reconstruction of the "Reports" screen.
- `src/DashboardDemo.jsx` — reconstruction of the dashboard: hero
  banner + promo card, Overview stat row, and the "By Transaction" /
  "Top Projects" chart panels with year stepper + view toggle.

`TrendAreaChart` depends on `recharts` — already listed as an available
library in this environment; if you're using this outside claude.ai,
run `npm install recharts`.

## Notes

- Icons in the example are placeholder inline SVGs — swap in
  `lucide-react` or your preferred icon set; the components accept any
  `icon` node.
- Colors are close visual matches read off the screenshot, not pixel-
  sampled hex values — nudge `brand-600` / `navy-950` slightly if you
  have exact brand hex codes from a design file.
