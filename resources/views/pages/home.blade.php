@extends('layouts.app', ['darkHeader' => true])

@php
    $heroMedia = [
        'air' => ['poster' => asset('images/freight-air.jpg')],
        'sea' => ['poster' => asset('images/freight-sea.jpg')],
        'road' => ['poster' => asset('images/freight-road.jpg')],
        'express' => ['poster' => asset('images/freight-express.jpg')],
    ];
    foreach (['air', 'sea', 'road', 'express'] as $scene) {
        foreach (['mp4', 'webm', 'poster'] as $kind) {
            $key = 'hero_'.$scene.'_'.$kind;
            if (isset($hero[$key])) {
                $heroMedia[$scene][$kind] = asset('storage/'.$hero[$key]);
            }
        }
    }
@endphp

@section('content')
    {{-- Hero section with rotating transport scenes and interactive globe --}}
    <section x-data="heroScenes" data-scene="{{ $defaultScene }}"
             @mouseenter="pauseRotation()" @mouseleave="resumeRotation()" @focusin="pauseRotation()" @focusout="resumeRotation()"
             class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true">
            <img src="/images/freight-air.jpg" :src="poster('air')" alt="" fetchpriority="high" class="hero-scene-image" :class="scene === 'air' ? 'is-active' : ''">
            <img src="/images/freight-sea.jpg" :src="poster('sea')" alt="" class="hero-scene-image" :class="scene === 'sea' ? 'is-active' : ''">
            <img src="/images/freight-road.jpg" :src="poster('road')" alt="" class="hero-scene-image" :class="scene === 'road' ? 'is-active' : ''">
            <img src="/images/freight-express.jpg" :src="poster('express')" alt="" class="hero-scene-image" :class="scene === 'express' ? 'is-active' : ''">
            <video x-ref="video" x-show="hasVideo" x-cloak class="absolute inset-0 size-full object-cover" muted loop playsinline preload="none"></video>
        </div>
        <div class="hero-scene-overlay absolute inset-0 -z-10"></div>
        <div class="hero-photo-wash pointer-events-none absolute inset-0 -z-10"></div>

        {{-- Decorative globe elements --}}
        <div class="absolute top-20 right-10 w-64 h-64 opacity-20 pointer-events-none hidden lg:block">
            <svg viewBox="0 0 200 200" class="w-full h-full">
                <defs>
                    <radialGradient id="globeGlow" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#83d6cf" stop-opacity="0.3"/>
                        <stop offset="100%" stop-color="#83d6cf" stop-opacity="0"/>
                    </radialGradient>
                </defs>
                <circle cx="100" cy="100" r="90" fill="none" stroke="#83d6cf" stroke-width="0.5" opacity="0.4"/>
                <circle cx="100" cy="100" r="70" fill="none" stroke="#83d6cf" stroke-width="0.3" opacity="0.3"/>
                <circle cx="100" cy="100" r="50" fill="none" stroke="#83d6cf" stroke-width="0.3" opacity="0.2"/>
                <ellipse cx="100" cy="100" rx="90" ry="40" fill="none" stroke="#83d6cf" stroke-width="0.5" opacity="0.5"/>
                <ellipse cx="100" cy="100" rx="90" ry="60" fill="none" stroke="#83d6cf" stroke-width="0.4" opacity="0.4"/>
            </svg>
        </div>

        <div class="container-page relative grid min-h-[min(900px,100svh)] items-center gap-8 pt-28 pb-10 lg:min-h-[790px] lg:grid-cols-12 lg:gap-4 lg:pt-24">
            <div class="relative z-10 lg:col-span-6 xl:col-span-6">
                <p class="eyebrow eyebrow-rule !text-brand-300">
                    <span class="size-2 rounded-full bg-brand-300 shadow-[0_0_0_4px_rgb(233_139_104/0.15)]"></span>
                    {{ __('Air, sea, road and express freight') }}
                </p>
                <h1 class="editorial-title mt-6 max-w-2xl text-[3.25rem] leading-[0.98] !text-white sm:text-6xl xl:text-[5.15rem]">
                    {{ __('Move goods across') }}
                    <span class="editorial-italic block">{{ __('any distance') }}</span>
                </h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-slate-200 sm:text-lg sm:leading-8">
                    {{ __('Whether shipping a single parcel across the ocean or coordinating pallets with a longer journey ahead, we help you choose the right route, see costs before booking and follow each handoff to delivery.') }}
                </p>

                {{-- Tracking and quote tool --}}
                <div x-data="trackBox" data-track-url="{{ lroute('track') }}" data-quote-url="{{ lroute('quote') }}"
                     class="mt-8 max-w-xl rounded-[1.35rem] border border-white/15 bg-ink-900/80 p-2 shadow-2xl shadow-black/30 backdrop-blur-md">
                    <div class="flex gap-1 p-1" role="tablist">
                        <button type="button" role="tab" :aria-selected="tab === 'track'" @click="tab = 'track'"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                                :class="tab === 'track' ? 'bg-paper text-ink-900 shadow-sm' : 'text-white/75 hover:text-white'">
                            <x-lucide name="radar" class="size-4" /> {{ __('Track a shipment') }}
                        </button>
                        <button type="button" role="tab" :aria-selected="tab === 'quote'" @click="tab = 'quote'"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                                :class="tab === 'quote' ? 'bg-paper text-ink-900 shadow-sm' : 'text-white/75 hover:text-white'">
                            <x-lucide name="calculator" class="size-4" /> {{ __('Price a shipment') }}
                        </button>
                    </div>

                    <form x-show="tab === 'track'" @submit.prevent="submit()" action="{{ lroute('track') }}" method="GET" class="p-2">
                        <label for="hero-track" class="sr-only">{{ __('Tracking numbers') }}</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1">
                                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                                <input id="hero-track" x-ref="trackInput" x-model="input" name="numbers" type="text" autocomplete="off" spellcheck="false"
                                       class="h-14 w-full rounded-xl border-0 bg-paper pr-4 pl-12 text-base font-medium text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/25 focus:outline-none"
                                       placeholder="{{ __('Enter a tracking number') }}">
                            </div>
                            <button type="submit" class="btn-primary h-14 !rounded-xl !px-7 text-base">
                                {{ __('Track') }} <x-lucide name="arrow-right" class="size-5" />
                            </button>
                        </div>
                        <div class="mt-3 flex min-h-6 flex-wrap items-center gap-x-3 gap-y-2 px-1 text-sm">
                            <span x-show="detected" x-cloak class="inline-flex items-center gap-1.5 font-medium text-route-400">
                                <x-lucide name="badge-check" class="size-4" /> <span x-text="detected"></span>
                            </span>
                            <span x-show="error" x-cloak class="text-brand-300" x-text="error"></span>
                            <span x-show="!detected && !error" class="text-slate-400">{{ __('Updates appear here once a shipment is recognised.') }}</span>
                        </div>
                    </form>

                    <form x-show="tab === 'quote'" x-cloak @submit.prevent="submitQuote()" class="grid gap-2 p-2 sm:grid-cols-[1fr_1fr_90px_auto]">
                        <label class="sr-only" for="hq-from">{{ __('From') }}</label>
                        <input id="hq-from" x-model="quoteFrom" type="text" placeholder="{{ __('From city') }}" class="h-14 rounded-xl border-0 bg-paper px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/25 focus:outline-none">
                        <label class="sr-only" for="hq-to">{{ __('To') }}</label>
                        <input id="hq-to" x-model="quoteTo" type="text" placeholder="{{ __('To city') }}" class="h-14 rounded-xl border-0 bg-paper px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/25 focus:outline-none">
                        <label class="sr-only" for="hq-kg">{{ __('Weight (kg)') }}</label>
                        <input id="hq-kg" x-model="quoteWeight" type="number" min="0.1" step="0.1" placeholder="kg" class="h-14 rounded-xl border-0 bg-paper px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/25 focus:outline-none">
                        <button type="submit" class="btn-primary h-14 !rounded-xl">{{ __('Price it') }}</button>
                    </form>
                </div>

                <ul class="mt-7 flex flex-wrap gap-x-6 gap-y-3 text-xs font-medium text-slate-300 sm:text-sm">
                    <li class="flex items-center gap-2"><x-lucide name="shield-check" class="size-4 text-route-400" /> {{ __('Payments checked by our team') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="radar" class="size-4 text-route-400" /> {{ __('One place to follow every handoff') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="globe" class="size-4 text-route-400" /> {{ __('Global coverage, local delivery') }}</li>
                </ul>
            </div>

            {{-- Interactive globe beside the hero content --}}
            <div class="relative lg:col-span-6 xl:col-span-6">
                <div class="pointer-events-none absolute inset-[10%] rounded-full border border-white/10"></div>
                <div class="pointer-events-none absolute inset-[17%] rounded-full border border-white/[0.07]"></div>
                <div x-data="globe" data-distance="2.75" class="relative mx-auto aspect-square w-full max-w-[590px]">
                    <div x-ref="canvas" class="absolute inset-0"></div>
                    <div x-show="!ready && !fallback" class="absolute inset-[12%] animate-pulse rounded-full bg-[radial-gradient(circle_at_35%_35%,#19323d,#07151c_70%)] shadow-[0_0_80px_rgba(131,214,207,0.16)]"></div>
                    <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center">
                        <div class="relative w-full overflow-hidden rounded-[1.35rem] border border-white/10">
                            <img src="/images/global-globe-logistics.jpg" alt="{{ __('Map of the worldwide network') }}" class="w-full" loading="lazy">
                            <template x-for="hub in hubs" :key="hub.city">
                                <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500 ring-4 ring-brand-500/25" :style="{ left: fallbackLeft(hub), top: fallbackTop(hub) }"></span>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Floating info cards --}}
                <div class="pointer-events-none absolute top-[12%] left-0 hidden rounded-xl border border-white/15 bg-ink-900/85 px-4 py-3 shadow-xl backdrop-blur sm:block">
                    <p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">{{ __('Sample route') }}</p>
                    <p class="mt-1.5 flex items-center gap-2 font-display text-sm font-bold text-white">Houston <x-lucide name="arrow-right" class="size-4 text-route-400" /> Paris</p>
                </div>
                <div class="pointer-events-none absolute right-0 bottom-[18%] hidden rounded-xl border border-white/15 bg-ink-900/85 px-4 py-3 shadow-xl backdrop-blur sm:block">
                    <p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">{{ __('Carriers') }}</p>
                    <p class="mt-1.5 text-sm font-semibold text-white">USPS · UPS · FedEx</p>
                </div>

                {{-- Scene selector --}}
                <div class="absolute right-1/2 bottom-0 flex translate-x-1/2 items-center gap-1.5 rounded-xl border border-white/15 bg-ink-950/90 p-1.5 shadow-xl backdrop-blur-md sm:right-1/2 sm:bottom-2" role="group" aria-label="{{ __('Choose a transport scene') }}">
                    @foreach (['air' => ['plane', __('Air')], 'sea' => ['ship', __('Sea')], 'road' => ['truck', __('Road')], 'express' => ['zap', __('Express')]] as $scene => [$icon, $label])
                        <button type="button" @click="setScene('{{ $scene }}')" :aria-pressed="scene === '{{ $scene }}'"
                                class="hero-scene-control flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition"
                                :class="scene === '{{ $scene }}' ? 'text-white' : 'text-white/60 hover:text-white'">
                            <x-lucide :name="$icon" class="size-4" /> {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <span class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" aria-hidden="true"></span>
    </section>

    {{-- Services overview --}}
    <section class="relative bg-paper py-20 sm:py-24">
        <div class="container-page">
            <div class="flex flex-col items-start justify-between gap-6 md:flex-row md:items-end" data-reveal>
                <div class="max-w-2xl">
                    <p class="eyebrow eyebrow-rule">{{ __('Choose your route') }}</p>
                    <h2 class="editorial-title mt-4 text-4xl sm:text-5xl">{{ __('Four ways to move your goods. One clear view.') }}</h2>
                    <p class="mt-4 max-w-xl leading-7 text-slate-600">{{ __('The right service depends on what you are sending, how far it is going and when it needs to arrive. Start with the essentials below, then compare routes in a quote.') }}</p>
                </div>
                <a href="{{ lroute('services') }}" class="btn-ghost shrink-0">{{ __('Compare all services') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>

            @php
                $serviceCards = [
                    'air' => ['plane', __('Air freight'), __('A practical choice for parcels, business samples and time-sensitive stock. Your shipment moves on scheduled flights, with updates through customs and delivery.'), __('3–7 days'), 'images/freight-air.jpg', __('Cargo aircraft crossing the evening sky')],
                    'sea' => ['ship', __('Sea freight'), __('Share container space for furniture, equipment or regular stock. It takes longer, but often brings the cost per kilogram down for larger loads.'), __('25–45 days'), 'images/freight-sea.jpg', __('Container ship approaching a busy port at sunrise')],
                    'road' => ['truck', __('Road freight'), __('For regional journeys where a flexible pickup and direct delivery make sense. Choose road for eligible parcels and palletised freight on land routes.'), __('2–10 days'), 'images/freight-road.jpg', __('Long-haul truck travelling along a highway at dusk')],
                    'express' => ['zap', __('Express'), __('When a deadline matters most, priority handling and the next available flight help urgent documents and smaller shipments keep moving.'), __('2–4 days'), 'images/freight-express.jpg', __('Express air cargo being prepared at an airport')],
                ];
            @endphp
            <div class="mt-10 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($serviceCards as $code => [$icon, $name, $text, $transit, $image, $alt])
                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$code)]) }}" data-reveal="{{ $loop->index * 90 }}"
                       class="group card card-hover flex flex-col overflow-hidden bg-white">
                        <div class="service-photo relative aspect-[1.55] overflow-hidden bg-ink-900">
                            <img src="/{{ $image }}" alt="{{ $alt }}" class="size-full object-cover" loading="lazy" width="1376" height="768">
                            <span class="absolute top-3 left-3 inline-flex items-center gap-2 rounded-md bg-ink-950/80 px-2.5 py-1.5 text-[10px] font-semibold tracking-[0.13em] text-white uppercase backdrop-blur-sm">
                                <x-lucide :name="$icon" class="size-3.5 text-brand-300" /> {{ sprintf('%02d', $loop->iteration) }} / {{ $name }}
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <h3 class="font-display text-lg font-bold">{{ $name }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $text }}</p>
                            <div class="mt-5 flex items-center justify-between border-t border-line pt-4 text-sm">
                                <span class="flex items-center gap-2 font-medium text-ink-900"><x-lucide name="clock" class="size-4 text-brand-500" /> {{ $transit }}</span>
                                <x-lucide name="arrow-up-right" class="size-5 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-brand-500" />
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="relative overflow-hidden bg-surface py-24">
        <div class="container-page">
            <div class="mx-auto max-w-2xl text-center" data-reveal>
                <p class="eyebrow">{{ __('How it works') }}</p>
                <h2 class="editorial-title mt-3 text-4xl sm:text-5xl">{{ __('From quote to doorstep in four steps') }}</h2>
                <p class="mt-4 text-slate-600">{{ __('Begin with a route and parcel details. We will show an estimate before you book, then keep the handoff and tracking steps together in one place.') }}</p>
            </div>

            @php
                $steps = [
                    ['calculator', __('Get a quote'), __('Enter origin, destination and parcel size. See price and transit time instantly.')], 
                    ['package', __('Book your shipment'), __('Add sender, recipient and customs details. We save your progress at every step.')],
                    ['shield-check', __('Pay and upload proof'), __('Pay with the method of your choice and upload your receipt. A verifier confirms it, usually within 30 minutes.')],
                    ['radar', __('Track to delivery'), __('Get your label and tracking number, then follow every scan by email and on the globe.')],
                ];
            @endphp
            <ol class="relative mt-16 grid gap-10 md:grid-cols-4 md:gap-6">
                <svg class="pointer-events-none absolute top-7 right-[12%] left-[12%] hidden h-2 md:block" preserveAspectRatio="none" viewBox="0 0 100 2" aria-hidden="true">
                    <line x1="0" y1="1" x2="100" y2="1" stroke="#c54727" stroke-width="2" vector-effect="non-scaling-stroke" class="route-dash" opacity="0.5"/>
                </svg>
                @foreach ($steps as [$icon, $name, $text])
                    <li class="relative text-center" data-reveal="{{ $loop->index * 120 }}">
                        <span class="relative mx-auto grid size-14 place-items-center rounded-2xl border border-line bg-white text-brand-500 shadow-[var(--shadow-card)]">
                            <x-lucide :name="$icon" class="size-6" />
                            <span class="absolute -top-2 -right-2 grid size-6 place-items-center rounded-full bg-ink-900 text-[11px] font-bold text-white">{{ $loop->iteration }}</span>
                        </span>
                        <h3 class="mt-5 text-base font-bold">{{ $name }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Coverage and network --}}
    <section class="bg-white py-24">
        <div class="container-page grid items-center gap-14 lg:grid-cols-2">
            <div data-reveal>
                <p class="eyebrow">{{ __('Network and coverage') }}</p>
                <h2 class="editorial-title mt-3 text-4xl sm:text-5xl">{{ __('International freight with local delivery handled') }}</h2>
                <p class="mt-4 text-slate-600">{{ __('Your shipment may pass through several teams before it reaches the door. We coordinate the long-distance leg, share clear updates at each handoff and work with established local carriers for the final delivery.') }}</p>

                <dl class="mt-10 space-y-4">
                    @foreach ([
                        ['map-pinned', __('North America'), __('USPS and UPS handle the local delivery on eligible routes across the United States. Your shipment keeps one clear tracking journey from pickup to doorstep.')],
                        ['map-pinned', __('Europe and the United Kingdom'), __('Established regional carriers complete delivery across supported European destinations, with coordinated handover at each stage.')],
                        ['route', __('Connecting routes worldwide'), __('For destinations beyond our core lanes, our team confirms the available service and local delivery partner before you book, so there are no surprises.')],
                    ] as [$icon, $region, $text])
                        <div class="flex gap-4 rounded-2xl border border-line p-5 transition hover:border-brand-200 hover:bg-brand-50/40" data-reveal="{{ $loop->index * 90 }}">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-lucide :name="$icon" class="size-5" /></span>
                            <div>
                                <dt class="font-display font-bold text-ink-900">{{ $region }}</dt>
                                <dd class="mt-1 text-sm leading-6 text-slate-600">{{ $text }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ lroute('network') }}" class="btn-dark mt-8">{{ __('Explore the network') }} <x-lucide name="globe" class="size-4" /></a>
            </div>

            <div class="relative" data-reveal="120">
                <div class="overflow-hidden rounded-[2rem] bg-ink-900 p-3 shadow-[var(--shadow-lift)]">
                    <div class="relative overflow-hidden rounded-[1.5rem]">
                        <img src="/images/global-globe-logistics.jpg" alt="{{ __('World map showing our hubs') }}" class="w-full" loading="lazy" width="1000" height="500">
                        @foreach ($hubs as $hub)
                            <span class="absolute" style="left: {{ number_format((($hub->lon + 180) / 360) * 100, 3, '.', '') }}%; top: {{ number_format(((90 - $hub->lat) / 180) * 100, 3, '.', '') }}%" title="{{ __('Network hub') }}">
                                <span class="absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500"></span>
                                <span class="absolute size-2.5 -translate-x-1/2 -translate-y-1/2 animate-pulse-ring rounded-full bg-brand-500"></span>
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="absolute -bottom-6 -left-4 hidden rounded-2xl border border-line bg-white p-4 shadow-[var(--shadow-lift)] sm:block">
                    <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Hubs') }}</p>
                    <p class="mt-1 font-display text-2xl font-extrabold text-ink-900">{{ $hubs->count() }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Trusted payments --}}
    <section class="relative overflow-hidden bg-ink-950 py-24 text-white">
        <div class="pointer-events-none absolute inset-0 grid-bg"></div>
        <div class="container-page relative grid gap-14 lg:grid-cols-2">
            <div data-reveal>
                <p class="eyebrow !text-brand-300">{{ __('Payments') }}</p>
                <h2 class="editorial-title mt-3 text-4xl !text-white sm:text-5xl">{{ __('Pay your way. A real person checks every payment.') }}</h2>
                <p class="mt-4 max-w-xl text-slate-300">{{ __('Choose a payment method at checkout. Account details are shown only to you, on your own order, and your label is released once our team confirms the payment.') }}</p>
                @if ($paymentMethods->isNotEmpty())
                    <ul class="mt-8 flex flex-wrap gap-2">
                        @foreach ($paymentMethods as $method)
                            <li class="rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-sm font-medium text-slate-100">{{ $method->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="grid gap-4" data-reveal="120">
                @foreach ([
                    ['lock', __('Details only after you choose'), __('Payment account details never appear on public pages. They are revealed only on your order, after you pick a method.')],
                    ['badge-check', __('Human verification'), __('A payment verifier checks the amount, date and reference of your proof before anything is released.')],
                    ['triangle-alert', __('Protect yourself'), __('Never pay for a shipment someone else asked you to pay for, and never send gift cards to a stranger. If in doubt, contact us first.')],
                ] as [$icon, $name, $text])
                    <div class="flex gap-4 rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-white/10 text-brand-300"><x-lucide :name="$icon" class="size-5" /></span>
                        <div>
                            <h3 class="font-display font-bold !text-white">{{ $name }}</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-300">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ preview --}}
    @if ($faqs->isNotEmpty())
        <section class="bg-white py-24">
            <div class="container-page grid gap-12 lg:grid-cols-3">
                <div data-reveal>
                    <p class="eyebrow">{{ __('Help center') }}</p>
                    <h2 class="editorial-title mt-3 text-4xl sm:text-5xl">{{ __('Questions, answered') }}</h2>
                    <p class="mt-4 text-slate-600">{{ __('Can not find what you need? Our team replies in English and French.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ lroute('help') }}" class="btn-ghost">{{ __('Visit the help center') }}</a>
                        <a href="{{ lroute('contact') }}" class="btn-dark">{{ __('Contact us') }}</a>
                    </div>
                </div>
                <div class="divide-y divide-line rounded-3xl border border-line lg:col-span-2" data-reveal="100">
                    @foreach ($faqs as $faq)
                        <details class="group p-6 [&_summary::-webkit-details-marker]:hidden">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-6 font-display text-base font-semibold text-ink-900">
                                {{ $faq->question }}
                                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-surface transition group-open:rotate-45 group-open:bg-brand-500 group-open:text-white"><x-lucide name="plus" class="size-4" /></span>
                            </summary>
                            <div class="prose-content mt-3 text-slate-600">{!! $faq->html !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Closing section --}}
    <section class="container-page pb-20 sm:pb-24">
        <div class="relative grid overflow-hidden rounded-2xl bg-ink-900 text-white shadow-[var(--shadow-lift)] md:min-h-[370px] md:grid-cols-2" data-reveal>
            <div class="relative z-10 flex flex-col items-start justify-center p-7 sm:p-10 md:py-14">
                <p class="eyebrow eyebrow-rule !text-brand-300">{{ __('Before the first mile') }}</p>
                <h2 class="editorial-title mt-4 max-w-xl text-4xl leading-[1.02] !text-white sm:text-5xl">{{ __('Start with a clear route.') }}</h2>
                <p class="mt-4 max-w-lg text-sm leading-6 text-slate-300 sm:text-base">{{ __('Share the places, parcel size and timing you have in mind. We will help you compare a service and see an estimate before you decide to book.') }}</p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Build a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                    <a href="{{ lroute('register') }}" class="btn-light">{{ __('Create a free account') }}</a>
                </div>
            </div>
            <div class="service-photo relative min-h-64 overflow-hidden bg-ink-800 md:min-h-full" aria-hidden="true">
                <img src="/images/freight-road.jpg" alt="" class="absolute inset-0 size-full object-cover object-center" loading="lazy" width="1376" height="768">
                <div class="absolute inset-0 bg-gradient-to-r from-ink-900 via-ink-900/45 to-transparent md:from-ink-900/70 md:via-ink-900/10 md:to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-ink-950/50 to-transparent"></div>
                <p class="absolute right-5 bottom-5 text-[10px] font-semibold tracking-[0.18em] text-white/80 uppercase">{{ __('A better view of every handoff') }}</p>
            </div>
        </div>
    </section>
@endsection

@push('data')
    <script type="application/json" id="hero-media">@json($heroMedia)</script>
    <script type="application/json" id="carrier-formats">@json($carrierFormats)</script>
@endpush
