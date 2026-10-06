#!/usr/bin/env python3
"""
Design preview harness.

There is no PHP runtime in the review environment, so the new visual language is
reviewed through a static mirror of the Blade templates: `preview/home.html` holds
the markup (copied from `resources/views/pages/home.blade.php` and the header and
footer partials), and this script pairs it with the stylesheet compiled by the
Tailwind CLI plus the Inter font files, writing a self-contained folder to
`public/preview` that any static server can serve.

Usage: npm run preview   (then npm run preview:serve)
"""
import base64
import json
from datetime import datetime, timedelta
import re
import shutil
import subprocess
import sys
from pathlib import Path

import sys
sys.path.insert(0, str(Path(__file__).resolve().parent))

from preview_data import (  # noqa: E402
    AIR_DETAIL, HUBS, HUB_COORDS, LANES, MODE_ICON, MODE_LABEL, MODE_TRANSIT,
    RATE_ROWS, REGIONS, SERVICE_CARDS, TRACK_FAQS, TRACK_FORMATS, TRACK_FOUND,
    TRACK_MILESTONES, TRACK_NOT_FOUND, TRACK_STATUS_MEANINGS, WEIGHTS,
)
from preview_account import account_body  # noqa: E402
from preview_admin import ADMIN_CSS, admin_body  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "public" / "preview"

NAV = [
    ("Ship", None), ("Track", None), ("Rates", None), ("Network", None), ("Support", None),
]

SHIP_MENU = [
    ("plane", "Air freight", "2–8 days", "Scheduled flights for parcels, samples and stock"),
    ("ship", "Sea freight", "10–45 days", "Shared container space for larger, heavier loads"),
    ("truck", "Road freight", "2–10 days", "Direct collection and delivery on land routes"),
    ("zap", "Express", "Confirmed per route", "Priority handling on the next available flight"),
]

SERVICES = [
    ("images/freight-air.webp", "Air freight", "2–8 days", "Scheduled flights for parcels, business samples and stock that should arrive sooner.",
     ["Customs coordination included", "Tracking from collection to delivery"]),
    ("images/freight-sea.webp", "Sea freight", "10–45 days", "Shared container space for furniture, equipment and planned stock replenishment.",
     ["Lower cost per kilogram on larger loads", "Consolidation before departure"]),
    ("images/freight-road.webp", "Road freight", "2–10 days", "For eligible regional journeys where flexible pickup and direct delivery matter.",
     ["Parcels and palletised goods", "Established land corridors"]),
    ("images/freight-express.webp", "Express", "Confirmed per route", "Priority handling and the next available flight when a delivery date is driving the decision.",
     ["Priority at key handoffs", "Ideal for documents and urgent items"]),
]

STEPS = [
    ("Get a quote", "Enter the route, weight and dimensions to see the price and the delivery window before you book."),
    ("Book your shipment", "Add the sender, recipient and customs details. Your progress is saved at every step."),
    ("Pay and upload proof", "Pay with the method of your choice and upload your receipt. A verifier confirms it, usually within 30 minutes."),
    ("Track to delivery", "Your label and tracking number are issued once payment is confirmed, then every scan is logged."),
]

OPERATION = [
    ("images/freight-air.webp", "Ground crew loading freight on the apron beside a cargo aircraft", "Loaded on scheduled air capacity"),
    ("images/freight-express.webp", "Express parcels moving along a belt loader into an aircraft hold", "Priority handling for urgent shipments"),
    ("images/freight-road.webp", "Long-haul truck carrying a container on a mountain highway", "Road haulage on supported land corridors"),
]

_ICON_CACHE: dict[str, str] = {}


# Which of the nav labels in the preview chrome have a page in this preview.
# Everything else stays a marked placeholder so a click can explain itself
# (the preview covers seven pages; the app has the rest).
PREVIEW_TARGETS = {
    "Home": "index", "Corvane": "index",
    "Track": "track", "Track a shipment": "track",
    "Air freight": "service-air", "Compare all services": "services",
    "Services": "services", "All services": "services", "Sea freight": "services", "Road freight": "services",
    "Express": "services", "Rates": "rates", "Rates and transit times": "rates",
    "Network": "network", "Network and coverage": "network",
    "Account": "account", "Sign in": "account", "Dashboard": "account",
    "My account": "account", "Orders and invoices": "account", "My shipments": "account",
}


def nav_href(label):
    """Section link for a nav label, or a marked placeholder."""
    target = PREVIEW_TARGETS.get(label)
    if target:
        return f'#page-{target}'
    return '#'



def icon(name, cls):
    """
    The same icon the application renders: read the real file from
    resources/icons (mirrors App\View\Components\Lucide) so the preview cannot
    drift from the app's icon set, and fail loudly if a name is wrong.
    """
    if name not in _ICON_CACHE:
        path = ROOT / "resources" / "icons" / f"{name}.svg"
        if not path.exists():
            raise SystemExit(f"preview: icon '{name}' not found in resources/icons")
        svg = path.read_text()
        inner = re.search(r"<svg[^>]*>(.*)</svg>", svg, re.S)
        _ICON_CACHE[name] = inner.group(1).strip() if inner else ""

    return (
        f'<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        f'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        f"{_ICON_CACHE[name]}</svg>"
    )


def picture(src, alt, cls, width=1400, height=768, priority=False):
    """Self-contained image: the WebP is inlined, so the file has no external requests."""
    attrs = 'fetchpriority="high" decoding="async"' if priority else 'loading="lazy" decoding="async"'
    return (
        f'<img src="{inline_image(src)}" alt="{alt}" width="{width}" height="{height}" {attrs} class="{cls}">'
    )


def header():
    """
    One bar, mirroring resources/views/partials/header.blade.php: brand, the three
    menu groups, the tracking field and a single account action. The panels carry
    the .nav-group / .nav-panel classes from app.css, so they open on hover or
    keyboard focus — the preview is static markup, and a menu must not be
    standing open when the page loads.
    """
    ship_items = "".join(
        f'<li><a href="{nav_href(name)}" class="flex gap-3 rounded-[4px] p-2.5 transition hover:bg-surface">'
        f'<span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700">{icon(i, "size-4.5")}</span>'
        f'<span class="min-w-0"><span class="flex items-center gap-2 text-sm font-semibold text-ink-950">{name} '
        f'<span class="text-xs font-medium text-slate-600">{transit}</span></span>'
        f'<span class="mt-0.5 block text-xs leading-5 text-slate-600">{text}</span></span></a></li>'
        for i, name, transit, text in SHIP_MENU
    )

    def row(icon_name, name, text, href="#", window=None):
        window_html = f' <span class="text-xs font-medium text-slate-600">{window}</span>' if window else ""
        return (
            f'<li><a href="{href}" class="flex gap-3 rounded-[4px] p-2.5 transition hover:bg-surface">'
            f'<span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700">{icon(icon_name, "size-4.5")}</span>'
            f'<span><span class="flex items-center gap-2 text-sm font-semibold text-ink-950">{name}{window_html}</span>'
            f'<span class="mt-0.5 block text-xs leading-5 text-slate-600">{text}</span></span></a></li>'
        )

    network_items = "".join([
        row("globe", "Network and coverage", "Supported routes, hubs and handoff points", "#page-network"),
        row("map-pinned", "Pickup and drop-off", "Collection points and local offices"),
        row("triangle-alert", "Service alerts", "Current route and carrier notices"),
    ])
    support_items = "".join([
        row("life-buoy", "Help center", "Answers on booking, payments and customs"),
        row("headset", "Contact us", "Replies in English and French, within one business day"),
        row("file-warning", "Claims", "How to report loss or damage"),
        row("landmark", "About us", "Who we are and how we work"),
    ])

    trigger = (
        '<button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-2 text-sm font-semibold {cls}" '
        'aria-haspopup="true" aria-controls="{menu}">{label} {caret}</button>'
    )

    return f'''<header class="fixed inset-x-0 top-0 z-50 border-b border-line bg-white">
    <div class="container-page flex h-[60px] items-center justify-between gap-3 lg:h-16 lg:gap-4">
        <a href="#page-index" class="flex shrink-0 items-center gap-2.5" aria-label="Corvane, Home">
            <span class="grid size-8 place-items-center rounded-[4px] bg-ink-900 text-white">
                <svg class="size-4 rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
            </span>
            <span class="font-display text-lg font-bold tracking-tight text-ink-950">Corvane</span>
        </a>
        <nav class="hidden h-full items-center gap-0.5 lg:flex" aria-label="Main">
            <div class="nav-group">
                {trigger.format(cls="text-brand-600", menu="menu-ship", label="Ship", caret=icon("chevron-down", "size-4"))}
                <div id="menu-ship" class="nav-panel left-0 w-[640px] rounded-[6px] border border-line bg-white p-5 shadow-[var(--shadow-lg)]">
                    <div class="grid grid-cols-[1.35fr_1fr] gap-5">
                        <ul class="space-y-0.5">{ship_items}
                            <li class="mt-1 border-t border-line pt-2">
                                <a href="#page-services" class="flex items-center gap-2 rounded-[4px] p-2.5 text-sm font-semibold text-ink-900 transition hover:bg-surface">
                                    {icon("boxes", "size-4 text-slate-500")} Compare all services {icon("arrow-right", "ml-auto size-4 text-slate-500")}
                                </a>
                            </li>
                        </ul>
                        <div class="border-l border-line pl-5">
                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-500 uppercase">Tools</p>
                            <ul class="mt-2.5 space-y-2 text-sm">
                                <li><a href="#" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600">{icon("calculator", "size-4 text-slate-500")} Get a quote</a></li>
                                <li><a href="#page-rates" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600">{icon("receipt", "size-4 text-slate-500")} Rates and transit times</a></li>
                                <li><a href="#" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600">{icon("box", "size-4 text-slate-500")} Packing guide</a></li>
                                <li><a href="#" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600">{icon("file-text", "size-4 text-slate-500")} Customs guide</a></li>
                                <li><a href="#" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600">{icon("triangle-alert", "size-4 text-slate-500")} Prohibited items</a></li>
                            </ul>
                            <a href="#" class="btn-primary mt-4 w-full !py-2">Get a quote</a>
                        </div>
                    </div>
                </div>
            </div>
            <a href="#page-track" class="rounded-[4px] px-3 py-2 text-sm font-semibold text-ink-900 transition hover:bg-surface">Track</a>
            <a href="#page-rates" class="rounded-[4px] px-3 py-2 text-sm font-semibold text-ink-900 transition hover:bg-surface">Rates</a>
            <div class="nav-group">
                {trigger.format(cls="text-ink-900 hover:bg-surface", menu="menu-network", label="Network", caret=icon("chevron-down", "size-4"))}
                <div id="menu-network" class="nav-panel left-0 w-[380px] rounded-[6px] border border-line bg-white p-3 shadow-[var(--shadow-lg)]">
                    <ul class="space-y-0.5">{network_items}</ul>
                </div>
            </div>
            <div class="nav-group">
                {trigger.format(cls="text-ink-900 hover:bg-surface", menu="menu-support", label="Support", caret=icon("chevron-down", "size-4"))}
                <div id="menu-support" class="nav-panel right-0 w-[420px] rounded-[6px] border border-line bg-white p-3 shadow-[var(--shadow-lg)]">
                    <ul class="space-y-0.5">{support_items}</ul>
                    <div class="mt-2 border-t border-line px-2.5 pt-3 text-xs text-slate-600">
                        <p class="flex items-center gap-1.5">{icon("mail", "size-3.5 text-slate-500")} <span class="font-medium text-ink-900">support@corvane.test</span></p>
                        <p class="mt-1.5 flex items-center gap-1.5">{icon("phone", "size-3.5 text-slate-500")} <span class="font-medium text-ink-900">+1 000 000 0000</span></p>
                        <p class="mt-1.5 flex items-center gap-1.5">{icon("clock", "size-3.5 text-slate-500")} Mon–Sat, 08:00–20:00 (UTC+1)</p>
                    </div>
                </div>
            </div>
        </nav>
        <div class="flex items-center gap-1.5 lg:gap-2">
            <form class="relative hidden xl:block" onsubmit="return false">
                <label for="header-track" class="sr-only">Tracking number</label>
                {icon("search", "pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500")}
                <input id="header-track" type="search" class="h-10 w-56 rounded-[4px] border border-slate-300 bg-surface pr-3 pl-9 text-sm text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:bg-white focus:outline-none" placeholder="Tracking number">
            </form>
            <a href="#" class="hidden items-center gap-1.5 rounded-[4px] px-2.5 py-2 text-xs font-semibold tracking-wide text-ink-700 uppercase transition hover:bg-surface lg:inline-flex" aria-label="Français">
                {icon("languages", "size-4")} FR
            </a>
            <a href="#page-account" class="hidden rounded-[4px] px-3 py-2 text-sm font-semibold text-ink-900 transition hover:bg-surface lg:inline-flex">Sign in</a>
            <a href="#" class="btn-primary !py-2">Get a quote</a>
            <button type="button" class="grid size-10 place-items-center rounded-[4px] text-ink-900 lg:hidden" aria-label="Menu">
                {icon("menu", "size-5")}
            </button>
        </div>
    </div>
</header>'''


