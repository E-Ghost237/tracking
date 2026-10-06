"""
Back-office of the design preview.

A static replica of the Filament panel for review: the same navigation groups
and labels the resources register, the same sidebar/topbar/page-header/table
anatomy, and the same visual language as `resources/css/filament/admin/theme.css`
(one navy, one signal accent, 4/6px radii, hairlines instead of shadows).

Two differences from the real panel are deliberate:

* The rules below are written as plain CSS with `bo-` class names. The live
  panel is styled only through Filament's `fi-*` hook classes inside the compiled
  theme, which needs PHP and the vendor tree to build — neither exists in this
  review environment. The tokens are copied 1:1, so what is reviewed here is what
  the panel renders.
* A light/dark switch is included because the panel supports both modes.

Figures are the demo environment's (DemoSeeder), not live data.
"""

# --------------------------------------------------------------------------
# Theme mirror: the `--cv-*` tokens from resources/css/filament/admin/theme.css
# --------------------------------------------------------------------------
ADMIN_CSS = """
.bo-frame { overflow-x: auto; padding: 1.25rem 1.25rem 2rem; background: var(--bo-canvas-outer, #f5f7fa); }
body:has(#bo-theme-dark:checked) .bo-frame { --bo-canvas-outer: #061526; }

.bo-app {
    --bo-canvas: #f5f7fa;
    --bo-panel: #ffffff;
    --bo-line: #e3e8ef;
    --bo-line-strong: #c0d0e2;
    --bo-ink: #061526;
    --bo-muted: #546078;
    --bo-field: #ffffff;
    --bo-hover: #eff3f8;
    --bo-accent: #c2410c;
    --bo-on-accent: #ffffff;
    --bo-on-ink: #ffffff;
    /* Method-mix slices and distribution bars, legible on both surfaces. */
    --bo-c1: #c2410c;
    --bo-c2: #375c86;
    --bo-c3: #e2703a;
    --bo-c4: #17334f;
    --bo-c5: #7fb3e0;
    --bo-bar: #234669;
    --bo-bar-alert: #b45309;

    display: flex;
    min-width: 1080px;
    /* A fixed app frame with its own scroll regions, like the panel. */
    height: min(920px, 92vh);
    overflow: hidden;
    border: 1px solid var(--bo-line);
    border-radius: 8px;
    background: var(--bo-canvas);
    color: var(--bo-ink);
    font: 400 14px/1.45 'Inter Variable', ui-sans-serif, system-ui, sans-serif;
}
body:has(#bo-theme-dark:checked) .bo-app {
    --bo-canvas: #061526;
    --bo-panel: #0b1f36;
    --bo-line: rgb(255 255 255 / 0.09);
    --bo-line-strong: rgb(255 255 255 / 0.18);
    --bo-ink: #eff3f8;
    --bo-muted: #94adc9;
    --bo-field: rgb(255 255 255 / 0.04);
    --bo-hover: rgb(255 255 255 / 0.06);
    --bo-accent: #ef9a68;
    --bo-on-accent: #061526;
    /* In dark mode the navy pill inverts, exactly as the panel's does. */
    --bo-on-ink: #061526;
    --bo-c1: #ef9a68;
    --bo-c2: #7fb3e0;
    --bo-c3: #f5bb9a;
    --bo-c4: #c0d0e2;
    --bo-c5: #94adc9;
    --bo-bar: #7fb3e0;
    --bo-bar-alert: #fbbf24;
}
.bo-app * { box-sizing: border-box; }
.bo-app h1, .bo-app h2, .bo-app h3, .bo-app p, .bo-app dl, .bo-app dd, .bo-app dt { margin: 0; }
.bo-num { font-variant-numeric: tabular-nums; }

/* ---- sidebar ---- */
.bo-side {
    flex: 0 0 248px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    scrollbar-gutter: stable;
    background: var(--bo-panel);
    border-inline-end: 1px solid var(--bo-line);
}
.bo-brand { display: flex; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--bo-line); }
.bo-brand-mark {
    display: grid; place-items: center;
    width: 28px; height: 28px; border-radius: 4px;
    background: var(--bo-ink); color: var(--bo-on-ink);
}
.bo-brand-mark svg { width: 16px; height: 16px; transform: rotate(45deg); }
.bo-brand-name { font-size: 18px; font-weight: 700; letter-spacing: -0.02em; }
.bo-nav { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 22px; }
.bo-group { display: flex; flex-direction: column; gap: 4px; }
.bo-group-label {
    padding: 0 10px 4px;
    font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--bo-muted);
}
.bo-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border-radius: 4px;
    font-size: 14px; font-weight: 500;
    color: var(--bo-ink); text-decoration: none;
}
.bo-item svg { width: 18px; height: 18px; flex: none; color: var(--bo-muted); }
.bo-item:hover { background: var(--bo-hover); }
.bo-item.is-active { background: var(--bo-ink); color: var(--bo-on-ink); }
.bo-item.is-active svg { color: var(--bo-on-ink); }
.bo-item .bo-count { margin-inline-start: auto; }
.bo-side-foot { padding: 12px 16px; border-top: 1px solid var(--bo-line); }

/* ---- topbar ---- */
.bo-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; scrollbar-gutter: stable; }
.bo-top {
    display: flex; align-items: center; gap: 16px;
    padding: 10px 20px;
    background: var(--bo-panel);
    border-bottom: 1px solid var(--bo-line);
}
.bo-search {
    display: flex; align-items: center; gap: 10px;
    width: 340px; padding: 7px 10px;
    border: 1px solid var(--bo-line-strong); border-radius: 4px;
    background: var(--bo-field); color: var(--bo-muted);
}
.bo-search svg { width: 16px; height: 16px; }
.bo-search kbd {
    margin-inline-start: auto;
    padding: 1px 5px; border: 1px solid var(--bo-line); border-radius: 3px;
    font: 500 11px/1.6 ui-monospace, monospace; color: var(--bo-muted);
}
.bo-top-end { margin-inline-start: auto; display: flex; align-items: center; gap: 14px; }
.bo-icon-btn {
    position: relative; display: grid; place-items: center;
    width: 34px; height: 34px; border-radius: 4px;
    border: 1px solid transparent; color: var(--bo-muted); background: none; cursor: pointer;
}
.bo-icon-btn:hover { background: var(--bo-hover); }
.bo-icon-btn svg { width: 18px; height: 18px; }
.bo-dot {
    position: absolute; top: 4px; inset-inline-end: 4px;
    min-width: 16px; height: 16px; padding: 0 4px;
    border-radius: 8px; background: #9a3412; color: #fff;
    font: 600 10px/16px 'Inter Variable', sans-serif; text-align: center;
}
.bo-user { display: flex; align-items: center; gap: 10px; padding-inline-start: 14px; border-inline-start: 1px solid var(--bo-line); }
.bo-avatar {
    display: grid; place-items: center;
    width: 32px; height: 32px; border-radius: 4px;
    background: #0b1f36; color: #ef9a68;
    font-size: 12px; font-weight: 700; letter-spacing: 0.02em;
}
.bo-user-name { font-size: 13px; font-weight: 600; }
.bo-user-mail { font-size: 12px; color: var(--bo-muted); }

/* ---- page ---- */
.bo-page { padding: 22px 24px 28px; display: flex; flex-direction: column; gap: 22px; }
.bo-crumbs { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--bo-muted); }
.bo-head { display: flex; align-items: flex-end; gap: 16px; }
.bo-head h1 { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; }
.bo-head p { margin-top: 6px; max-width: 640px; font-size: 15px; color: var(--bo-muted); }
.bo-head-actions { margin-inline-start: auto; display: flex; gap: 10px; }

.bo-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 14px; border-radius: 4px;
    border: 1px solid var(--bo-line-strong); background: var(--bo-panel); color: var(--bo-ink);
    font: 600 13px/1.3 'Inter Variable', sans-serif; cursor: pointer;
}
.bo-btn:hover { background: var(--bo-hover); }
.bo-btn svg { width: 16px; height: 16px; }
.bo-btn.is-primary { border-color: transparent; background: var(--bo-accent); color: var(--bo-on-accent); }

/* Filter bar: the same fields as the panel, sized for a table toolbar. */
.bo-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; padding: 14px 18px 0; }
.bo-filter { display: flex; flex-direction: column; gap: 5px; min-width: 168px; }
.bo-filter > span {
    font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--bo-muted);
}
.bo-select {
    appearance: none; color-scheme: light;
    width: 100%; padding: 8px 34px 8px 11px;
    border: 1px solid var(--bo-line-strong); border-radius: 4px;
    background-color: var(--bo-panel); color: var(--bo-ink);
    font: 500 13px/1.35 'Inter Variable', sans-serif;
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23546078' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; background-size: 15px 15px;
    cursor: pointer; transition: border-color 140ms ease, box-shadow 140ms ease;
}
.bo-select:hover { border-color: var(--bo-ink); }
.bo-select:focus-visible {
    outline: none; border-color: var(--bo-ink);
    box-shadow: 0 0 0 2px color-mix(in oklab, var(--bo-ink) 14%, transparent);
}
.bo-select option { background-color: var(--bo-panel); color: var(--bo-ink); }
.bo-filters .bo-reset {
    padding: 8px 4px; border: 0; background: none; cursor: pointer;
    font: 600 13px/1.35 'Inter Variable', sans-serif; color: var(--bo-accent);
}
.bo-filters .bo-reset:hover { text-decoration: underline; }

@supports (appearance: base-select) {
    .bo-select, .bo-select::picker(select) { appearance: base-select; }
    .bo-select { background-image: none; padding-right: 11px; }
    .bo-select::picker-icon { color: var(--bo-muted); transition: transform 160ms ease; }
    .bo-select:open::picker-icon { transform: rotate(180deg); }
    .bo-select::picker(select) {
        margin-block: 6px; padding: 4px;
        border: 1px solid var(--bo-line-strong); border-radius: 8px;
        background: var(--bo-panel);
        box-shadow: 0 14px 26px -18px rgb(6 21 38 / 0.5);
        opacity: 0; transform: translateY(-4px);
        transition: opacity 140ms ease, transform 140ms ease,
            display 140ms allow-discrete, overlay 140ms allow-discrete;
    }
    .bo-select:open::picker(select) { opacity: 1; transform: none; }
    .bo-select option { border-radius: 4px; padding: 8px 10px; }
    .bo-select option:hover, .bo-select option:focus-visible { background-color: var(--bo-hover); }
    .bo-select option:checked { background-color: color-mix(in oklab, var(--bo-accent) 14%, transparent); }
    .bo-select option::checkmark { color: var(--bo-accent); }
}

/* Meters fill when the panel appears. */
@media (prefers-reduced-motion: no-preference) {
    .bo-bar-fill { transform-origin: left center; animation: bar-grow 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
}

.bo-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 8px; border-radius: 3px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}

.bo-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.bo-card {
    padding: 18px; border: 1px solid var(--bo-line); border-radius: 6px;
    background: var(--bo-panel);
}
.bo-stat-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--bo-muted);
}
.bo-stat-label svg { width: 16px; height: 16px; }
.bo-stat-value { margin-top: 10px; font-size: 26px; font-weight: 700; letter-spacing: -0.02em; }
.bo-stat-desc { margin-top: 4px; font-size: 13px; color: var(--bo-muted); }

.bo-grid { display: grid; gap: 12px; }
.bo-grid.two { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); }
.bo-grid.table { grid-template-columns: minmax(0, 1fr) 320px; }
.bo-card-head { display: flex; align-items: flex-start; gap: 12px; padding: 16px 18px 0; }
.bo-card-head h2 { font-size: 15px; font-weight: 600; }
.bo-card-head p { margin-top: 3px; font-size: 13px; color: var(--bo-muted); }
.bo-card-body { padding: 14px 18px 18px; }

/* ---- charts ---- */
.bo-chart { display: block; width: 100%; height: auto; }
.bo-chart .grid-line { stroke: var(--bo-line); stroke-width: 1; }
.bo-chart .axis { fill: var(--bo-muted); font: 500 10px 'Inter Variable', sans-serif; }
.bo-chart .series { fill: none; stroke: var(--bo-accent); stroke-width: 2; }
.bo-chart .area { fill: var(--bo-accent); opacity: 0.10; }
.bo-chart .dot { fill: var(--bo-panel); stroke: var(--bo-accent); stroke-width: 2; }

.bo-legend { display: flex; flex-direction: column; gap: 9px; margin-top: 4px; }
.bo-legend-row { display: flex; align-items: center; gap: 10px; font-size: 13px; }
.bo-swatch { width: 10px; height: 10px; border-radius: 2px; flex: none; }
.bo-legend-row .bo-legend-value { margin-inline-start: auto; font-weight: 600; color: var(--bo-ink); }

.bo-bars { display: flex; flex-direction: column; gap: 10px; }
.bo-bar-row { display: grid; grid-template-columns: 116px minmax(0, 1fr) 32px; align-items: center; gap: 10px; font-size: 13px; }
.bo-bar-track { height: 8px; border-radius: 2px; background: var(--bo-canvas); overflow: hidden; }
.bo-bar-fill { height: 100%; border-radius: 2px; background: var(--bo-bar); }
.bo-bar-row.is-alert .bo-bar-fill { background: var(--bo-bar-alert); }
.bo-bar-value { text-align: end; font-weight: 600; }

/* ---- table ---- */
.bo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.bo-table th {
    padding: 0 12px 8px; text-align: start;
    font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--bo-muted); border-bottom: 1px solid var(--bo-line);
}
.bo-table td { padding: 11px 12px; border-bottom: 1px solid var(--bo-line); vertical-align: middle; }
.bo-table tbody tr:hover { background: var(--bo-hover); }
.bo-table th:first-child, .bo-table td:first-child { padding-inline-start: 18px; }
.bo-table th:last-child, .bo-table td:last-child { padding-inline-end: 18px; }
.bo-ref { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; font-weight: 600; }
.bo-sub { display: block; margin-top: 2px; font-size: 11px; color: var(--bo-muted); }
.bo-link { color: var(--bo-accent); font-weight: 600; text-decoration: none; }
.bo-tone-danger { background: #fee2e2; color: #9f1239; }
.bo-tone-warning { background: #fef3c7; color: #78350f; }
.bo-tone-success { background: #d1fae5; color: #065f46; }
.bo-tone-neutral { background: #e3e8ef; color: #234669; }
body:has(#bo-theme-dark:checked) .bo-tone-danger { background: rgb(159 18 57 / 0.22); color: #fecdd3; }
body:has(#bo-theme-dark:checked) .bo-tone-warning { background: rgb(180 83 9 / 0.24); color: #fde68a; }
body:has(#bo-theme-dark:checked) .bo-tone-success { background: rgb(6 95 70 / 0.28); color: #a7f3d0; }
body:has(#bo-theme-dark:checked) .bo-tone-neutral { background: rgb(255 255 255 / 0.08); color: #c0d0e2; }
body:has(#bo-theme-dark:checked) .bo-avatar { background: #0f2740; }

.bo-tone-text-danger { color: #9f1239; }
.bo-tone-text-success { color: #065f46; }
body:has(#bo-theme-dark:checked) .bo-tone-text-danger { color: #fecdd3; }
body:has(#bo-theme-dark:checked) .bo-tone-text-success { color: #a7f3d0; }

/* ---- review chrome (not part of the panel) ---- */
.bo-review-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px;
    margin-bottom: 14px; padding: 12px 16px;
    border: 1px solid #e3e8ef; border-radius: 6px; background: #fff;
}
.bo-review-bar p { font-size: 13px; color: #546078; }
.bo-review-bar strong { color: #061526; font-weight: 600; }
.bo-switch { display: flex; gap: 2px; margin-inline-start: auto; padding: 2px; border: 1px solid #e3e8ef; border-radius: 4px; }
.bo-switch label {
    padding: 5px 10px; border-radius: 3px; cursor: pointer;
    font: 600 12px/1 'Inter Variable', sans-serif; color: #234669;
}
.bo-switch label:hover { background: #f5f7fa; }
body:has(#bo-theme-light:checked) .bo-switch label[for='bo-theme-light'],
body:has(#bo-theme-dark:checked) .bo-switch label[for='bo-theme-dark'] { background: #0b1f36; color: #fff; }
.bo-switch input:focus-visible + label { outline: 2px solid #c2410c; outline-offset: 1px; }
.bo-a11y { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
"""

