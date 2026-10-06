"""
Page bodies for the static design preview (services, service detail, rates,
network). Kept separate from the build script so each page stays readable.

The markup mirrors the corresponding Blade templates. Values that the application
computes (rate card prices) are labelled as samples in the preview.
"""

from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# --- Data mirrors of the seeded reference data ------------------------------

HUBS = [
    ("Houston", "US", 29.7604, -95.3698, ["air", "sea", "road"]),
    ("Paris", "FR", 48.8566, 2.3522, ["air", "road"]),
    ("Le Havre", "FR", 49.4944, 0.1079, ["sea"]),
    ("Brussels", "BE", 50.8503, 4.3517, ["air", "road"]),
    ("London", "GB", 51.5072, -0.1276, ["air"]),
    ("New York", "US", 40.7128, -74.0060, ["air", "road"]),
    ("Atlanta", "US", 33.7490, -84.3880, ["air", "road"]),
    ("Montreal", "CA", 45.5019, -73.5674, ["air"]),
    ("Lagos", "NG", 6.5244, 3.3792, ["air", "sea", "road"]),
    ("Abidjan", "CI", 5.3600, -4.0083, ["air", "sea", "road"]),
    ("Dakar", "SN", 14.7167, -17.4677, ["air", "road"]),
    ("Dubai", "AE", 25.2048, 55.2708, ["air", "sea"]),
    ("Guangzhou", "CN", 23.1291, 113.2644, ["air", "sea"]),
]

HUB_COORDS = {city: (lat, lon) for city, _country, lat, lon, _modes in HUBS}

LANES = [
    ("Houston", "Paris", "air"), ("Houston", "Brussels", "air"), ("Houston", "Le Havre", "sea"),
    ("Houston", "New York", "sea"), ("New York", "Paris", "air"), ("Lagos", "London", "air"),
    ("Abidjan", "Paris", "air"), ("Dakar", "Paris", "air"), ("Paris", "New York", "air"),
    ("Paris", "Montreal", "air"), ("Brussels", "Atlanta", "air"), ("Houston", "Atlanta", "road"),
    ("Paris", "Brussels", "road"), ("Lagos", "Abidjan", "road"), ("Abidjan", "Dakar", "road"),
    ("Dubai", "Houston", "air"), ("Guangzhou", "New York", "sea"), ("Guangzhou", "Lagos", "sea"),
    ("Dubai", "Paris", "air"), ("New York", "Atlanta", "road"),
]

REGIONS = [
    ("us", "United States", "USPS and UPS last-mile delivery", 39.5, -98.35),
    ("europe", "Europe", "FedEx last-mile delivery", 50.1, 9.7),
    ("africa", "Africa", "Corvane network and local partners", 4.0, 15.0),
    ("asia", "Asia and Middle East", "Corvane network and local partners", 25.0, 70.0),
    ("americas", "Canada and Latin America", "Corvane network and local partners", 10.0, -70.0),
]

MODE_LABEL = {"air": "Air", "sea": "Sea", "road": "Road", "express": "Express"}
MODE_ICON = {"air": "plane", "sea": "ship", "road": "truck", "express": "zap"}
MODE_TRANSIT = {"air": "2–8 days", "sea": "10–45 days", "road": "2–10 days", "express": "Confirmed per route"}

SERVICE_CARDS = [
    {
        "code": "air", "photo": "images/freight-air.webp", "icon": "plane", "name": "Air freight",
        "transit": "2–8 days",  # min/max transit on the active rate card, air lines
        "summary": "Scheduled flights for parcels, business samples and stock that should arrive sooner.",
        "fit": "Parcels, documents and time-sensitive stock",
        "limits": "Charged on chargeable weight: the greater of the scale weight and the parcel volume.",
        "included": ["Export documentation prepared with you", "Customs coordination at both ends", "Local delivery by an established carrier"],
    },
    {
        "code": "sea", "photo": "images/freight-sea.webp", "icon": "ship", "name": "Sea freight",
        "transit": "10–45 days",  # sea lines: 10–20 same continent, 25–45 intercontinental
        "summary": "Shared container space for furniture, equipment and planned stock replenishment.",
        "fit": "Furniture, equipment and bulk stock",
        "limits": "Bulky, low-density cargo is charged on volume rather than scale weight.",
        "included": ["Consolidation before the vessel departs", "Import and export documentation", "A lower cost per kilogram on larger loads"],
    },
    {
        "code": "road", "photo": "images/freight-road.webp", "icon": "truck", "name": "Road freight",
        "transit": "2–10 days",  # road lines exist only for same-continent pairs
        "summary": "Direct collection and delivery on supported land corridors.",
        "fit": "Regional parcels and palletised freight",
        "limits": "Only when origin and destination are on the same connected land area.",
        "included": ["Collection from your address", "Direct delivery without a terminal handoff", "Route checked before your booking is confirmed"],
    },
    {
        "code": "express", "photo": "images/freight-express.webp", "icon": "zap", "name": "Express",
        "transit": "Confirmed per route",  # no express rate lines are published yet
        "summary": "Priority handling for smaller shipments when a date is driving the decision.",
        "fit": "Documents and smaller urgent goods",
        "limits": "Availability is confirmed per route before the booking is accepted.",
        "included": ["Priority handling at key handoffs", "Customs coordination and local delivery", "Confirmed availability before you pay"],
    },
]

