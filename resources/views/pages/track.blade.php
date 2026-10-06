@extends('layouts.app', ['title' => __('Track a parcel'), 'noindex' => true, 'description' => __('Track USPS, UPS, FedEx and :brand shipments in one place.', ['brand' => config('platform.brand.name')])])

@section('content')
    <div x-data="trackPage" data-track-url="{{ lroute('track') }}" data-number="{{ $number }}" data-numbers="{{ request()->query('numbers') && is_string(request()->query('numbers')) ? \Illuminate\Support\Str::limit(request()->query('numbers'), 900, '') : '' }}">
        <section class="relative overflow-hidden bg-ink-950 text-white">
            <div class="pointer-events-none absolute inset-0 grid-bg"></div>
            <div class="container-page relative py-14">
                <p class="eyebrow eyebrow-rule !text-brand-300"><x-lucide name="radar" class="size-4" /> {{ __('Shipment tracking') }}</p>
                <h1 class="editorial-title mt-4 max-w-3xl text-5xl leading-[0.98] !text-white sm:text-6xl">{{ __('See where the journey stands.') }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">{{ __('Enter up to 20 tracking numbers, one per line or separated by commas. We recognise supported USPS, UPS, FedEx and brand numbers, then bring available scans together so you do not have to check several sites.', ['brand' => config('platform.brand.name')]) }}</p>

                <form @submit.prevent="lookup()" class="mt-8 grid max-w-3xl gap-3 sm:grid-cols-[1fr_auto]" action="{{ lroute('track') }}" method="GET">
                    <label for="track-input" class="sr-only">{{ __('Tracking numbers') }}</label>
                    <textarea id="track-input" x-model="input" name="numbers" rows="2" spellcheck="false" autocomplete="off"
                              class="min-h-14 w-full resize-y rounded-2xl border-0 bg-white px-4 py-4 font-mono text-sm text-ink-900 placeholder:font-sans placeholder:text-slate-400 focus:ring-4 focus:ring-brand-500/40 focus:outline-none"
                              placeholder="CV-AIR-100013, 1Z999AA10123456784">{{ $number }}</textarea>
                    <button type="submit" class="btn-primary h-14 !rounded-2xl !px-8" :disabled="loading">
                        <span x-show="!loading">{{ __('Track') }}</span>
                        <span x-show="loading" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Searching') }}</span>
                    </button>
                </form>

                <div x-show="captcha" x-cloak class="mt-4 flex max-w-3xl flex-wrap items-center gap-3 rounded-2xl bg-white/10 p-4">
                    <img :src="captcha && captcha.src" alt="{{ __('Arithmetic question') }}" width="170" height="54" class="rounded-lg">
                    <input x-model="captchaAnswer" type="text" inputmode="numeric" class="field max-w-[140px]" placeholder="{{ __('Answer') }}" aria-label="{{ __('Answer') }}">
                    <button type="button" class="btn-primary" @click="lookup()">{{ __('Continue') }}</button>
                </div>
                <p x-show="error" x-cloak class="mt-4 text-sm text-brand-300" x-text="error" role="alert"></p>
            </div>
        </section>

        <div class="container-page grid gap-8 py-12 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-7">
                <template x-if="results.length === 0 && !loading">
                    <div class="card flex flex-col items-center px-6 py-16 text-center">
                        <span class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-500"><x-lucide name="package" class="size-7" /></span>
                        <h2 class="mt-5 text-lg font-bold">{{ __('Your results will appear here') }}</h2>
                        <p class="mt-2 max-w-sm text-sm text-slate-600">{{ __('Tracking numbers appear once payment is approved. Check your confirmation email for yours.') }}</p>
                    </div>
                </template>

                <template x-for="result in results" :key="result.number">
                    <article class="card overflow-hidden">
                        <template x-if="!result.found">
                            <div class="flex gap-4 p-6">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600"><x-lucide name="circle-alert" class="size-5" /></span>
                                <div>
                                    <p class="font-mono text-sm font-semibold text-ink-900" x-text="result.number"></p>
                                    <p class="mt-1 text-sm text-slate-600" x-text="result.message"></p>
                                    <a x-show="result.external_url" :href="result.external_url" target="_blank" rel="noopener noreferrer nofollow" class="link mt-2 inline-flex items-center gap-1 text-sm">
                                        {{ __('Check on the carrier website') }} <x-lucide name="external-link" class="size-3.5" />
                                    </a>
                                </div>
                            </div>
                        </template>

                        <template x-if="result.found">
                            <div>
                                <div class="border-b border-line p-6">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase"><span x-text="result.carrier.name"></span><span x-show="result.service"> · <span x-text="result.service"></span></span></p>
                                            <p class="mt-1 font-mono text-lg font-bold text-ink-900" x-text="result.number"></p>
                                        </div>
                                        <span class="badge ring-1 ring-inset" :class="statusTone(result.status)" x-text="result.status_label"></span>
                                    </div>

                                    <div class="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">{{ __('From') }}</p>
                                            <p class="font-semibold text-ink-900" x-text="result.origin || '{{ __('Not provided') }}'"></p>
                                        </div>
                                        <x-lucide name="arrow-right" class="size-5 text-slate-300" />
                                        <div class="text-right">
                                            <p class="text-xs text-slate-500">{{ __('To') }}</p>
                                            <p class="font-semibold text-ink-900" x-text="result.destination || '{{ __('Not provided') }}'"></p>
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <div class="relative h-2 overflow-hidden rounded-full bg-surface" role="progressbar" :aria-valuenow="progressValue(result)" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-full rounded-full bg-gradient-to-r from-route-500 to-brand-500 transition-[width] duration-700" :style="{ width: progressPercent(result) }"></div>
                                        </div>
                                        <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                                            <span x-show="result.eta">{{ __('Estimated delivery') }}: <strong class="text-ink-900" x-text="when(result.eta)"></strong></span>
                                            <span x-show="result.weight_kg">{{ __('Weight') }}: <strong class="text-ink-900" x-text="result.weight_kg + ' kg'"></strong></span>
                                            <span x-show="result.partner">{{ __('Last mile') }}: <strong class="text-ink-900" x-text="result.partner && (result.partner.name + ' ' + result.partner.number)"></strong></span>
                                        </div>
                                    </div>
                                </div>

                                <ol class="relative space-y-0 p-6">
                                    <template x-for="(event, index) in result.events" :key="index">
                                        <li class="relative flex gap-4 pb-6 last:pb-0">
                                            <div class="relative flex flex-col items-center">
                                                <span class="relative z-10 mt-1 size-3 rounded-full" :class="index === 0 ? 'bg-brand-500 timeline-dot' : 'bg-slate-300'"></span>
                                                <span x-show="index < result.events.length - 1" class="absolute top-4 bottom-[-4px] w-px bg-line"></span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold" :class="index === 0 ? 'text-ink-900' : 'text-slate-700'" x-text="event.label"></p>
                                                <p class="mt-0.5 text-xs text-slate-500"><span x-text="event.place"></span><span x-show="event.place"> · </span><span x-text="when(event.at)"></span></p>
                                                <p class="mt-1 text-[11px] font-medium tracking-wide text-slate-400 uppercase">{{ __('Source') }}: <span x-text="event.source"></span></p>
                                            </div>
                                        </li>
                                    </template>
                                </ol>

                                <div class="flex flex-wrap items-center gap-2 border-t border-line bg-surface/60 p-4">
                                    <button type="button" class="btn-ghost !py-2 text-xs" x-show="result.route" @click="showOnGlobe(result)"><x-lucide name="globe" class="size-4" /> {{ __('Show on globe') }}</button>
                                    <button type="button" class="btn-ghost !py-2 text-xs" @click="copyLink(result)">
                                        <x-lucide name="copy" class="size-4" />
                                        <span x-show="copied !== result.number">{{ __('Copy link') }}</span>
                                        <span x-show="copied === result.number" x-cloak>{{ __('Link copied') }}</span>
                                    </button>
                                    <button type="button" class="btn-ghost !py-2 text-xs" @click="openSubscribe(result)"><x-lucide name="bell" class="size-4" /> {{ __('Email alerts') }}</button>
                                    <details class="relative ml-auto">
                                        <summary class="btn-ghost !py-2 text-xs list-none"><x-lucide name="qr-code" class="size-4" /> {{ __('QR code') }}</summary>
                                        <div class="absolute right-0 z-20 mt-2 rounded-2xl border border-line bg-white p-3 shadow-[var(--shadow-lift)]">
                                            <img :src="qrUrl(result)" alt="{{ __('QR code linking to this tracking page') }}" width="160" height="160" loading="lazy">
                                        </div>
                                    </details>
                                </div>

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

            <aside class="lg:col-span-5">
                <div class="sticky top-24 overflow-hidden rounded-[1.75rem] bg-ink-950 shadow-[var(--shadow-lift)]" id="route-globe">
                    <div x-data="globe" data-autorotate="true" data-distance="2.9" class="relative aspect-square">
                        <div x-ref="canvas" class="absolute inset-0"></div>
                        <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center p-4"><img src="/images/world-map.svg" alt="{{ __('World map') }}" class="w-full rounded-xl"></div>
                        <div class="absolute right-3 bottom-3 flex flex-col gap-1">
                            <button type="button" class="grid size-9 place-items-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20" @click="zoomIn()" aria-label="{{ __('Zoom in') }}"><x-lucide name="plus" class="size-4" /></button>
                            <button type="button" class="grid size-9 place-items-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20" @click="zoomOut()" aria-label="{{ __('Zoom out') }}"><x-lucide name="minus" class="size-4" /></button>
                        </div>
                    </div>
                    <div class="border-t border-white/10 p-5 text-sm text-slate-300">
                        <p class="flex items-center gap-2 font-semibold text-white"><x-lucide name="info" class="size-4 text-route-400" /> {{ __('About tracking data') }}</p>
                        <p class="mt-2 leading-6">{{ __('Events come from :brand scans or directly from the carrier. Each event shows its source. Public tracking never shows addresses, phone numbers or emails.', ['brand' => config('platform.brand.name')]) }}</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('data')
    @if ($result)
        <script type="application/json" id="track-result">@json($result)</script>
    @endif
@endpush