def footer():
    columns = [
        ("Freight services", ["All services", "Air freight", "Sea freight", "Road freight", "Express", "Rates and transit times"]),
        ("Tracking and help", ["Track a shipment", "Network and coverage", "Pickup and drop-off", "Service alerts", "Help center", "Contact us"]),
        ("Guides", ["Customs guide", "Packing guide", "Prohibited items", "Shipping policy", "Claims", "About us"]),
        ("Legal", ["Terms of service", "Privacy policy", "Cookie policy", "Payment terms", "Refund policy"]),
    ]
    cols = "".join(
        f'<div><h2 class="text-xs font-semibold tracking-[0.08em] text-white uppercase">{title}</h2>'
        f'<ul class="mt-3.5 space-y-2.5 text-sm">'
        + "".join(f'<li><a href="{nav_href(link)}" class="text-slate-400 transition hover:text-white">{link}</a></li>' for link in links)
        + "</ul></div>"
        for title, links in columns
    )
    return f'''<footer class="bg-ink-950 text-slate-300">
    <div class="border-b border-white/10">
        <div class="container-page grid gap-6 py-8 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white">{icon("headset", "size-4.5")}</span>
                <div><p class="text-sm font-semibold text-white">Talk to our team</p><p class="mt-0.5 text-sm text-slate-300">+1 000 000 0000</p></div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white">{icon("mail", "size-4.5")}</span>
                <div><p class="text-sm font-semibold text-white">Email</p><p class="mt-0.5 text-sm text-slate-300">support@corvane.test</p></div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white">{icon("clock", "size-4.5")}</span>
                <div><p class="text-sm font-semibold text-white">Support hours</p><p class="mt-0.5 text-sm text-slate-300">Mon–Sat, 08:00–20:00 (UTC+1)</p></div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white">{icon("map-pin", "size-4.5")}</span>
                <div><p class="text-sm font-semibold text-white">Office</p><p class="mt-0.5 text-sm text-slate-300">Registered address to be confirmed</p></div>
            </div>
        </div>
    </div>
    <div class="container-page grid gap-10 py-12 lg:grid-cols-[1.3fr_2.7fr] lg:gap-14">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="grid size-8 place-items-center rounded-[4px] bg-brand-500 text-white">
                    <svg class="size-4 rotate-45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-white">Corvane</span>
            </div>
            <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">Air, sea, road and express freight between the United States, Europe and supported destinations worldwide. Quote, book, pay and follow every handoff in one place.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="#" class="btn-primary !py-2">Get a quote</a>
                <a href="#" class="btn-light !py-2">Track a shipment</a>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">{cols}</div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-page flex flex-col gap-3 py-6 text-xs text-slate-400 md:flex-row md:items-center md:justify-between">
            <p>© 2026 Corvane Logistics (placeholder entity). All rights reserved.</p>
            <p class="max-w-3xl md:text-right">USPS, UPS, FedEx and other carrier names are trademarks of their respective owners. They are used only to describe the networks that may handle a leg of a shipment and to recognise their tracking numbers.</p>
        </div>
    </div>
</footer>'''


def service_cards():
    cards = ""
    for image, name, transit, text, points in SERVICES:
        lis = "".join(f'<li class="flex items-start gap-2">{icon("check", "mt-0.5 size-3.5 shrink-0 text-emerald-700")} {p}</li>' for p in points)
        cards += f'''<article class="card card-hover flex flex-col overflow-hidden">
    {picture(image, name, "h-40 w-full object-cover")}
    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="text-base font-bold">{name}</h3>
            <span class="shrink-0 text-xs font-semibold whitespace-nowrap text-slate-600 tabular">{transit}</span>
        </div>
        <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{text}</p>
        <ul class="mt-3.5 space-y-1.5 border-t border-line pt-3.5 text-xs text-slate-600">{lis}</ul>
        <a href="#" class="btn-link mt-4">Service details {icon("arrow-right", "size-3.5")}</a>
    </div>
</article>'''
    return cards


def steps():
    li = ""
    for index, (name, text) in enumerate(STEPS, start=1):
        border = "border-ink-900" if index == 1 else "border-ink-300"
        li += f'''<li class="border-t-2 {border} pt-4">
    <div class="flex items-center gap-2.5">{icon("package" if index == 2 else ("calculator" if index == 1 else ("shield-check" if index == 3 else "radar")), "size-5 text-ink-700")}
        <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Step {index}</p>
    </div>
    <h3 class="mt-2 text-base font-bold">{name}</h3>
    <p class="mt-1.5 text-sm leading-6 text-slate-600">{text}</p>
</li>'''
    return li


TRUST_ITEMS = [
    ("receipt", "Prices and windows published before booking",
     "The rate tables and transit windows are on the site, not held back for a sales conversation. Your quote repeats them for your own route and weight.",
     "./rates.html", "See published prices"),
    ("shield-check", "A person reviews every payment",
     "A verifier checks the amount, the date and the reference on your proof before a label is released. Two approvals are required above a threshold.",
     "./account.html", "See the order page"),
    ("file-text", "Documents you keep",
     "Your invoice, receipt, label and commercial invoice stay downloadable in your account — not emailed once and lost.",
     "./account.html", "See the documents"),
    ("radar", "Scans with a time and a source",
     "Each tracking event records when it happened and where it came from, including the carrier handoff, so progress is checkable rather than asserted.",
     "./track.html", "See a tracking result"),
    ("headset", "Support in two languages",
     "English and French, during staffed hours, with a first reply target of one business day.",
     "#", "Open the help centre"),
    ("lock", "Payment details only in your account",
     "We never send account details or payment links by email, and we never ask for a payment on social media.",
     "./account.html", "See how to reach us"),
]


def trust():
    """The trust band on the home page: mirrors home.blade.php."""
    return "".join(
        f'''<li class="bg-white p-5">
    <span class="grid size-9 place-items-center rounded-[4px] bg-ink-50 text-ink-700">{icon(ic, "size-4")}</span>
    <h3 class="mt-3.5 text-base font-bold text-ink-950">{heading}</h3>
    <p class="mt-1.5 text-sm leading-6 text-slate-600">{text}</p>
    <a href="{href}" class="btn-link mt-3">{label}</a>
</li>'''
        for ic, heading, text, href, label in TRUST_ITEMS
    )


def operation():
    figs = ""
    for image, alt, caption in OPERATION:
        figs += f'''<figure class="overflow-hidden rounded-[6px] border border-line">
    {picture(image, alt, "aspect-[4/3] w-full object-cover")}
    <figcaption class="border-t border-line px-4 py-3 text-sm text-slate-600">{caption}</figcaption>
</figure>'''
    return figs

INLINE_DIR = Path("/tmp/preview-inline")


def inline_css(css: str) -> str:
    """Inline the latin font files and drop the subsets the preview never uses."""
    keep = ("latin-wght-normal", "latin-ext-wght-normal")
    out = []
    for block in re.split(r"(?=@font-face)", css):
        if block.startswith("@font-face"):
            match = re.search(r"url\(([^)]+)\)", block)
            name = Path(match.group(1)).name if match else ""
            if match and not any(k in name for k in keep):
                continue  # cyrillic, greek, vietnamese subsets are dead weight here
            if match:
                data = (ROOT / "node_modules" / "@fontsource-variable" / "inter" / "files" / name).read_bytes()
                block = block.replace(match.group(1), f"data:font/woff2;base64,{base64.b64encode(data).decode()}")
        out.append(block)
    return "".join(out)


def inline_image(src: str) -> str:
    """Downscaled WebP of a public image, as a data URI (cached per run)."""
    name = Path(src).name
    source = ROOT / "public" / "images" / name
    target = INLINE_DIR / f"{source.stem}-preview.webp"
    if not target.exists():
        INLINE_DIR.mkdir(parents=True, exist_ok=True)
        subprocess.run(
            ["convert", str(source), "-strip", "-resize", "1400x", "-quality", "72", "-define", "webp:method=6", str(target)],
            check=True,
        )
    return "data:image/webp;base64," + base64.b64encode(target.read_bytes()).decode()


PAGES = [
    ("index.html", "Home"),
    ("track.html", "Track"),
    ("services.html", "Services"),
    ("service-air.html", "Service detail"),
    ("rates.html", "Rates"),
    ("network.html", "Network"),
    ("account.html", "Account"),
    ("admin.html", "Back-office"),
    ("components.html", "Components"),
]


_GLOBE_EMITTED = False


def reset_globe_payload():
    """Each standalone page carries its own payload; the combined file carries one."""
    global _GLOBE_EMITTED
    _GLOBE_EMITTED = False


def globe_payload():
    """Real three.js globe plus the reference data the app ships to the browser."""
    global _GLOBE_EMITTED
    if _GLOBE_EMITTED:
        return ""
    _GLOBE_EMITTED = True

    bundle = Path("/tmp/preview-globe/globe.js")
    if not bundle.exists():
        return "<!-- preview globe bundle missing: run npx vite build --config vite.preview.config.js -->"

    land = json.loads((ROOT / "public" / "data" / "land-dots.json").read_text())
    hubs = [{"lat": lat, "lon": lon} for _c, _k, lat, lon, _m in HUBS]
    lanes = [{"from": HUB_COORDS[a], "to": HUB_COORDS[b], "mode": mode} for a, b, mode in LANES]

    return (
        '<div class="hidden" aria-hidden="true">'
        f'<script type="application/json" id="land-dots">{json.dumps(land)}</script>'
        f'<script type="application/json" id="globe-hubs">{json.dumps(hubs)}</script>'
        f'<script type="application/json" id="globe-lanes">{json.dumps(lanes)}</script>'
        "</div>"
        f"<script>{bundle.read_text()}</script>"
    )


# Single-file preview: all pages in one document, switched in place. Anchors and
# CSS do the work, so clicking a tab never needs a network request.
SWITCH_CSS = """
.preview-page { display: none; }
.preview-page:target,
.preview-page[data-active='true'] { display: block; }
html:not(.js) body:not(:has(.preview-page:target)) #page-home { display: block; }

/* The arriving page fades up, so switching reads as a change of page. */
@media (prefers-reduced-motion: no-preference) {
    .preview-page[data-active='true'] { animation: rise-in 0.35s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
}
"""

