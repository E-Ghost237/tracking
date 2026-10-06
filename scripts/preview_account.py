"""
Account area of the design preview.

A static mirror of the account Blade templates in the new visual language:
the real account shell (sidebar, user chip, help block) plus the dashboard,
the shipment list, a shipment detail, the order list, the manual-payment page
and the booking wizard. Values come from `preview_data.ACCOUNT_*`, which mirror
DemoSeeder, so the layout is reviewed with the figures the application shows.

Everything here is preview-only scaffolding: the application renders the same
markup from `resources/views/account/*` and `resources/views/layouts/account.blade.php`.
"""

from preview_data import (
    ACCOUNT_ACTIVITY, ACCOUNT_ACTIVE, ACCOUNT_AWAITING, ACCOUNT_BOOKING,
    ACCOUNT_ORDERS, ACCOUNT_PAY, ACCOUNT_REVIEW, ACCOUNT_SHIPMENTS,
    ACCOUNT_SHIPMENT_DETAIL, ACCOUNT_STATS, ACCOUNT_STATUS_MEANINGS, ACCOUNT_USER,
)

# Sidebar entries. Labels with a panel switch the review tabs; the rest keep the
# placeholder behaviour of the rest of the preview.
NAV_PRIMARY = [
    ("layout-dashboard", "Dashboard", "dashboard", "Shipping on one screen"),
    ("package", "Shipments", "shipments", "Every booking and its scans"),
    ("receipt", "Orders and invoices", "orders", "Payment state and documents"),
    ("calculator", "Saved quotes", None, ""),
    ("map-pin", "Addresses", None, ""),
    ("life-buoy", "Support", None, ""),
    ("file-warning", "Claims", None, ""),
    ("bell", "Notifications", None, ""),
    ("settings", "Profile and security", None, ""),
]

REVIEW_TABS = [
    ("dashboard", "Dashboard"),
    ("shipments", "Shipments"),
    ("shipment-detail", "Shipment detail"),
    ("orders", "Orders and invoices"),
    ("payment", "Payment"),
    ("booking", "Booking wizard"),
]


def _badge(label, tone):
    return f'<span class="badge {tone}">{label}</span>'


def _tones(status):
    """Same three semantic families as components/status-badge.blade.php."""
    if status in ("paid", "delivered", "approved", "answered"):
        return "bg-emerald-50 text-emerald-800"
    if status in ("under_review", "at_customs", "delayed", "proof_rejected", "more_info_requested", "partially_paid"):
        return "bg-amber-50 text-amber-900"
    if status in ("cancelled", "returned", "expired"):
        return "bg-red-50 text-red-800"
    if status in ("awaiting_payment", "method_selected", "draft"):
        return "bg-slate-100 text-slate-700"
    return "bg-ink-50 text-ink-800"