AIR_DETAIL = {
    "icon": "plane", "photo": "images/freight-air.webp", "name": "Air freight",
    "tagline": "For parcels that have a little less time to spare.",
    "intro": [
        "Air freight suits shipments where a shorter journey matters more than the lowest price per kilogram. It is a practical choice for parcels, business samples and stock that needs to reach its destination on a tighter schedule.",
        "We plan the shipment around scheduled air capacity, then coordinate the documentation, customs steps and local delivery handoff. You can follow progress from collection through to the final scan.",
    ],
    "fit": "Parcels, documents and time-sensitive stock",
    "transit": "2–8 days",   # from the rate card
    "factor": "1.00",        # TransportMode.multiplier
    "divisor": "5,000",      # TransportMode.volumetric_divisor
    "limits": [
        "Charged on chargeable weight: the greater of the scale weight and the volumetric weight of the parcel.",
        "Volumetric weight is length × width × height divided by the divisor shown for this service.",
        "Rates are published in weight bands. Where no band matches a shipment, the quote tool asks you to contact us for a custom price instead of quoting blind.",
    ],
    "documents": [
        "A commercial invoice for business shipments, showing what the goods are and what they are worth.",
        "A packing list when one consignment contains several packages.",
        "Any permit or licence the destination requires for the goods you are sending.",
    ],
}

# Sample values, labelled as such in the preview: the application computes these
# from the active rate card.
# Lanes mirror RatesController::LANES. Prices below are illustrative values for
# layout review only — the published page computes them from the active rate card.
RATE_ROWS = [
    ("Houston → Paris", "air", {"1": "$116", "5": "$128", "10": "$146", "25": "$218", "50": "$342"}, "4–8"),
    ("Houston → Paris", "sea", {"1": "$40", "5": "$62", "10": "$88", "25": "$158", "50": "$262"}, "25–45"),
    ("Houston → Paris", "road", {"1": "$35", "5": "$52", "10": "$74", "25": "$132", "50": "$214"}, "4–10"),
    ("New York → London", "air", {"1": "$108", "5": "$118", "10": "$132", "25": "$196", "50": "$308"}, "4–8"),
    ("New York → London", "sea", {"1": "$38", "5": "$58", "10": "$82", "25": "$148", "50": "$246"}, "25–45"),
    ("Paris → Houston", "air", {"1": "$114", "5": "$126", "10": "$142", "25": "$212", "50": "$334"}, "4–8"),
    ("Paris → Houston", "sea", {"1": "$42", "5": "$64", "10": "$90", "25": "$162", "50": "$268"}, "25–45"),
    ("Lagos → London", "air", {"1": "$124", "5": "$138", "10": "$158", "25": "$236", "50": "$372"}, "4–8"),
    ("New York → Accra", "air", {"1": "$128", "5": "$142", "10": "$164", "25": "$244", "50": "$386"}, "4–8"),
    ("Paris → Brussels", "air", {"1": "$88", "5": "$96", "10": "$108", "25": "$158", "50": "$248"}, "2–5"),
    ("Paris → Brussels", "sea", {"1": "$34", "5": "$50", "10": "$70", "25": "$126", "50": "$208"}, "10–20"),
    ("Paris → Brussels", "road", {"1": "$28", "5": "$40", "10": "$56", "25": "$98", "50": "$158"}, "2–5"),
]

