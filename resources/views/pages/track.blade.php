@extends('layouts.app', ['title' => __('Track a parcel'), 'noindex' => true, 'description' => __('Track USPS, UPS, FedEx and :brand shipments in one place.', ['brand' => config('platform.brand.name')])])

@php
    /*
     * Tracking is the most-used page on the site, so the lookup tool sits in the
     * hero and everything below it explains the result rather than decorating it.
     */
    $milestones = [
        ['label' => __('Label created'), 'at' => 5],
        ['label' => __('Picked up'), 'at' => 15],
        ['label' => __('In transit'), 'at' => 50],
        ['label' => __('At customs'), 'at' => 70],
        ['label' => __('Out for delivery'), 'at' => 90],
        ['label' => __('Delivered'), 'at' => 100],
    ];

    $statusMeanings = [
        [\App\Enums\ShipmentStatus::Ready->label(), 'clock', __('The label is issued and the parcel is waiting for collection or drop-off.')],
        [\App\Enums\ShipmentStatus::PickedUp->label(), 'package', __('We have the parcel and it is being prepared for the long-distance leg.')],
        [\App\Enums\ShipmentStatus::InTransit->label(), 'plane', __('The parcel is moving between two handoff points, in the air, at sea or on the road.')],
        [\App\Enums\ShipmentStatus::AtCustoms->label(), 'file-text', __('Customs is reviewing the paperwork. This is usually where a missing document is noticed.')],
        [\App\Enums\ShipmentStatus::OutForDelivery->label(), 'truck', __('The parcel is with the delivery partner and is on its way to the recipient.')],
        [\App\Enums\ShipmentStatus::Delivered->label(), 'circle-check', __('Delivery is complete. The proof of delivery is kept on the shipment record.')],
        [\App\Enums\ShipmentStatus::Delayed->label(), 'hourglass', __('A schedule, customs or weather problem has moved the expected delivery date.')],
        [\App\Enums\ShipmentStatus::Returned->label(), 'life-buoy', __('The parcel could not be delivered and is on its way back to the sender.')],
    ];

    $faqs = [
        [__('Where do I find my tracking number?'), __('It is issued when your payment is approved. You will find it in the confirmation email, in your account under Shipments, and printed on the label and the receipt PDF.')],
        [__('Why has the status not changed?'), __('Scans are only recorded at handoffs: collection, departure, arrival, customs and delivery. On sea freight several days can pass between two scans, and on air freight the gaps are usually measured in hours. If nothing has changed at all for longer than the window shown on your quote, contact us and we will chase it.')],
        [__('What does the source on an event mean?'), __('It tells you who recorded the scan. Your shipment shows our own team as the source, because each handoff we control is entered by staff. For a recognised carrier number we show the carrier\'s data instead, so you can always tell the two apart.')],
        [__('Can I track a USPS, UPS or FedEx number here?'), __('Yes, when we recognise the number format. We show that carrier\'s own events next to a link to their site. A :brand tracking number gives you the whole journey instead, including the customs and last-mile legs.', ['brand' => config('platform.brand.name')])],
        [__('Why is there no street address on this page?'), __('Public tracking shows cities and countries only. Street addresses, phone numbers and email addresses are never published, so a tracking link is safe to share with whoever needs it.')],
        [__('What happens if a delivery is missed?'), __('The delivery partner records the attempt as an event and holds the parcel for a further attempt or collection. We use the contact details on the booking to reach you, and if the parcel comes back to us we treat it as a return.')],
    ];
@endphp

