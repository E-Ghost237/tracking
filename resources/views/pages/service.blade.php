@php
    /*
     * Service detail page. Narrative content is per mode; every figure (transit
     * window, rate factor, volumetric divisor) is read from the transport mode
     * record and the active rate card, so nothing here can drift from pricing.
     */
    $content = [
        'air' => [
            'icon' => 'plane',
            'photo' => 'service_air',
            'tagline' => __('For parcels that have a little less time to spare.'),
            'intro' => [
                __('Air freight suits shipments where a shorter journey matters more than the lowest price per kilogram. It is a practical choice for parcels, business samples and stock that needs to reach its destination on a tighter schedule.'),
                __('We plan the shipment around scheduled air capacity, then coordinate the documentation, customs steps and local delivery handoff. You can follow progress from collection through to the final scan.'),
            ],
            'fit' => __('Parcels, documents and time-sensitive stock'),
            'limits' => [
                __('Charged on chargeable weight: the greater of the scale weight and the volumetric weight of the parcel.'),
                __('Volumetric weight is length × width × height divided by the divisor shown for this service.'),
                __('Rates are published in weight bands. Where no band matches a shipment, the quote tool asks you to contact us for a custom price instead of quoting blind.'),
            ],
            'documents' => [
                __('A commercial invoice for business shipments, showing what the goods are and what they are worth.'),
                __('A packing list when one consignment contains several packages.'),
                __('Any permit or licence the destination requires for the goods you are sending.'),
            ],
        ],
        'sea' => [
            'icon' => 'ship',
            'photo' => 'service_sea',
            'tagline' => __('More room for the things that make a long journey worthwhile.'),
            'intro' => [
                __('When your goods are heavy, bulky or moving on a planned schedule, shared container space can be a more economical choice than air. It is often used for furniture, equipment and regular business stock moving between continents.'),
                __('Your shipment is consolidated with other eligible cargo before departure. We coordinate export and import documentation, share progress updates while it is in transit and arrange the next delivery step once it reaches port.'),
            ],
            'fit' => __('Furniture, equipment and bulk stock'),
            'limits' => [
                __('Charged on volume more than on scale weight, so bulky light cargo is billed on the space it occupies.'),
                __('Palletised goods and shared container loads are confirmed at booking, with dimensions and total volume recorded.'),
                __('Sea space is booked per sailing, so the quote reflects the next available departure rather than a daily cut-off.'),
            ],
            'documents' => [
                __('A commercial invoice and packing list covering the whole consignment.'),
                __('Consignee details for the bill of lading, including who clears the goods at the port.'),
                __('Import permits or certificates the destination authority requires.'),
            ],
        ],
        'road' => [
            'icon' => 'truck',
            'photo' => 'service_road',
            'tagline' => __('A direct option for supported journeys over land.'),
            'intro' => [
                __('Road freight connects collection and delivery points on established regional corridors. It suits eligible parcels and palletised goods where a flexible pickup and a door-to-door handoff are useful.'),
                __('Road service is available when origin and destination sit within the same supported land area. For a longer journey the quote tool will compare air or sea alternatives instead, so you are not offered a route we cannot run.'),
            ],
            'fit' => __('Regional parcels and palletised freight'),
            'limits' => [
                __('Available only when the origin and destination are on the same connected land area.'),
                __('Charged on chargeable weight, with volume measured against the divisor shown for this service.'),
                __('Where a distance exceeds what we cover directly, the route is priced with a partner carrier before it is offered to you.'),
            ],
            'documents' => [
                __('A delivery note or invoice that describes the consignment.'),
                __('Customs paperwork when the route crosses a border, including the value of the goods.'),
                __('Proof of delivery arrangements if someone other than the recipient will sign.'),
            ],
        ],
        'express' => [
            'icon' => 'zap',
            'photo' => 'service_express',
            'tagline' => __('Priority handling for smaller shipments when a date matters.'),
            'intro' => [
                __('Express moves smaller shipments through priority handling at each handoff. It is used for documents, samples and replacement parts where the cost of waiting is higher than the cost of shipping.'),
                __('Availability depends on the route and the space available on the day, so the quote confirms the option before you pay. Nothing is promised that we cannot run.'),
            ],
            'fit' => __('Documents and smaller urgent goods'),
            'limits' => [
                __('Confirmed per route: the quote tells you whether express handling is available before you book.'),
                __('Charged on chargeable weight in the same way as air freight, with the same volumetric divisor.'),
                __('Larger consignments are moved on the air freight service instead, which is priced per published band.'),
            ],
            'documents' => [
                __('A commercial invoice or a short contents description with a declared value.'),
                __('Recipient contact details, because urgent shipments often need a delivery call.'),
                __('Any licence the destination requires for the goods being sent.'),
            ],
        ],
    ];

    /*
     * The tables below are built from the pricing rules themselves — the volume
     * divisor comes from the mode record and the band ladder matches the rate
     * card — so nothing on this page can drift from what the quote tool does.
     */
    $divisor = number_format($mode->volumetric_divisor);

    $service = $content[$code];

    // Columns: label, rule.
    $weightRules = [
        [__('Chargeable weight'), __('The greater of the scale weight and the volumetric weight, counted package by package.')],
        [__('Volumetric weight'), __('Length × width × height in centimetres, divided by the volume divisor for this service.')],
        [__('Volume divisor'), $divisor],
        [__('Rounding'), __('Rounded up to the next half kilogram, with a minimum of 0.5 kg.')],
        [__('Several packages'), __('Each package is weighed and measured, then the chargeable weights are added before the rate is applied.')],
        [__('Small shipments'), __('A minimum charge applies to the smallest shipments; it is always shown in your quote breakdown.')],
    ];

    // Columns: band, what it usually is.
    $bands = [
        ['1–5 kg', __('Documents, small parcels and samples')],
        ['5–20 kg', __('Several parcels, or one carton packed solid')],
        ['20–50 kg', __('Palletised goods moving on air or road')],
        ['50–200 kg', __('Grouped cargo travelling together')],
        ['200–1,000 kg', __('Part loads and heavier air freight')],
        ['1,000–3,000 kg', __('Consolidated shipments and shared container space')],
    ];

    // Columns: what you are sending, what to attach, what usually holds it up.
    $paperwork = [
        [
            __('Documents and letters'),
            __('A short contents description with a declared value.'),
            __('A vague description, such as “documents”.'),
        ],
        [
            __('Personal effects'),
            __('An itemised list of what is inside, with a value for each item.'),
            __('One total value with nothing itemised.'),
        ],
        [
            __('Commercial samples'),
            __('A commercial invoice that marks the goods as samples and states their value.'),
            __('A declared value that does not reflect the goods.'),
        ],
        [
            __('Goods for resale'),
            __('A commercial invoice, a packing list for every package, and the permits the destination requires.'),
            __('An invoice that does not match what is in the parcel.'),
        ],
        [
            __('Medicine and batteries'),
            __('The permit, or the safety documentation the destination authority asks for.'),
            __('No permit or safety data sheet attached.'),
        ],
    ];

    $packaging = [
        __('Double-wall cartons for anything that can break, and rigid corners for flat items.'),
        __('About five centimetres of padding on every side so the contents cannot move.'),
        __('One label per package, and no old labels or barcodes left on the box.'),
        __('Inner protection for liquids, and nothing fragile packed loose beside them.'),
    ];
