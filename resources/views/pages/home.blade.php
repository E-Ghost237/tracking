@extends('layouts.app')

@php
    $heroImage = media('hero_air');
    $heroMp4 = $hero['hero_air_mp4'] ?? null;
    $heroWebm = $hero['hero_air_webm'] ?? null;
    $heroPoster = $hero['hero_air_poster'] ?? null;

    // Transit windows come from the active rate card (passed by HomeController),
    // so the home page can never disagree with pricing.
    $windowLabel = fn (string $mode) => $transit[$mode] ?? __('Confirmed per route');

    $serviceCards = [
        [
            'air', 'service_air', __('Air freight'), $windowLabel('air'),
            __('Scheduled flights for parcels, business samples and stock that should arrive sooner.'),
            [__('Customs coordination included'), __('Tracking from collection to delivery')],
        ],
        [
            'sea', 'service_sea', __('Sea freight'), $windowLabel('sea'),
            __('Shared container space for furniture, equipment and planned stock replenishment.'),
            [__('Lower cost per kilogram on larger loads'), __('Consolidation before departure')],
        ],
        [
            'road', 'service_road', __('Road freight'), $windowLabel('road'),
            __('For eligible regional journeys where flexible pickup and direct delivery matter.'),
            [__('Parcels and palletised goods'), __('Established land corridors')],
        ],
        [
            'express', 'service_express', __('Express'), $windowLabel('express'),
            __('Priority handling and the next available flight when a delivery date is driving the decision.'),
            [__('Priority at key handoffs'), __('Ideal for documents and urgent items')],
        ],
    ];

    $steps = [
        ['calculator', __('Get a quote'), __('Enter the route, weight and dimensions to see the price and the delivery window before you book.')],
        ['package', __('Book your shipment'), __('Add the sender, recipient and customs details. Your progress is saved at every step.')],
        ['shield-check', __('Pay and upload proof'), __('Pay with the method of your choice and upload your receipt. A verifier confirms it, usually within :minutes minutes.', ['minutes' => $reviewTargetMinutes])],
        ['radar', __('Track to delivery'), __('Your label and tracking number are issued once payment is confirmed, then every scan is logged.')],
    ];

    // The operation strip uses the apron, express and road photographs. Captions
    // describe the handling step, not the carrier whose equipment is pictured.
    $operation = [
        ['service_air', __('Loaded on scheduled air capacity')],
        ['service_express', __('Priority handling for urgent shipments')],
        ['service_road', __('Road haulage on supported land corridors')],
    ];
    $hubPhoto = media('editorial_hub');
@endphp

