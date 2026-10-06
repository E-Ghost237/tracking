@extends('layouts.app', ['title' => __('Network and coverage'), 'description' => __('Explore supported shipping routes, local delivery connections and nearby hubs on an interactive globe.')])

@section('content')
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="pointer-events-none absolute inset-0 grid-bg opacity-30"></div>
        <div class="pointer-events-none absolute -right-32 -top-40 size-[560px] rounded-full border border-white/[0.06]"></div>
        <div class="pointer-events-none absolute -right-16 -top-24 size-[420px] rounded-full border border-white/[0.06]"></div>
        <div class="container-page relative grid gap-10 py-14 sm:py-16 lg:grid-cols-12 lg:items-center lg:gap-8 lg:py-20">
            <div class="relative z-10 lg:col-span-5">
                <p class="eyebrow eyebrow-rule !text-brand-300"><x-lucide name="globe" class="size-4" /> {{ __('Network and coverage') }}</p>
                <h1 class="editorial-title mt-5 text-5xl leading-[0.98] !text-white sm:text-6xl">{{ __('A clearer picture of the journey.') }}</h1>
                <p class="mt-5 max-w-lg text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">{{ __('Explore the network, look up a city or drop a pin on the globe. We will show the delivery options available for that point and how far it is from a network hub, so you can plan the next step with more confidence.') }}</p>

                <div x-data="networkPanel" data-quote-url="{{ lroute('quote') }}" data-invalid-coordinates="{{ __('Enter a latitude between -90 and 90 and a longitude between -180 and 180.') }}"
                     @place-selected="onPlace($event.detail)" class="mt-8 space-y-4">
                    <div class="rounded-xl border border-white/10 bg-paper p-4 text-ink-900 shadow-xl">
                        <x-place-field field="network" :label="__('Search a city')" />
                        <details class="mt-3 border-t border-line pt-3 text-sm">
                            <summary class="cursor-pointer font-semibold text-slate-600">{{ __('Search by coordinates instead') }}</summary>
                            <form @submit.prevent="submitCoordinates()" class="mt-3 grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input x-model="lat" type="number" step="any" min="-90" max="90" class="field" placeholder="{{ __('Latitude') }}" aria-label="{{ __('Latitude') }}">
                                <input x-model="lon" type="number" step="any" min="-180" max="180" class="field" placeholder="{{ __('Longitude') }}" aria-label="{{ __('Longitude') }}">
                                <button class="btn-dark !px-4" type="submit">{{ __('Go') }}</button>
                            </form>
                            <p class="field-error" x-show="error" x-text="error"></p>
                        </details>
                    </div>

                    <div class="rounded-xl border border-white/12 bg-white/[0.045] p-5" aria-live="polite">
                        <template x-if="!point">
                            <div>
                                <p class="flex items-center gap-2 text-sm font-semibold text-white"><x-lucide name="map-pin" class="size-4 text-route-400" /> {{ __('Choose a point on the globe') }}</p>
                                <p class="mt-2 max-w-sm text-sm leading-6 text-slate-400">{{ __('A nearby city or a set of coordinates will reveal the available network and a route to booking.') }}</p>
                            </div>
                        </template>
                        <template x-if="point">
                            <div class="space-y-3 text-sm">
                                <p class="font-display text-lg font-bold text-white"><span x-show="place" x-text="place && place.label"></span><span x-show="!place">{{ __('No city nearby') }}</span></p>
                                <p class="font-mono text-xs text-slate-400"><span x-text="point.lat"></span>, <span x-text="point.lon"></span></p>
                                <div x-show="network" class="flex items-start gap-2 text-slate-200"><x-lucide name="route" class="size-4 shrink-0 text-route-400" /> <span x-text="network && network.label"></span></div>
                                <div x-show="hub" class="flex items-start gap-2 text-slate-200"><x-lucide name="warehouse" class="size-4 shrink-0 text-route-400" />
                                    <span>{{ __('Nearest network hub') }}: <strong class="text-white"><span x-text="hub && hub.distance_km.toLocaleString()"></span> km away</strong></span>
                                </div>
                                <a :href="shipUrl()" x-show="place" class="btn-primary mt-2 w-full">{{ __('Plan a shipment to this point') }} <x-lucide name="arrow-right" class="size-4" /></a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="relative mx-auto max-w-[700px]">
                    <div class="pointer-events-none absolute inset-[8%] rounded-full border border-white/[0.08]"></div>
                    <div x-data="globe" data-picker="true" data-distance="2.7" class="relative aspect-square w-full">
                        <div x-ref="canvas" class="absolute inset-0 cursor-grab active:cursor-grabbing"></div>
                        <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center">
                            <div class="relative w-full overflow-hidden rounded-xl border border-white/10">
                                <img src="/images/global-globe-logistics.jpg" alt="{{ __('World map of supported routes') }}" class="w-full">
                                <template x-for="hub in hubs" :key="hub.city">
                                    <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500" :style="{ left: fallbackLeft(hub), top: fallbackTop(hub) }"></span>
                                </template>
                            </div>
                        </div>
                        <div class="absolute top-3 right-3 flex flex-col gap-2">
                            <button type="button" class="grid size-10 place-items-center rounded-lg border border-white/10 bg-ink-900/75 text-white backdrop-blur transition hover:bg-ink-800" @click="zoomIn()" aria-label="{{ __('Zoom in') }}"><x-lucide name="plus" class="size-4" /></button>
                            <button type="button" class="grid size-10 place-items-center rounded-lg border border-white/10 bg-ink-900/75 text-white backdrop-blur transition hover:bg-ink-800" @click="zoomOut()" aria-label="{{ __('Zoom out') }}"><x-lucide name="minus" class="size-4" /></button>
                        </div>
                        <div class="absolute inset-x-0 bottom-1 flex flex-wrap justify-center gap-2" x-show="regions.length">
                            <template x-for="region in regions" :key="region.code">
                                <button type="button" @click="focusRegion(region)" class="rounded-lg border border-white/15 bg-ink-900/85 px-3 py-2 text-xs font-semibold text-white backdrop-blur transition hover:border-brand-300/60" x-text="region.label"></button>
                            </template>
                        </div>
                    </div>
                    <p class="mx-auto mt-2 max-w-sm text-center text-xs leading-5 text-slate-400">{{ __('Drag to explore. Use the region controls to move across the map, or click a point to check coverage.') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="container-page py-16 sm:py-20">
        <div class="flex flex-col gap-4 border-b border-line pb-7 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow eyebrow-rule">{{ __('Network locations') }}</p>
                <h2 class="editorial-title mt-4 text-4xl">{{ __('Hubs and handoff points') }}</h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-slate-600">{{ __('These locations help organise collection, consolidation and delivery. Service availability can vary by route, so use the quote tool to confirm the current options for your shipment.') }}</p>
        </div>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($hubs as $hub)
                @continue($hub->country === 'CM')
                <li class="card flex items-start gap-4 p-5" data-reveal="{{ ($loop->index % 3) * 70 }}">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-lucide name="warehouse" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-display font-bold text-ink-900">{{ $hub->city }}</p>
                        <p class="text-sm text-slate-500">{{ \App\Support\Geo::countryName($hub->country) }}</p>
                        <p class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($hub->modes ?? [] as $hubMode)
                                <span class="badge bg-surface text-slate-700">{{ __(ucfirst($hubMode)) }}</span>
                            @endforeach
                        </p>
                    </div>
                    <x-lucide name="arrow-up-right" class="mt-1 size-4 shrink-0 text-slate-400" />
                </li>
            @endforeach
        </ul>
    </section>
@endsection