TOAST_CSS = """
.preview-toast {
    position: fixed;
    z-index: 80;
    bottom: 4.25rem;
    left: 50%;
    max-width: 22rem;
    padding: 0.65rem 0.9rem;
    border-radius: 6px;
    background: #0b1f36;
    color: #fff;
    font: 500 12px/1.5 'Inter Variable', system-ui, sans-serif;
    box-shadow: 0 10px 24px -12px rgb(6 21 38 / 0.5);
    transform: translateX(-50%);
    transition: opacity 0.15s ease;
}
.preview-toast[hidden] { display: none; }
"""

TOAST_JS = """
(function () {
    var toast = document.getElementById('preview-toast');
    if (!toast) return;
    var timer;

    function explain() {
        clearTimeout(timer);
        toast.hidden = false;
        timer = setTimeout(function () { toast.hidden = true; }, 2600);
    }

    // A page that this preview does not contain: say so rather than doing nothing.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a');
        if (!link) return;
        if (link.getAttribute('href') === '#') {
            event.preventDefault();
            explain();
        }
    });
})();
"""

SWITCH_JS = """
(function () {
    var root = document.documentElement;
    root.classList.add('js');
    var pages = Array.prototype.slice.call(document.querySelectorAll('.preview-page'));

    function show() {
        var name = location.hash.replace('#page-', '') || 'home';
        var target = document.getElementById('page-' + name) || document.getElementById('page-home');
        pages.forEach(function (page) {
            page.setAttribute('data-active', page === target ? 'true' : 'false');
        });
        // Hidden pages measure zero; nudge the canvas-backed views to re-fit.
        window.dispatchEvent(new Event('resize'));
        window.scrollTo(0, 0);
    }

    window.addEventListener('hashchange', show);
    show();
})();
"""


def combined_body():
    """Every page body wrapped in its own switchable section."""
    order = [
        ("index", "Home", build_body()),
        ("track", "Track", track_body()),
        ("services", "Services", services_body()),
        ("service-air", "Service detail", service_detail_body()),
        ("rates", "Rates", rates_body()),
        ("network", "Network", network_body()),
        ("account", "Account", account_body(icon, nav_href)),
        ("admin", "Back-office", admin_body(icon)),
        ("components", "Components", components_body()),
    ]

    # Cross-page links written as ./rates.html become in-document switches, so no
    # link in the combined file depends on a second file being served.
    def in_document(body: str) -> str:
        for href, _label in PAGES:
            body = body.replace(f'"{href}"', f'"#page-{href.removesuffix(".html")}"')
            body = body.replace(f'"./{href}"', f'"#page-{href.removesuffix(".html")}"')
        return body

    # The header is identical on every page (the active state is per-page styling,
    # not markup), so it sits once above the switchable sections: one bar, whether
    # the reader is on the home page or the back office.
    page_header = ""
    bodies = []

    for slug, label, body in order:
        found = re.search(r'<header class="fixed.*?</header>', body, re.S)

        if found and not page_header:
            page_header = found.group(0)

        bodies.append((slug, re.sub(r'<header class="fixed.*?</header>', '', body, count=1, flags=re.S)))

    return page_header + "".join(
        f'<section class="preview-page" id="page-{slug}">{in_document(body)}</section>'
        for slug, body in bodies
    ) + f"<script>{SWITCH_JS}</script>"


def with_switch(html, page, *, single=False):
    """Tabs for the preview switcher: hash links in the combined file, file links
    in the per-page files (which the local server serves standalone)."""
    links = ""
    for href, label in PAGES:
        target = href.removesuffix(".html")
        current = target == "index" if single else href == page
        url = f"#page-{target}" if single else f"./{href}"
        links += f'<a href="{url}"' + (' aria-current="page"' if current else "") + f'>{label}</a>'

    return html.replace("</body>", f'<div class="preview-switch">{links}</div></body>')


def build_body():
    return f'''{header()}
<main class="pt-[var(--header-h)]">
    <div class="border-b border-amber-200 bg-amber-50">
        <div class="container-page flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-sm">
            <span class="flex items-center gap-2 font-semibold text-ink-950">{icon("info", "size-4")} Service notice</span>
            <span class="text-ink-900">Weather delays on the Houston → Paris air lane this week.</span>
            <a href="#" class="btn-link ml-auto">All service alerts {icon("arrow-right", "size-3.5")}</a>
        </div>
    </div>

    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">
            {picture("images/hero-freight-air.webp", "Ground crew loading freight on an airport apron beside a cargo aircraft at dusk", "absolute inset-0 size-full object-cover object-right", 1600, 640, priority=True)}
        </div>
        <div class="hero-scene-overlay absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="container-page grid items-center gap-10 py-12 lg:grid-cols-12 lg:gap-8 lg:py-16">
            <div class="lg:col-span-7">
                <p class="eyebrow !text-slate-300">Air · Sea · Road · Express</p>
                <h1 class="editorial-title mt-3 max-w-2xl text-[2rem] leading-[1.08] text-white sm:text-[2.6rem] lg:text-[3.1rem]">Freight from Houston to Paris, and across our network</h1>
                <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300 sm:text-base">Compare air, sea, road and express services, see the cost and the delivery window before you book, then follow every handoff with a single tracking number.</p>

                <div class="mt-7 max-w-2xl rounded-[6px] border border-line bg-white p-2 shadow-[var(--shadow-xl)]">
                    <div class="flex gap-1 border-b border-line p-1 pb-2" role="tablist" aria-label="Track or price a shipment">
                        <button type="button" role="tab" id="hero-tab-track" aria-controls="hero-panel-track" aria-selected="true" tabindex="0"
                                class="flex flex-1 items-center justify-center gap-2 rounded-[4px] bg-ink-900 px-4 py-2.5 text-sm font-semibold text-white">Track a shipment</button>
                        <button type="button" role="tab" id="hero-tab-quote" aria-controls="hero-panel-quote" aria-selected="false" tabindex="-1"
                                class="flex flex-1 items-center justify-center gap-2 rounded-[4px] px-4 py-2.5 text-sm font-semibold text-slate-600">Price a shipment</button>
                    </div>
                    <form class="p-2" id="hero-panel-track" role="tabpanel" aria-labelledby="hero-tab-track" tabindex="0" onsubmit="return false">
                        <label for="hero-track" class="sr-only">Tracking numbers</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1">
                                {icon("search", "pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-500")}
                                <input id="hero-track" type="search" class="h-12 w-full rounded-[4px] border border-slate-300 bg-white pr-4 pl-11 text-base text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:outline-none" placeholder="Enter a tracking number or paste up to 20">
                            </div>
                            <button type="submit" class="btn-primary h-12 !px-6 text-base">Track</button>
                        </div>
                        <p class="mt-2.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-600">
                            USPS, UPS and FedEx numbers are recognised automatically.
                            <a href="#" class="font-medium text-ink-900 underline underline-offset-2 hover:text-brand-600">See an example</a>
                        </p>
                    </form>
                </div>

                <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-300">
                    <li class="flex items-center gap-2">{icon("shield-check", "size-4 shrink-0")} Every payment checked by a person</li>
                    <li class="flex items-center gap-2">{icon("truck", "size-4 shrink-0")} Local delivery by established carriers</li>
                    <li class="flex items-center gap-2">{icon("languages", "size-4 shrink-0")} Support in English and French</li>
                </ul>
            </div>

            <div class="lg:col-span-5">
                <div class="rounded-[6px] border border-line bg-white p-5 shadow-[var(--shadow-xl)]">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Sample lane</p>
                        <span class="badge bg-surface text-slate-700">USD</span>
                    </div>
                    <p class="mt-2 flex items-center gap-2 text-lg font-bold text-ink-950">Houston {icon("arrow-right", "size-4 text-slate-500")} Paris</p>
                    <table class="mt-3 w-full text-sm">
                        <caption class="sr-only">Typical transit times by service from Houston to Paris</caption>
                        <thead>
                            <tr class="border-b border-line text-left text-xs text-slate-600">
                                <th scope="col" class="pb-2 font-medium">Service</th>
                                <th scope="col" class="pb-2 text-right font-medium">Typical transit</th>
                            </tr>
                        </thead>
                        <tbody class="text-ink-900">
                            <tr class="border-b border-line"><th scope="row" class="py-2.5 text-left font-medium">Air freight</th><td class="py-2.5 text-right tabular">2–8 days</td></tr>
                            <tr class="border-b border-line"><th scope="row" class="py-2.5 text-left font-medium">Sea freight</th><td class="py-2.5 text-right tabular">10–45 days</td></tr>
                            <tr><th scope="row" class="py-2.5 text-left font-medium">Road freight</th><td class="py-2.5 text-right tabular">2–10 days</td></tr>
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs leading-5 text-slate-600">Indicative transit times. Prices for your parcel size and weight are confirmed in a quote before you book.</p>
                    <a href="#" class="btn-ghost mt-4 w-full !py-2">See all rates and transit times</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">Four services, one way of working</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">The right service depends on what you are sending, how far it is going and when it needs to arrive. Every service includes collection, customs coordination and tracking.</p>
                </div>
                <a href="#" class="btn-ghost">Compare all services</a>
            </div>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{service_cards()}</div>
        </div>
    </section>

    <section class="bg-surface py-14 lg:py-16">
        <div class="container-page">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-bold sm:text-3xl">From quote to doorstep</h2>
                <p class="mt-2.5 text-[15px] leading-7 text-slate-600">Four steps, each one visible in your account, with a person reachable at every stage.</p>
            </div>
            <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">{steps()}</ol>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-16">
        <div class="container-page grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <h2 class="text-2xl font-bold sm:text-3xl">International freight, with local delivery handled</h2>
                <p class="mt-3 text-[15px] leading-7 text-slate-600">Your shipment may pass through several teams before it reaches the door. We coordinate the long-distance leg, share clear updates at each handoff and work with established local carriers for the final delivery.</p>
                <dl class="mt-7 divide-y divide-line border-y border-line">
                    <div class="flex gap-4 py-4">
                        {icon("map-pin", "mt-0.5 size-5 shrink-0 text-ink-600")}
                        <div><dt class="font-semibold text-ink-950">North America</dt><dd class="mt-1 text-sm leading-6 text-slate-600">USPS and UPS handle local delivery on eligible routes across the United States, with one tracking journey from pickup to doorstep.</dd></div>
                    </div>
                    <div class="flex gap-4 py-4">
                        {icon("map-pin", "mt-0.5 size-5 shrink-0 text-ink-600")}
                        <div><dt class="font-semibold text-ink-950">Europe and the United Kingdom</dt><dd class="mt-1 text-sm leading-6 text-slate-600">Regional carriers complete delivery across supported European destinations, with a coordinated handover at each stage.</dd></div>
                    </div>
                    <div class="flex gap-4 py-4">
                        {icon("map-pin", "mt-0.5 size-5 shrink-0 text-ink-600")}
                        <div><dt class="font-semibold text-ink-950">Connecting routes worldwide</dt><dd class="mt-1 text-sm leading-6 text-slate-600">For destinations beyond our core lanes, our team confirms the available service and local partner before you book.</dd></div>
                    </div>
                </dl>
                <p class="mt-5 text-sm text-slate-600">13 consolidation hubs and <a href="#" class="link">pickup and drop-off points</a> across 10 countries.</p>
                <a href="#" class="btn-dark mt-6">Explore the network {icon("arrow-right", "size-4")}</a>
            </div>
            <figure class="overflow-hidden rounded-[6px] border border-line">
                {picture("images/warehouse-hub.webp", "Freight distribution hub with lit loading docks and a yard of trailers at twilight", "aspect-[16/10] w-full object-cover")}
                <figcaption class="border-t border-line bg-surface px-4 py-3 text-xs text-slate-600">Consolidation hub: freight is grouped before the long-distance leg</figcaption>
            </figure>
        </div>
    </section>

    <section class="bg-ink-950 py-14 text-white lg:py-16">
        <div class="container-page grid gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <p class="eyebrow !text-slate-300">Payments</p>
                <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">Pay the way you already pay. A person checks it.</h2>
                <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300">Choose your method at checkout, transfer the amount and upload your receipt. Our team verifies the amount, date and reference before your label is released — usually within 30 minutes during staffed hours.</p>
                <ul class="mt-6 flex flex-wrap gap-2">
                    <li class="badge bg-white/10 text-slate-100">Bank transfer (IBAN)</li>
                    <li class="badge bg-white/10 text-slate-100">Cash App</li>
                    <li class="badge bg-white/10 text-slate-100">Zelle</li>
                    <li class="badge bg-white/10 text-slate-100">PayPal</li>
                </ul>
                <p class="mt-6 border-l-2 border-brand-500 pl-4 text-sm leading-6 text-slate-300">Never pay for a shipment someone else asked you to pay for, and never send gift cards to a stranger. If in doubt, contact us first.</p>
            </div>
            <ul class="space-y-5">
                <li class="flex gap-3.5 border-l-2 border-white/20 pl-4">
                    {icon("lock", "mt-0.5 size-5 shrink-0 text-slate-400")}
                    <div><p class="font-semibold text-white">Details only on your own order</p><p class="mt-1 text-sm leading-6 text-slate-300">Payment account details never appear on public pages. They are shown to you after you choose a method.</p></div>
                </li>
                <li class="flex gap-3.5 border-l-2 border-white/20 pl-4">
                    {icon("check", "mt-0.5 size-5 shrink-0 text-slate-400")}
                    <div><p class="font-semibold text-white">Human verification</p><p class="mt-1 text-sm leading-6 text-slate-300">A verifier checks the amount, date and reference of your proof before anything is released.</p></div>
                </li>
                <li class="flex gap-3.5 border-l-2 border-white/20 pl-4">
                    {icon("file-text", "mt-0.5 size-5 shrink-0 text-slate-400")}
                    <div><p class="font-semibold text-white">Documents you can download</p><p class="mt-1 text-sm leading-6 text-slate-300">Labels, receipts, invoices and commercial invoices are ready in your account as soon as payment clears.</p></div>
                </li>
            </ul>
        </div>
    </section>

    {{-- Trust band: mirrors resources/views/pages/home.blade.php. Every claim
         points at a page, a document or a record the platform really produces. --}}
    <section class="border-y border-line bg-surface py-14 lg:py-16">
        <div class="container-page">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="lg:col-span-5">
                    <p class="eyebrow">Why you can check us</p>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">Nothing about the price appears only after you pay.</h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">Freight is a market where vague promises are common, so we keep the verifiable parts in the open. Every claim below is a page you can open, a document you can download, or a date and time recorded against your own shipment.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="./rates.html" class="btn-ghost">See published prices</a>
                        <a href="./track.html" class="btn-ghost">Look at a real tracking page</a>
                    </div>
                </div>
                <ul class="grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-2 lg:col-span-7">
                    {trust()}
                </ul>
            </div>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">What happens between booking and delivery</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">A shipment is packed, weighed, labelled, scanned at every handoff and delivered locally by a partner carrier. You can see each of those steps on the tracking page.</p>
                </div>
                <a href="#" class="btn-ghost">Track a shipment</a>
            </div>
            <div class="mt-8 grid gap-5 sm:grid-cols-3">{operation()}</div>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="grid overflow-hidden rounded-[6px] bg-ink-900 text-white lg:grid-cols-[1.4fr_1fr]">
                <div class="p-7 sm:p-9">
                    <p class="eyebrow !text-slate-300">Before the first mile</p>
                    <h2 class="mt-3 max-w-xl text-2xl font-bold text-white sm:text-3xl">Start with a route and a weight</h2>
                    <p class="mt-3.5 max-w-lg text-[15px] leading-7 text-slate-300">Share the origin, destination and packed size. We will show the price, the delivery window and the delivery network before you decide to book.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#" class="btn-primary">Build a quote {icon("arrow-right", "size-4")}</a>
                        <a href="#" class="btn-light">Create a free account</a>
                    </div>
                </div>
                <div class="relative min-h-56 lg:min-h-full">
                    {picture("images/customs-documents.webp", "Hands reviewing shipping paperwork beside a parcel and a laptop", "absolute inset-0 size-full object-cover")}
                </div>
            </div>
        </div>
    </section>
</main>
{footer()}'''