def account_body(icon, page_href):
    user = ACCOUNT_USER

    # ---- sidebar ----------------------------------------------------------
    sidebar_links = ""
    for name, label, panel, hint in NAV_PRIMARY:
        if panel:
            active = panel == "dashboard"
            classes = (
                "bg-ink-900 text-white" if active else "text-slate-600 hover:bg-white hover:text-ink-950"
            )
            title = f' title="{hint}"' if hint else ""
            sidebar_links += (
                f'<label for="acct-{panel}"{title} class="flex cursor-pointer items-center gap-3 '
                f'rounded-[4px] px-3 py-2.5 text-sm font-medium transition {classes}">'
                f'{icon(name, "size-[18px]")} {label}</label>'
            )
        else:
            sidebar_links += (
                f'<a href="#" class="flex items-center gap-3 rounded-[4px] px-3 py-2.5 text-sm font-medium '
                f'text-slate-600 transition hover:bg-white hover:text-ink-950">{icon(name, "size-[18px]")} {label}</a>'
            )

    sidebar = f'''<aside class="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:h-fit">
    <div class="flex items-center gap-3 border-b border-line pb-4">
        <span class="grid size-10 place-items-center rounded-full bg-ink-900 text-sm font-bold text-white">C</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-ink-950">{user["name"]}</p>
            <p class="truncate text-xs text-slate-600">{user["email"]}</p>
        </div>
    </div>
    <nav class="mt-3 flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="Account">
        {sidebar_links}
        <a href="#" class="flex shrink-0 items-center gap-3 rounded-[4px] px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-red-700">{icon("log-out", "size-[18px]")} Sign out</a>
    </nav>
    <a href="#" class="btn-dark mt-3 w-full !py-2">{icon("layout-dashboard", "size-4")} Back-office</a>
    <div class="mt-5 border-t border-line pt-5 text-sm">
        <p class="font-semibold text-ink-950">Need a hand?</p>
        <p class="mt-1 text-xs leading-5 text-slate-600">Payments, customs documents or a delayed shipment — a person answers in English and French.</p>
        <div class="mt-3 space-y-1.5">
            <a href="{page_href("Support")}" class="flex items-center gap-2 text-xs font-medium text-ink-900 hover:text-brand-600">{icon("headset", "size-3.5 text-slate-500")} Contact us</a>
            <span class="flex items-center gap-2 text-xs font-medium text-ink-900">{icon("phone", "size-3.5 text-slate-500")} +1 713 555 0100</span>
        </div>
    </div>
</aside>'''

    # ---- dashboard --------------------------------------------------------
    stats = "".join(
        f'''<div class="bg-white p-5">
    <dt class="flex items-center gap-2 text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{icon(ic, "size-4 text-slate-500")} {label}</dt>
    <dd class="mt-2.5 text-3xl font-bold text-ink-950 tabular">{count}</dd>
    <p class="mt-1 text-xs leading-5 text-slate-600">{hint}</p>
</div>'''
        for ic, label, count, hint in ACCOUNT_STATS
    )

    awaiting = ""
    for order in ACCOUNT_AWAITING:
        urgent = "text-red-700" if order["urgent"] else "text-ink-950"
        awaiting += f'''<article class="card flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-3">
            <p class="font-semibold text-ink-950">{order["number"]}</p>
            {_badge(order["status_label"], _tones(order["status"]))}
        </div>
        <p class="mt-1.5 text-sm text-slate-600">{order["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}</p>
        <p class="mt-1 text-xs text-slate-600">Payment reference <span class="font-mono font-semibold text-ink-900">{order["reference"]}</span>
            <span class="mx-1.5 text-slate-300">·</span> Amount <span class="font-semibold text-ink-900 tabular">{order["total"]}</span></p>
    </div>
    <div class="shrink-0 text-sm">
        <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Time left to pay</p>
        <p class="mt-1 font-mono font-semibold tabular {urgent}">{order["remaining"]}</p>
        <p class="text-xs text-slate-600">{order["expires_label"]}</p>
    </div>
    <a href="#" class="btn-primary shrink-0 !py-2 sm:self-center">Pay now</a>
</article>'''

    review_rows = "".join(
        f'''<a href="#" class="flex flex-wrap items-center gap-4 p-5 transition hover:bg-surface">
    <span class="grid size-10 place-items-center rounded-[4px] bg-amber-50 text-amber-700">{icon("hourglass", "size-5")}</span>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-ink-950">{o["number"]}</p>
        <p class="mt-0.5 text-sm text-slate-600">{o["route"]} · <span class="tabular">{o["total"]}</span></p>
    </div>
    {_badge(o["status_label"], _tones(o["status"]))}
    {icon("chevron-right", "size-4 shrink-0 text-slate-400")}
</a>'''
        for o in ACCOUNT_REVIEW
    )

    active_cards = "".join(
        f'''<a href="#" class="card card-hover block p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-sm font-bold break-all text-ink-950">{s["tracking"]}</p>
            <p class="mt-1 text-xs text-slate-600">{s["service"]}</p>
        </div>
        {_badge(s["status_label"], _tones(s["status"]))}
    </div>
    <p class="mt-3.5 text-sm text-ink-900">{s["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}</p>
    <div class="mt-4">
        <div class="h-1 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow="{s["progress"]}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-brand-500" style="width: {s["progress"]}%"></div>
        </div>
        <p class="mt-2.5 text-xs text-slate-600"><span class="font-medium text-ink-900">{s["last_label"]}</span> · <span class="tabular">{s["last_hours"]} h ago</span> · {s["last_place"]}</p>
    </div>
</a>'''
        for s in ACCOUNT_ACTIVE
    )

    steps = [
        ("calculator", "Get a quote", "Enter the route and the packed size to see the price and the delivery window."),
        ("package", "Book the shipment", "Add both parties and the customs details. Your progress is saved at every step."),
        ("shield-check", "Pay and upload proof", "Transfer the amount, upload your receipt, and a person verifies it."),
        ("radar", "Follow every scan", "Your tracking number is issued once payment is approved, then each handoff is logged."),
    ]
    step_cards = "".join(
        f'''<a href="#" class="card card-hover flex flex-col p-5">
    <span class="flex items-center gap-2 text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
        <span class="grid size-6 place-items-center rounded-[4px] bg-ink-900 text-[11px] font-bold text-white tabular">{i + 1}</span>
        Step {i + 1}
    </span>
    <span class="mt-3 flex items-center gap-2 text-sm font-bold text-ink-950">{icon(ic, "size-4 text-ink-600")} {heading}</span>
    <span class="mt-1.5 flex-1 text-sm leading-6 text-slate-600">{copy}</span>
</a>'''
        for i, (ic, heading, copy) in enumerate(steps)
    )

    activity_rows = "".join(
        f'''<li class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 text-sm">
    <span class="font-medium text-ink-950">{label}</span>
    <span class="text-xs text-slate-600">{object_type} <span class="font-mono">{object_id}</span></span>
    <span class="ml-auto text-xs text-slate-600 tabular">{when}</span>
</li>'''
        for label, object_type, object_id, when in ACCOUNT_ACTIVITY
    )

    dashboard = f'''<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Hello, Chantal</h1>
            <p class="mt-1.5 text-sm text-slate-600">Here is what is happening with your shipments.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="#" class="btn-primary !py-2">{icon("plus", "size-4")} New shipment</a>
            <a href="{page_href("Track")}" class="btn-ghost !py-2">{icon("radar", "size-4")} Track a parcel</a>
        </div>
    </div>

    <dl class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">{stats}</dl>

    <section class="mt-9">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Waiting on you</h2>
                <p class="mt-1 text-sm text-slate-600">Complete the payment and upload your proof to release the label and tracking number.</p>
            </div>
            <a href="#" class="btn-ghost !py-2">All orders</a>
        </div>
        <div class="mt-4 space-y-3">{awaiting}</div>
    </section>

    <section class="mt-9">
        <h2 class="text-lg font-bold">Payments under review</h2>
        <p class="mt-1 text-sm text-slate-600">A verifier checks the amount, the date and the reference against your receipt. Target review time: 30 minutes during staffed hours (Mon–Sat, 08:00–19:00 UTC).</p>
        <div class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">{review_rows}</div>
    </section>

    <section class="mt-9">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">Active shipments</h2>
            <a href="#" class="link text-sm">View all</a>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">{active_cards}</div>
    </section>

    <section class="mt-9">
        <h2 class="text-lg font-bold">How a shipment works from here</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{step_cards}</div>
    </section>

    <section class="mt-9">
        <h2 class="text-lg font-bold">Recent account activity</h2>
        <p class="mt-1 text-sm text-slate-600">Every money, security and status change is written to an append-only trail. These are the most recent entries on your account.</p>
        <ul class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">{activity_rows}</ul>
    </section>
</div>'''

    # ---- shipments --------------------------------------------------------
    shipment_rows = ""
    for s in ACCOUNT_SHIPMENTS:
        tracking_cell = (
            f'<a href="#" class="font-mono font-semibold text-ink-950 hover:text-brand-700">{s["tracking"]}</a>'
            if s["released"] else '<span class="text-slate-600">Pending</span>'
        )
        shipment_rows += f'''<tr class="transition-colors hover:bg-surface/70">
    <td class="px-5 py-4">{tracking_cell}</td>
    <td class="px-5 py-4 text-slate-700">{s["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}</td>
    <td class="px-5 py-4 text-slate-700">{s["service"]}</td>
    <td class="px-5 py-4">{_badge(s["status_label"], _tones(s["status"]))}</td>
    <td class="px-5 py-4 text-slate-600 tabular">{s["created"]}</td>
    <td class="px-5 py-4 text-right"><a href="#" class="inline-flex items-center gap-1 text-xs font-semibold text-ink-900 hover:text-brand-700">Open {icon("chevron-right", "size-3.5")}</a></td>
</tr>'''

    status_means = "".join(
        f'<div><dt class="text-sm font-semibold text-ink-950">{term}</dt><dd class="mt-0.5 text-sm leading-6 text-slate-600">{text}</dd></div>'
        for term, text in ACCOUNT_STATUS_MEANINGS
    )

    shipments_panel = f'''<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">My shipments</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">Search by tracking number, recipient or destination city. A shipment appears here as soon as the booking is saved; tracking scans start once payment is approved.</p>
        </div>
        <a href="#" class="btn-primary !py-2">{icon("plus", "size-4")} New shipment</a>
    </div>

    <form method="get" class="mt-6 border-b border-line pb-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="lg:col-span-2">
                <span class="field-label">Search</span>
                <span class="relative block">
                    {icon("search", "pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500")}
                    <input type="search" placeholder="Number, recipient or city" class="field !pl-9">
                </span>
            </label>
            <label>
                <span class="field-label">Status</span>
                <select class="field"><option>All statuses</option><option>In transit</option><option>At customs</option><option>Delivered</option></select>
            </label>
            <label>
                <span class="field-label">Mode</span>
                <select class="field"><option>All modes</option><option>Air</option><option>Sea</option><option>Road</option><option>Express</option></select>
            </label>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button type="button" class="btn-dark !py-2">Filter</button>
            <a href="#" class="btn-ghost !py-2">Clear</a>
            <p class="text-xs text-slate-600">3 shipments in total</p>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-[6px] border border-line bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <caption class="sr-only">My shipments</caption>
                <thead class="border-b border-line bg-surface text-left text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                    <tr>
                        <th scope="col" class="px-5 py-3">Tracking number</th>
                        <th scope="col" class="px-5 py-3">Route</th>
                        <th scope="col" class="px-5 py-3">Mode</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Booked</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">{shipment_rows}</tbody>
            </table>
        </div>
    </div>
    <div class="mt-6 flex items-center gap-2 text-sm text-slate-600"><span class="badge bg-ink-50 text-ink-800">1</span> <span class="text-slate-600">of 1 page</span></div>

    <section class="mt-9">
        <h2 class="text-lg font-bold">Reading your shipment status</h2>
        <dl class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-2">{status_means}</dl>
        <p class="mt-4 text-sm text-slate-600">Full definitions, limits and the claims procedure are in the <a href="{page_href("Support")}" class="link">help centre</a>.</p>
    </section>
</div>'''

    # ---- shipment detail --------------------------------------------------
    detail = ACCOUNT_SHIPMENT_DETAIL
    packages = "".join(
        f'<tr><td class="px-4 py-3 text-ink-950">{d}</td><td class="px-4 py-3 text-slate-600">{c}</td>'
        f'<td class="px-4 py-3 text-right tabular">{w}</td><td class="px-4 py-3 text-right tabular">{dim}</td></tr>'
        for d, c, w, dim in detail["packages"]
    )

    journey = ""
    for index, (label, place, when, source) in enumerate(detail["timeline"]):
        dot = 'bg-brand-500 ring-4 ring-brand-500/15' if index == 0 else 'bg-slate-300'
        line = '' if index == len(detail["timeline"]) - 1 else '<span class="absolute top-4 bottom-[-6px] w-px bg-line"></span>'
        journey += f'''<li class="relative flex gap-4 pb-6 last:pb-0">
    <span class="relative flex w-3 shrink-0 justify-center"><span class="mt-1.5 size-2.5 rounded-full {dot}"></span>{line}</span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-3">
            <p class="text-sm font-semibold text-ink-950">{label}</p>
            <p class="text-xs text-slate-600 tabular">{when}</p>
        </div>
        <p class="mt-0.5 text-xs text-slate-600">{place} <span class="mx-1.5 text-slate-300">·</span> {source}</p>
    </div>
</li>'''

    documents = ""
    for label, ic, available in detail["documents"]:
        trailing = (
            icon("download", "size-4 shrink-0 text-slate-400 group-hover:text-ink-900")
            if available else '<span class="text-xs text-slate-500">Preparing…</span>'
        )
        documents += f'''<li><a href="#" class="group flex items-center gap-3 px-4 py-3.5 text-sm transition hover:bg-surface">
    {icon(ic, "size-4 shrink-0 text-brand-600")}<span class="min-w-0 flex-1 font-medium text-ink-950">{label}</span>{trailing}
</a></li>'''

    detail_panel = f'''<div>
    <nav class="text-sm" aria-label="Breadcrumb">
        <a href="#" class="inline-flex items-center gap-1 text-slate-600 hover:text-ink-950">{icon("chevron-left", "size-4")} My shipments</a>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{detail["service"]} freight</p>
            <h1 class="mt-1 font-mono text-2xl font-bold break-all">{detail["tracking"]}</h1>
            <p class="mt-1.5 text-sm text-slate-600">{detail["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {_badge(detail["status_label"], _tones(detail["status"]))}
            <a href="{page_href("Track")}" class="btn-ghost !py-2">{icon("radar", "size-4")} Public tracking</a>
            <a href="#" class="btn-ghost !py-2">{icon("life-buoy", "size-4")} Get help</a>
        </div>
    </div>

    <div class="card mt-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-ink-950">{detail["status_label"]}</p>
            <p class="text-xs text-slate-600 tabular">{detail["progress"]}%</p>
        </div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow="{detail["progress"]}" aria-valuemin="0" aria-valuemax="100" aria-label="Shipment progress">
            <div class="h-full rounded-full bg-brand-500" style="width: {detail["progress"]}%"></div>
        </div>
        <p class="mt-3 text-sm text-slate-600">{detail["timeline"][0][0]} <span class="mx-1.5 text-slate-300">·</span> {detail["timeline"][0][1]} <span class="mx-1.5 text-slate-300">·</span> <span class="tabular">6 h ago</span></p>
    </div>

    <div class="mt-8 grid gap-8 xl:grid-cols-3">
        <div class="space-y-8 xl:col-span-2">
            <section>
                <h2 class="text-lg font-bold">Journey</h2>
                <ol class="mt-5">{journey}</ol>
            </section>
            <section>
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">Packages</h2>
                    <p class="text-sm text-slate-600"><span class="tabular">1</span> item</p>
                </div>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <caption class="sr-only">Packages</caption>
                            <thead class="border-b border-line bg-surface text-left text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                                <tr><th scope="col" class="px-4 py-3">Description</th><th scope="col" class="px-4 py-3">Category</th><th scope="col" class="px-4 py-3 text-right">Weight</th><th scope="col" class="px-4 py-3 text-right">Dimensions</th></tr>
                            </thead>
                            <tbody class="divide-y divide-line">{packages}</tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-8">
            <section>
                <h2 class="text-lg font-bold">Documents</h2>
                <ul class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">{documents}</ul>
            </section>
            <section>
                <h2 class="text-lg font-bold">Parties</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div class="border-b border-line pb-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Sender</dt>
                        <dd class="mt-1 font-medium text-ink-950">{detail["sender"][0]}</dd>
                        <dd class="text-slate-600">{detail["sender"][1]}</dd>
                        <dd class="text-slate-600">{detail["sender"][2]}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Recipient</dt>
                        <dd class="mt-1 font-medium text-ink-950">{detail["recipient"][0]}</dd>
                        <dd class="text-slate-600">{detail["recipient"][1]}</dd>
                        <dd class="text-slate-600">{detail["recipient"][2]}</dd>
                    </div>
                </dl>
            </section>
            <section class="rounded-[6px] bg-surface p-4 text-sm leading-6 text-slate-600">
                <p class="font-semibold text-ink-950">Something wrong?</p>
                <p class="mt-1">Open a claim for loss, damage or delay, or send us a message and we will answer on this shipment.</p>
                <div class="mt-3 flex flex-wrap gap-2"><a href="#" class="btn-ghost !py-2">Open a claim</a></div>
            </section>
        </div>
    </div>
</div>'''

    # ---- orders -----------------------------------------------------------
    order_cards = ""
    for order in ACCOUNT_ORDERS:
        invoices = ""
        if order["invoices"]:
            invoices = '<dl class="grid gap-px border-t border-line bg-line sm:grid-cols-2 lg:grid-cols-3">' + "".join(
                f'''<a href="#" class="group flex items-center gap-3 bg-white px-5 py-3.5 text-sm transition hover:bg-surface">
    {icon("file-down", "size-4 shrink-0 text-brand-600")}
    <span class="min-w-0 flex-1">
        <span class="block font-medium text-ink-950">{kind} {number}</span>
        <span class="block text-xs text-slate-600">Download PDF</span>
    </span>
    {icon("download", "size-4 shrink-0 text-slate-400 group-hover:text-ink-900")}
</a>'''
                for kind, number in order["invoices"]
            ) + "</dl>"

        meta = f'<span class="mx-1.5 text-slate-300">·</span> {order["expires_label"]}' if order["expires_label"] and order["open"] else ""
        note = ""
        if not order["open"]:
            note = (
                '<p class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-slate-600">'
                + icon("info", "mt-0.5 size-3.5 shrink-0 text-slate-500")
                + "<span>Payment approved. Your label, tracking number and receipt are ready.</span></p>"
            )
        action = (
            f'<a href="#" class="btn-primary !py-2">{order["action"]}</a>' if order["open"]
            else f'<a href="#" class="btn-ghost !py-2">{order["action"]}</a>'
        )
        order_cards += f'''<article class="card overflow-hidden">
    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-3">
                <p class="font-semibold text-ink-950">{order["number"]}</p>
                {_badge(order["status_label"], _tones(order["status"]))}
            </div>
            <p class="mt-1.5 text-sm text-slate-600">{order["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}<span class="mx-1.5 text-slate-300">·</span>{order["created"]}</p>
            <p class="mt-1 text-xs text-slate-600">Payment reference <span class="font-mono font-semibold text-ink-900">{order["reference"]}</span>{meta}</p>
            {note}
        </div>
        <div class="flex flex-col items-start gap-3 lg:items-end">
            <p class="text-lg font-bold text-ink-950 tabular">{order["total"]}</p>
            {action}
        </div>
    </div>
    {invoices}
</article>'''

    settled = "".join(
        f'''<div class="bg-white p-5">
    <p class="flex items-center gap-2 text-sm font-bold text-ink-950">{icon(ic, "size-4 text-ink-600")} {heading}</p>
    <p class="mt-2 text-sm leading-6 text-slate-600">{copy}</p>
</div>'''
        for ic, heading, copy in [
            ("calculator", "1. Price confirmed", "The price comes from the live rate card for your route, weight and service. It is frozen on the order for the validity window shown above."),
            ("shield-check", "2. Payment verified by a person", "You transfer the amount, then upload the receipt. A verifier matches the amount, the date and the reference. Target time: 30 minutes during staffed hours (Mon–Sat, 08:00–19:00 UTC)."),
            ("file-down", "3. Documents released", "Approval releases the shipping label, the tracking number and the receipt. The shipment then becomes visible in public tracking."),
        ]
    )

    orders_panel = f'''<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Orders and invoices</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">Every order you place keeps its own reference and its own payment instructions. A person verifies each payment before the label and the tracking number are released.</p>
        </div>
        <a href="#" class="btn-primary !py-2">{icon("plus", "size-4")} New shipment</a>
    </div>

    <p class="mt-5 text-sm text-slate-600"><span class="font-semibold text-ink-950 tabular">3</span> orders on this account</p>
    <div class="mt-6 space-y-3">{order_cards}</div>

    <section class="mt-9">
        <h2 class="text-lg font-bold">How an order is settled</h2>
        <div class="mt-4 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">{settled}</div>
        <p class="mt-4 flex items-start gap-2 rounded-[4px] bg-surface px-4 py-3 text-xs leading-5 text-slate-600">
            {icon("triangle-alert", "mt-0.5 size-3.5 shrink-0 text-amber-600")}
            <span>Only pay for an order you created yourself. If someone asked you to pay for a parcel, or to buy gift cards to release a shipment, stop and contact us: it may be a scam.</span>
        </p>
    </section>
</div>'''

    # ---- payment ----------------------------------------------------------
    pay = ACCOUNT_PAY
    fee_line = f'<p class="mt-1 text-xs text-slate-600 tabular">+ {pay["fee"]} payment fee</p>' if pay["fee"] else ""
    method_tiles = "".join(
        f'''<button type="button" class="flex items-center gap-4 rounded-[6px] border bg-white p-4 text-left transition-colors {'border-brand-500' if selected else 'border-line'}">
    <span class="grid size-11 shrink-0 place-items-center rounded-[4px] {'bg-brand-500 text-white' if selected else 'bg-surface text-ink-900'}">{icon(ic, "size-5")}</span>
    <span class="min-w-0 flex-1">
        <span class="block font-semibold text-ink-950">{name}</span>
        <span class="block text-xs text-slate-600">{currency} · fee {fee}</span>
    </span>
    {'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5 shrink-0 text-brand-600" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>' if selected else ""}
</button>'''
        for slug, name, currency, fee, selected in pay["methods"]
        for ic in [{"zelle": "smartphone", "cashapp": "smartphone", "iban": "landmark", "paypal": "credit-card"}.get(slug, "credit-card")]
    )

    pay_fields = "".join(
        f'''<div class="flex items-start gap-4 p-4">
    <div class="min-w-0 flex-1">
        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{label}</dt>
        <dd class="mt-1 font-mono text-sm break-all text-ink-950">{value}</dd>
    </div>
    <button type="button" class="btn-ghost shrink-0 !px-3 !py-1.5 text-xs">{icon("copy", "size-3.5")} Copy</button>
</div>'''
        for label, value, _type in pay["fields"]
    )

    pay_steps = "".join(
        f'''<li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-[4px] bg-ink-900 text-xs font-bold text-white tabular">{i + 1}</span><span class="leading-6">{step}</span></li>'''
        for i, step in enumerate(pay["steps"])
    )

    history_rows = "".join(
        f'''<div class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 text-sm">
    <span class="font-semibold text-ink-950">{method}</span>
    <span class="text-slate-600 tabular">{amount}</span>
    <span class="text-slate-600 tabular">{when}</span>
    <span class="ml-auto badge bg-surface text-slate-700">{decision}</span>
    <p class="basis-full text-slate-600">{note}</p>
</div>'''
        for method, amount, when, decision, note in pay["history"]
    )

    payment_panel = f'''<div>
    <nav class="text-sm" aria-label="Breadcrumb">
        <a href="#" class="inline-flex items-center gap-1 text-slate-600 hover:text-ink-950">{icon("chevron-left", "size-4")} Orders</a>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Pay for order {pay["number"]}</h1>
            <p class="mt-1.5 text-sm text-slate-600">{pay["route"].replace(" → ", ' <span class="text-slate-400">→</span> ')}</p>
        </div>
        {_badge(pay["status_label"], _tones(pay["status"]))}
    </div>

    <dl class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">
        <div class="bg-white p-5">
            <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Shipment price</dt>
            <dd class="mt-2 text-2xl font-bold text-ink-950 tabular">{pay["subtotal"]}</dd>
            {fee_line}
            <p class="mt-1 text-xs text-slate-600">Total due <span class="font-semibold text-ink-900 tabular">{pay["total"]}</span></p>
        </div>
        <div class="bg-white p-5">
            <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Payment reference</dt>
            <dd class="mt-2 font-mono text-2xl font-bold break-all text-brand-700">{pay["reference"]}</dd>
            <p class="mt-1 text-xs text-slate-600">Write it in the payment note so the verifier can match your transfer.</p>
        </div>
        <div class="bg-white p-5">
            <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">Time left to pay</dt>
            <dd class="mt-2 font-mono text-2xl font-bold text-ink-950 tabular">{pay["remaining"]}</dd>
            <p class="mt-1 text-xs text-slate-600">{pay["expires_label"]}</p>
        </div>
    </dl>

    <section class="mt-10">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4">
            <div>
                <p class="eyebrow">Step 1 of 3</p>
                <h2 class="mt-1 text-lg font-bold">Choose how to pay</h2>
            </div>
            <p class="max-w-md text-xs leading-5 text-slate-600">Account details appear only after you choose a method, and only on this page. Nothing is sent by email.</p>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{method_tiles}</div>
    </section>

    <section class="mt-10">
        <div class="border-b border-line pb-4">
            <p class="eyebrow">Step 2 of 3</p>
            <h2 class="mt-1 text-lg font-bold">Send the payment</h2>
        </div>
        <div class="mt-5 grid gap-6 lg:grid-cols-5">
            <div class="overflow-hidden rounded-[6px] border border-line bg-white lg:col-span-3">
                <div class="flex flex-wrap items-end justify-between gap-4 bg-ink-950 p-5 text-white">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.08em] text-ink-200 uppercase">Amount to send</p>
                        <p class="mt-1 text-3xl font-bold tabular">{pay["total"]}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold tracking-[0.08em] text-ink-200 uppercase">Reference</p>
                        <p class="mt-1 font-mono text-lg font-bold text-brand-300">{pay["reference"]}</p>
                    </div>
                </div>
                <dl class="divide-y divide-line">{pay_fields}</dl>
            </div>
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-[6px] border border-line bg-white p-5">
                    <p class="font-semibold text-ink-950">Steps</p>
                    <ol class="mt-3 space-y-3 text-sm text-slate-700">{pay_steps}</ol>
                    <p class="mt-4 rounded-[4px] bg-surface p-3 text-sm leading-6 text-slate-700">{pay["instructions"]}</p>
                    <p class="mt-3 flex items-start gap-2 text-xs leading-5 text-slate-600">
                        {icon("clock", "mt-0.5 size-3.5 shrink-0 text-slate-500")}
                        <span>Send the exact amount with the reference. A transfer without the reference takes longer to match, and a different amount creates a balance.</span>
                    </p>
                </div>
                <div class="rounded-[6px] bg-surface p-4 text-xs leading-5 text-slate-700">
                    <p class="flex items-center gap-1.5 font-semibold text-ink-950">{icon("shield-check", "size-4 text-amber-700")} Before you pay</p>
                    <p class="mt-1.5">Only pay for a shipment you booked yourself. If someone asked you to pay for a parcel or to buy gift cards, stop and contact us: it may be a scam.</p>
                    <p class="mt-1.5">We never ask for a payment to a personal account, and we never ask for your password or a code by phone.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-10">
        <div class="border-b border-line pb-4">
            <p class="eyebrow">Step 3 of 3</p>
            <h2 class="mt-1 text-lg font-bold">Upload your proof of payment</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">A screenshot or a photo of the receipt is enough. The verifier reads the amount, the date, the reference and the payer name — make sure all four are visible.</p>
        </div>
        <div class="mt-5 space-y-5 rounded-[6px] border border-line bg-white p-6">
            <div class="rounded-[6px] border border-dashed border-slate-300 p-6 text-center">
                {icon("upload", "mx-auto size-8 text-brand-600")}
                <p class="mt-2 text-sm font-medium text-ink-950">Drag your screenshot or receipt here</p>
                <p class="mt-0.5 text-xs text-slate-600">JPG, PNG, WebP, HEIC or PDF · up to 8 MB · up to 3 files</p>
                <span class="btn-ghost mt-4 cursor-pointer !py-2 text-sm">Choose files</span>
                <ul class="mt-4 space-y-2 text-left">
                    <li class="flex items-center gap-3 rounded-[4px] bg-surface px-3 py-2 text-sm">
                        {icon("file-text", "size-4 text-slate-500")}
                        <span class="flex-1 truncate">zelle-confirmation.png</span>
                        <span class="text-xs text-slate-600 tabular">412 KB</span>
                        <span class="text-slate-500">{icon("x", "size-4")}</span>
                    </li>
                </ul>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="field-label">Amount paid (USD)</span><input class="field tabular" value="425.00"></label>
                <label class="block"><span class="field-label">Payment date</span><input type="date" class="field" value="2026-10-08"></label>
                <label class="block"><span class="field-label">Name of the payer</span><input class="field" value="Chantal Mbarga"></label>
                <label class="block"><span class="field-label">Transaction ID <span class="font-normal text-slate-500">(optional)</span></span><input class="field font-mono" value="ZL-8841-2290"></label>
            </div>
            <label class="block"><span class="field-label">Note (optional)</span><textarea rows="2" class="field">Reference written in the memo.</textarea></label>
            <button type="button" class="btn-primary">Submit proof of payment</button>
            <p class="text-xs text-slate-600">Files are stored privately and are visible only to the verifier handling your order.</p>
        </div>
    </section>

    <section class="mt-12">
        <h2 class="text-lg font-bold">Proof history</h2>
        <p class="mt-1 text-sm text-slate-600">Every proof you sent, with the decision it received.</p>
        <div class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">{history_rows}</div>
    </section>
</div>'''

    # ---- booking wizard ---------------------------------------------------
    booking = ACCOUNT_BOOKING
    stepper = "".join(
        f'''<li>
    <span class="block h-1 {'bg-brand-500' if i + 1 <= booking['current'] else 'bg-line'}"></span>
    <span class="mt-2 hidden items-center gap-1.5 text-xs font-semibold sm:flex {'text-ink-950' if i + 1 == booking['current'] else 'text-slate-500'}">
        <span class="grid size-5 place-items-center rounded-[3px] text-[10px] tabular {'bg-emerald-700 text-white' if i + 1 < booking['current'] else ('bg-ink-900 text-white' if i + 1 == booking['current'] else 'bg-line text-slate-600')}">{i + 1}</span>
        {label}
    </span>
</li>'''
        for i, label in enumerate(booking["steps"])
    )

    package_cards = "".join(
        f'''<div class="rounded-[6px] border border-line p-5">
    <div class="flex items-center justify-between">
        <p class="font-semibold text-ink-950">Package <span class="tabular">{i + 1}</span></p>
        <span class="text-sm text-slate-500">Remove</span>
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="text-sm font-medium text-ink-900 sm:col-span-2">Description of contents<input class="field mt-1.5" value="{description}"></label>
        <label class="text-sm font-medium text-ink-900">Category<select class="field mt-1.5"><option>{category}</option></select></label>
        <label class="text-sm font-medium text-ink-900">Value (USD)<input class="field mt-1.5 tabular" value="200.00"></label>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <label class="text-xs font-medium text-slate-600">Weight (kg)<input class="field mt-1 tabular" value="{weight}"></label>
        <label class="text-xs font-medium text-slate-600">Length (cm)<input class="field mt-1 tabular" value="{length}"></label>
        <label class="text-xs font-medium text-slate-600">Width (cm)<input class="field mt-1 tabular" value="{width}"></label>
        <label class="text-xs font-medium text-slate-600">Height (cm)<input class="field mt-1 tabular" value="{height}"></label>
    </div>
</div>'''
        for i, (description, category, weight, length, width, height) in enumerate(booking["packages"])
    )

    option_tiles = "".join(
        f'''<button type="button" class="rounded-[6px] border p-5 text-left transition-colors {'border-brand-500 bg-brand-50/60' if selected else ('border-line' if available else 'border-line opacity-60')}" {'disabled' if not available else ''}>
    <div class="flex items-start justify-between gap-3">
        <p class="font-bold text-ink-950">{label} freight</p>
        <p class="text-lg font-bold text-ink-950 tabular">{price if available else '—'}</p>
    </div>
    <p class="mt-2 text-sm text-slate-600">{transit + ' · ' + weight if available else reason}</p>
</button>'''
        for mode, label, available, price, transit, weight in booking["options"]
        for selected in [mode == "air"]
        for reason in [weight]
    )

    customs_rows = "".join(
        f'<label class="text-sm font-medium text-ink-900">{label}<input class="field mt-1.5" value="{value}"></label>'
        for label, value in booking["customs"]
    )

    review_tiles = "".join(
        f'''<div class="rounded-[6px] border border-line p-4">
    <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{term}</dt>
    <dd class="mt-1 text-ink-900">{value}</dd>
    <dd class="text-slate-600">{extra}</dd>
</div>'''
        for term, value, extra in [
            ("From", "1000 Shipping Lane, Houston US", "Chantal Mbarga"),
            ("To", "12 rue de Rivoli, Paris FR", "Marc Lefèvre"),
            ("Packages", "2 · 12.8 kg", "12.5 kg chargeable"),
            ("Service", "Air freight", "Insured"),
        ]
    )

    booking_panel = f'''<div>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">New shipment</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">Five short steps: the route, the packages, the service, the people involved, then a review. Your progress is saved after every step, so you can close the page and come back to it.</p>
        </div>
        <span class="btn-ghost !py-2 text-sm">{icon("refresh-cw", "size-4")} Resume a draft</span>
    </div>

    <ol class="mt-8 grid grid-cols-5 gap-2" aria-label="Booking steps">{stepper}</ol>

    <div class="card mt-6 p-6 sm:p-8">
        <p class="mb-6 inline-flex items-center gap-2 rounded-[3px] bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700">{icon("receipt", "size-3.5")} From quote <span class="font-mono">{booking["quote_reference"]}</span></p>

        <section>
            <h2 class="text-xl font-bold">What are you sending?</h2>
            <p class="mt-1.5 text-sm leading-6 text-slate-600">Some goods cannot be carried. See the <a href="#" class="link">prohibited items list</a>.</p>
            <div class="mt-6 space-y-4">{package_cards}</div>
            <p class="mt-4 text-sm text-slate-600">Total: <strong>12.8 kg</strong> · <strong>$400.00</strong></p>
        </section>

        <section class="mt-10 border-t border-line pt-8">
            <h2 class="text-xl font-bold">Choose your service</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">Live prices for your route and packages. The window shown is the published transit range for the service you pick.</p>
            <div class="mt-6 grid gap-3 sm:grid-cols-2">{option_tiles}</div>
            <label class="mt-5 flex cursor-pointer items-center gap-3 rounded-[6px] border border-line p-4">
                <span class="size-5 rounded-[3px] border border-slate-300 bg-white"></span>
                <span class="text-sm"><span class="font-semibold text-ink-950">Insure my shipment</span><br><span class="text-slate-600">Coverage for loss and damage up to the declared value.</span></span>
            </label>
        </section>

        <section class="mt-10 border-t border-line pt-8">
            <h2 class="text-xl font-bold">Sender and recipient</h2>
            <div class="mt-6 grid gap-8 lg:grid-cols-2">
                <fieldset class="space-y-3">
                    <legend class="font-semibold text-ink-950">Sender</legend>
                    <label class="block text-sm font-medium text-ink-900">Full name<input class="field mt-1.5" value="Chantal Mbarga"></label>
                    <label class="block text-sm font-medium text-ink-900">Company (optional)<input class="field mt-1.5"></label>
                    <label class="block text-sm font-medium text-ink-900">Phone<input class="field mt-1.5" value="+1 713 555 0123"></label>
                    <label class="block text-sm font-medium text-ink-900">Email (optional)<input class="field mt-1.5" value="customer@corvane.test"></label>
                </fieldset>
                <fieldset class="space-y-3">
                    <legend class="font-semibold text-ink-950">Recipient</legend>
                    <label class="block text-sm font-medium text-ink-900">Full name<input class="field mt-1.5" value="Marc Lefèvre"></label>
                    <label class="block text-sm font-medium text-ink-900">Company (optional)<input class="field mt-1.5"></label>
                    <label class="block text-sm font-medium text-ink-900">Phone<input class="field mt-1.5" value="+33 6 12 34 56 78"></label>
                    <label class="block text-sm font-medium text-ink-900">Email (optional)<input class="field mt-1.5" value="marc.lefevre@example.fr"></label>
                </fieldset>
            </div>
            <fieldset class="mt-8 rounded-[6px] bg-surface p-5">
                <legend class="px-1 font-semibold text-ink-950">Customs details</legend>
                <p class="mt-1 mb-3 text-xs leading-5 text-slate-600">A commercial invoice is generated from these fields. A vague description or a missing HS code is the most common reason a parcel waits at customs.</p>
                <div class="grid gap-3 sm:grid-cols-3">{customs_rows}</div>
            </fieldset>
        </section>

        <section class="mt-10 border-t border-line pt-8">
            <h2 class="text-xl font-bold">Review and confirm</h2>
            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">{review_tiles}</dl>
            <div class="mt-6 flex items-center justify-between rounded-[6px] bg-ink-950 p-5 text-white">
                <span class="text-sm text-ink-100">Total to pay</span>
                <span class="text-2xl font-bold tabular">{booking["total"]}</span>
            </div>
            <label class="mt-6 flex items-start gap-3 text-sm text-slate-700">
                <span class="mt-0.5 size-5 shrink-0 rounded-[3px] border border-slate-300 bg-white"></span>
                <span>I confirm the contents are accurately described, contain no prohibited items, and I accept the <a href="#" class="link">terms of service</a> and <a href="#" class="link">shipping policy</a>.</span>
            </label>
        </section>

        <div class="mt-8 flex items-center justify-between border-t border-line pt-6">
            <span class="btn-ghost">{icon("chevron-left", "size-4")} Back</span>
            <span class="btn-primary">{icon("loader", "size-4 animate-spin")} Saving…</span>
        </div>
    </div>

    <p class="mt-4 text-xs leading-5 text-slate-600">Steps 1, 2, 4 and 5 are shown in one scroll here so the whole wizard can be reviewed at once; in the application the customer moves through them one screen at a time, and the draft is saved after every step.</p>
</div>'''

    # ---- assembly ---------------------------------------------------------
    panels = "".join(
        f'''<section class="preview-panel {tone}" id="acct-panel-{slug}">{body}</section>'''
        for slug, body, tone in [
            ("dashboard", dashboard, ""),
            ("shipments", shipments_panel, ""),
            ("shipment-detail", detail_panel, ""),
            ("orders", orders_panel, ""),
            ("payment", payment_panel, ""),
            ("booking", booking_panel, ""),
        ]
    )

    tab_labels = "".join(f'<label for="acct-{slug}">{label}</label>' for slug, label in REVIEW_TABS)
    tab_inputs = "".join(
        f'<input type="radio" name="preview-account" id="acct-{slug}" {"checked" if index == 0 else ""}>'
        for index, (slug, _label) in enumerate(REVIEW_TABS)
    )

    return f'''<main class="bg-surface pb-16">
    <div class="border-b border-line bg-white">
        <div class="container-page py-6">
            <p class="eyebrow">Sample account</p>
            <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-600">The account area uses the seeded demo customer so the screens are reviewed with the values the application shows. Tracking numbers carry a real check digit and the references follow the formats the platform generates.</p>
        </div>
    </div>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[248px_1fr] lg:gap-10 lg:py-10">
        {sidebar}
        <div class="min-w-0">
            <div class="preview-note mb-5 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="preview-note-label">Review these account screens</span>
                <span class="text-xs text-slate-600">In the application each one is reached from the sidebar, from an order or from the shipments list.</span>
            </div>
            <div class="preview-tabs">
                {tab_inputs}
                <div class="preview-tablist" role="tablist" aria-label="Account screens">{tab_labels}</div>
                <div class="preview-panels">{panels}</div>
            </div>
        </div>
    </div>
</main>'''