WEIGHTS = ["1", "5", "10", "25", "50"]


# --- Tracking -----------------------------------------------------------------
# Shapes mirror TrackingService::presentShipment(). The sample result follows the
# seeded air scenario (Houston → Paris, in transit) so the layout is reviewed with
# the same values the application produces.
TRACK_FORMATS = ["Corvane", "UPS", "USPS", "FedEx"]

TRACK_FOUND = {
    "number": "CV-AIR-100013",
    "found": True,
    "carrier": {"code": "corvane", "name": "Corvane"},
    "partner": None,
    "service": "Air freight",
    "mode": "air",
    "status": "in_transit",
    "status_label": "In transit",
    "origin": "Houston, US",
    "destination": "Paris, FR",
    "eta_hours": 72,
    "progress": 0.55,
    "weight_kg": 12.0,
    "data_source": "Corvane",
    "events": [
        ("Arrived at destination airport", "Paris CDG, FR", 6, "Corvane"),
        ("Departed origin airport", "Houston, US", 30, "Corvane"),
        ("Picked up at sender address", "Houston, US", 60, "Corvane"),
        ("Shipment registered, label created", "Houston, US", 72, "Corvane"),
    ],
}

TRACK_NOT_FOUND = {
    "number": "1Z999AA10123456784",
    "found": False,
    "carrier": {"code": "ups", "name": "UPS"},
    "message": "We recognised this as a UPS number, but live UPS data is not available here yet.",
    "external_url": "https://www.ups.com/track?tracknum=1Z999AA10123456784",
}

TRACK_MILESTONES = [
    ("Label created", 5), ("Picked up", 15), ("In transit", 50),
    ("At customs", 70), ("Out for delivery", 90), ("Delivered", 100),
]

TRACK_STATUS_MEANINGS = [
    ("Ready for pickup or drop-off", "clock", "The label is issued and the parcel is waiting for collection or drop-off."),
    ("Picked up", "package", "We have the parcel and it is being prepared for the long-distance leg."),
    ("In transit", "plane", "The parcel is moving between two handoff points, in the air, at sea or on the road."),
    ("At customs", "file-text", "Customs is reviewing the paperwork. This is usually where a missing document is noticed."),
    ("Out for delivery", "truck", "The parcel is with the delivery partner and is on its way to the recipient."),
    ("Delivered", "circle-check", "Delivery is complete. The proof of delivery is kept on the shipment record."),
    ("Delayed", "hourglass", "A schedule, customs or weather problem has moved the expected delivery date."),
    ("Returned", "life-buoy", "The parcel could not be delivered and is on its way back to the sender."),
]

TRACK_FAQS = [
    ("Where do I find my tracking number?",
     "It is issued when your payment is approved. You will find it in the confirmation email, in your account under Shipments, and printed on the label and the receipt PDF."),
    ("Why has the status not changed?",
     "Scans are only recorded at handoffs: collection, departure, arrival, customs and delivery. On sea freight several days can pass between two scans, and on air freight the gaps are usually measured in hours. If nothing has changed at all for longer than the window shown on your quote, contact us and we will chase it."),
    ("What does the source on an event mean?",
     "It tells you who recorded the scan. Your shipment shows our own team as the source, because each handoff we control is entered by staff. For a recognised carrier number we show the carrier's data instead, so you can always tell the two apart."),
    ("Can I track a USPS, UPS or FedEx number here?",
     "Yes, when we recognise the number format. We show that carrier's own events next to a link to their site. A Corvane tracking number gives you the whole journey instead, including the customs and last-mile legs."),
    ("Why is there no street address on this page?",
     "Public tracking shows cities and countries only. Street addresses, phone numbers and email addresses are never published, so a tracking link is safe to share with whoever needs it."),
    ("What happens if a delivery is missed?",
     "The delivery partner records the attempt as an event and holds the parcel for a further attempt or collection. We use the contact details on the booking to reach you, and if the parcel comes back to us we treat it as a return."),
]


# --- Account ------------------------------------------------------------------
# Mirror of the seeded demo account (DemoSeeder) so the account surface is reviewed
# with the same values the application shows: Chantal Mbarga, three shipments on the
# Houston → Paris, Guangzhou → New York and Paris → London scenarios. Tracking
# numbers carry a real Luhn check digit, order and invoice numbers follow
# SequenceGenerator::orderNumber()/invoiceNumber(), payment references follow
# ReferenceGenerator (no ambiguous characters).
ACCOUNT_USER = {"name": "Chantal Mbarga", "email": "customer@corvane.test", "phone": "+1 713 555 0123"}

