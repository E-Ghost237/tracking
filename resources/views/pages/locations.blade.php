@extends('layouts.app', ['title' => __('Pickup and drop-off locations'), 'description' => __('Find a supported collection point or arrange a pickup for your shipment.')])

@php
    // Cameroon locations are operational only; they are not published as customer points.
    $publicLocations = $locations->reject(fn ($items, $country) => $country === 'CM');
    $hubCount = $publicLocations->flatten()->where('type', 'hub')->count();
    $pointCount = $publicLocations->flatten()->where('type', '!=', 'hub')->count();
@endphp

@section('content')
    <x-page-header photo="editorial_hub" :eyebrow="__('Locations')" icon="map-pinned"
                   :title="__('Find a collection point that works for you')"
                   :lead="__('Use a listed hub or partner point when it suits your schedule, or ask for a pickup at your address. Each location shows the services available there and any published opening information.')">
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Plan a shipment') }} <x-lucide name="arrow-right" class="size-4" /></a>
            <a href="{{ lroute('contact') }}" class="btn-light">{{ __('Ask about a pickup') }}</a>
        </div>
    </x-page-header>

    @if ($publicLocations->isNotEmpty())
        <section class="bg-white py-12 lg:py-14">
            <div class="container-page">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div class="max-w-2xl">
                        <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Around the network') }}</h2>
                        <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Availability depends on the destination, shipment size and service selected. If you are unsure whether a point can handle your parcel, include the route in a quote and our team will confirm it.') }}</p>
                    </div>
                    <dl class="flex gap-6">
                        <div>
                            <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Hubs') }}</dt>
                            <dd class="mt-1 text-2xl font-bold text-ink-950 tabular">{{ $hubCount }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Partner points') }}</dt>
                            <dd class="mt-1 text-2xl font-bold text-ink-950 tabular">{{ $pointCount }}</dd>
                        </div>
                    </dl>
                </div>

                @foreach ($publicLocations as $country => $items)
                    <section class="mt-10">
                        <h3 class="flex items-center gap-3 text-lg font-bold">
                            {{ \App\Support\Geo::countryName($country) }}
                            <span class="badge bg-surface text-slate-700">{{ $items->count() }}</span>
                        </h3>

                        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $location)
                                <article class="card flex flex-col p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                                                {{ $location->type === 'hub' ? __('Network hub') : __('Partner point') }}
                                            </p>
                                            <h4 class="mt-1.5 text-base font-bold text-ink-950">{{ $location->name }}</h4>
                                        </div>
                                        <x-lucide name="map-pin" class="mt-0.5 size-5 shrink-0 text-slate-500" />
                                    </div>

                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        {{ $location->line1 }}@if ($location->line1), @endif{{ $location->city }}
                                    </p>

                                    @if (! empty($location->modes))
                                        <p class="mt-3 flex flex-wrap gap-1.5">
                                            @foreach ($location->modes as $mode)
                                                <span class="badge bg-surface text-slate-700">
                                                    {{ ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')][$mode] ?? __(ucfirst($mode)) }}
                                                </span>
                                            @endforeach
                                        </p>
                                    @endif

                                    <div class="mt-4 flex-1 space-y-2 border-t border-line pt-4 text-sm text-slate-600">
                                        @forelse ($location->opening_hours ?? [] as $line)
                                            <p class="flex items-center gap-2">
                                                <x-lucide name="clock" class="size-4 shrink-0 text-slate-500" /> {{ $line }}
                                            </p>
                                        @empty
                                            <p class="flex items-center gap-2">
                                                <x-lucide name="clock" class="size-4 shrink-0 text-slate-500" /> {{ __('Opening hours confirmed on request') }}
                                            </p>
                                        @endforelse
                                        @if ($location->phone)
                                            <p class="flex items-center gap-2">
                                                <x-lucide name="phone" class="size-4 shrink-0 text-slate-500" /> {{ $location->phone }}
                                            </p>
                                        @endif
                                    </div>

                                    {{-- Pre-fills the quote form with this point as the destination --}}
                                    <a href="{{ lroute('quote').'?'.http_build_query([
                                            'to_city' => $location->city,
                                            'to_country' => $location->country,
                                            'to_lat' => $location->lat,
                                            'to_lon' => $location->lon,
                                        ]) }}" class="btn-ghost mt-4 !py-2">
                                        {{ __('Price a shipment to here') }} <x-lucide name="arrow-right" class="size-3.5" />
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
    @else
        <section class="bg-white py-12 lg:py-14">
            <div class="container-page">
                <div class="rounded-[6px] border border-dashed border-line px-6 py-14 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="map-pinned" class="size-6" /></span>
                    <h2 class="mt-4 text-lg font-bold">{{ __('Locations are being confirmed') }}</h2>
                    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ __('Tell us where your parcel needs to go. We will confirm the available collection and delivery options before you book.') }}</p>
                    <a href="{{ lroute('contact') }}" class="btn-primary mt-5">{{ __('Ask our team') }} <x-lucide name="arrow-right" class="size-4" /></a>
                </div>
            </div>
        </section>
    @endif

    {{-- Pickup guidance --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page grid gap-5 md:grid-cols-3">
            @foreach ([
                ['package', __('Drop off at a point'), __('Bring the packed parcel and your booking reference to a listed point. We weigh, scan and take it from there.')],
                ['truck', __('Ask for a collection'), __('For larger or multiple packages, tell us the address and a preferred window in the booking, and we arrange the pickup.')],
                ['file-text', __('Prepare the paperwork'), __('Have the contents description, value and recipient details ready — they are needed for the label and customs documents.')],
            ] as [$icon, $heading, $text])
                <div class="card p-5">
                    <span class="grid size-10 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide :name="$icon" class="size-5" /></span>
                    <h2 class="mt-3.5 text-base font-bold">{{ $heading }}</h2>
                    <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