# --------------------------------------------------------------------------
# Demo figures. Same scale as DemoSeeder; no figures are presented as live data.
# --------------------------------------------------------------------------
NAV_GROUPS = [
    ("Operations", [
        ("layout-dashboard", "Dashboard", True, None),
        ("package", "Shipments", False, None),
        ("upload", "Import events", False, None),
        ("truck", "Carriers", False, None),
    ]),
    ("Payments", [
        ("shield-check", "Proofs queue", False, 12),
        ("receipt", "Orders", False, 7),
        ("credit-card", "Payment methods", False, None),
    ]),
    ("Pricing", [
        ("ruler", "Rate cards", False, None),
        ("globe", "Zones", False, None),
        ("tag", "Surcharges", False, None),
        ("zap", "Mode multipliers", False, None),
    ]),
    ("Customers and support", [
        ("user", "Users", False, None),
        ("headset", "Support", False, 4),
        ("file-warning", "Claims", False, None),
    ]),
    ("Content", [
        ("file-text", "Pages", False, None),
        ("message-circle", "FAQ", False, None),
        ("triangle-alert", "Service alerts", False, None),
        ("map-pin", "Locations", False, None),
        ("boxes", "Hero media", False, None),
        ("mail", "Email templates", False, None),
    ]),
    ("System", [
        ("settings", "Settings", False, None),
        ("lock", "Roles", False, None),
        ("list", "Audit log", False, None),
    ]),
]