ACCOUNT_STATS = [
    ("package", "Active shipments", 2, "In transit, at customs or out for delivery"),
    ("hourglass", "Awaiting payment", 2, "Orders that still need a payment or a proof"),
    ("shield-check", "Payments under review", 1, "With a verifier, usually under 30 minutes"),
]

ACCOUNT_AWAITING = [
    {
        "number": "ORD-2026-000148", "status": "awaiting_payment", "status_label": "Awaiting payment",
        "route": "Houston, US → Paris, FR", "reference": "PAY-4KP7ZX", "total": "$425.00",
        "expires_label": "Until 12 Oct, 09:30 UTC", "remaining": "21:46:12", "urgent": False,
    },
    {
        "number": "ORD-2026-000147", "status": "proof_rejected", "status_label": "Proof not accepted",
        "route": "Paris, FR → Brussels, BE", "reference": "PAY-9TW2MN", "total": "$98.00",
        "expires_label": "Until 11 Oct, 17:05 UTC", "remaining": "05:12:40", "urgent": True,
    },
]

ACCOUNT_REVIEW = [
    {
        "number": "ORD-2026-000146", "status": "under_review", "status_label": "Under review",
        "route": "Guangzhou, CN → New York, US", "total": "$1,860.00",
    },
]

ACCOUNT_ACTIVE = [
    {
        "tracking": "CV-AIR-482109", "service": "Air", "route": "Houston, US → Paris, FR",
        "status": "in_transit", "status_label": "In transit", "progress": 55,
        "last_label": "Arrived at destination airport", "last_place": "Paris CDG, FR", "last_hours": 6,
    },
    {
        "tracking": "CV-SEA-482117", "service": "Sea", "route": "Guangzhou, CN → New York, US",
        "status": "in_transit", "status_label": "In transit", "progress": 40,
        "last_label": "Vessel in transit, Pacific Ocean", "last_place": "At sea", "last_hours": 120,
    },
]

ACCOUNT_ACTIVITY = [
    ("Proof of payment submitted", "Order", "ORD-2026-000146", "3 h ago"),
    ("Payment method selected", "Order", "ORD-2026-000148", "6 h ago"),
    ("Order created", "Order", "ORD-2026-000148", "6 h ago"),
    ("Two-factor authentication enabled", "User", "customer@corvane.test", "12 days ago"),
]

ACCOUNT_SHIPMENTS = [
    {
        "tracking": "CV-AIR-482109", "released": True, "route": "Houston, US → Paris, FR", "service": "Air",
        "status": "in_transit", "status_label": "In transit", "created": "8 Oct 2026",
    },
    {
        "tracking": "CV-SEA-482117", "released": True, "route": "Guangzhou, CN → New York, US", "service": "Sea",
        "status": "in_transit", "status_label": "In transit", "created": "24 Sep 2026",
    },
    {
        "tracking": "CV-RD-482125", "released": True, "route": "Paris, FR → London, GB", "service": "Road",
        "status": "delivered", "status_label": "Delivered", "created": "2 Sep 2026",
    },
]

ACCOUNT_STATUS_MEANINGS = [
    ("Pending", "Booked and priced, waiting for the payment to be verified. No tracking number is issued yet."),
    ("In transit", "The parcel has been picked up and is moving along the corridor. Each handoff adds a scan with its place."),
    ("At customs", "Held by the customs authority of the destination country. We publish the scan we receive; clearance is decided by them."),
    ("Delivered", "Handed to the recipient or to their address. The proof of delivery becomes available in the shipment documents."),
]

ACCOUNT_ORDERS = [
    {
        "number": "ORD-2026-000148", "status": "awaiting_payment", "status_label": "Awaiting payment", "open": True,
        "route": "Houston, US → Paris, FR", "created": "8 Oct 2026", "reference": "PAY-4KP7ZX",
        "expires_label": "Until 12 Oct, 09:30 UTC", "total": "$425.00", "action": "Pay now",
        "invoices": [],
    },
    {
        "number": "ORD-2026-000146", "status": "under_review", "status_label": "Under review", "open": True,
        "route": "Guangzhou, CN → New York, US", "created": "3 Oct 2026", "reference": "PAY-6HD3QV",
        "expires_label": "Until 13 Oct, 11:00 UTC", "total": "$1,860.00", "action": "View status", "invoices": [],
    },
    {
        "number": "ORD-2026-000131", "status": "paid", "status_label": "Paid", "open": False,
        "route": "Paris, FR → London, GB", "created": "2 Sep 2026", "reference": "PAY-2XR8JT",
        "expires_label": "", "total": "$98.00", "action": "View shipment",
        "invoices": [("Invoice", "INV-2026-000131"), ("Receipt", "RCPT-2026-000131")],
    },
]

