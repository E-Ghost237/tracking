@extends('layouts.app', ['title' => __('Network and coverage'), 'description' => __('Our hubs, lanes and last-mile partners on an interactive globe.')])

@section('content')
    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="pointer-events-none absolute inset-0 grid-bg"></div>
        <div class="container-page relative grid gap-10 py-14 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="eyebrow !text-brand-300"><x-lucide name="globe" class="size-4" /> {{ __('Network and coverage') }}</p>
                <h1 class="mt-3 text-3xl font-extrabold !text-white sm:text-5xl">{{ __('Find the network that delivers to any point') }}</h1>
                <p class="mt-4 text-slate-300">{{ __('Drag the globe, click anywhere or search a city. We show the delivery network, the nearest hub and the distance, then you can ship to that point.') }}</p>

                <div x-data="networkPanel" data-quote-url="{{ lroute('quote') }}" data-invalid-coordinates="{{ __('Enter a latitude between -90 and 90 and a longitude between -180 and 180.') }}"
                     @place-selected="onPlace($event.detail)" class="mt-8 space-y-4">
                    <div class="rounded-2xl bg-white p-4 text-ink-900">
                        <x-place-field field="network" :label="__('Search a city')" />
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer font-medium text-slate-600">{{ __('Or enter coordinates') }}</summary>
                            <form @submit.prevent="submitCoordinates()" class="mt-3 grid grid-cols-[1fr_1fr_auto] gap-2">
                                <input x-model="lat" type="number" step="any" min="-90" max="90" class="field" placeholder="{{ __('Latitude') }}" aria-label="{{ __('Latitude') }}">
                                <input x-model="lon" type="number" step="any" min="-180" max="180" class="field" placeholder="{{ __('Longitude') }}" aria-label="{{ __('Longitude') }}">
                                <button class="btn-dark !px-4" type="submit">{{ __('Go') }}</button>
                            </form>
                            <p class="field-error" x-show="error" x-text="error"></p>
                        </details>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur" aria-live="polite">
                        <template x-if="!point">
                            <p class="flex items-center gap-2 text-sm text-slate-300"><x-lucide name="map-pin" class="size-4 text-route-400" /> {{ __('Click the globe to drop a pin.') }}</p>
                        </template>
                        <template x-if="point">
                            <div class="space-y-3 text-sm">
                                <p class="font-display text-lg font-bold text-white"><span x-show="place" x-text="place && place.label"></span><span x-show="!place">{{ __('No city nearby') }}</span></p>
                                <p class="font-mono text-xs text-slate-400"><span x-text="point.lat"></span>, <span x-text="point.lon"></span></p>
                                <div x-show="network" class="flex items-start gap-2"><x-lucide name="truck" class="size-4 shrink-0 text-route-400" /> <span x-text="network && network.label"></span></div>
                                <div x-show="hub" class="flex items-start gap-2"><x-lucide name="warehouse" class="size-4 shrink-0 text-route-400" />
                                    <span>{{ __('Nearest hub') }}: <strong class="text-white" x-text="hub && (hub.name + ' (' + hub.distance_km.toLocaleString() + ' km)')"></strong></span>
                                </div>
                                <a :href="shipUrl()" x-show="place" class="btn-primary mt-2 w-full">{{ __('Ship to this point') }} <x-lucide name="arrow-right" class="size-4" /></a>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div x-data="globe" data-picker="true" data-distance="2.7" class="relative mx-auto aspect-square w-full max-w-[680px]">
                    <div x-ref="canvas" class="absolute inset-0 cursor-grab active:cursor-grabbing"></div>
                    <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center">
                        <div class="relative w-full overflow-hidden rounded-3xl border border-white/10">
                            <img src="/images/world-map.svg" alt="{{ __('Map of our network') }}" class="w-full">
                            <template x-for="hub in hubs" :key="hub.city">
                                <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500" :style="{ left: fallbackLeft(hub), top: fallbackTop(hub) }"></span>
                            </template>
                        </div>
                    </div>
                    <div class="absolute top-2 right-2 flex flex-col gap-1">
                        <button type="button" class="grid size-10 place-items-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20" @click="zoomIn()" aria-label="{{ __('Zoom in') }}"><x-lucide name="plus" class="size-4" /></button>
                        <button type="button" class="grid size-10 place-items-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20" @click="zoomOut()" aria-label="{{ __('Zoom out') }}"><x-lucide name="minus" class="size-4" /></button>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 flex flex-wrap justify-center gap-2" x-show="regions.length">
                        <template x-for="region in regions" :key="region.code">
                            <button type="button" @click="focusRegion(region)" class="rounded-full border border-white/15 bg-ink-900/70 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur hover:border-white/40" x-text="region.label"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container-page py-16">
        <h2 class="text-2xl font-extrabold">{{ __('Our hubs') }}</h2>
        <p class="mt-2 text-slate-600">{{ __('A text list of the locations shown on the globe.') }}</p>
        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($hubs as $hub)
                <li class="card p-5" data-reveal="{{ ($loop->index % 4) * 70 }}">
                    <p class="font-display font-bold text-ink-900">{{ $hub->city }}</p>
                    <p class="text-sm text-slate-500">{{ \App\Support\Geo::countryName($hub->country) }}</p>
                    <p class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($hub->modes ?? [] as $hubMode)
                            <span class="badge bg-surface text-slate-700">{{ __(ucfirst($hubMode)) }}</span>
                        @endforeach
                    </p>
                </li>
            @endforeach
        </ul>
    </section>
@endsection