@endphp

@extends('layouts.app', [
    'title' => $mode->localizedName(),
    'description' => $content[$code]['tagline'],
])

@section('content')
    {{-- Hero: photograph, breadcrumb and the two figures a shipper checks first --}}
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">
            <x-photo :key="$service['photo']" class="size-full object-cover object-center" sizes="100vw" priority />
        </div>
        <div class="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true"></div>

        <div class="container-page py-10 sm:py-12 lg:py-16">
            <nav aria-label="{{ __('Breadcrumb') }}" class="text-sm">
                <ol class="flex flex-wrap items-center gap-2 text-slate-300">
                    <li><a href="{{ lroute('services') }}" class="transition hover:text-white">{{ __('Services') }}</a></li>
                    <li aria-hidden="true" class="text-slate-500">/</li>
                    <li class="font-medium text-white" aria-current="page">{{ $mode->localizedName() }}</li>
                </ol>
            </nav>

            <div class="mt-6 grid gap-8 lg:grid-cols-[1.6fr_1fr] lg:items-end">
                <div>
                    <p class="eyebrow !text-slate-300">
                        <x-lucide :name="$service['icon']" class="size-4" />
                        {{ __('Freight service') }}
                    </p>
                    <h1 class="mt-3 text-[2rem] leading-[1.08] font-bold text-white sm:text-[2.6rem] lg:text-[3rem]">{{ $mode->localizedName() }}</h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-slate-300">{{ $service['tagline'] }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-[6px] border border-white/15 bg-white/10">
                    <div class="bg-ink-950/70 p-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">{{ __('Published transit') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-white tabular">{{ $transit[$code] ?? __('Confirmed per route') }}</dd>
                    </div>
                    <div class="bg-ink-950/70 p-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">{{ __('Rate factor') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-white tabular">× {{ number_format($mode->multiplier, 2) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-primary">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                <a href="{{ lroute('rates') }}" class="btn-light">{{ __('See published rates') }}</a>
            </div>
        </div>
    </section>

    <div class="container-page grid gap-10 py-12 lg:grid-cols-12 lg:gap-14 lg:py-16">
        <article class="lg:col-span-7">
            <div class="prose-content">
                <h2 class="!mt-0">{{ __('How this service works') }}</h2>
                @foreach ($service['intro'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('Size, weight and limits') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('How the chargeable weight is worked out for this service, and what happens when a shipment falls outside the published bands.') }}</p>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    @foreach ($service['limits'] as $item)
                        <li class="flex items-start gap-3 py-3.5 text-sm text-slate-700">
                            <x-lucide name="ruler" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('How the chargeable weight is worked out') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Every quote and every invoice uses the same method. It is worth knowing before you buy packaging, because a large light parcel can cost more than a small heavy one.') }}</p>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('How the chargeable weight is worked out for this service') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Rule') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('What it means for your shipment') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($weightRules as [$rule, $explanation])
                                <tr>
                                    <th scope="row" class="w-[38%] px-4 py-3.5 text-left font-semibold text-ink-950">{{ $rule }}</th>
                                    <td class="px-4 py-3.5 text-slate-700 tabular">{{ $explanation }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-8 text-base font-bold">{{ __('Published weight bands') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Rates are published in bands, and the price per kilogram falls as a consignment gets heavier. Anything above the top band is priced with you individually rather than quoted blind.') }}</p>
                <ul class="mt-4 divide-y divide-line border-y border-line text-sm">
                    @foreach ($bands as [$range, $suits])
                        <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                            <span class="w-[110px] shrink-0 font-semibold text-ink-950 tabular">{{ $range }}</span>
                            <span class="text-slate-700">{{ $suits }}</span>
                        </li>
                    @endforeach
                    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-baseline sm:gap-4">
                        <span class="w-[110px] shrink-0 font-semibold text-ink-950">{{ __('Above 3,000 kg') }}</span>
                        <span class="text-slate-700">{{ __('Tell us the details and we will build the price with you, including any handling the load needs.') }}</span>
                    </li>
                </ul>
            </section>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('Documents to prepare') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Missing paperwork is the most common cause of a customs delay. Have these ready before collection.') }}</p>
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    @foreach ($service['documents'] as $item)
                        <li class="flex items-start gap-3 py-3.5 text-sm text-slate-700">
                            <x-lucide name="file-text" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
                <h3 class="mt-8 text-base font-bold">{{ __('Paperwork at a glance') }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('What to attach depends on what you are sending. This is the short version; the customs guide covers declarations, values and duties in full.') }}</p>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-sm">
                            <caption class="sr-only">{{ __('Documents to attach by type of shipment') }}</caption>
                            <thead>
                                <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('What you are sending') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('What to attach') }}</th>
                                    <th scope="col" class="px-4 py-3 font-medium">{{ __('What usually holds it up') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($paperwork as [$kind, $attach, $delay])
                                    <tr>
                                        <th scope="row" class="px-4 py-3.5 text-left font-semibold text-ink-950">{{ $kind }}</th>
                                        <td class="px-4 py-3.5 text-slate-700">{{ $attach }}</td>
                                        <td class="px-4 py-3.5 text-slate-600">{{ $delay }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-slate-600">
                    <a href="{{ lroute('page.customs') }}" class="link">{{ __('Read the customs guide') }}</a>
                    <a href="{{ lroute('page.packing') }}" class="link">{{ __('Packing guide') }}</a>
                    <a href="{{ lroute('page.prohibited-items') }}" class="link">{{ __('Restricted and prohibited goods') }}</a>
                </p>
            </section>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('Packaging that survives the journey') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Most damage is decided before the parcel is collected. A few minutes of packing prevents the claim that takes weeks to settle.') }}</p>
                <ul class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ($packaging as $rule)
                        <li class="flex items-start gap-2.5 text-sm text-slate-700">
                            <x-lucide name="boxes" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                            {{ $rule }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ __('Keep a photograph of the packed parcel and its contents. If something does go wrong, those pictures make a claim straightforward rather than a negotiation.') }}</p>
            </section>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('Before you book') }}</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        __('Sender and recipient names, addresses and phone numbers'),
                        __('A clear contents description and a realistic declared value'),
                        __('Parcel dimensions and weight, measured after packing'),
                        __('Any permits required for the goods or the destination'),
                    ] as $item)
                        <li class="flex items-start gap-2.5 rounded-[6px] border border-line p-3.5 text-sm text-slate-700">
                            <x-lucide name="check" class="mt-0.5 size-4 shrink-0 text-emerald-700" />
                            {{ $item }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-5 text-sm leading-6 text-slate-600">{{ __('Transit windows are estimates and can change with schedules, customs processing and local delivery conditions. Your quote shows the current window for the route you enter.') }}</p>
            </section>

            <section class="mt-10">
                <h2 class="text-xl font-bold">{{ __('What the price covers') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Freight pricing is easier to trust when you know where it stops. These are the two lists that decide what you pay at the end.') }}</p>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div class="rounded-[6px] border border-line p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink-950">
                            <x-lucide name="circle-check" class="size-4 text-emerald-700" />
                            {{ __('Included in the price') }}
                        </p>
                        <ul class="mt-3.5 space-y-2.5 text-sm text-slate-700">
                            @foreach ([
                                __('Carriage on the service you booked, on the route in your quote.'),
                                __('A tracking number, with a scan logged at each handoff.'),
                                __('The shipping label and the documents issued with it.'),
                                __('Coordination with the customs steps on the booked route.'),
                                __('Email updates when the shipment status changes.'),
                            ] as $item)
                                <li class="flex items-start gap-2.5">
                                    <x-lucide name="check" class="mt-0.5 size-4 shrink-0 text-emerald-700" />
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="rounded-[6px] border border-line p-5">
                        <p class="flex items-center gap-2 text-sm font-semibold text-ink-950">
                            <x-lucide name="info" class="size-4 text-slate-500" />
                            {{ __('Charged separately') }}
                        </p>
                        <ul class="mt-3.5 space-y-2.5 text-sm text-slate-700">
                            @foreach ([
                                __('Import duties and taxes, which are set by the destination country.'),
                                __('Any charge a customs authority or port adds for inspection or storage.'),
                                __('Insurance, which is arranged on request and listed in your quote.'),
                                __('A difference in price if the measured weight or size is greater than declared — and a refund if it is smaller.'),
                            ] as $item)
                                <li class="flex items-start gap-2.5">
                                    <x-lucide name="info" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ __('Nothing else is added later without appearing in your account first. Your order page lists every charge, and the invoice and receipt stay available there for download.') }}</p>
            </section>
        </article>

        {{-- Sticky sidebar: the summary a customer keeps open while filling the quote form --}}
        <aside class="space-y-5 lg:col-span-5">
            <div class="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
                <div class="card overflow-hidden">
                    <div class="border-b border-line bg-surface px-5 py-4">
                        <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('At a glance') }}</p>
                        <p class="mt-1.5 font-bold text-ink-950">{{ $service['fit'] }}</p>
                    </div>
                    <dl class="divide-y divide-line text-sm">
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <dt class="text-slate-600">{{ __('Published transit') }}</dt>
                            <dd class="font-semibold text-ink-900 tabular">{{ $transit[$code] ?? __('Confirmed per route') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <dt class="text-slate-600">{{ __('Rate factor') }}</dt>
                            <dd class="font-semibold text-ink-900 tabular">× {{ number_format($mode->multiplier, 2) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <dt class="text-slate-600">{{ __('Volume divisor') }}</dt>
                            <dd class="font-semibold text-ink-900 tabular">{{ $divisor }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <dt class="text-slate-600">{{ __('Tracking') }}</dt>
                            <dd class="font-semibold text-ink-900">{{ __('Included') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                            <dt class="text-slate-600">{{ __('Insurance') }}</dt>
                            <dd class="font-semibold text-ink-900">{{ __('Optional') }}</dd>
                        </div>
                    </dl>
                    <div class="border-t border-line p-5">
                        <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-primary w-full">{{ __('Price this route') }} <x-lucide name="arrow-right" class="size-4" /></a>
                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ __('The final price depends on the route, chargeable weight and the options you choose.') }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-[6px] border border-line p-5">
                    <h2 class="text-base font-bold">{{ __('How the price is shaped') }}</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600">
                        <li class="flex gap-3"><x-lucide name="scale" class="mt-0.5 size-4 shrink-0 text-slate-500" /> {{ __('Chargeable weight: the greater of scale weight and volumetric weight, rounded up to the next half kilogram.') }}</li>
                        <li class="flex gap-3"><x-lucide name="route" class="mt-0.5 size-4 shrink-0 text-slate-500" /> {{ __('The published band for your route and weight, then this service rate factor.') }}</li>
                        <li class="flex gap-3"><x-lucide name="receipt" class="mt-0.5 size-4 shrink-0 text-slate-500" /> {{ __('Fuel, handling, insurance and any destination charges, each listed separately in the quote.') }}</li>
                        <li class="flex gap-3"><x-lucide name="info" class="mt-0.5 size-4 shrink-0 text-slate-500" /> {{ __('A minimum charge applies to the smallest shipments; it is shown in the quote breakdown.') }}</li>
                    </ul>
                </div>

                <div class="mt-5 flex items-start gap-3 rounded-[6px] bg-surface p-4 text-sm">
                    <x-lucide name="headset" class="mt-0.5 size-5 shrink-0 text-slate-500" />
                    <p class="text-slate-600">
                        {{ __('Questions about this service?') }}
                        <a href="{{ lroute('contact') }}" class="link">{{ __('Talk to our team') }}</a>
                    </p>
                </div>
            </div>
        </aside>
    </div>

    {{-- Other services --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Other ways to move goods') }}</h2>
            <div class="mt-6 grid gap-5 sm:grid-cols-3">
                @foreach ($modes as $other)
                    @continue($other->code === $code)
                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$other->code)]) }}" class="card card-hover flex items-center gap-4 p-5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700">
                            <x-lucide :name="$content[$other->code]['icon'] ?? 'package'" class="size-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-bold text-ink-950">{{ $other->localizedName() }}</span>
                            <span class="mt-0.5 block text-sm text-slate-600 tabular">
                                {{ $transit[$other->code] ?? __('Confirmed per route') }}
                            </span>
                        </span>
                        <x-lucide name="arrow-right" class="ml-auto size-4 shrink-0 text-slate-500" />
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