# label, value, description, tone, icon
STATS = [
    ("Proofs waiting", "12", "Oldest: 4 h 12 m", "danger", "shield-check"),
    ("Orders awaiting payment", "7", "3 reminders sent today", "neutral", "clock"),
    ("Late shipments", "3", "Against the published window", "danger", "triangle-alert"),
    ("Revenue today", "$1,284.00", "7 days: $9,410.00", "success", "receipt"),
]

# Revenue by day, last 14 days (USD).
REVENUE = [180, 240, 0, 420, 310, 265, 190, 480, 520, 375, 240, 610, 430, 355]
REVENUE_LABELS = ["Sep 23", "24", "25", "26", "27", "28", "29", "30", "Oct 1", "2", "3", "4", "5", "6"]

# Approved payments by method, 30 days.
# label, approved payments, palette token (see --bo-c1..--bo-c5)
METHODS = [
    ("IBAN transfer", 36, "--bo-c1"),
    ("PayPal", 20, "--bo-c2"),
    ("Zelle", 13, "--bo-c3"),
    ("Cash App", 11, "--bo-c4"),
    ("Gift card", 6, "--bo-c5"),
]

# status, label, count
SHIPMENT_STATUS = [
    ("Ready", 4, False),
    ("Picked up", 2, False),
    ("In transit", 9, False),
    ("At customs", 3, True),
    ("Delivered", 18, False),
    ("Delayed", 2, True),
    ("Cancelled", 1, True),
]