def masthead(photo, eyebrow, title, lead, actions=""):
    return f'''<section class="page-masthead relative isolate overflow-hidden">
    <div class="absolute inset-0 -z-20" aria-hidden="true">
        {picture(photo, "", "size-full object-cover object-center")}
    </div>
    <div class="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true"></div>
    <div class="container-page py-10 sm:py-12 lg:py-14">
        <p class="eyebrow !text-slate-300">{eyebrow}</p>
        <h1 class="mt-3 max-w-4xl text-[1.75rem] leading-[1.12] font-bold text-white sm:text-4xl lg:text-[2.6rem]">{title}</h1>
        <p class="mt-4 max-w-2xl text-[15px] leading-7 text-slate-300 sm:text-base">{lead}</p>
        {actions}
    </div>
</section>'''


def services_body():
    cards = ""
    for card in SERVICE_CARDS:
        included = "".join(
            f'<li class="flex items-start gap-2.5">{icon("check", "mt-0.5 size-4 shrink-0 text-emerald-700")} {point}</li>'
            for point in card["included"]
        )
        cards += f'''<article class="card card-hover flex flex-col overflow-hidden">
    {picture(card["photo"], card["name"], "h-52 w-full object-cover")}
    <div class="flex flex-1 flex-col p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h2 class="flex items-center gap-2.5 text-xl font-bold">{icon(card["icon"], "size-5 text-ink-700")} {card["name"]}</h2>
            <span class="badge bg-surface text-slate-700">{card["transit"]}</span>
        </div>
        <p class="mt-3 text-sm leading-7 text-slate-600">{card["summary"]}</p>
        <dl class="mt-5 space-y-3 border-t border-line pt-4 text-sm">
            <div>
                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">A good fit for</dt>
                <dd class="mt-1 text-ink-900">{card["fit"]}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">How it is charged</dt>
                <dd class="mt-1 text-ink-900">{card["limits"]}</dd>
            </div>
        </dl>
        <ul class="mt-5 space-y-2 text-sm text-slate-700">{included}</ul>
        <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
            <a href="./service-air.html" class="btn-dark !py-2">Service details</a>
            <a href="#" class="btn-ghost !py-2">Get a quote</a>
        </div>
    </div>
</article>'''

    rows = ""
    for card in SERVICE_CARDS:
        rows += f'''<tr>
    <th scope="row" class="px-5 py-4 text-left font-semibold text-ink-950"><span class="flex items-center gap-2">{icon(card["icon"], "size-4 text-ink-600")} {card["name"]}</span></th>
    <td class="px-5 py-4 tabular text-slate-700">{card["transit"]}</td>
    <td class="px-5 py-4 text-slate-700">{card["fit"]}</td>
    <td class="px-5 py-4 text-slate-700">{card["limits"]}</td>
    <td class="px-5 py-4"><a href="#" class="btn-link">Quote this</a></td>
</tr>'''

    actions = (
        '<div class="mt-6 flex flex-wrap gap-3">'
        f'<a href="#" class="btn-primary">Get a quote {icon("arrow-right", "size-4")}</a>'
        '<a href="./rates.html" class="btn-light">See rates and transit times</a>'
        "</div>"
    )

    return f'''{header()}
<main class="pt-[var(--header-h)]">
    {masthead("images/freight-air.webp", "Our services", "The right route for what you are sending",
              "Every shipment has its own balance of urgency, size and budget. Compare the ways we move goods, see the usual transit window and choose a service that suits the job.",
              actions)}

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="grid gap-5 sm:grid-cols-2">{cards}</div>
        </div>
    </section>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">Compare at a glance</h2>
            <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">Transit windows are typical door-to-door estimates. The final quote reflects your route, dimensions and any options you select.</p>
            <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-sm">
                        <caption class="sr-only">Comparison of services by transit time, best fit, size limits and quote</caption>
                        <thead><tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                            <th scope="col" class="px-5 py-3 font-medium">Service</th>
                            <th scope="col" class="px-5 py-3 font-medium">Typical transit</th>
                            <th scope="col" class="px-5 py-3 font-medium">Best for</th>
                            <th scope="col" class="px-5 py-3 font-medium">How it is charged</th>
                            <th scope="col" class="px-5 py-3 font-medium">Quote</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">{rows}</tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="grid gap-8 rounded-[6px] border border-line p-7 sm:p-9 lg:grid-cols-[1.5fr_1fr] lg:items-center">
                <div>
                    <h2 class="text-2xl font-bold">Not sure which service fits?</h2>
                    <p class="mt-2.5 max-w-xl text-[15px] leading-7 text-slate-600">Send us the route and a short description of what you are shipping. We will suggest the service, the likely transit window and anything you need to prepare — before you book.</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="#" class="btn-primary">Ask our team {icon("arrow-right", "size-4")}</a>
                        <a href="#" class="btn-ghost">Read the packing guide</a>
                    </div>
                </div>
                <div class="overflow-hidden rounded-[6px]">{picture("images/warehouse-hub.webp", "Freight distribution hub with lit loading docks and a yard of trailers at twilight", "aspect-[16/10] w-full object-cover")}</div>
            </div>
        </div>
    </section>
</main>
{footer()}'''


