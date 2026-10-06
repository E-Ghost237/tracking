@extends('layouts.app', ['title' => __('Network and coverage'), 'description' => __('Explore supported shipping routes, local delivery connections and nearby hubs on an interactive globe.')])

@php
    $regions = [
        ['code' => 'us', 'label' => __('United States'), 'network' => __('USPS and UPS last-mile delivery'), 'lat' => 39.5, 'lon' => -98.35],
        ['code' => 'europe', 'label' => __('Europe'), 'network' => __('FedEx last-mile delivery'), 'lat' => 50.1, 'lon' => 9.7],
        ['code' => 'africa', 'label' => __('Africa'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 4.0, 'lon' => 15.0],
        ['code' => 'asia', 'label' => __('Asia and Middle East'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 25.0, 'lon' => 70.0],
        ['code' => 'americas', 'label' => __('Canada and Latin America'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 10.0, 'lon' => -70.0],
    ];

    $lanes = [
        ['Houston', 'Paris', 'air'],
        ['New York', 'London', 'air'],
        ['Paris', 'Brussels', 'road'],
        ['Lagos', 'London', 'air'],
        ['Guangzhou', 'Lagos', 'sea'],
        ['Dubai', 'Paris', 'air'],
    ];

    $modeLabels = ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')];
@endphp

@section('content')
    <x-page-header photo="editorial_hub" :eyebrow="__('Network and coverage')" icon="globe"
                   :title="__('A clearer picture of the journey')"
                   :lead="__('Explore the corridors we run, look up a city or drop a pin on the globe. We will show the delivery option available for that point and how far it is from a network hub, so you can plan the next step with more confidence.')" />

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page grid gap-10 lg:grid-cols-[1fr_1.35fr] lg:gap-12">

            {{-- Panel: search, coordinates and the coverage result --}}
            <div x-data="networkPanel" data-quote-url="{{ lroute('quote') }}"
                 data-invalid-coordinates="{{ __('Enter a latitude between -90 and 90 and a longitude between -180 and 180.') }}"
                 @place-selected="onPlace($event.detail)" class="space-y-5">
                <div class="card p-5">
                    <h2 class="text-base font-bold">{{ __('Check coverage for a place') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Search a city to see the delivery option that applies and the nearest hub.') }}</p>
                    <div class="mt-4">
                        <x-place-field field="network" :label="__('City or postcode')" />
                    </div>

                    <details class="mt-4 border-t border-line pt-4">
                        <summary class="cursor-pointer text-sm font-medium text-slate-600 hover:text-ink-900">{{ __('Search by coordinates instead') }}</summary>
                        <form @submit.prevent="submitCoordinates()" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <div>
                                <label for="net-lat" class="field-label">{{ __('Latitude') }}</label>
                                <input id="net-lat" x-model="lat" type="number" step="any" min="-90" max="90" class="field" placeholder="29.7604">
                            </div>
                            <div>
                                <label for="net-lon" class="field-label">{{ __('Longitude') }}</label>
                                <input id="net-lon" x-model="lon" type="number" step="any" min="-180" max="180" class="field" placeholder="-95.3698">
                            </div>
                            <button type="submit" class="btn-dark self-end !py-2.5 sm:col-span-1">{{ __('Check') }}</button>
                        </form>
                        <p class="field-error" x-show="error" x-cloak x-text="error"></p>
                    </details>
                </div>

                <div class="card p-5" aria-live="polite">
                    <template x-if="!point">
                        <div class="flex items-start gap-3 text-sm">
                            <x-lucide name="map-pin" class="mt-0.5 size-5 shrink-0 text-slate-500" />
                            <p class="text-slate-600">{{ __('Choose a point on the globe, or search a city above. Coverage, the nearest hub and a route to booking appear here.') }}</p>
                        </div>
                    </template>
                    <template x-if="point">
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Selected point') }}</p>
                                <p class="mt-1 text-base font-bold text-ink-950">
                                    <span x-show="place" x-text="place && place.label"></span>
                                    <span x-show="!place">{{ __('No city nearby') }}</span>
                                </p>
                                <p class="mt-0.5 font-mono text-xs text-slate-600"><span x-text="point.lat"></span>, <span x-text="point.lon"></span></p>
                            </div>

                            <dl class="divide-y divide-line border-y border-line text-sm">
                                <div class="flex items-start justify-between gap-4 py-3" x-show="network">
                                    <dt class="text-slate-600">{{ __('Local delivery') }}</dt>
                                    <dd class="text-right font-medium text-ink-900" x-text="network && network.label"></dd>
                                </div>
                                <div class="flex items-start justify-between gap-4 py-3" x-show="hub">
                                    <dt class="text-slate-600">{{ __('Nearest hub') }}</dt>
                                    <dd class="text-right font-medium text-ink-900">
                                        <span x-text="hub && hub.city"></span> ·
                                        <span class="tabular" x-text="hub && hub.distance_km.toLocaleString()"></span> km
                                    </dd>
                                </div>
                            </dl>

                            <a :href="shipUrl()" x-show="place" class="btn-primary w-full">{{ __('Plan a shipment from here') }} <x-lucide name="arrow-right" class="size-4" /></a>
                        </div>
                    </template>
                </div>

                <div class="rounded-[6px] border border-line bg-surface p-4 text-xs leading-5 text-slate-600">
                    <p class="flex items-start gap-2">
                        <x-lucide name="info" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                        {{ __('Coverage shown here is indicative. We confirm the available service and the local delivery partner for your route before you book.') }}
                    </p>
                </div>
            </div>

            {{-- Globe: fitted so the atmosphere is never clipped by the frame --}}
            <div class="min-w-0">
                <div class="rounded-[6px] bg-ink-950 p-3 sm:p-4">
                    <div x-data="globe" data-picker="true" data-distance="3.1" class="relative aspect-square w-full sm:aspect-[4/3]">
                        {{-- The 3D canvas is a visual aid: the corridors, hubs and pins it shows are
         all readable as text around it (tables, region buttons, the address form),
         so it is hidden from assistive technology rather than half-described. --}}
                        <div x-ref="canvas" class="absolute inset-0 cursor-grab active:cursor-grabbing" aria-hidden="true"></div>

                        {{-- WebGL unavailable: a flat map with the same hub data --}}
                        <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center p-4">
                            <div class="relative w-full">
                                <img src="{{ asset('images/world-map.svg') }}" alt="{{ __('World map of supported routes') }}" class="w-full rounded-[4px]">
                                <template x-for="hub in hubs" :key="hub.city">
                                    <span class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500 ring-2 ring-brand-500/30" :style="{ left: fallbackLeft(hub), top: fallbackTop(hub) }"></span>
                                </template>
                            </div>
                        </div>

                        <div x-show="!ready && !fallback" class="absolute inset-0 grid place-items-center">
                            <span class="text-sm text-slate-300">{{ __('Loading the network…') }}</span>
                        </div>
                        {{-- Screen readers get the same facts the canvas draws. --}}
                        <p class="sr-only">{{ __('An interactive globe showing the hubs and corridors in the table below. Use the region buttons under the globe to move between them.') }}</p>

                        {{-- Controls: one toolbar instead of floating buttons --}}
                        <div class="absolute right-3 bottom-3 flex items-center gap-1.5">
                            <button type="button" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" @click="zoomIn()" aria-label="{{ __('Zoom in') }}"><x-lucide name="plus" class="size-4" /></button>
                            <button type="button" class="grid size-9 place-items-center rounded-[4px] border border-white/15 bg-ink-900/80 text-white transition hover:bg-ink-800" @click="zoomOut()" aria-label="{{ __('Zoom out') }}"><x-lucide name="minus" class="size-4" /></button>
                        </div>
                    </div>

                    {{-- Region shortcuts sit below the globe, never over it --}}
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-white/10 pt-3" role="group" aria-label="{{ __('Jump to a region') }}">
                        @foreach ($regions as $region)
                            <button type="button" class="rounded-[4px] border border-white/15 px-3 py-1.5 text-xs font-semibold text-slate-200 transition hover:border-white/40 hover:text-white"
                                    @click="focusRegion({{ Js::from($region) }})">{{ $region['label'] }}</button>
                        @endforeach
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-600">{{ __('Drag to rotate. Select a point to check coverage for that location.') }}</p>
            </div>
        </div>
    </section>

    {{-- Sample corridors --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Corridors we run today') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('These are the lanes our team works with most often. Others are quoted case by case with a confirmed delivery partner before you book.') }}</p>
                </div>
                <a href="{{ lroute('rates') }}" class="btn-ghost">{{ __('See rates and transit times') }}</a>
            </div>

            <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('Sample corridors with the service used and typical transit time') }}</caption>
                    <thead>
                        <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                            <th scope="col" class="px-5 py-3 font-medium">{{ __('Lane') }}</th>
                            <th scope="col" class="px-5 py-3 font-medium">{{ __('Service') }}</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">{{ __('Typical transit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($lanes as [$from, $to, $mode])
                            <tr>
                                <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{{ __($from) }} → {{ __($to) }}</th>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-2 text-slate-700">
                                        <x-lucide :name="['air' => 'plane', 'sea' => 'ship', 'road' => 'truck'][$mode]" class="size-4 text-ink-600" />
                                        {{ $modeLabels[$mode] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right tabular text-slate-700">
                                    {{ $transit[$mode] ?? __('Confirmed per route') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-slate-600">{{ __('Transit times are typical door-to-door estimates, not guarantees.') }}</p>
        </div>
    </section>

    {{-- Regional coverage --}}
    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Who completes the last mile') }}</h2>
            <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">{{ __('Long-distance legs are coordinated by our team. The final delivery is completed by a carrier or partner with a local presence, so your tracking stays on one journey.') }}</p>

            <div class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($regions as $region)
                    <div class="bg-white p-5">
                        <h3 class="text-sm font-bold">{{ $region['label'] }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $region['network'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Hubs --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Hubs and handoff points') }}</h2>
                    <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">{{ __('Freight is consolidated and checked at these locations before the long-distance leg. Service availability can vary by route, so confirm the options for your shipment in a quote.') }}</p>
                </div>
                <a href="{{ lroute('locations') }}" class="btn-ghost">{{ __('All locations') }}</a>
            </div>

            @if ($hubs->isNotEmpty())
                <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Network hubs with their city, country and available services') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Hub') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('City') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Country') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Services') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($hubs as $hub)
                                <tr>
                                    <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{{ $hub->name }}</th>
                                    <td class="px-5 py-3.5 text-slate-700">{{ $hub->city }}</td>
                                    <td class="px-5 py-3.5 text-slate-700">{{ \App\Support\Geo::countryName($hub->country) }}</td>
                                    <td class="px-5 py-3.5">
                                        <span class="flex flex-wrap gap-1.5">
                                            @foreach ($hub->modes ?? [] as $hubMode)
                                                <span class="badge bg-surface text-slate-700">{{ $modeLabels[$hubMode] ?? __(ucfirst($hubMode)) }}</span>
                                            @endforeach
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    {{-- Closing call to action --}}
    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-6 rounded-[6px] bg-ink-900 p-7 text-white sm:p-9">
                <div>
                    <h2 class="text-2xl font-bold text-white">{{ __('Planning a shipment on one of these lanes?') }}</h2>
                    <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-300">{{ __('Enter the route, weight and packed size to see the price and the delivery window before you commit.') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Build a quote') }}</a>
                    <a href="{{ lroute('contact') }}" class="btn-light">{{ __('Ask about a route') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