# Proof, order, customer, amount, method, waiting, status, tone, flags
PROOFS = [
    ("PAY-4KP7ZX", "ORD-2026-000148", "Chantal Mbarga", "$425.00", "IBAN transfer", "4 h 12 m", "Under review", "warning", "Two approvals"),
    ("PAY-6QX3LM", "ORD-2026-000151", "Samuel Okonkwo", "$1,240.00", "PayPal", "1 h 48 m", "Under review", "warning", None),
    ("PAY-9TW2MN", "ORD-2026-000147", "Chantal Mbarga", "$98.00", "PayPal", "22 h 05 m", "Proof rejected", "danger", "Duplicate file"),
    ("PAY-2HR8VD", "ORD-2026-000150", "Awa Nkemdirim", "$310.00", "Zelle", "38 m", "Under review", "warning", None),
    ("PAY-8ZT5NP", "ORD-2026-000144", "Diego Ferreira", "$760.00", "IBAN transfer", "6 h 30 m", "Under review", "warning", None),
    ("PAY-3WD7RK", "ORD-2026-000149", "Linh Tran", "$186.00", "Cash App", "2 h 14 m", "More info requested", "neutral", None),
]

# The preview runs on plain Python, so the brand name is a constant here
# (config/platform.php holds the real value).
BRAND = "Corvane"