def service_detail_body():
    detail = AIR_DETAIL
    intro = "".join(f"<p>{p}</p>" for p in detail["intro"])
    limits = "".join(
        f'<li class="flex items-start gap-3 py-3.5 text-sm text-slate-700">{icon("ruler", "mt-0.5 size-4 shrink-0 text-slate-500")} {item}</li>'
        for item in detail["limits"]
    )
    documents = "".join(
        f'<li class="flex items-start gap-3 py-3.5 text-sm text-slate-700">{icon("file-text", "mt-0.5 size-4 shrink-0 text-slate-500")} {item}</li>'
        for item in detail["documents"]
    )
    before = "".join(
        f'<li class="flex items-start gap-2.5 rounded-[6px] border border-line p-3.5 text-sm text-slate-700">{icon("check", "mt-0.5 size-4 shrink-0 text-emerald-700")} {item}</li>'
        for item in [
            "Sender and recipient names, addresses and phone numbers",
            "A clear contents description and a realistic declared value",
            "Parcel dimensions and weight, measured after packing",
            "Any permits required for the goods or the destination",
        ]
    )
    # Same tables as resources/views/pages/service.blade.php, from the same rules.
    weight_rules = "".join(
        f'<tr><th scope="row" class="w-[38%] px-4 py-3.5 text-left font-semibold text-ink-950">{rule}</th>'
        f'<td class="px-4 py-3.5 text-slate-700 tabular">{explanation}</td></tr>'
        for rule, explanation in [
            ("Chargeable weight", "The greater of the scale weight and the volumetric weight, counted package by package."),
            ("Volumetric weight", "Length × width × height in centimetres, divided by the volume divisor for this service."),
            ("Volume divisor", detail["divisor"]),
            ("Rounding", "Rounded up to the next half kilogram, with a minimum of 0.5 kg."),
            ("Several packages", "Each package is weighed and measured, then the chargeable weights are added before the rate is applied."),
            ("Small shipments", "A minimum charge applies to the smallest shipments; it is always shown in your quote breakdown."),
        ]
    )
    band_rows = "".join(
        f'<li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">'
        f'<span class="w-[110px] shrink-0 font-semibold text-ink-950 tabular">{low}</span>'
        f'<span class="text-slate-700">{suits}</span></li>'
        for low, suits in [
            ("1–5 kg", "Documents, small parcels and samples"),
            ("5–20 kg", "Several parcels, or one carton packed solid"),
            ("20–50 kg", "Palletised goods moving on air or road"),
            ("50–200 kg", "Grouped cargo travelling together"),
            ("200–1,000 kg", "Part loads and heavier air freight"),
            ("1,000–3,000 kg", "Consolidated shipments and shared container space"),
        ]
    )
    paperwork = "".join(
        f'<tr><th scope="row" class="px-4 py-3.5 text-left font-semibold text-ink-950">{kind}</th>'
        f'<td class="px-4 py-3.5 text-slate-700">{attach}</td>'
        f'<td class="px-4 py-3.5 text-slate-600">{delay}</td></tr>'
        for kind, attach, delay in [
            ("Documents and letters", "A short contents description with a declared value.", "A vague description, such as “documents”."),
            ("Personal effects", "An itemised list of what is inside, with a value for each item.", "One total value with nothing itemised."),
            ("Commercial samples", "A commercial invoice that marks the goods as samples and states their value.", "A declared value that does not reflect the goods."),
            ("Goods for resale", "A commercial invoice, a packing list for every package, and the permits the destination requires.", "An invoice that does not match what is in the parcel."),
            ("Medicine and batteries", "The permit, or the safety documentation the destination authority asks for.", "No permit or safety data sheet attached."),
        ]
    )
    packaging = "".join(
        f'<li class="flex items-start gap-2.5 text-sm text-slate-700">{icon("boxes", "mt-0.5 size-4 shrink-0 text-slate-500")} {rule}</li>'
        for rule in [
            "Double-wall cartons for anything that can break, and rigid corners for flat items.",
            "About five centimetres of padding on every side so the contents cannot move.",
            "One label per package, and no old labels or barcodes left on the box.",
            "Inner protection for liquids, and nothing fragile packed loose beside them.",
        ]
    )
    covered = "".join(
        f'<li class="flex items-start gap-2.5">{icon("check", "mt-0.5 size-4 shrink-0 text-emerald-700")} {item}</li>'
        for item in [
            "Carriage on the service you booked, on the route in your quote.",
            "A tracking number, with a scan logged at each handoff.",
            "The shipping label and the documents issued with it.",
            "Coordination with the customs steps on the booked route.",
            "Email updates when the shipment status changes.",
        ]
    )
    excluded = "".join(
        f'<li class="flex items-start gap-2.5">{icon("info", "mt-0.5 size-4 shrink-0 text-slate-400")} {item}</li>'
        for item in [
            "Import duties and taxes, which are set by the destination country.",
            "Any charge a customs authority or port adds for inspection or storage.",
            "Insurance, which is arranged on request and listed in your quote.",
            "A difference in price if the measured weight or size is greater than declared — and a refund if it is smaller.",
        ]
    )

    shipping = "".join(
        f'<li class="flex gap-3">{icon(ic, "mt-0.5 size-4 shrink-0 text-slate-500")} {txt}</li>'
        for ic, txt in [
            ("scale", "Chargeable weight: the greater of scale weight and volumetric weight, rounded up to the next half kilogram."),
            ("route", "The published band for your route and weight, then this service rate factor."),
            ("receipt", "Fuel, handling, insurance and any destination charges, each listed separately in the quote."),
            ("info", "A minimum charge applies to the smallest shipments; it is shown in the quote breakdown."),
        ]
    )
    others = "".join(
        f'''<a href="./services.html" class="card card-hover flex items-center gap-4 p-5">
    <span class="grid size-10 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700">{icon(c["icon"], "size-5")}</span>
    <span class="min-w-0"><span class="block font-bold text-ink-950">{c["name"]}</span><span class="mt-0.5 block text-sm text-slate-600">{c["transit"]}</span></span>
    {icon("arrow-right", "ml-auto size-4 shrink-0 text-slate-500")}
</a>'''
        for c in SERVICE_CARDS if c["code"] != "air"
    )

    return f'''{header()}
<main class="pt-[var(--header-h)]">
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">{picture("images/freight-air.webp", "Cargo aircraft being loaded with freight on an airport apron at dusk", "size-full object-cover object-center")}</div>
        <div class="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true"></div>
        <div class="container-page py-10 sm:py-12 lg:py-14">
            <nav aria-label="Breadcrumb" class="text-sm">
                <ol class="flex items-center gap-2 text-slate-300">
                    <li><a href="./services.html" class="hover:text-white">Services</a></li>
                    <li aria-hidden="true" class="text-slate-500">/</li>
                    <li class="font-medium text-white" aria-current="page">{detail["name"]}</li>
                </ol>
            </nav>
            <div class="mt-6 grid gap-8 lg:grid-cols-[1.6fr_1fr] lg:items-end">
                <div>
                    <p class="eyebrow !text-slate-300">{icon(detail["icon"], "size-4")} Freight service</p>
                    <h1 class="mt-3 text-[2rem] leading-[1.08] font-bold text-white sm:text-[2.6rem] lg:text-[3rem]">{detail["name"]}</h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-300">{detail["tagline"]}</p>
                </div>
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-[6px] border border-white/15 bg-white/10">
                    <div class="bg-ink-950/70 p-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">Published transit</dt>
                        <dd class="mt-1 text-lg font-bold text-white tabular">{detail["transit"]}</dd>
                    </div>
                    <div class="bg-ink-950/70 p-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">Rate factor</dt>
                        <dd class="mt-1 text-lg font-bold text-white tabular">× {detail["factor"]}</dd>
                    </div>
                </dl>
            </div>
            <div class="mt-7 flex flex-wrap gap-3">
                <a href="#" class="btn-primary">Get a quote {icon("arrow-right", "size-4")}</a>
                <a href="./rates.html" class="btn-light">See rates</a>
            </div>
        </div>
    </section>

    <div class="container-page grid gap-10 py-12 lg:grid-cols-12 lg:gap-14 lg:py-14">
        <article class="lg:col-span-7">
            <div class="prose-content">
                <h2 class="!mt-0">How this service works</h2>
                {intro}
            </div>
            <section class="mt-10">
                <h2 class="text-xl font-bold">Size, weight and limits</h2>
                <ul class="mt-4 divide-y divide-line border-y border-line">{limits}</ul>
                <h3 class="mt-8 text-base font-bold">How the chargeable weight is worked out</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Every quote and every invoice uses the same method. It is worth knowing before you buy packaging, because a large light parcel can cost more than a small heavy one.</p>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line">
                    <table class="w-full text-sm">
                        <caption class="sr-only">How the chargeable weight is worked out for this service</caption>
                        <thead>
                            <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                <th scope="col" class="px-4 py-3 font-medium">Rule</th>
                                <th scope="col" class="px-4 py-3 font-medium">What it means for your shipment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">{weight_rules}</tbody>
                    </table>
                </div>
                <h3 class="mt-8 text-base font-bold">Published weight bands</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Rates are published in bands, and the price per kilogram falls as a consignment gets heavier. Anything above the top band is priced with you individually rather than quoted blind.</p>
                <ul class="mt-4 divide-y divide-line border-y border-line text-sm">
                    {band_rows}
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                        <span class="w-[110px] shrink-0 font-semibold text-ink-950">Above 3,000 kg</span>
                        <span class="text-slate-700">Tell us the details and we will build the price with you, including any handling the load needs.</span>
                    </li>
                </ul>
            </section>
            <section class="mt-10">
                <h2 class="text-xl font-bold">Documents to prepare</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Missing paperwork is the most common cause of a customs delay. Have these ready before collection.</p>
                <ul class="mt-4 divide-y divide-line border-y border-line">{documents}</ul>
                <h3 class="mt-8 text-base font-bold">Paperwork at a glance</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">What to attach depends on what you are sending. This is the short version; the customs guide covers declarations, values and duties in full.</p>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-sm">
                            <caption class="sr-only">Documents to attach by type of shipment</caption>
                            <thead>
                                <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                    <th scope="col" class="px-4 py-3 font-medium">What you are sending</th>
                                    <th scope="col" class="px-4 py-3 font-medium">What to attach</th>
                                    <th scope="col" class="px-4 py-3 font-medium">What usually holds it up</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">{paperwork}</tbody>
                        </table>
                    </div>
                </div>
                <p class="mt-4 text-sm text-slate-600"><a href="#" class="link">Read the customs guide</a> · <a href="#" class="link">Packing guide</a> · <a href="#" class="link">Restricted and prohibited goods</a></p>
            </section>
            <section class="mt-10">
                <h2 class="text-xl font-bold">Packaging that survives the journey</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Most damage is decided before the parcel is collected. A few minutes of packing prevents the claim that takes weeks to settle.</p>
                <ul class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">{packaging}</ul>
                <p class="mt-4 text-sm leading-6 text-slate-600">Keep a photograph of the packed parcel and its contents. If something does go wrong, those pictures make a claim straightforward rather than a negotiation.</p>
            </section>
            <section class="mt-10">
                <h2 class="text-xl font-bold">Before you book</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">{before}</ul>
                <p class="mt-5 text-sm leading-6 text-slate-600">Transit windows are estimates and can change with schedules, customs processing and local delivery conditions. Your quote shows the current window for the route you enter.</p>
            </section>
            <section class="mt-10">
                <h2 class="text-xl font-bold">What the price covers</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Freight pricing is easier to trust when you know where it stops. These are the two lists that decide what you pay at the end.</p>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="rounded-[6px] border border-line p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink-950">{icon("circle-check", "size-4 text-emerald-700")} Included in the price</p>
                        <ul class="mt-3.5 space-y-2.5 text-sm text-slate-700">{covered}</ul>
                    </div>
                    <div class="rounded-[6px] border border-line p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink-950">{icon("info", "size-4 text-slate-500")} Charged separately</p>
                        <ul class="mt-3.5 space-y-2.5 text-sm text-slate-700">{excluded}</ul>
                    </div>
                </div>
                <p class="mt-4 text-sm leading-6 text-slate-600">Nothing else is added later without appearing in your account first. Your order page lists every charge, and the invoice and receipt stay available there for download.</p>
            </section>
        </article>

        <aside class="space-y-5 lg:col-span-5">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-surface px-5 py-4">
                    <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">At a glance</p>
                    <p class="mt-1.5 font-bold text-ink-950">{detail["fit"]}</p>
                </div>
                <dl class="divide-y divide-line text-sm">
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5"><dt class="text-slate-600">Published transit</dt><dd class="font-semibold text-ink-900 tabular">{detail["transit"]}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5"><dt class="text-slate-600">Rate factor</dt><dd class="font-semibold text-ink-900 tabular">× {detail["factor"]}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5"><dt class="text-slate-600">Volume divisor</dt><dd class="font-semibold text-ink-900 tabular">{detail["divisor"]}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5"><dt class="text-slate-600">Tracking</dt><dd class="font-semibold text-ink-900">Included</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3.5"><dt class="text-slate-600">Insurance</dt><dd class="font-semibold text-ink-900">Optional</dd></div>
                </dl>
                <div class="border-t border-line p-5">
                    <a href="#" class="btn-primary w-full">Price this route {icon("arrow-right", "size-4")}</a>
                    <p class="mt-3 text-xs leading-5 text-slate-600">The final price depends on route, chargeable weight and the options you choose.</p>
                </div>
            </div>
            <div class="rounded-[6px] border border-line p-5">
                <h2 class="text-base font-bold">How the price is shaped</h2>
                <ul class="mt-4 space-y-3 text-sm text-slate-600">{shipping}</ul>
            </div>
            <div class="flex items-start gap-3 rounded-[6px] bg-surface p-4 text-sm">
                {icon("headset", "mt-0.5 size-5 shrink-0 text-slate-500")}
                <p class="text-slate-600">Questions about this service? <a href="#" class="link">Talk to our team</a></p>
            </div>
        </aside>
    </div>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">Other ways to move goods</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-3">{others}</div>
        </div>
    </section>
</main>
{footer()}'''