@section('content')
    <div x-data="trackPage"
         data-track-url="{{ lroute('track') }}"
         data-number="{{ $number }}"
         data-numbers="{{ request()->query('numbers') && is_string(request()->query('numbers')) ? \Illuminate\Support\Str::limit(request()->query('numbers'), 900, '') : '' }}">

        {{-- Lookup: the tool sits in the hero, above the fold, on every visit --}}
        <section class="relative isolate overflow-hidden bg-ink-950 text-white">
            <div class="absolute inset-0 -z-20">
                <x-photo key="editorial_network" class="size-full object-cover object-center" sizes="100vw" priority />
            </div>
            <div class="absolute inset-0 -z-10 bg-ink-950/88" aria-hidden="true"></div>

            <div class="container-page grid gap-10 py-10 sm:py-12 lg:grid-cols-[1.15fr_1fr] lg:items-center lg:gap-14 lg:py-14">
                <div>
                    <p class="eyebrow !text-slate-300"><x-lucide name="radar" class="size-4" /> {{ __('Shipment tracking') }}</p>
                    <h1 class="mt-3 text-[2rem] leading-[1.08] font-bold text-white sm:text-[2.6rem] lg:text-[3rem]">{{ __('See where the journey stands.') }}</h1>
                    <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300 sm:text-base">{{ __('Enter up to 20 tracking numbers, one per line or separated by commas. We recognise supported USPS, UPS, FedEx and :brand numbers, then bring the available scans together so you do not have to check several sites.', ['brand' => config('platform.brand.name')]) }}</p>

                    <form @submit.prevent="lookup()" class="mt-7 grid gap-3 sm:grid-cols-[1fr_auto]" action="{{ lroute('track') }}" method="GET">
                        <label for="track-input" class="sr-only">{{ __('Tracking numbers') }}</label>
                        <textarea id="track-input" x-model="input" name="numbers" rows="2" spellcheck="false" autocomplete="off"
                                  class="min-h-[3.25rem] w-full resize-y rounded-[6px] border-0 bg-white px-4 py-3.5 font-mono text-sm text-ink-900 placeholder:font-sans placeholder:text-slate-500 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                                  placeholder="{{ __('CV-AIR-100013, 1Z999AA10123456784') }}">{{ $number }}</textarea>
                        <button type="submit" class="btn-primary h-[3.25rem] !px-7" :disabled="loading">
                            <span x-show="!loading" class="flex items-center gap-2"><x-lucide name="search" class="size-4" /> {{ __('Track') }}</span>
                            <span x-show="loading" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Searching') }}</span>
                        </button>
                    </form>

                    @if (! empty($carrierFormats))
                        <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-slate-400">
                            <span class="font-semibold tracking-[0.08em] uppercase">{{ __('Recognised formats') }}</span>
                            @foreach ($carrierFormats as $carrier)
                                <span class="rounded-[4px] border border-white/15 px-2 py-0.5 font-medium text-slate-200">{{ $carrier['name'] }}</span>
                            @endforeach
                        </p>
                    @endif

                    <div x-show="captcha" x-cloak class="mt-4 flex max-w-xl flex-wrap items-center gap-3 rounded-[6px] bg-white/10 p-4">
                        <img :src="captcha && captcha.src" alt="{{ __('Arithmetic question') }}" width="170" height="54" class="rounded-[4px]">
                        <div class="min-w-0 flex-1">
                            <label for="captcha-answer" class="text-xs text-slate-300">{{ __('Answer to continue tracking') }}</label>
                            <input id="captcha-answer" x-model="captchaAnswer" type="text" inputmode="numeric" class="field mt-1 max-w-[10rem]" placeholder="{{ __('Answer') }}">
                        </div>
                        <button type="button" class="btn-primary !py-2.5" @click="lookup()">{{ __('Continue') }}</button>
                    </div>

                    <p x-show="error" x-cloak class="mt-4 flex items-start gap-2 text-sm text-amber-200" role="alert">
                        <x-lucide name="circle-alert" class="mt-0.5 size-4 shrink-0" />
                        <span x-text="error"></span>
                    </p>
                </div>

                {{-- What the page can and cannot show: set expectations before the lookup --}}
                <div class="rounded-[6px] border border-white/15 bg-white/[0.06] p-5 sm:p-6">
                    <p class="text-sm font-semibold text-white">{{ __('What you get from a number') }}</p>
                    <ul class="mt-4 space-y-3.5 text-sm text-slate-300">
                        <li class="flex gap-3"><x-lucide name="route" class="mt-0.5 size-4 shrink-0 text-route-400" /> {{ __('Every recorded handoff in order, with the place and the time.') }}</li>
                        <li class="flex gap-3"><x-lucide name="info" class="mt-0.5 size-4 shrink-0 text-route-400" /> {{ __('The source of each scan, so you know whether it came from our team or from a carrier.') }}</li>
                        <li class="flex gap-3"><x-lucide name="globe" class="mt-0.5 size-4 shrink-0 text-route-400" /> {{ __('The route on a map, the expected delivery date and the last-mile partner.') }}</li>
                        <li class="flex gap-3"><x-lucide name="lock" class="mt-0.5 size-4 shrink-0 text-route-400" /> {{ __('Cities and countries only. No street addresses, phone numbers or emails are ever published.') }}</li>
                    </ul>
                    <p class="mt-5 border-t border-white/10 pt-4 text-xs leading-5 text-slate-400">{{ __('Your number is issued when your payment is approved. Until then the booking reference in your account shows the status of the order itself.') }}</p>
                </div>
            </div>
        </section>

        {{-- Results --}}
        <section class="bg-surface py-12 lg:py-14">
            <div class="container-page grid gap-10 lg:grid-cols-12 lg:gap-12">
                <div class="space-y-6 lg:col-span-7">
                    {{-- Before the first lookup --}}
                    <div x-show="results.length === 0 && !loading" class="card p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="package" class="size-5" /></span>
                            <div>
                                <h2 class="text-lg font-bold">{{ __('Your results will appear here') }}</h2>
                                <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ __('Enter a number above, or open a link someone shared with you. Nothing is stored against your name, and no account is needed to track a parcel.') }}</p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 border-t border-line pt-6 sm:grid-cols-3">
                            @foreach ([
                                ['mail', __('In your inbox'), __('The tracking number is in the confirmation email sent when your payment was approved.')],
                                ['layout-dashboard', __('In your account'), __('Open Shipments to see every parcel you have booked, with its current status.')],
                                ['file-down', __('On your documents'), __('The label, invoice and receipt PDFs all carry the same number.')],
                            ] as [$icon, $heading, $copy])
                                <div>
                                    <span class="flex items-center gap-2 text-sm font-semibold text-ink-950"><x-lucide :name="$icon" class="size-4 text-ink-600" /> {{ $heading }}</span>
                                    <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $copy }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Loading --}}
                    <div x-show="loading" x-cloak class="card flex items-center gap-3 p-6" aria-hidden="true">
                        <x-lucide name="loader" class="size-5 animate-spin text-ink-600" />
                        <p class="text-sm text-slate-600">{{ __('Looking up your numbers…') }}</p>
                    </div>

                    {{-- One card per number --}}
                    {{-- Results arrive after the request: role="status" announces them
                         without stealing focus, and aria-live is scoped to this list. --}}
                    <div role="status" aria-live="polite" aria-atomic="false" class="space-y-5">
                    <template x-for="result in results" :key="result.number">
                        <article class="card overflow-hidden">
                            {{-- Not found: say what happened and where to look next --}}
                            <template x-if="!result.found">
                                <div class="p-6">
                                    <div class="flex items-start gap-4">
                                        <span class="grid size-11 shrink-0 place-items-center rounded-[4px] bg-amber-50 text-amber-700"><x-lucide name="circle-alert" class="size-5" /></span>
                                        <div class="min-w-0">
                                            <p class="font-mono text-sm font-semibold break-all text-ink-950" x-text="result.number"></p>
                                            <p class="mt-1.5 text-sm leading-6 text-slate-600" x-text="result.message"></p>
                                            <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                                                <li class="flex gap-2"><x-lucide name="check" class="mt-0.5 size-3.5 shrink-0 text-slate-500" /> {{ __('Check for a missing character or a space in the middle of the number.') }}</li>
                                                <li class="flex gap-2"><x-lucide name="check" class="mt-0.5 size-3.5 shrink-0 text-slate-500" /> {{ __('Tracking numbers appear once the payment for the shipment is approved.') }}</li>
                                                <li class="flex gap-2"><x-lucide name="check" class="mt-0.5 size-3.5 shrink-0 text-slate-500" /> {{ __('Newly created labels can take a few hours before the first scan is recorded.') }}</li>
                                            </ul>
                                            <div class="mt-4 flex flex-wrap gap-3">
                                                <a x-show="result.external_url" :href="result.external_url" target="_blank" rel="noopener noreferrer nofollow" class="btn-ghost !py-2">
                                                    {{ __('Check on the carrier website') }} <x-lucide name="external-link" class="size-3.5" />
                                                </a>
                                                <a href="{{ lroute('contact') }}" class="btn-ghost !py-2">{{ __('Ask us to look into it') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template x-if="result.found">
                                <div>
                                    {{-- Summary --}}
                                    <div class="border-b border-line p-6">
                                        <div class="flex flex-wrap items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                                                    <span x-text="result.carrier.name"></span><span x-show="result.service"> · <span x-text="result.service"></span></span>
                                                </p>
                                                <p class="mt-1.5 font-mono text-lg font-bold break-all text-ink-950" x-text="result.number"></p>
                                                <p class="mt-1 text-xs text-slate-600" x-show="result.last_update">
                                                    {{ __('Last update') }} <span class="tabular" x-text="when(result.last_update)"></span>
                                                </p>
                                            </div>
                                            <span class="badge ring-1 ring-inset" :class="statusTone(result.status)" x-text="result.status_label"></span>
                                        </div>

                                        <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-start gap-3">
                                            <div class="min-w-0">
                                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('From') }}</p>
                                                <p class="mt-1 text-sm font-semibold break-words text-ink-950" x-text="result.origin || '{{ __('Not provided') }}'"></p>
                                            </div>
                                            <x-lucide name="arrow-right" class="mt-5 size-5 text-slate-500" />
                                            <div class="min-w-0 text-right">
                                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('To') }}</p>
                                                <p class="mt-1 text-sm font-semibold break-words text-ink-950" x-text="result.destination || '{{ __('Not provided') }}'"></p>
                                            </div>
                                        </div>

                                        {{-- Journey rail: milestones derived from the recorded progress --}}
                                        <div class="mt-6" role="img" :aria-label="result.status_label">
                                            <ol class="grid grid-cols-3 gap-x-2 gap-y-4 sm:grid-cols-6">
                                                @foreach ($milestones as $milestone)
                                                    <li>
                                                        <div class="h-1 rounded-full" :class="progressValue(result) >= {{ $milestone['at'] }} ? 'bg-brand-500' : 'bg-line'"></div>
                                                        <p class="mt-2 text-[11px] leading-4" :class="progressValue(result) >= {{ $milestone['at'] }} ? 'font-semibold text-ink-900' : 'text-slate-500'">{{ $milestone['label'] }}</p>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        </div>

                                        {{-- Key facts --}}
                                        <dl class="mt-6 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4">
                                            <div>
                                                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Expected delivery') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-ink-950">
                                                    <span x-show="result.eta" class="tabular" x-text="when(result.eta)"></span>
                                                    <span x-show="!result.eta" class="text-slate-500">{{ __('Not yet confirmed') }}</span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Weight') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-ink-950">
                                                    <span x-show="result.weight_kg" class="tabular" x-text="result.weight_kg + ' kg'"></span>
                                                    <span x-show="!result.weight_kg" class="text-slate-500">{{ __('Not recorded here') }}</span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Last mile') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-ink-950">
                                                    <span x-show="result.partner" x-text="result.partner && (result.partner.name + ' ' + (result.partner.number || ''))"></span>
                                                    <span x-show="!result.partner" class="text-slate-500">{{ __('Handled by our network') }}</span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Data source') }}</dt>
                                                <dd class="mt-1 text-sm font-semibold text-ink-950" x-text="result.data_source || result.carrier.name"></dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {{-- Timeline --}}
                                    <div class="p-6">
                                        <h2 class="text-sm font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Journey so far') }}</h2>
                                        <ol class="mt-5">
                                            <template x-for="(event, index) in result.events" :key="index">
                                                <li class="relative flex gap-4 pb-6 last:pb-0">
                                                    <span class="relative flex w-3 shrink-0 justify-center">
                                                        <span class="mt-1.5 size-2.5 rounded-full" :class="index === 0 ? 'bg-brand-500 ring-4 ring-brand-500/15' : 'bg-slate-300'"></span>
                                                        <span x-show="index < result.events.length - 1" class="absolute top-4 bottom-[-6px] w-px bg-line"></span>
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold" :class="index === 0 ? 'text-ink-950' : 'text-slate-700'" x-text="event.label"></p>
                                                        <p class="mt-0.5 text-xs text-slate-600">
                                                            <span x-text="when(event.at)" class="tabular"></span>
                                                            <template x-if="event.place"><span> · <span x-text="event.place"></span></span></template>
                                                        </p>
                                                        <p class="mt-1.5 text-[11px] font-medium tracking-wide text-slate-500 uppercase">{{ __('Source') }}: <span class="normal-case" x-text="event.source"></span></p>
                                                    </div>
                                                </li>
                                            </template>
                                        </ol>

                                        <p class="mt-2 flex items-start gap-2 border-t border-line pt-4 text-xs leading-5 text-slate-600">
                                            <x-lucide name="info" class="mt-0.5 size-3.5 shrink-0 text-slate-500" />
                                            {{ __('Scans are recorded at handoffs. Quiet periods between two events are normal, especially on sea freight.') }}
                                        </p>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex flex-wrap items-center gap-2 border-t border-line bg-white p-4">
                                        <button type="button" class="btn-ghost !py-2 text-xs" x-show="result.route && result.route.origin && result.route.destination" @click="showOnGlobe(result)">
                                            <x-lucide name="globe" class="size-4" /> {{ __('Show the route') }}
                                        </button>
                                        <button type="button" class="btn-ghost !py-2 text-xs" @click="copyLink(result)">
                                            <x-lucide name="copy" class="size-4" />
                                            <span x-show="copied !== result.number">{{ __('Copy share link') }}</span>
                                            <span x-show="copied === result.number" x-cloak>{{ __('Link copied') }}</span>
                                        </button>
                                        <button type="button" class="btn-ghost !py-2 text-xs" @click="openSubscribe(result)">
                                            <x-lucide name="bell" class="size-4" /> {{ __('Email me updates') }}
                                        </button>
                                        <details class="relative ml-auto">
                                            <summary class="btn-ghost !py-2 text-xs list-none"><x-lucide name="qr-code" class="size-4" /> {{ __('QR code') }}</summary>
                                            <div class="absolute right-0 z-20 mt-2 rounded-[6px] border border-line bg-white p-3 shadow-[var(--shadow-lg)]">
                                                <img :src="qrUrl(result)" alt="{{ __('QR code linking to this tracking page') }}" width="160" height="160" loading="lazy" class="rounded-[4px]">
                                                <p class="mt-2 max-w-[10rem] text-[11px] leading-4 text-slate-600">{{ __('Scan to open this tracking page on a phone.') }}</p>
                                            </div>
                                        </details>
                                    </div>

                                    {{-- Email alerts --}}
                                    <form x-show="subscribeFor === result.number" x-cloak @submit.prevent="subscribe()" class="flex flex-col gap-2 border-t border-line p-4 sm:flex-row sm:items-center">
                                        <label :for="'sub-' + result.number" class="sr-only">{{ __('Email') }}</label>
                                        <input :id="'sub-' + result.number" x-model="subscribeEmail" type="email" required class="field flex-1" placeholder="{{ __('you@example.com') }}">
                                        <button class="btn-dark !py-2.5" type="submit">{{ __('Notify me') }}</button>
                                        <p class="text-sm text-slate-600 sm:basis-full" x-show="subscribeMessage" x-text="subscribeMessage"></p>
                                    </form>
                                </div>
                            </template>
                        </article>
                    </template>
                    </div>

                    {{-- Outside the result cards: how the data is produced --}}
                    <section class="card overflow-hidden">
                        <x-photo key="service_express" class="h-40 w-full object-cover" sizes="(min-width: 1024px) 58vw, 100vw" />
                        <div class="p-6">
                            <h2 class="text-lg font-bold">{{ __('How a scan reaches this page') }}</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach ([
                                    ['package', __('Recorded at handoffs'), __('Collection, departure, arrival, customs clearance and the handover to the delivery partner are entered by our own team as they happen.')],
                                    ['radar', __('Carrier data where we have it'), __('For a recognised USPS, UPS or FedEx number we read that carrier\'s own tracking data and show it next to the source, so you can tell who reported what.')],
                                    ['refresh-cw', __('Cached, not hammered'), __('Carrier responses are cached for :minutes minutes. A refresh shows the last known position instead of failing when the carrier is slow or busy.', ['minutes' => (int) config('platform.tracking.cache_minutes', 15)])],
                                    ['lock', __('Public fields only'), __('This page is built from the public view of a shipment: city, country, status, time and source. Nothing else leaves the account.')],
                                ] as [$icon, $heading, $copy])
                                    <div class="border-t border-line pt-4">
                                        <p class="flex items-center gap-2 text-sm font-semibold text-ink-950"><x-lucide :name="$icon" class="size-4 text-ink-600" /> {{ $heading }}</p>
                                        <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $copy }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    {{-- Status reference --}}
                    <section class="card p-6">
                        <h2 class="text-lg font-bold">{{ __('What each status means') }}</h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ __('Statuses come from the shipment record itself, not from a summary written by hand. Here is what each one tells you.') }}</p>
                        <dl class="mt-5 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                            @foreach ($statusMeanings as [$label, $icon, $meaning])
                                <div class="flex gap-3 border-t border-line pt-4">
                                    <x-lucide :name="$icon" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                                    <div>
                                        <dt class="text-sm font-semibold text-ink-950">{{ $label }}</dt>
                                        <dd class="mt-0.5 text-sm leading-6 text-slate-600">{{ $meaning }}</dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    {{-- Questions --}}
                    <section class="card overflow-hidden">
                        <h2 class="border-b border-line px-6 py-5 text-lg font-bold">{{ __('Tracking questions') }}</h2>
                        <div class="divide-y divide-line">
                            @foreach ($faqs as [$question, $answer])
                                <details class="group px-6">
                                    <summary class="flex cursor-pointer items-center justify-between gap-4 py-4 text-sm font-semibold text-ink-950">
                                        {{ $question }}
                                        <x-lucide name="chevron-down" class="size-4 shrink-0 text-slate-500 transition group-open:rotate-180" />
                                    </summary>
                                    <p class="pb-5 text-sm leading-6 text-slate-600">{{ $answer }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                </div>

                {{-- Sidebar: the route, the data note and a way to reach a human --}}
                <aside class="lg:col-span-5">
                    <div class="space-y-5 lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
                        <div class="overflow-hidden rounded-[6px] bg-ink-950" id="route-globe">
                            <div x-data="globe" data-autorotate="true" data-distance="2.9" class="relative aspect-square">
                                <div x-ref="canvas" class="absolute inset-0 cursor-grab active:cursor-grabbing"></div>
                                <div class="absolute top-4 left-4 text-xs font-semibold tracking-[0.08em] text-slate-300 uppercase">{{ __('Route map') }}</div>
                                <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center p-4">
                                    <img src="{{ asset('images/world-map.svg') }}" alt="{{ __('World map') }}" class="w-full rounded-[4px]">
                                </div>
                                <div class="absolute right-3 bottom-3 flex items-center gap-1.5">
                                    <button type="button" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" @click="zoomIn()" aria-label="{{ __('Zoom in') }}"><x-lucide name="plus" class="size-4" /></button>
                                    <button type="button" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" @click="zoomOut()" aria-label="{{ __('Zoom out') }}"><x-lucide name="minus" class="size-4" /></button>
                                </div>
                            </div>
                            <div class="border-t border-white/10 p-5">
                                <p class="flex items-center gap-2 text-sm font-semibold text-white"><x-lucide name="info" class="size-4 text-route-400" /> {{ __('About this map') }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-300">{{ __('Select a result and press "Show the route" to place the journey on the globe. The line follows the recorded handoffs, not a live satellite position.') }}</p>
                            </div>
                        </div>

                        <div class="rounded-[6px] border border-line bg-white p-5">
                            <h2 class="text-base font-bold">{{ __('Still stuck?') }}</h2>
                            <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ __('Send us the number and what you expected to see. A person reads every message, and we answer in English and French.') }}</p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <a href="{{ lroute('contact') }}" class="btn-primary !py-2">{{ __('Contact us') }} <x-lucide name="arrow-right" class="size-4" /></a>
                                <a href="{{ lroute('help') }}" class="btn-ghost !py-2">{{ __('Help center') }}</a>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        {{-- Closing: the tracking page is also a sales page --}}
        <section class="bg-white py-12 lg:py-14">
            <div class="container-page">
                <div class="flex flex-wrap items-center justify-between gap-6 rounded-[6px] bg-ink-900 p-7 text-white sm:p-9">
                    <div>
                        <h2 class="text-2xl font-bold text-white">{{ __('Following a parcel, or about to send one?') }}</h2>
                        <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-300">{{ __('Get a price for your own shipment, with the transit window and the services available on that route, before you commit to anything.') }}</p>
                    </div>
                    <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('data')
    @if ($result)
        <script type="application/json" id="track-result">@json($result)</script>
    @endif
@endpush