TONE_CLASS = {"danger": "bo-tone-danger", "warning": "bo-tone-warning", "success": "bo-tone-success", "neutral": "bo-tone-neutral"}


def _line_chart(width=720, height=230, pad_left=46, pad_right=12, pad_top=14, pad_bottom=28, top_value=700):
    """Revenue sparkline: hairline grid, accent series, light area fill."""
    plot_w = width - pad_left - pad_right
    plot_h = height - pad_top - pad_bottom
    step = plot_w / (len(REVENUE) - 1)

    def y(value):
        return round(pad_top + (1 - value / top_value) * plot_h, 1)

    points = [(round(pad_left + i * step, 1), y(v)) for i, v in enumerate(REVENUE)]
    polyline = " ".join(f"{x},{v}" for x, v in points)
    area = f"M {points[0][0]},{y(0)} L " + " L ".join(f"{x},{v}" for x, v in points) + f" L {points[-1][0]},{y(0)} Z"

    grid = ""
    for value in (0, 200, 400, 600):
        grid += f'<line class="grid-line" x1="{pad_left}" y1="{y(value)}" x2="{width - pad_right}" y2="{y(value)}"/>'
        grid += f'<text class="axis" x="{pad_left - 8}" y="{y(value) + 3}" text-anchor="end">{value}</text>'

    labels = ""
    for i, (x, _v) in enumerate(points):
        if i % 2 == 0 or i == len(points) - 1:
            labels += f'<text class="axis" x="{x}" y="{height - 8}" text-anchor="middle">{REVENUE_LABELS[i]}</text>'

    dot = points[-1]
    return (
        f'<svg class="bo-chart" viewBox="0 0 {width} {height}" role="img" '
        f'aria-label="Revenue by day, last 14 days. Highest day 610 US dollars, lowest 0.">'
        f"{grid}{labels}"
        f'<path class="area" d="{area}"/>'
        f'<polyline class="series" points="{polyline}"/>'
        f'<circle class="dot" cx="{dot[0]}" cy="{dot[1]}" r="4"/>'
        f"</svg>"
    )