@section('content')
    @if ($alerts->isNotEmpty())
        @php($alert = $alerts->first())
        <div @class([
            'border-b',
            'border-amber-200 bg-amber-50' => $alert->severity !== 'critical',
            'border-red-200 bg-red-50' => $alert->severity === 'critical',
        ])>
            <div class="container-page flex flex-wrap items-center gap-x-3 gap-y-1 py-2.5 text-sm">
                <span class="flex items-center gap-2 font-semibold text-ink-950">
                    <x-lucide :name="$alert->severity === 'critical' ? 'triangle-alert' : 'info'" class="size-4" />
                    {{ __('Service notice') }}
                </span>
                <span class="text-ink-900">{{ $alert->title }}</span>
                <a href="{{ lroute('status') }}" class="btn-link ml-auto">{{ __('All service alerts') }} <x-lucide name="arrow-right" class="size-3.5" /></a>
            </div>
        </div>
    @endif

    {{-- Hero: one photograph, one message, and the tools above the fold. --}}
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">
            @if ($heroMp4 || $heroWebm)
                {{-- The poster is the first paint; the clip only starts when the visitor
                     has not asked for reduced motion (handled in app.js). --}}
                <video class="hero-scene-image is-active" data-hero-video
                       poster="{{ $heroPoster ? asset('storage/'.$heroPoster) : $heroImage['webp'] }}"
                       muted loop playsinline preload="none" aria-hidden="true" tabindex="-1">
                    @if ($heroWebm)<source src="{{ asset('storage/'.$heroWebm) }}" type="video/webm">@endif
                    @if ($heroMp4)<source src="{{ asset('storage/'.$heroMp4) }}" type="video/mp4">@endif
                </video>
            @else
                <x-photo key="hero_air" priority sizes="100vw" class="absolute inset-0 size-full object-cover object-right" />
            @endif
        </div>
        <div class="hero-scene-overlay absolute inset-0 -z-10" aria-hidden="true"></div>

        <div class="container-page grid items-center gap-10 py-12 lg:grid-cols-12 lg:gap-8 lg:py-16">
            <div class="lg:col-span-7">
                <p class="eyebrow !text-slate-300">{{ __('Air · Sea · Road · Express') }}</p>
                <h1 class="editorial-title mt-3 max-w-2xl text-[2rem] leading-[1.08] text-white sm:text-[2.6rem] lg:text-[3.1rem]">
                    {{ __('Freight from Houston to Paris, and across our network') }}
                </h1>
                <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300 sm:text-base">
                    {{ __('Compare air, sea, road and express services, see the cost and the delivery window before you book, then follow every handoff with a single tracking number.') }}
                </p>

                {{-- Track or price without leaving the page. --}}
                <div x-data="trackBox" data-track-url="{{ lroute('track') }}" data-quote-url="{{ lroute('quote') }}"
                     class="mt-7 max-w-2xl rounded-[6px] border border-line bg-white p-2 shadow-[var(--shadow-xl)]">
                    {{-- ARIA tabs with roving tabindex and arrow-key support: the keyboard
                         behaves the way a screen-reader user expects from role="tab". --}}
                    <div class="flex gap-1 border-b border-line p-1 pb-2" role="tablist" aria-label="{{ __('Track or price a shipment') }}">
                        <button type="button" role="tab" id="hero-tab-track" aria-controls="hero-panel-track"
                                x-ref="tabTrack" :aria-selected="tab === 'track'" :tabindex="tab === 'track' ? 0 : -1"
                                @click="tab = 'track'"
                                @keydown.arrow-right.prevent="tab = 'quote'; $refs.tabQuote.focus()"
                                @keydown.arrow-left.prevent="tab = 'quote'; $refs.tabQuote.focus()"
                                @keydown.home.prevent="tab = 'track'; $refs.tabTrack.focus()"
                                @keydown.end.prevent="tab = 'quote'; $refs.tabQuote.focus()"
                                class="flex flex-1 items-center justify-center gap-2 rounded-[4px] px-4 py-2.5 text-sm font-semibold transition-colors"
                                :class="tab === 'track' ? 'bg-ink-900 text-white' : 'text-slate-600 hover:bg-surface hover:text-ink-900'">
                            <x-lucide name="radar" class="size-4" /> {{ __('Track a shipment') }}
                        </button>
                        <button type="button" role="tab" id="hero-tab-quote" aria-controls="hero-panel-quote"
                                x-ref="tabQuote" :aria-selected="tab === 'quote'" :tabindex="tab === 'quote' ? 0 : -1"
                                @click="tab = 'quote'"
                                @keydown.arrow-right.prevent="tab = 'track'; $refs.tabTrack.focus()"
                                @keydown.arrow-left.prevent="tab = 'track'; $refs.tabTrack.focus()"
                                @keydown.home.prevent="tab = 'track'; $refs.tabTrack.focus()"
                                @keydown.end.prevent="tab = 'quote'; $refs.tabQuote.focus()"
                                class="flex flex-1 items-center justify-center gap-2 rounded-[4px] px-4 py-2.5 text-sm font-semibold transition-colors"
                                :class="tab === 'quote' ? 'bg-ink-900 text-white' : 'text-slate-600 hover:bg-surface hover:text-ink-900'">
                            <x-lucide name="calculator" class="size-4" /> {{ __('Price a shipment') }}
                        </button>
                    </div>

                    <form x-show="tab === 'track'" id="hero-panel-track" role="tabpanel" aria-labelledby="hero-tab-track" tabindex="0"
                          @submit.prevent="submit()" action="{{ lroute('track') }}" method="GET" class="p-2">
                        <label for="hero-track" class="sr-only">{{ __('Tracking numbers') }}</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1">
                                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-500" />
                                <input id="hero-track" x-ref="trackInput" x-model="input" name="numbers" type="text" autocomplete="off" spellcheck="false"
                                       class="h-12 w-full rounded-[4px] border border-slate-300 bg-white pr-4 pl-11 text-base text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:ring-2 focus:ring-ink-900/10 focus:outline-none"
                                       placeholder="{{ __('Enter a tracking number or paste up to 20') }}">
                            </div>
                            <button type="submit" class="btn-primary h-12 !px-6 text-base">{{ __('Track') }}</button>
                        </div>
                        <div class="mt-2.5 flex min-h-5 flex-wrap items-center gap-x-3 text-xs">
                            <span x-show="detected" x-cloak class="inline-flex items-center gap-1.5 font-medium text-emerald-700">
                                <x-lucide name="badge-check" class="size-3.5" /> <span x-text="detected"></span>
                            </span>
                            <span x-show="error" x-cloak class="text-red-700" x-text="error"></span>
                            <span x-show="!detected && !error" class="text-slate-600">
                                {{ __('USPS, UPS and FedEx numbers are recognised automatically.') }}
                                @if ($examples->isNotEmpty())
                                    <a href="{{ lroute('track', ['number' => $examples->first()]) }}" class="font-medium text-ink-900 underline underline-offset-2 hover:text-brand-600">{{ __('See an example') }}</a>
                                @endif
                            </span>
                        </div>
                    </form>

                    <form x-show="tab === 'quote'" x-cloak id="hero-panel-quote" role="tabpanel" aria-labelledby="hero-tab-quote" tabindex="0"
                          @submit.prevent="submitQuote()" class="grid gap-2 p-2 sm:grid-cols-[1fr_1fr_100px_auto]">
                        <label class="sr-only" for="hq-from">{{ __('From') }}</label>
                        <input id="hq-from" x-model="quoteFrom" type="text" placeholder="{{ __('From city') }}" class="h-12 rounded-[4px] border border-slate-300 px-3.5 text-sm text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:outline-none">
                        <label class="sr-only" for="hq-to">{{ __('To') }}</label>
                        <input id="hq-to" x-model="quoteTo" type="text" placeholder="{{ __('To city') }}" class="h-12 rounded-[4px] border border-slate-300 px-3.5 text-sm text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:outline-none">
                        <label class="sr-only" for="hq-kg">{{ __('Weight (kg)') }}</label>
                        <input id="hq-kg" x-model="quoteWeight" type="number" min="0.1" step="0.1" placeholder="{{ __('kg') }}" class="h-12 rounded-[4px] border border-slate-300 px-3.5 text-sm text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:outline-none">
                        <button type="submit" class="btn-primary h-12 !px-5">{{ __('Get price') }}</button>
                    </form>
                </div>

                <ul class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-300">
                    <li class="flex items-center gap-2"><x-lucide name="shield-check" class="size-4 shrink-0" /> {{ __('Every payment checked by a person') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="truck" class="size-4 shrink-0" /> {{ __('Local delivery by established carriers') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="languages" class="size-4 shrink-0" /> {{ __('Support in English and French') }}</li>
                </ul>
            </div>

            {{-- Sample lane: a fact people can act on, not a decorative globe. --}}
            <div class="lg:col-span-5">
                <div class="rounded-[6px] border border-line bg-white p-5 shadow-[var(--shadow-xl)]">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Sample lane') }}</p>
                        <span class="badge bg-surface text-slate-700">{{ __('USD') }}</span>
                    </div>
                    <p class="mt-2 flex items-center gap-2 text-lg font-bold text-ink-950">
                        {{ __('Houston') }} <x-lucide name="arrow-right" class="size-4 text-slate-500" /> {{ __('Paris') }}
                    </p>
                    <table class="mt-3 w-full text-sm">
                        <caption class="sr-only">{{ __('Typical transit times by service from Houston to Paris') }}</caption>
                        <thead>
                            <tr class="border-b border-line text-left text-xs text-slate-600">
                                <th scope="col" class="pb-2 font-medium">{{ __('Service') }}</th>
                                <th scope="col" class="pb-2 text-right font-medium">{{ __('Typical transit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-ink-900">
                            @foreach ([['air', __('Air freight')], ['sea', __('Sea freight')], ['road', __('Road freight')]] as [$mode, $label])
                                <tr class="border-b border-line last:border-0">
                                    <th scope="row" class="py-2.5 text-left font-medium">
                                        <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$mode)]) }}" class="hover:text-brand-600">{{ $label }}</a>
                                    </th>
                                    <td class="py-2.5 text-right tabular">{{ $windowLabel($mode) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs leading-5 text-slate-600">{{ __('Indicative transit times. Prices for your parcel size and weight are confirmed in a quote before you book.') }}</p>
                    <a href="{{ lroute('rates') }}" class="btn-ghost mt-4 w-full !py-2">{{ __('See all rates and transit times') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Services: photography carries the meaning, copy stays short. --}}
    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Four services, one way of working') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('The right service depends on what you are sending, how far it is going and when it needs to arrive. Every service includes collection, customs coordination and tracking.') }}</p>
                </div>
                <a href="{{ lroute('services') }}" class="btn-ghost">{{ __('Compare all services') }}</a>
            </div>

            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($serviceCards as [$mode, $photo, $name, $transit, $text, $points])
                    <article class="card card-hover flex flex-col overflow-hidden">
                        <x-photo :key="$photo" class="h-40 w-full object-cover" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw" />
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-base font-bold">{{ $name }}</h3>
                                <span class="shrink-0 text-xs font-semibold whitespace-nowrap text-slate-600 tabular">{{ $transit }}</span>
                            </div>
                            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $text }}</p>
                            <ul class="mt-3.5 space-y-1.5 border-t border-line pt-3.5 text-xs text-slate-600">
                                @foreach ($points as $point)
                                    <li class="flex items-start gap-2"><x-lucide name="check" class="mt-0.5 size-3.5 shrink-0 text-emerald-700" /> {{ $point }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$mode)]) }}" class="btn-link mt-4">
                                {{ __('Service details') }} <x-lucide name="arrow-right" class="size-3.5" />
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="bg-surface py-14 lg:py-16">
        <div class="container-page">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-bold sm:text-3xl">{{ __('From quote to doorstep') }}</h2>
                <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Four steps, each one visible in your account, with a person reachable at every stage.') }}</p>
            </div>
            <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($steps as [$icon, $name, $text])
                    <li class="border-t-2 border-ink-900 pt-4" data-reveal="{{ $loop->index * 80 }}">
                        <div class="flex items-center gap-2.5">
                            <x-lucide :name="$icon" class="size-5 text-ink-700" />
                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Step :number', ['number' => $loop->iteration]) }}</p>
                        </div>
                        <h3 class="mt-2 text-base font-bold">{{ $name }}</h3>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Coverage: photography plus the places we actually operate. --}}
    <section class="bg-white py-14 lg:py-16">
        <div class="container-page grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <h2 class="text-2xl font-bold sm:text-3xl">{{ __('International freight, with local delivery handled') }}</h2>
                <p class="mt-3 text-[15px] leading-7 text-slate-600">{{ __('Your shipment may pass through several teams before it reaches the door. We coordinate the long-distance leg, share clear updates at each handoff and work with established local carriers for the final delivery.') }}</p>

                <dl class="mt-7 divide-y divide-line border-y border-line">
                    @foreach ([
                        [__('North America'), __('USPS and UPS handle local delivery on eligible routes across the United States, with one tracking journey from pickup to doorstep.')],
                        [__('Europe and the United Kingdom'), __('Regional carriers complete delivery across supported European destinations, with a coordinated handover at each stage.')],
                        [__('Connecting routes worldwide'), __('For destinations beyond our core lanes, our team confirms the available service and local partner before you book.')],
                    ] as [$region, $text])
                        <div class="flex gap-4 py-4">
                            <x-lucide name="map-pinned" class="mt-0.5 size-5 shrink-0 text-ink-600" />
                            <div>
                                <dt class="font-semibold text-ink-950">{{ $region }}</dt>
                                <dd class="mt-1 text-sm leading-6 text-slate-600">{{ $text }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>

                @if ($hubs->isNotEmpty())
                    <p class="mt-5 text-sm text-slate-600">
                        {{ __(':hubs consolidation hubs and pickup and drop-off points across :countries countries.', ['hubs' => $hubs->count(), 'countries' => $hubs->pluck('country')->unique()->count()]) }}
                        <a href="{{ lroute('locations') }}" class="link">{{ __('See the locations') }}</a>
                    </p>
                @endif

                <a href="{{ lroute('network') }}" class="btn-dark mt-6">{{ __('Explore the network') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>

            <figure class="overflow-hidden rounded-[6px] border border-line">
                <x-photo key="editorial_hub" class="aspect-[16/10] w-full object-cover" sizes="(min-width: 1024px) 50vw, 100vw" />
                @if ($hubPhoto['caption'])
                    <figcaption class="border-t border-line bg-surface px-4 py-3 text-xs text-slate-600">{{ $hubPhoto['caption'] }}</figcaption>
                @endif
            </figure>
        </div>
    </section>

    {{-- Payments --}}
    <section class="bg-ink-950 py-14 text-white lg:py-16">
        <div class="container-page grid gap-10 lg:grid-cols-2 lg:gap-14">
            <div>
                <p class="eyebrow !text-slate-300">{{ __('Payments') }}</p>
                <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">{{ __('Pay the way you already pay. A person checks it.') }}</h2>
                <p class="mt-4 max-w-xl text-[15px] leading-7 text-slate-300">{{ __('Choose your method at checkout, transfer the amount and upload your receipt. Our team verifies the amount, date and reference before your label is released — usually within :minutes minutes during staffed hours (:hours).', ['minutes' => $reviewTargetMinutes, 'hours' => $staffedHours]) }}</p>
                @if ($paymentMethods->isNotEmpty())
                    <ul class="mt-6 flex flex-wrap gap-2">
                        @foreach ($paymentMethods as $method)
                            <li class="badge bg-white/10 text-slate-100">{{ $method->name }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-6 flex items-start gap-2.5 border-l-2 border-brand-500 pl-4 text-sm leading-6 text-slate-300">
                    {{ __('Never pay for a shipment someone else asked you to pay for, and never send gift cards to a stranger. If in doubt, contact us first.') }}
                </p>
            </div>
            <ul class="space-y-5">
                @foreach ([
                    ['lock', __('Details only on your own order'), __('Payment account details never appear on public pages. They are shown to you after you choose a method.')],
                    ['badge-check', __('Human verification'), __('A verifier checks the amount, date and reference of your proof before anything is released.')],
                    ['file-text', __('Documents you can download'), __('Labels, receipts, invoices and commercial invoices are ready in your account as soon as payment clears.')],
                ] as [$icon, $name, $text])
                    <li class="flex gap-3.5 border-l-2 border-white/20 pl-4">
                        <x-lucide :name="$icon" class="mt-0.5 size-5 shrink-0 text-slate-400" />
                        <div>
                            <p class="font-semibold text-white">{{ $name }}</p>
                            <p class="mt-1 text-sm leading-6 text-slate-300">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Trust: things a customer can verify before paying, with no invented
         numbers — each line points at something the platform actually does. --}}
    <section class="border-y border-line bg-surface py-14 lg:py-16">
        <div class="container-page">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="lg:col-span-5">
                    <p class="eyebrow">{{ __('Why you can check us') }}</p>
                    <h2 class="mt-3 text-2xl font-bold sm:text-3xl">{{ __('Nothing about the price appears only after you pay.') }}</h2>
                    <p class="mt-4 text-[15px] leading-7 text-slate-600">{{ __('Freight is a market where vague promises are common, so we keep the verifiable parts in the open. Every claim below is a page you can open, a document you can download, or a date and time recorded against your own shipment.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ lroute('rates') }}" class="btn-ghost">{{ __('See published prices') }}</a>
                        <a href="{{ lroute('track') }}" class="btn-ghost">{{ __('Look at a real tracking page') }}</a>
                    </div>
                </div>

                <ul class="grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-2 lg:col-span-7">
                    @foreach ([
                        ['receipt', __('Prices and windows published before booking'), __('The rate tables and transit windows are on the site, not held back for a sales conversation. Your quote repeats them for your own route and weight.'), lroute('rates'), __('See published prices')],
                        ['shield-check', __('A person reviews every payment'), __('A verifier checks the amount, the date and the reference on your proof before a label is released. Two approvals are required above a threshold.'), lroute('account.orders'), __('See the order page')],
                        ['file-text', __('Documents you keep'), __('Your invoice, receipt, label and commercial invoice stay downloadable in your account — not emailed once and lost.'), lroute('account.orders'), __('See the documents')],
                        ['radar', __('Scans with a time and a source'), __('Each tracking event records when it happened and where it came from, including the carrier handoff, so progress is checkable rather than asserted.'), lroute('track'), __('See a tracking result')],
                        ['headset', __('Support in two languages'), __('English and French, during staffed hours, with a first reply target of one business day.'), lroute('help'), __('Open the help centre')],
                        ['lock', __('Payment details only in your account'), __('We never send account details or payment links by email, and we never ask for a payment on social media.'), lroute('contact'), __('See how to reach us')],
                    ] as [$icon, $heading, $text, $href, $linkLabel])
                        <li class="bg-white p-5">
                            <span class="grid size-9 place-items-center rounded-[4px] bg-ink-50 text-ink-700">
                                <x-lucide :name="$icon" class="size-4" />
                            </span>
                            <h3 class="mt-3.5 text-base font-bold text-ink-950">{{ $heading }}</h3>
                            <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $text }}</p>
                            <a href="{{ $href }}" class="btn-link mt-3">{{ $linkLabel }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Inside the operation: three photographs, plainly captioned. --}}
    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('What happens between booking and delivery') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('A shipment is packed, weighed, labelled, scanned at every handoff and delivered locally by a partner carrier. You can see each of those steps on the tracking page.') }}</p>
                </div>
                <a href="{{ lroute('track') }}" class="btn-ghost">{{ __('Track a shipment') }}</a>
            </div>

            <div class="mt-8 grid gap-5 sm:grid-cols-3">
                @foreach ($operation as [$key, $caption])
                    <figure class="overflow-hidden rounded-[6px] border border-line" data-reveal="{{ $loop->index * 80 }}">
                        <x-photo :key="$key" class="aspect-[4/3] w-full object-cover" sizes="(min-width: 640px) 33vw, 100vw" />
                        <figcaption class="border-t border-line px-4 py-3 text-sm text-slate-600">{{ $caption }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    @if ($faqs->isNotEmpty())
        <section class="bg-surface py-14 lg:py-16">
            <div class="container-page grid gap-10 lg:grid-cols-3 lg:gap-14">
                <div>
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Questions, answered') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Our team replies in English and French, usually within one business day.') }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ lroute('help') }}" class="btn-ghost">{{ __('Help center') }}</a>
                        <a href="{{ lroute('contact') }}" class="btn-dark">{{ __('Contact us') }}</a>
                    </div>
                </div>
                <div class="divide-y divide-line border-y border-line lg:col-span-2">
                    @foreach ($faqs as $faq)
                        <details class="group py-4 [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 font-semibold text-ink-950">
                                {{ $faq->question }}
                                <x-lucide name="plus" class="size-4 shrink-0 text-slate-500 transition group-open:rotate-45" />
                            </summary>
                            <div class="prose-content mt-3 text-slate-600">{!! $faq->html !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Closing call to action with photography --}}
    <section class="bg-white py-14 lg:py-16">
        <div class="container-page">
            <div class="grid overflow-hidden rounded-[6px] bg-ink-900 text-white lg:grid-cols-[1.4fr_1fr]">
                <div class="p-7 sm:p-9">
                    <p class="eyebrow !text-slate-300">{{ __('Before the first mile') }}</p>
                    <h2 class="mt-3 max-w-xl text-2xl font-bold text-white sm:text-3xl">{{ __('Start with a route and a weight') }}</h2>
                    <p class="mt-3.5 max-w-lg text-[15px] leading-7 text-slate-300">{{ __('Share the origin, destination and packed size. We will show the price, the delivery window and the delivery network before you decide to book.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Build a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                        <a href="{{ lroute('register') }}" class="btn-light">{{ __('Create a free account') }}</a>
                    </div>
                </div>
                <div class="relative min-h-56 lg:min-h-full">
                    <x-photo key="editorial_documents" class="absolute inset-0 size-full object-cover" sizes="(min-width: 1024px) 40vw, 100vw" />
                </div>
            </div>
        </div>
    </section>
@endsection

@push('data')
    <script type="application/json" id="carrier-formats">@json($carrierFormats)</script>
@endpush