def rates_body():
    rows = ""
    for lane, mode, prices, transit in RATE_ROWS:
        cells = "".join(f'<td class="px-5 py-3.5 text-right text-ink-900 tabular">{prices.get(w, "—")}</td>' for w in WEIGHTS)
        rows += f'''<tr>
    <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{lane}</th>
    <td class="px-5 py-3.5"><span class="inline-flex items-center gap-2 text-slate-700">{icon(MODE_ICON[mode], "size-4 text-ink-600")} {MODE_LABEL[mode]}</span></td>
    {cells}
    <td class="px-5 py-3.5 text-right text-slate-700 tabular">{transit} days</td>
</tr>'''

    lanes = {}
    for lane, mode, prices, transit in RATE_ROWS:
        lanes.setdefault(lane, []).append((mode, prices, transit))
    mobile = ""
    for lane, services in lanes.items():
        items = ""
        for mode, prices, transit in services:
            cells = "".join(
                f'<div class="rounded-[4px] bg-surface px-2.5 py-2"><dt class="text-xs text-slate-600">{w} kg</dt><dd class="mt-0.5 font-semibold text-ink-900 tabular">{prices.get(w, "—")}</dd></div>'
                for w in WEIGHTS
            )
            items += f'''<li class="p-4">
    <div class="flex items-center justify-between gap-3">
        <span class="inline-flex items-center gap-2 text-sm font-semibold text-ink-950">{icon(MODE_ICON[mode], "size-4 text-ink-600")} {MODE_LABEL[mode]}</span>
        <span class="text-xs text-slate-600 tabular">{transit} days</span>
    </div>
    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">{cells}</dl>
</li>'''
        mobile += f'''<div class="card overflow-hidden">
    <div class="border-b border-line bg-surface px-4 py-3"><p class="font-bold text-ink-950">{lane}</p></div>
    <ul class="divide-y divide-line">{items}</ul>
</div>'''

    cards = "".join(
        f'''<div class="card p-5">
    <span class="grid size-10 place-items-center rounded-[4px] bg-ink-50 text-ink-700">{icon(ic, "size-5")}</span>
    <h3 class="mt-3.5 text-base font-bold">{heading}</h3>
    <p class="mt-1.5 text-sm leading-6 text-slate-600">{text}</p>
</div>'''
        for ic, heading, text in [
            ("scale", "Weight and dimensions", "Chargeable weight compares the scale weight with the parcel volume (L × W × H ÷ the divisor for the selected service), and the greater value is used."),
            ("route", "Route and service", "Origin, destination and the transport mode shape the base rate, the fuel component and the transit window."),
            ("receipt", "Options and duties", "Insurance, handling and destination charges are listed separately in the quote, so nothing is hidden in the total."),
        ]
    )
    transit_cards = "".join(
        f'''<div class="rounded-[4px] bg-surface px-3 py-3">
    <dt class="flex items-center justify-center gap-1.5 text-xs text-slate-600">{icon(MODE_ICON[m], "size-3.5")} {MODE_LABEL[m]}</dt>
    <dd class="mt-1 text-sm font-bold text-ink-950 whitespace-nowrap">{MODE_TRANSIT[m]}</dd>
</div>'''
        for m in ("air", "sea", "road")
    )

    actions = (
        '<div class="mt-6 flex flex-wrap gap-3">'
        f'<a href="#" class="btn-primary">Build a route-specific quote {icon("arrow-right", "size-4")}</a>'
        '<a href="./network.html" class="btn-light">See the corridors we run</a>'
        "</div>"
    )

    return f'''{header()}
<main class="pt-[var(--header-h)]">
    {masthead("images/customs-documents.webp", "Price guide", "Know the likely cost before you commit",
              "Use these sample prices and delivery windows as a starting point. Your own quote is calculated from the route, service and packed dimensions you enter.",
              actions)}

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">Sample lane prices</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">Indicative prices for a compact parcel on selected routes, in US dollars. Fuel and handling are included where listed; duties and taxes are shown separately when they apply.</p>
                </div>
                <span class="badge bg-surface text-slate-700">Current rate card</span>
            </div>

            <div class="mt-7 hidden overflow-hidden rounded-[6px] border border-line md:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <caption class="sr-only">Indicative prices by lane, service and parcel weight</caption>
                        <thead><tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                            <th scope="col" class="px-5 py-3 font-medium">Lane</th>
                            <th scope="col" class="px-5 py-3 font-medium">Service</th>
                            {"".join(f'<th scope="col" class="px-5 py-3 text-right font-medium">{w} kg</th>' for w in WEIGHTS)}
                            <th scope="col" class="px-5 py-3 text-right font-medium">Transit</th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">{rows}</tbody>
                    </table>
                </div>
            </div>

            <div class="mt-7 space-y-4 md:hidden">{mobile}</div>

            <p class="mt-4 text-xs leading-5 text-slate-600">Prices are shown in USD for a sample parcel measuring 10 × 10 × 10 cm. Values in this preview are placeholders for layout review; the published page computes them from the active rate card.</p>
        </div>
    </section>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">How a price is built</h2>
            <div class="mt-7 grid gap-5 md:grid-cols-3">{cards}</div>
            <div class="mt-7 grid gap-5 rounded-[6px] border border-line bg-white p-6 sm:grid-cols-[1.6fr_1fr] sm:items-center sm:p-8">
                <div>
                    <h3 class="text-lg font-bold">Transit windows by service</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-600">Typical door-to-door estimates for the lanes above. Customs processing and local delivery can extend these on individual shipments.</p>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-center">{transit_cards}</dl>
            </div>
        </div>
    </section>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-6 rounded-[6px] bg-ink-900 p-7 text-white sm:p-9">
                <div>
                    <h2 class="text-2xl font-bold text-white">Want the exact price for your parcel?</h2>
                    <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-300">Enter the route, weight and dimensions to see the price, the transit window and the delivery network for your specific shipment.</p>
                </div>
                <a href="#" class="btn-primary">Build a quote {icon("arrow-right", "size-4")}</a>
            </div>
        </div>
    </section>
</main>
{footer()}'''


def network_body():
    corridors = ""
    for lane, mode, _prices, transit in RATE_ROWS[:6]:
        corridors += f'''<tr>
    <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{lane}</th>
    <td class="px-5 py-3.5"><span class="inline-flex items-center gap-2 text-slate-700">{icon(MODE_ICON[mode], "size-4 text-ink-600")} {MODE_LABEL[mode]}</span></td>
    <td class="px-5 py-3.5 text-right tabular text-slate-700">{transit} days</td>
</tr>'''

    region_cards = "".join(
        f'<div class="bg-white p-5"><h3 class="text-sm font-bold">{label}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{network}</p></div>'
        for _code, label, network, _lat, _lon in REGIONS
    )
    region_buttons = "".join(
        f'<button type="button" data-globe-region data-lat="{lat}" data-lon="{lon}" class="rounded-[4px] border border-white/15 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-white/40 hover:text-white">{label}</button>'
        for _code, label, _network, lat, lon in REGIONS
    )
    hub_rows = "".join(
        f'''<tr>
    <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{city} Hub</th>
    <td class="px-5 py-3.5 text-slate-700">{city}</td>
    <td class="px-5 py-3.5 text-slate-700">{country}</td>
    <td class="px-5 py-3.5"><span class="flex flex-wrap gap-1.5">{"".join(f'<span class="badge bg-surface text-slate-700">{MODE_LABEL[m]}</span>' for m in modes)}</span></td>
</tr>'''
        for city, country, _lat, _lon, modes in HUBS
    )

    return f'''{header()}
<main class="pt-[var(--header-h)]">
    {masthead("images/warehouse-hub.webp", "Network and coverage", "A clearer picture of the journey",
              "Explore the corridors we run, look up a city or drop a pin on the globe. We will show the delivery option available for that point and how far it is from a network hub.",
              "")}

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page grid gap-10 lg:grid-cols-[1fr_1.35fr] lg:gap-12">
            <div class="space-y-5">
                <div class="card p-5">
                    <h2 class="text-base font-bold">Check coverage for a place</h2>
                    <p class="mt-1 text-sm text-slate-600">Search a city to see the delivery option that applies and the nearest hub.</p>
                    <div class="mt-4">
                        <label class="field-label" for="net-city">City or postcode</label>
                        <div class="relative">
                            {icon("search", "pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500")}
                            <input id="net-city" class="field !pl-9" placeholder="Houston" value="Houston">
                        </div>
                    </div>
                    <details class="mt-4 border-t border-line pt-4">
                        <summary class="cursor-pointer text-sm font-medium text-slate-600 hover:text-ink-900">Search by coordinates instead</summary>
                        <form class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_auto]" onsubmit="return false">
                            <div><label class="field-label" for="net-lat">Latitude</label><input id="net-lat" class="field" value="29.7604"></div>
                            <div><label class="field-label" for="net-lon">Longitude</label><input id="net-lon" class="field" value="-95.3698"></div>
                            <button type="submit" class="btn-dark self-end !py-2.5">Check</button>
                        </form>
                    </details>
                </div>

                <div class="card p-5">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Selected point</p>
                            <p class="mt-1 text-base font-bold text-ink-950">Houston, United States</p>
                            <p class="mt-0.5 font-mono text-xs text-slate-600">29.7604, -95.3698</p>
                        </div>
                        <dl class="divide-y divide-line border-y border-line text-sm">
                            <div class="flex items-start justify-between gap-4 py-3"><dt class="text-slate-600">Local delivery</dt><dd class="text-right font-medium text-ink-900">USPS and UPS last-mile delivery</dd></div>
                            <div class="flex items-start justify-between gap-4 py-3"><dt class="text-slate-600">Nearest hub</dt><dd class="text-right font-medium text-ink-900">Houston · <span class="tabular">0</span> km</dd></div>
                        </dl>
                        <a href="#" class="btn-primary w-full">Plan a shipment from here {icon("arrow-right", "size-4")}</a>
                    </div>
                </div>

                <div class="rounded-[6px] border border-line bg-surface p-4 text-xs leading-5 text-slate-600">
                    <p class="flex items-start gap-2">{icon("info", "mt-0.5 size-4 shrink-0 text-slate-500")} Coverage shown here is indicative. We confirm the available service and the local delivery partner for your route before you book.</p>
                </div>
            </div>

            <div class="min-w-0">
                <div data-globe-wrap class="rounded-[6px] bg-ink-950 p-3 sm:p-4">
                    <div class="relative aspect-square w-full sm:aspect-[4/3]">
                        <div data-preview-globe data-distance="3.1" class="absolute inset-0 cursor-grab active:cursor-grabbing"></div>
                        <div class="absolute right-3 bottom-3 flex items-center gap-1.5">
                            <button type="button" data-globe-zoom="in" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" aria-label="Zoom in">{icon("plus", "size-4")}</button>
                            <button type="button" data-globe-zoom="out" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" aria-label="Zoom out">{icon("minus", "size-4")}</button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-white/10 pt-3">{region_buttons}</div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-600">Drag to rotate. Select a point to check coverage. <span data-globe-fit class="text-slate-500"></span></p>
            </div>
        </div>
    </section>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">Corridors we run today</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">These are the lanes our team works with most often. Others are quoted case by case with a confirmed delivery partner before you book.</p>
                </div>
                <a href="./rates.html" class="btn-ghost">See rates and transit times</a>
            </div>
            <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                <table class="w-full text-sm">
                    <caption class="sr-only">Sample corridors with the service used and typical transit time</caption>
                    <thead><tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                        <th scope="col" class="px-5 py-3 font-medium">Lane</th>
                        <th scope="col" class="px-5 py-3 font-medium">Service</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Typical transit</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">{corridors}</tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-600">Transit times are typical door-to-door estimates, not guarantees.</p>
        </div>
    </section>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">Who completes the last mile</h2>
            <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">Long-distance legs are coordinated by our team. The final delivery is completed by a carrier or partner with a local presence, so your tracking stays on one journey.</p>
            <div class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-2 lg:grid-cols-5">{region_cards}</div>
        </div>
    </section>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h2 class="text-2xl font-bold sm:text-3xl">Hubs and handoff points</h2>
                    <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">Freight is consolidated and checked at these locations before the long-distance leg.</p>
                </div>
                <a href="#" class="btn-ghost">All locations</a>
            </div>
            <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                <table class="w-full text-sm">
                    <caption class="sr-only">Network hubs with their city, country and available services</caption>
                    <thead><tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                        <th scope="col" class="px-5 py-3 font-medium">Hub</th>
                        <th scope="col" class="px-5 py-3 font-medium">City</th>
                        <th scope="col" class="px-5 py-3 font-medium">Country</th>
                        <th scope="col" class="px-5 py-3 font-medium">Services</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">{hub_rows}</tbody>
                </table>
            </div>
        </div>
    </section>
</main>
{footer()}
{globe_payload()}'''