ACCOUNT_SHIPMENT_DETAIL = {
    "tracking": "CV-AIR-482109", "service": "Air", "status": "in_transit", "status_label": "In transit",
    "route": "Houston, US → Paris, FR", "progress": 55, "released": True,
    "packages": [("Clothes and shoes", "Personal effects", "12.00 kg", "40 × 30 × 30 cm")],
    "timeline": [
        ("Arrived at destination airport", "Paris CDG, FR", "6 h ago", "Reported by the carrier"),
        ("Departed origin airport", "Houston, US", "30 h ago", "Reported by our partner carrier"),
        ("Picked up at sender address", "Houston, US", "60 h ago", "Reported by our partner carrier"),
        ("Shipment registered, label created", "Houston, US", "72 h ago", "Reported by Corvane"),
    ],
    "documents": [
        ("Shipping label", "tag", True), ("Commercial invoice", "file-text", True),
        ("Payment receipt", "file-check", True), ("Proof of delivery", "package-check", False),
    ],
    "sender": ("Chantal Mbarga", "1000 Shipping Lane", "Houston 77002 · US"),
    "recipient": ("Marc Lefèvre", "12 rue de Rivoli", "Paris 75001 · FR"),
}

ACCOUNT_PAY = {
    "number": "ORD-2026-000148", "status": "awaiting_payment", "status_label": "Awaiting payment",
    "route": "Houston, US → Paris, FR", "subtotal": "$425.00", "fee": "", "total": "$425.00",
    "reference": "PAY-4KP7ZX", "remaining": "21:46:12", "urgent": False,
    "expires_label": "Until 12 Oct, 09:30 UTC",
    "methods": [
        ("zelle", "Zelle", "USD", "$0.00", True),
        ("cashapp", "Cash App", "USD", "$0.00", False),
        ("iban", "IBAN transfer", "EUR", "$0.00", False),
        ("paypal", "PayPal", "USD", "$14.88", False),
    ],
    "fields": [
        ("Email", "payments@corvane.test", "copy"),
        ("Account holder", "Corvane Logistics (DEMO)", "copy"),
    ],
    "steps": [
        "Open Zelle in your bank app and choose Send money.",
        "Enter the email above and the exact amount: $425.00.",
        "Write PAY-4KP7ZX in the memo, then keep the confirmation screenshot.",
    ],
    "instructions": "Send the exact amount and write your payment reference in the note. Keep a screenshot of the confirmation.",
    "history": [
        ("Zelle", "$425.00", "8 Oct 2026, 09:12", "Rejected", "The reference was missing from the memo. Send the transfer again with PAY-4KP7ZX in the note, or upload a screenshot showing it."),
        ("Zelle", "$425.00", "7 Oct 2026, 21:40", "More information requested", "The screenshot was cropped: the amount and the date were not visible."),
    ],
}

ACCOUNT_BOOKING = {
    "steps": ["Route", "Packages", "Service", "Parties", "Review"],
    "current": 3,
    "quote_reference": "Q-7QM4KD",
    "packages": [
        ("Clothes and shoes", "Personal effects", "12", "40", "30", "30"),
        ("Documents and samples", "Documents", "0.8", "32", "24", "4"),
    ],
    "options": [
        ("air", "Air", True, "$425.00", "2–8 days", "12.5 kg chargeable"),
        ("sea", "Sea", True, "$186.00", "10–45 days", "12.5 kg chargeable"),
        ("road", "Road", False, "", "", "Only when origin and destination are on the same connected land area."),
        ("express", "Express", False, "", "", "Availability is confirmed per route before the booking is accepted."),
    ],
    "total": "$425.00",
    "customs": [("Contents", "Worn clothes and two business samples"), ("HS code", "6204.42"), ("Reason for export", "Personal effects")],
}
