@extends('layouts.app', ['darkHeader' => true])

@php
    $heroMedia = [];
    foreach (['air', 'sea', 'road'] as $scene) {
        $heroMedia[$scene] = array_filter([
            'mp4' => isset($hero['hero_'.$scene.'_mp4']) ? asset('storage/'.$hero['hero_'.$scene.'_mp4']) : null,
            'webm' => isset($hero['hero_'.$scene.'_webm']) ? asset('storage/'.$hero['hero_'.$scene.'_webm']) : null,
            'poster' => isset($hero['hero_'.$scene.'_poster']) ? asset('storage/'.$hero['hero_'.$scene.'_poster']) : null,
        ]);
    }
@endphp

@section('content')
    {{-- Hero (FR-01 to FR-05) --}}
    <section x-data="heroScenes" data-scene="{{ $defaultScene }}"
             class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-0 -z-20">
            <img :src="poster()" x-show="poster()" x-cloak alt="" class="size-full object-cover" fetchpriority="high">
            <video x-ref="video" x-show="hasVideo" x-cloak class="size-full object-cover" muted loop playsinline preload="none" aria-hidden="true"></video>
        </div>
        {{-- Dark overlay keeps the headline above a 4.5:1 contrast ratio (FR-03). --}}
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_70%_45%,rgba(34,184,240,0.16),transparent_55%),linear-gradient(100deg,#050c18_0%,rgba(5,12,24,0.94)_38%,rgba(5,12,24,0.55)_70%,rgba(5,12,24,0.75)_100%)]"></div>
        <div class="absolute inset-0 -z-10 grid-bg"></div>

        <div class="container-page grid min-h-[min(940px,100svh)] items-center gap-10 pt-28 pb-16 lg:grid-cols-12 lg:pt-24">
            <div class="lg:col-span-6 xl:col-span-6">
                <p class="eyebrow !text-brand-300">
                    <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-brand-400 opacity-75"></span><span class="relative inline-flex size-2 rounded-full bg-brand-400"></span></span>
                    {{ __('Air · Sea · Road freight') }}
                </p>
                <h1 class="mt-5 text-4xl leading-[1.05] font-extrabold !text-white sm:text-5xl xl:text-6xl">
                    {{ __('Ship across continents.') }}
                    <span class="bg-gradient-to-r from-brand-300 via-brand-400 to-route-400 bg-clip-text text-transparent">{{ __('Track every mile.') }}</span>
                </h1>
                <p class="mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg">
                    {{ __('Door-to-door shipping between Africa, Europe and the United States. Instant quotes, verified payments and one tracking box for every carrier.') }}
                </p>

                {{-- Tracking box (FR-04) --}}
                <div x-data="trackBox" data-track-url="{{ lroute('track') }}" data-quote-url="{{ lroute('quote') }}"
                     class="mt-8 max-w-xl rounded-3xl border border-white/10 bg-white/[0.06] p-2 shadow-2xl shadow-black/40 backdrop-blur-xl">
                    <div class="flex gap-1 p-1" role="tablist">
                        <button type="button" role="tab" :aria-selected="tab === 'track'" @click="tab = 'track'"
                                class="flex flex-1 items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition"
                                :class="tab === 'track' ? 'bg-white text-ink-900 shadow' : 'text-white/75 hover:text-white'">
                            <x-lucide name="radar" class="size-4" /> {{ __('Track') }}
                        </button>
                        <button type="button" role="tab" :aria-selected="tab === 'quote'" @click="tab = 'quote'"
                                class="flex flex-1 items-center justify-center gap-2 rounded-2xl px-4 py-2.5 text-sm font-semibold transition"
                                :class="tab === 'quote' ? 'bg-white text-ink-900 shadow' : 'text-white/75 hover:text-white'">
                            <x-lucide name="calculator" class="size-4" /> {{ __('Get a quote') }}
                        </button>
                    </div>

                    <form x-show="tab === 'track'" @submit.prevent="submit()" action="{{ lroute('track') }}" method="GET" class="p-2">
                        <label for="hero-track" class="sr-only">{{ __('Tracking numbers') }}</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1">
                                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                                <input id="hero-track" x-ref="trackInput" x-model="input" name="numbers" type="text" autocomplete="off" spellcheck="false"
                                       class="h-14 w-full rounded-2xl border-0 bg-white pr-4 pl-12 text-base font-medium text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/40 focus:outline-none"
                                       placeholder="{{ __('Enter up to 20 tracking numbers') }}">
                            </div>
                            <button type="submit" class="btn-primary h-14 !rounded-2xl !px-7 text-base">
                                {{ __('Track') }} <x-lucide name="arrow-right" class="size-5" />
                            </button>
                        </div>
                        <div class="mt-3 flex min-h-6 flex-wrap items-center gap-x-3 gap-y-2 px-1 text-sm">
                            <span x-show="detected" x-cloak class="inline-flex items-center gap-1.5 font-medium text-route-400">
                                <x-lucide name="badge-check" class="size-4" /> <span x-text="detected"></span>
                            </span>
                            <span x-show="error" x-cloak class="text-brand-300" x-text="error"></span>
                            @if ($examples->isNotEmpty())
                                <span x-show="!detected && !error" class="text-slate-400">{{ __('Try:') }}</span>
                                @foreach ($examples as $example)
                                    <button type="button" x-show="!detected && !error" data-number="{{ $example }}" @click="useExample($el.dataset.number)" class="rounded-full border border-white/15 px-2.5 py-0.5 font-mono text-xs text-slate-200 transition hover:border-white/40 hover:text-white">{{ $example }}</button>
                                @endforeach
                            @endif
                        </div>
                    </form>

                    <form x-show="tab === 'quote'" x-cloak @submit.prevent="submitQuote()" class="grid gap-2 p-2 sm:grid-cols-[1fr_1fr_110px_auto]">
                        <label class="sr-only" for="hq-from">{{ __('From') }}</label>
                        <input id="hq-from" x-model="quoteFrom" type="text" placeholder="{{ __('From (city)') }}" class="h-14 rounded-2xl border-0 bg-white px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/40 focus:outline-none">
                        <label class="sr-only" for="hq-to">{{ __('To') }}</label>
                        <input id="hq-to" x-model="quoteTo" type="text" placeholder="{{ __('To (city)') }}" class="h-14 rounded-2xl border-0 bg-white px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/40 focus:outline-none">
                        <label class="sr-only" for="hq-kg">{{ __('Weight (kg)') }}</label>
                        <input id="hq-kg" x-model="quoteWeight" type="number" min="0.1" step="0.1" placeholder="kg" class="h-14 rounded-2xl border-0 bg-white px-4 text-ink-900 placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/40 focus:outline-none">
                        <button type="submit" class="btn-primary h-14 !rounded-2xl">{{ __('Price it') }}</button>
                    </form>
                </div>

                <ul class="mt-8 grid max-w-xl grid-cols-1 gap-3 text-sm text-slate-300 sm:grid-cols-3">
                    <li class="flex items-center gap-2"><x-lucide name="shield-check" class="size-5 text-route-400" /> {{ __('Payments verified by our team') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="radar" class="size-5 text-route-400" /> {{ __('USPS, UPS, FedEx & own freight') }}</li>
                    <li class="flex items-center gap-2"><x-lucide name="languages" class="size-5 text-route-400" /> {{ __('Support in English & French') }}</li>
                </ul>
            </div>

            {{-- Live network globe --}}
            <div class="relative lg:col-span-6 xl:col-span-6">
                <div x-data="globe" data-distance="2.75" class="relative mx-auto aspect-square w-full max-w-[640px]">
                    <div x-ref="canvas" class="absolute inset-0"></div>
                    <div x-show="!ready && !fallback" class="absolute inset-[12%] animate-pulse rounded-full bg-[radial-gradient(circle_at_35%_35%,#16325a,#0a1628_70%)] shadow-[0_0_80px_rgba(58,160,255,0.25)]"></div>
                    <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center">
                        <div class="relative w-full overflow-hidden rounded-3xl border border-white/10">
                            <img src="/images/world-map.svg" alt="{{ __('Map of our network') }}" class="w-full" loading="lazy">
                            <template x-for="hub in hubs" :key="hub.city">
                                <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500 ring-4 ring-brand-500/25" :style="{ left: fallbackLeft(hub), top: fallbackTop(hub) }"></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-2 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full border border-white/10 bg-ink-900/70 p-1 backdrop-blur" role="group" aria-label="{{ __('Transport scene') }}">
                    @foreach (['air' => ['plane', __('Air')], 'sea' => ['ship', __('Sea')], 'road' => ['truck', __('Road')]] as $scene => [$icon, $label])
                        <button type="button" @click="setScene('{{ $scene }}')" :aria-pressed="scene === '{{ $scene }}'"
                                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition"
                                :class="scene === '{{ $scene }}' ? 'bg-white text-ink-900' : 'text-white/70 hover:text-white'">
                            <x-lucide :name="$icon" class="size-4" /> {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="pointer-events-none absolute top-[12%] -left-2 hidden animate-float rounded-2xl border border-white/10 bg-ink-900/80 p-3 shadow-xl backdrop-blur sm:block">
                    <p class="text-[11px] tracking-wider text-slate-400 uppercase">{{ __('Lane') }}</p>
                    <p class="mt-1 flex items-center gap-2 font-display text-sm font-bold">Douala <x-lucide name="plane" class="size-4 text-route-400" /> Paris</p>
                </div>
                <div class="pointer-events-none absolute right-0 bottom-[16%] hidden animate-float rounded-2xl border border-white/10 bg-ink-900/80 p-3 shadow-xl backdrop-blur [animation-delay:-3s] sm:block">
                    <p class="text-[11px] tracking-wider text-slate-400 uppercase">{{ __('Last mile') }}</p>
                    <p class="mt-1 text-sm font-semibold">USPS · UPS · FedEx</p>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-b from-transparent to-white/0"></div>
    </section>

    @if ($alerts->isNotEmpty())
        <section class="border-b border-amber-200 bg-amber-50">
            <div class="container-page flex flex-col gap-2 py-3 text-sm text-amber-900 sm:flex-row sm:items-center">
                <x-lucide name="triangle-alert" class="size-5 shrink-0 text-amber-600" />
                <p class="flex-1"><strong>{{ $alerts->first()->title }}</strong> — {{ \Illuminate\Support\Str::limit($alerts->first()->body, 160) }}</p>
                <a href="{{ lroute('status') }}" class="font-semibold underline-offset-4 hover:underline">{{ __('All service alerts') }}</a>
            </div>
        </section>
    @endif

    {{-- Services --}}
    <section class="relative bg-white py-24">
        <div class="container-page">
            <div class="flex flex-col items-start justify-between gap-6 md:flex-row md:items-end" data-reveal>
                <div class="max-w-2xl">
                    <p class="eyebrow">{{ __('Services') }}</p>
                    <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ __('One partner for every way your goods travel') }}</h2>
                </div>
                <a href="{{ lroute('services') }}" class="btn-ghost">{{ __('All services') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>

            @php
                $serviceCards = [
                    'air' => ['plane', __('Air freight'), __('The fastest way between continents. Ideal for parcels, documents and urgent goods.'), __('3–7 days'), 'from-sky-500/15'],
                    'sea' => ['ship', __('Sea freight'), __('The economical choice for heavy and bulky shipments, by consolidated container.'), __('25–45 days'), 'from-blue-600/15'],
                    'road' => ['truck', __('Road freight'), __('Door-to-door within Europe, North America and across West and Central Africa.'), __('2–10 days'), 'from-orange-500/15'],
                    'express' => ['zap', __('Express'), __('Priority handling and the first available flight, for when every day counts.'), __('2–4 days'), 'from-amber-400/20'],
                ];
            @endphp
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($serviceCards as $code => [$icon, $name, $text, $transit, $tint])
                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$code)]) }}" data-reveal="{{ $loop->index * 90 }}"
                       class="group card card-hover relative flex flex-col overflow-hidden p-6">
                        <div class="absolute inset-x-0 top-0 h-32 bg-gradient-to-b {{ $tint }} to-transparent"></div>
                        <span class="relative grid size-12 place-items-center rounded-2xl bg-ink-900 text-white shadow-lg transition duration-300 group-hover:scale-110 group-hover:bg-brand-500">
                            <x-lucide :name="$icon" class="size-6" />
                        </span>
                        <h3 class="relative mt-6 text-lg font-bold">{{ $name }}</h3>
                        <p class="relative mt-2 flex-1 text-sm leading-6 text-slate-600">{{ $text }}</p>
                        <div class="relative mt-6 flex items-center justify-between border-t border-line pt-4 text-sm">
                            <span class="flex items-center gap-1.5 font-medium text-ink-900"><x-lucide name="clock" class="size-4 text-brand-500" /> {{ $transit }}</span>
                            <x-lucide name="arrow-up-right" class="size-5 text-slate-400 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-brand-500" />
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
                <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ __('From quote to doorstep in four steps') }}</h2>
                <p class="mt-4 text-slate-600">{{ __('Book in under five minutes. Your label and tracking number are released as soon as our team confirms your payment.') }}</p>
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
                    <line x1="0" y1="1" x2="100" y2="1" stroke="#ff6b2c" stroke-width="2" vector-effect="non-scaling-stroke" class="route-dash" opacity="0.5"/>
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

    {{-- Coverage --}}
    <section class="bg-white py-24">
        <div class="container-page grid items-center gap-14 lg:grid-cols-2">
            <div data-reveal>
                <p class="eyebrow">{{ __('Network and coverage') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">{{ __('Our freight, trusted last-mile networks') }}</h2>
                <p class="mt-4 text-slate-600">{{ __('We move your goods on our own air, sea and road lanes, then hand over to established local networks for the final delivery.') }}</p>

                <dl class="mt-10 space-y-4">
                    @foreach ([
                        ['map-pinned', __('United States'), __('Last mile by USPS and UPS. Track with the same number end to end.')],
                        ['map-pinned', __('Europe'), __('Last mile by FedEx across the EU, the UK and Switzerland.')],
                        ['route', __('Africa and the rest of the world'), __(':brand air, sea and road freight with our hubs and local partners.', ['brand' => config('platform.brand.name')])],
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
                        <img src="/images/world-map.svg" alt="{{ __('World map showing our hubs') }}" class="w-full" loading="lazy" width="1000" height="500">
                        @foreach ($hubs as $hub)
                            <span class="absolute" style="left: {{ number_format((($hub->lon + 180) / 360) * 100, 3, '.', '') }}%; top: {{ number_format(((90 - $hub->lat) / 180) * 100, 3, '.', '') }}%" title="{{ $hub->city }}">
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

    {{-- Payments you can trust --}}
    <section class="relative overflow-hidden bg-ink-950 py-24 text-white">
        <div class="pointer-events-none absolute inset-0 grid-bg"></div>
        <div class="pointer-events-none absolute -top-40 right-0 size-[520px] rounded-full bg-brand-500/10 blur-3xl"></div>
        <div class="container-page relative grid gap-14 lg:grid-cols-2">
            <div data-reveal>
                <p class="eyebrow !text-brand-300">{{ __('Payments') }}</p>
                <h2 class="mt-3 text-3xl font-extrabold !text-white sm:text-4xl">{{ __('Pay your way. A real person checks every payment.') }}</h2>
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
                    <h2 class="mt-3 text-3xl font-extrabold">{{ __('Questions, answered') }}</h2>
                    <p class="mt-4 text-slate-600">{{ __('Can’t find what you need? Our team replies in English and French.') }}</p>
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

    {{-- CTA --}}
    <section class="px-4 pb-24 sm:px-6 lg:px-8">
        <div class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-500 via-brand-600 to-brand-700 px-6 py-16 text-white shadow-[0_30px_60px_-30px_rgb(232_85_26/0.7)] sm:px-14" data-reveal>
            <div class="pointer-events-none absolute -right-10 -bottom-24 opacity-20"><x-lucide name="plane" class="size-[340px] -rotate-12" /></div>
            <div class="relative max-w-2xl">
                <h2 class="text-3xl font-extrabold !text-white sm:text-4xl">{{ __('Ready to ship?') }}</h2>
                <p class="mt-3 text-lg text-white/85">{{ __('Get a price in seconds and book in under five minutes.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ lroute('quote') }}" class="btn bg-white text-ink-900 hover:-translate-y-0.5">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                    <a href="{{ lroute('register') }}" class="btn-light">{{ __('Create a free account') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('data')
    <script type="application/json" id="hero-media">@json($heroMedia)</script>
    <script type="application/json" id="carrier-formats">@json($carrierFormats)</script>
@endpush