def _track_stamp(hours_ago):
    """Roughly what Intl.DateTimeFormat produces in the app (preview display only)."""
    when = datetime.now() - timedelta(hours=hours_ago)
    return when.strftime("%-d %b %Y, %H:%M")


def track_body():
    # ---- hero -------------------------------------------------------------
    chips = "".join(
        f'<span class="rounded-[4px] border border-white/15 px-2 py-0.5 font-medium text-slate-200">{name}</span>'
        for name in TRACK_FORMATS
    )

    # ---- found result ------------------------------------------------------
    rail = "".join(
        f'''<li>
    <div class="h-1 rounded-full {done}"></div>
    <p class="mt-2 text-[11px] leading-4 {label}">{label_text}</p>
</li>'''
        for label_text, threshold in TRACK_MILESTONES
        for done in ["bg-brand-500" if TRACK_FOUND["progress"] * 100 >= threshold else "bg-line"]
        for label in ["font-semibold text-ink-900" if TRACK_FOUND["progress"] * 100 >= threshold else "text-slate-500"]
    )

    timeline = ""
    for index, (label, place, hours, source) in enumerate(TRACK_FOUND["events"]):
        dot = 'bg-brand-500 ring-4 ring-brand-500/15' if index == 0 else 'bg-slate-300'
        line = '' if index == len(TRACK_FOUND["events"]) - 1 else '<span class="absolute top-4 bottom-[-6px] w-px bg-line"></span>'
        title = 'text-ink-950' if index == 0 else 'text-slate-700'
        timeline += f'''<li class="relative flex gap-4 pb-6 last:pb-0">
    <span class="relative flex w-3 shrink-0 justify-center">
        <span class="mt-1.5 size-2.5 rounded-full {dot}"></span>
        {line}
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold {title}">{label}</p>
        <p class="mt-0.5 text-xs text-slate-600"><span class="tabular">{_track_stamp(hours)}</span> · {place}</p>
        <p class="mt-1.5 text-[11px] font-medium tracking-wide text-slate-500 uppercase">Source: <span class="normal-case">{source}</span></p>
    </div>
</li>'''

    status_cards = "".join(
        f'''<div class="flex gap-3 border-t border-line pt-4">
    {icon(ic, "mt-0.5 size-4 shrink-0 text-slate-500")}
    <div><dt class="text-sm font-semibold text-ink-950">{label}</dt><dd class="mt-0.5 text-sm leading-6 text-slate-600">{text}</dd></div>
</div>'''
        for label, ic, text in TRACK_STATUS_MEANINGS
    )

    faqs = "".join(
        f'''<details class="group px-6">
    <summary class="flex cursor-pointer items-center justify-between gap-4 py-4 text-sm font-semibold text-ink-950">{q} {icon("chevron-down", "size-4 shrink-0 text-slate-500 transition group-open:rotate-180")}</summary>
    <p class="pb-5 text-sm leading-6 text-slate-600">{a}</p>
</details>'''
        for q, a in TRACK_FAQS
    )

    how_rows = "".join(
        f'''<div class="border-t border-line pt-4">
    <p class="flex items-center gap-2 text-sm font-semibold text-ink-950">{icon(ic, "size-4 text-ink-600")} {heading}</p>
    <p class="mt-1.5 text-sm leading-6 text-slate-600">{text}</p>
</div>'''
        for ic, heading, text in [
            ("package", "Recorded at handoffs", "Collection, departure, arrival, customs clearance and the handover to the delivery partner are entered by our own team as they happen."),
            ("radar", "Carrier data where we have it", "For a recognised USPS, UPS or FedEx number we read that carrier's own tracking data and show it next to the source, so you can tell who reported what."),
            ("refresh-cw", "Cached, not hammered", "Carrier responses are cached for 15 minutes. A refresh shows the last known position instead of failing when the carrier is slow or busy."),
            ("lock", "Public fields only", "This page is built from the public view of a shipment: city, country, status, time and source. Nothing else leaves the account."),
        ]
    )

    return f'''{header()}
<main class="pt-[var(--header-h)]">
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">{picture("images/global-globe-logistics.webp", "World map of trade routes", "size-full object-cover object-center")}</div>
        <div class="absolute inset-0 -z-10 bg-ink-950/88" aria-hidden="true"></div>
        <div class="container-page grid gap-10 py-10 sm:py-12 lg:grid-cols-[1.15fr_1fr] lg:items-center lg:gap-14 lg:py-14">
            <div>
                <p class="eyebrow !text-slate-300">{icon("radar", "size-4")} Shipment tracking</p>
                <h1 class="mt-3 text-[2rem] leading-[1.08] font-bold text-white sm:text-[2.6rem] lg:text-[3rem]">See where the journey stands.</h1>
                <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300 sm:text-base">Enter up to 20 tracking numbers, one per line or separated by commas. We recognise supported USPS, UPS, FedEx and Corvane numbers, then bring the available scans together so you do not have to check several sites.</p>

                <form class="mt-7 grid gap-3 sm:grid-cols-[1fr_auto]" onsubmit="return false">
                    <label for="track-input" class="sr-only">Tracking numbers</label>
                    <textarea id="track-input" rows="2" spellcheck="false" class="min-h-[3.25rem] w-full resize-y rounded-[6px] border-0 bg-white px-4 py-3.5 font-mono text-sm text-ink-900 placeholder:font-sans placeholder:text-slate-500 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="CV-AIR-100013, 1Z999AA10123456784"></textarea>
                    <button type="button" class="btn-primary h-[3.25rem] !px-7">{icon("search", "size-4")} Track</button>
                </form>

                <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-400">
                    <span class="font-semibold tracking-[0.08em] uppercase">Recognised formats</span>{chips}
                </p>
            </div>

            <div class="rounded-[6px] border border-white/15 bg-white/[0.06] p-5 sm:p-6">
                <p class="text-sm font-semibold text-white">What you get from a number</p>
                <ul class="mt-4 space-y-3.5 text-sm text-slate-300">
                    <li class="flex gap-3">{icon("route", "mt-0.5 size-4 shrink-0 text-route-400")} Every recorded handoff in order, with the place and the time.</li>
                    <li class="flex gap-3">{icon("info", "mt-0.5 size-4 shrink-0 text-route-400")} The source of each scan, so you know whether it came from our team or from a carrier.</li>
                    <li class="flex gap-3">{icon("globe", "mt-0.5 size-4 shrink-0 text-route-400")} The route on a map, the expected delivery date and the last-mile partner.</li>
                    <li class="flex gap-3">{icon("lock", "mt-0.5 size-4 shrink-0 text-route-400")} Cities and countries only. No street addresses, phone numbers or emails are ever published.</li>
                </ul>
                <p class="mt-5 border-t border-white/10 pt-4 text-xs leading-5 text-slate-400">Your number is issued when your payment is approved. Until then the booking reference in your account shows the status of the order itself.</p>
            </div>
        </div>
    </section>

    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page grid gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="space-y-6 lg:col-span-7">
                <article class="card overflow-hidden">
                    <div class="border-b border-line p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{TRACK_FOUND["carrier"]["name"]} · {TRACK_FOUND["service"]}</p>
                                <p class="mt-1.5 font-mono text-lg font-bold break-all text-ink-950">{TRACK_FOUND["number"]}</p>
                                <p class="mt-1 text-xs text-slate-600">Last update <span class="tabular">{_track_stamp(TRACK_FOUND["events"][0][2])}</span></p>
                            </div>
                            <span class="badge bg-ink-50 text-ink-800 ring-1 ring-ink-600/20 ring-inset">{TRACK_FOUND["status_label"]}</span>
                        </div>

                        <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-start gap-3">
                            <div class="min-w-0"><p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">From</p><p class="mt-1 text-sm font-semibold text-ink-950">{TRACK_FOUND["origin"]}</p></div>
                            {icon("arrow-right", "mt-5 size-5 text-slate-400")}
                            <div class="min-w-0 text-right"><p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">To</p><p class="mt-1 text-sm font-semibold text-ink-950">{TRACK_FOUND["destination"]}</p></div>
                        </div>

                        <ol class="mt-6 grid grid-cols-3 gap-x-2 gap-y-4 sm:grid-cols-6">{rail}</ol>

                        <dl class="mt-6 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Expected delivery</dt><dd class="mt-1 text-sm font-semibold text-ink-950 tabular">{_track_stamp(-TRACK_FOUND["eta_hours"])}</dd></div>
                            <div><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Weight</dt><dd class="mt-1 text-sm font-semibold text-ink-950 tabular">{TRACK_FOUND["weight_kg"]} kg</dd></div>
                            <div><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Last mile</dt><dd class="mt-1 text-sm font-semibold text-slate-500">Handled by our network</dd></div>
                            <div><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Data source</dt><dd class="mt-1 text-sm font-semibold text-ink-950">{TRACK_FOUND["data_source"]}</dd></div>
                        </dl>
                    </div>

                    <div class="p-6">
                        <h2 class="text-sm font-semibold tracking-[0.08em] text-slate-600 uppercase">Journey so far</h2>
                        <ol class="mt-5">{timeline}</ol>
                        <p class="mt-2 flex items-start gap-2 border-t border-line pt-4 text-xs leading-5 text-slate-600">{icon("info", "mt-0.5 size-3.5 shrink-0 text-slate-400")} Scans are recorded at handoffs. Quiet periods between two events are normal, especially on sea freight.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-line bg-white p-4">
                        <button type="button" class="btn-ghost !py-2 text-xs">{icon("globe", "size-4")} Show the route</button>
                        <button type="button" class="btn-ghost !py-2 text-xs">{icon("copy", "size-4")} Copy share link</button>
                        <button type="button" class="btn-ghost !py-2 text-xs">{icon("bell", "size-4")} Email me updates</button>
                        <button type="button" class="btn-ghost ml-auto !py-2 text-xs">{icon("qr-code", "size-4")} QR code</button>
                    </div>
                </article>

                <article class="card overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-[4px] bg-amber-50 text-amber-700">{icon("circle-alert", "size-5")}</span>
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-semibold break-all text-ink-950">{TRACK_NOT_FOUND["number"]}</p>
                                <p class="mt-1.5 text-sm leading-6 text-slate-600">{TRACK_NOT_FOUND["message"]}</p>
                                <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                                    <li class="flex gap-2">{icon("check", "mt-0.5 size-3.5 shrink-0 text-slate-400")} Check for a missing character or a space in the middle of the number.</li>
                                    <li class="flex gap-2">{icon("check", "mt-0.5 size-3.5 shrink-0 text-slate-400")} Tracking numbers appear once the payment for the shipment is approved.</li>
                                    <li class="flex gap-2">{icon("check", "mt-0.5 size-3.5 shrink-0 text-slate-400")} Newly created labels can take a few hours before the first scan is recorded.</li>
                                </ul>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <a href="#" class="btn-ghost !py-2">Check on the carrier website {icon("external-link", "size-3.5")}</a>
                                    <a href="#" class="btn-ghost !py-2">Ask us to look into it</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <section class="card overflow-hidden">
                    {picture("images/freight-express.webp", "Parcels moving along a belt loader into an aircraft hold", "h-40 w-full object-cover")}
                    <div class="p-6">
                        <h2 class="text-lg font-bold">How a scan reaches this page</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">{how_rows}</div>
                    </div>
                </section>

                <section class="card p-6">
                    <h2 class="text-lg font-bold">What each status means</h2>
                    <p class="mt-1.5 text-sm leading-6 text-slate-600">Statuses come from the shipment record itself, not from a summary written by hand. Here is what each one tells you.</p>
                    <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2">{status_cards}</dl>
                </section>

                <section class="card overflow-hidden">
                    <h2 class="border-b border-line px-6 py-5 text-lg font-bold">Tracking questions</h2>
                    <div class="divide-y divide-line">{faqs}</div>
                </section>
            </div>

            <aside class="lg:col-span-5">
                <div class="space-y-5 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
                    <div class="overflow-hidden rounded-[6px] bg-ink-950" id="route-globe">
                        <div data-globe-wrap class="relative aspect-square">
                            <div data-preview-globe data-distance="2.9" data-route="29.7604,-95.3698,48.8566,2.3522" class="absolute inset-0 cursor-grab active:cursor-grabbing"></div>
                            <div class="absolute top-4 left-4 text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">Route map</div>
                            <div class="absolute right-3 bottom-3 flex items-center gap-1.5">
                                <button type="button" data-globe-zoom="in" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" aria-label="Zoom in">{icon("plus", "size-4")}</button>
                                <button type="button" data-globe-zoom="out" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" aria-label="Zoom out">{icon("minus", "size-4")}</button>
                            </div>
                        </div>
                        <div class="border-t border-white/10 p-5">
                            <p class="flex items-center gap-2 text-sm font-semibold text-white">{icon("info", "size-4 text-route-400")} About this map</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Select a result and press "Show the route" to place the journey on the globe. The line follows the recorded handoffs, not a live satellite position.</p>
                        </div>
                    </div>

                    <div class="rounded-[6px] border border-line bg-white p-5">
                        <h2 class="text-base font-bold">Still stuck?</h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">Send us the number and what you expected to see. A person reads every message, and we answer in English and French.</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            <a href="#" class="btn-primary !py-2">Contact us {icon("arrow-right", "size-4")}</a>
                            <a href="#" class="btn-ghost !py-2">Help center</a>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-6 rounded-[6px] bg-ink-900 p-7 text-white sm:p-9">
                <div>
                    <h2 class="text-2xl font-bold text-white">Following a parcel, or about to send one?</h2>
                    <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-300">Get a price for your own shipment, with the transit window and the services available on that route, before you commit to anything.</p>
                </div>
                <a href="#" class="btn-primary">Get a quote {icon("arrow-right", "size-4")}</a>
            </div>
        </div>
    </section>
</main>
{footer()}
{globe_payload()}'''