def _donut(size=180, radius=62, stroke=20):
    """Approved payments by method, as a doughnut with no libraries."""
    total = sum(count for _label, count, _colour in METHODS)
    circumference = 2 * 3.141592653589793 * radius
    offset = 0.0
    rings = ""
    for _label, count, colour in METHODS:
        length = circumference * count / total
        rings += (
            f'<circle cx="{size / 2}" cy="{size / 2}" r="{radius}" fill="none" '
            f'stroke="var({colour})" stroke-width="{stroke}" '
            f'stroke-dasharray="{round(length, 2)} {round(circumference - length, 2)}" '
            f'stroke-dashoffset="{round(-offset, 2)}"/>'
        )
        offset += length

    legend = ""
    for label, count, colour in METHODS:
        legend += (
            f'<div class="bo-legend-row"><span class="bo-swatch" style="background:var({colour})"></span>'
            f'<span>{label}</span><span class="bo-legend-value bo-num">{count}</span></div>'
        )

    return (
        f'<div style="display:flex; align-items:center; gap:22px; flex-wrap:wrap">'
        f'<svg viewBox="0 0 {size} {size}" width="{size}" height="{size}" role="img" '
        f'aria-label="Approved payments by method: IBAN transfer 36, PayPal 20, Zelle 13, Cash App 11, gift card 6; 86 approved payments in the last 30 days.">'
        f'<g transform="rotate(-90 {size / 2} {size / 2})">'
        f'<circle cx="{size / 2}" cy="{size / 2}" r="{radius}" fill="none" stroke="var(--bo-line)" stroke-width="{stroke}"/>'
        f"{rings}</g>"
        f'<text x="{size / 2}" y="{size / 2 - 2}" text-anchor="middle" fill="var(--bo-ink)" '
        f'style="font:700 22px \'Inter Variable\',sans-serif">{total}</text>'
        f'<text x="{size / 2}" y="{size / 2 + 16}" text-anchor="middle" fill="var(--bo-muted)" '
        f'style="font:500 11px \'Inter Variable\',sans-serif">payments</text>'
        f"</svg>"
        f'<div class="bo-legend" style="flex:1 1 160px">{legend}</div>'
        f"</div>"
    )


def admin_body(icon):
    """The review page: a static replica of the Filament panel."""
    # ---- sidebar ----
    nav = ""
    for group, items in NAV_GROUPS:
        rows = ""
        for name, label, active, count in items:
            badge = f'<span class="bo-badge bo-tone-neutral bo-count bo-num">{count}</span>' if count else ""
            rows += (
                f'<a class="bo-item{" is-active" if active else ""}" href="#page-admin"'
                + (' aria-current="page"' if active else "")
                + f">{icon(name, '')}{label}{badge}</a>"
            )
        nav += f'<div class="bo-group"><p class="bo-group-label">{group}</p>{rows}</div>'

    # ---- stats ----
    stats = ""
    for label, value, description, tone, name in STATS:
        value_class = "bo-stat-value bo-num" if tone == "neutral" else f"bo-stat-value bo-num bo-tone-text-{tone}"
        stats += (
            f'<div class="bo-card">'
            f'<p class="bo-stat-label">{icon(name, "")}{label}</p>'
            f'<p class="{value_class}">{value}</p>'
            f'<p class="bo-stat-desc">{description}</p>'
            f"</div>"
        )

    # ---- table ----
    rows = ""
    for proof, order, customer, amount, method, waiting, status, tone, flag in PROOFS:
        flag_badge = (
            f'<span class="bo-badge {TONE_CLASS["danger"] if flag != "Two approvals" else TONE_CLASS["warning"]}">{flag}</span>'
            if flag
            else ""
        )
        rows += (
            f"<tr>"
            f'<td><span class="bo-ref">{proof}</span><span class="bo-sub">{order}</span></td>'
            f"<td>{customer}</td>"
            f'<td class="bo-num">{amount}</td>'
            f"<td>{method}</td>"
            f'<td class="bo-num">{waiting}</td>'
            f'<td><span class="bo-badge {TONE_CLASS[tone]}">{status}</span> {flag_badge}</td>'
            f'<td><a class="bo-link" href="#page-admin">Review</a></td>'
            f"</tr>"
        )

    # ---- shipments by status ----
    peak = max(count for _label, count, _alert in SHIPMENT_STATUS)
    bars = ""
    for label, count, alert in SHIPMENT_STATUS:
        width = round(count / peak * 100)
        bars += (
            f'<div class="bo-bar-row{" is-alert" if alert else ""}">'
            f"<span>{label}</span>"
            f'<span class="bo-bar-track"><span class="bo-bar-fill" style="width:{width}%"></span></span>'
            f'<span class="bo-bar-value bo-num">{count}</span>'
            f"</div>"
        )

    return f"""<div class="bo-frame">
    <div class="bo-review-bar">
        <p><strong>Back-office.</strong> Static replica of the Filament 5 panel for review — same navigation groups, pages and table anatomy as <span class="bo-ref">app/Filament</span>, styled by the new admin theme. Demo figures from DemoSeeder, not live data.</p>
        <div class="bo-switch" role="group" aria-label="Panel colour scheme">
            <input class="bo-a11y" type="radio" name="bo-theme" id="bo-theme-light" checked>
            <label for="bo-theme-light">Light</label>
            <input class="bo-a11y" type="radio" name="bo-theme" id="bo-theme-dark">
            <label for="bo-theme-dark">Dark</label>
        </div>
    </div>

    <div class="bo-app">
        <aside class="bo-side">
            <div class="bo-brand">
                <span class="bo-brand-mark">{icon("navigation", "")}</span>
                <span class="bo-brand-name">{BRAND}</span>
            </div>
            <nav class="bo-nav" aria-label="Back-office">{nav}</nav>
            <div class="bo-side-foot">
                <a class="bo-item" href="#page-admin">{icon("bell", "")}Notifications<span class="bo-badge bo-tone-neutral bo-count bo-num">3</span></a>
            </div>
        </aside>

        <div class="bo-main">
            <div class="bo-top">
                <span class="bo-search">{icon("search", "")}Search orders, shipments, customers<kbd>&#8984;K</kbd></span>
                <div class="bo-top-end">
                    <button class="bo-icon-btn" type="button" aria-label="Notifications">{icon("bell", "")}<span class="bo-dot">3</span></button>
                    <button class="bo-icon-btn" type="button" aria-label="Open the public site">{icon("external-link", "")}</button>
                    <div class="bo-user">
                        <span class="bo-avatar">AB</span>
                        <span><span class="bo-user-name">Amina Bello</span><br><span class="bo-user-mail">admin@corvane.test</span></span>
                    </div>
                </div>
            </div>

            <div class="bo-page">
                <div>
                    <p class="bo-crumbs">Operations<span> / </span>Dashboard</p>
                    <div class="bo-head" style="margin-top:6px">
                        <div>
                            <h1>Dashboard</h1>
                            <p>Proofs waiting for a decision, orders that still need payment, and shipments running against their window.</p>
                        </div>
                        <div class="bo-head-actions">
                            <button class="bo-btn" type="button">{icon("upload", "")}Import events</button>
                            <button class="bo-btn is-primary" type="button">{icon("shield-check", "")}Review proofs (12)</button>
                        </div>
                    </div>
                </div>

                <div class="bo-stats">{stats}</div>

                <div class="bo-grid two">
                    <section class="bo-card">
                        <div class="bo-card-head"><div><h2>Revenue by day</h2><p>Paid orders, last 14 days, USD</p></div></div>
                        <div class="bo-card-body">{_line_chart()}</div>
                    </section>
                    <section class="bo-card">
                        <div class="bo-card-head"><div><h2>Approved payments by method</h2><p>Last 30 days</p></div></div>
                        <div class="bo-card-body">{_donut()}</div>
                    </section>
                </div>

                <div class="bo-grid table">
                    <section class="bo-card" style="padding-bottom:6px">
                        <div class="bo-card-head"><div><h2>Proofs queue</h2><p>Oldest first — the 60-minute alert is measured from submission</p></div></div>
                        <div class="bo-filters">
                            <label class="bo-filter"><span>Status</span>
                                <select class="bo-select">
                                    <option>Awaiting review</option>
                                    <option>Approved</option>
                                    <option>Rejected</option>
                                    <option selected>All statuses</option>
                                </select>
                            </label>
                            <label class="bo-filter"><span>Method</span>
                                <select class="bo-select">
                                    <option selected>All methods</option>
                                    <option>Bank transfer</option>
                                    <option>Mobile money</option>
                                    <option>Card</option>
                                    <option>Cash at counter</option>
                                    <option>Gift card</option>
                                </select>
                            </label>
                            <label class="bo-filter"><span>Waiting</span>
                                <select class="bo-select">
                                    <option selected>Any waiting time</option>
                                    <option>Over 60 minutes</option>
                                    <option>Over 4 hours</option>
                                    <option>Over 24 hours</option>
                                </select>
                            </label>
                            <button class="bo-reset" type="button">Clear filters</button>
                        </div>
                        <table class="bo-table" style="margin-top:14px">
                            <thead>
                                <tr><th>Proof</th><th>Customer</th><th>Amount</th><th>Method</th><th>Waiting</th><th>Status</th><th><span class="bo-a11y">Actions</span></th></tr>
                            </thead>
                            <tbody>{rows}</tbody>
                        </table>
                    </section>
                    <section class="bo-card">
                        <div class="bo-card-head"><div><h2>Shipments by status</h2><p>All released shipments</p></div></div>
                        <div class="bo-card-body"><div class="bo-bars">{bars}</div></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>"""