def components_body():
    return f'''{header()}
<main class="pt-[var(--header-h)]">
    <section class="page-masthead relative isolate overflow-hidden">
        <div class="absolute inset-0 -z-20" aria-hidden="true">
            {picture("images/freight-sea.webp", "", "size-full object-cover object-center")}
        </div>
        <div class="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true"></div>
        <div class="container-page py-10 sm:py-12 lg:py-14">
            <p class="eyebrow !text-slate-300">Our services</p>
            <h1 class="mt-3 max-w-4xl text-[1.75rem] leading-[1.12] font-bold text-white sm:text-4xl lg:text-[2.6rem]">The right route for what you are sending</h1>
            <p class="mt-4 max-w-2xl text-[15px] leading-7 text-slate-300 sm:text-base">Every sub-page opens with the same navy masthead. A photograph sits behind a dark wash so the pattern stays consistent across services, rates, help and contact.</p>
        </div>
    </section>

    <section class="bg-white py-14 lg:py-16">
        <div class="container-page space-y-12">
            <div>
                <h2 class="text-xl font-bold">Buttons</h2>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="#" class="btn-primary">Primary action</a>
                    <a href="#" class="btn-dark">Secondary action</a>
                    <a href="#" class="btn-ghost">Tertiary action</a>
                    <a href="#" class="btn-link">Text link {icon("arrow-right", "size-3.5")}</a>
                    <button type="button" class="btn-primary" disabled>Disabled</button>
                </div>
                <div class="mt-4 rounded-[6px] bg-ink-950 p-4">
                    <a href="#" class="btn-light">On dark surfaces</a>
                </div>
                <p class="mt-3 text-sm text-slate-600">One radius (4px), one weight, no lift on hover. The signal accent is reserved for the primary action.</p>
            </div>

            <div>
                <h2 class="text-xl font-bold">Fields and status chips</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="field-label" for="d1">Tracking number</label>
                        <input id="d1" class="field font-mono" value="CV-AIR-100016">
                    </div>
                    <div>
                        <label class="field-label" for="d2">Service</label>
                        <select id="d2" class="field"><option>Air freight</option><option>Sea freight</option></select>
                    </div>
                    <div>
                        <label class="field-label" for="d3">Weight (kg)</label>
                        <input id="d3" class="field" value="abc">
                        <p class="field-error">Enter the weight in kilograms.</p>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="badge bg-emerald-50 text-emerald-800 ring-1 ring-emerald-700/20 ring-inset">Delivered</span>
                    <span class="badge bg-amber-50 text-amber-900 ring-1 ring-amber-700/20 ring-inset">At customs</span>
                    <span class="badge bg-red-50 text-red-800 ring-1 ring-red-700/20 ring-inset">Returned</span>
                    <span class="badge bg-slate-100 text-slate-700 ring-1 ring-slate-500/20 ring-inset">Awaiting payment</span>
                    <span class="badge bg-ink-50 text-ink-800 ring-1 ring-ink-600/20 ring-inset">In transit</span>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold">Panel and data table</h2>
                <div class="mt-4 grid gap-5 lg:grid-cols-3">
                    <div class="card lg:col-span-2">
                        <div class="border-b border-line px-5 py-4">
                            <h3 class="font-bold text-ink-950">Sample lane prices</h3>
                            <p class="mt-1 text-xs text-slate-600">Indicative rates for a compact parcel on selected routes</p>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line text-left text-xs text-slate-600">
                                    <th scope="col" class="px-5 py-3 font-medium">Lane</th>
                                    <th scope="col" class="px-5 py-3 font-medium">Service</th>
                                    <th scope="col" class="px-5 py-3 text-right font-medium">1 kg</th>
                                    <th scope="col" class="px-5 py-3 text-right font-medium">Transit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-line"><th scope="row" class="px-5 py-3 text-left font-medium text-ink-950">Houston → Paris</th><td class="px-5 py-3 text-slate-600">Air</td><td class="px-5 py-3 text-right tabular">$116</td><td class="px-5 py-3 text-right tabular">4–8 days</td></tr>
                                <tr class="border-b border-line"><th scope="row" class="px-5 py-3 text-left font-medium text-ink-950">New York → London</th><td class="px-5 py-3 text-slate-600">Air</td><td class="px-5 py-3 text-right tabular">$108</td><td class="px-5 py-3 text-right tabular">2–5 days</td></tr>
                                <tr><th scope="row" class="px-5 py-3 text-left font-medium text-ink-950">Lagos → London</th><td class="px-5 py-3 text-slate-600">Sea</td><td class="px-5 py-3 text-right tabular">$40</td><td class="px-5 py-3 text-right tabular">25–45 days</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="space-y-5">
                        <div class="card p-5">
                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Order summary</p>
                            <p class="mt-2 text-2xl font-bold text-ink-950 tabular">$248.00</p>
                            <dl class="mt-3 divide-y divide-line text-sm">
                                <div class="flex justify-between py-2"><dt class="text-slate-600">Freight</dt><dd class="text-ink-900 tabular">$210.00</dd></div>
                                <div class="flex justify-between py-2"><dt class="text-slate-600">Fuel and handling</dt><dd class="text-ink-900 tabular">$38.00</dd></div>
                            </dl>
                            <a href="#" class="btn-primary mt-4 w-full">Pay for this order</a>
                        </div>
                        <div class="rounded-[6px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">Before you pay</p>
                            <p class="mt-1 leading-6">Only pay for a shipment you booked yourself. If someone asked you to pay, stop and contact us first.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold">Photography in cards</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{service_cards()}</div>
            </div>
        </div>
    </section>
</main>
{footer()}'''


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for stale in ("index.html", "app.css"):
        (OUT / stale).unlink(missing_ok=True)
    if (OUT / "files").exists():
        shutil.rmtree(OUT / "files")

    subprocess.run(
        ["npx", "-y", "@tailwindcss/cli@4", "-i", "resources/css/app.css", "-o", str(OUT / "app.css")],
        cwd=ROOT, check=True,
    )

    css = inline_css((OUT / "app.css").read_text())

    template = (ROOT / "preview" / "shell.html").read_text().replace(
        "<!--PREVIEW_CSS-->", f"<style>{css}</style>"
    )

    # Preview-only globe bundle (the real three.js globe from resources/js).
    subprocess.run(
        ["npx", "vite", "build", "--config", "vite.preview.config.js"],
        cwd=ROOT, check=True, stdout=subprocess.DEVNULL,
    )

    pages = {
        "index.html": ("Design preview · Corvane", build_body),
        "track.html": ("Track · Corvane design preview", track_body),
        "services.html": ("Services · Corvane design preview", services_body),
        "service-air.html": ("Air freight · Corvane design preview", service_detail_body),
        "rates.html": ("Rates · Corvane design preview", rates_body),
        "network.html": ("Network · Corvane design preview", network_body),
        "account.html": ("Account · Corvane design preview", lambda: account_body(icon, nav_href)),
        "admin.html": ("Back-office · Corvane design preview", lambda: admin_body(icon)),
        "components.html": ("Components · Corvane design preview", components_body),
    }
    # Review-only stylesheet for pages that bring their own component CSS.
    head_extra = {"admin.html": ADMIN_CSS}

    for name, (title, factory) in pages.items():
        reset_globe_payload()
        html = template.replace("<title>Design preview · Corvane</title>", f"<title>{title}</title>")
        if name in head_extra:
            html = html.replace("</head>", f'<style>{head_extra[name]}</style></head>')
        (OUT / name).write_text(with_switch(html.replace("<!--PREVIEW_BODY-->", factory()), name))

    # One self-contained document for viewers that render a single file with no
    # working relative links (the sandboxed file viewer is one of those).
    reset_globe_payload()
    single = template.replace(
        "</head>", f"<style>{SWITCH_CSS}{TOAST_CSS}{ADMIN_CSS}</style></head>"
    ).replace("<!--PREVIEW_BODY-->", combined_body() + (
        '<div id="preview-toast" class="preview-toast" role="status" aria-live="polite" hidden>That page is not part of this design preview. The tabs at the bottom switch between the pages that are &mdash; in the application every page is reached from this same navigation.</div>'
        f"<script>{TOAST_JS}</script>"
    ))
    (OUT / "preview.html").write_text(with_switch(single, "index", single=True))

    for stale in ("images", "files"):
        if (OUT / stale).exists():
            shutil.rmtree(OUT / stale)
    (OUT / "app.css").unlink(missing_ok=True)

    for page in [*pages, "preview.html"]:
        size = (OUT / page).stat().st_size
        print(f"  {page:18s} {size / 1024:,.0f} KB")
    print("Preview written to public/preview (self-contained) — npm run preview:serve")


if __name__ == "__main__":
    sys.exit(main())
