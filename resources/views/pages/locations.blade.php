@extends('layouts.app', ['title' => __('Pickup and drop-off locations'), 'description' => __('Find a supported collection point or arrange a pickup for your shipment.')])

@section('content')
    <x-page-header :eyebrow="__('Locations')" icon="map-pinned" :title="__('Find a collection point that works for you')" :lead="__('Use a listed hub or partner point when it suits your schedule, or request a pickup at your door. Each location shows the services available there and any published opening information.')">
        <a href="{{ lroute('quote') }}" class="btn-primary mt-7">{{ __('Plan a shipment') }} <x-lucide name="arrow-right" class="size-4" /></a>
    </x-page-header>

    @php($publicLocations = $locations->reject(fn ($items, $country) => $country === 'CM'))

    <section class="container-page py-12 sm:py-16">
        <div class="mb-8 flex flex-col gap-3 border-b border-line pb-6 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow eyebrow-rule">{{ __('Collection and handoff') }}</p>
                <h2 class="editorial-title mt-4 text-4xl">{{ __('Around the network') }}</h2>
            </div>
            <p class="max-w-xl text-sm leading-6 text-slate-600">{{ __('Availability depends on the destination, shipment size and service selected. If you are unsure whether a point can handle your parcel, include the route in a quote and our team can help you check.') }}</p>
        </div>

        @forelse ($publicLocations as $country => $items)
            <section class="mb-12 last:mb-0">
                <div class="flex items-center gap-3">
                    <span class="h-px w-8 bg-brand-500"></span>
                    <h3 class="font-display text-lg font-bold text-ink-900">{{ \App\Support\Geo::countryName($country) }}</h3>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $location)
                        <article class="card group p-5 transition duration-300 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-[var(--shadow-lift)]" data-reveal="{{ ($loop->index % 3) * 70 }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold tracking-[0.15em] text-slate-500 uppercase">{{ $location->type === 'hub' ? __('Network hub') : __('Partner location') }}</p>
                                    <h4 class="mt-2 font-display text-lg font-bold text-ink-900">{{ $location->name }}</h4>
                                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ $location->line1 }}{{ $location->line1 ? ', ' : '' }}{{ $location->city }}</p>
                                </div>
                                <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white"><x-lucide name="map-pin" class="size-5" /></span>
                            </div>
                            @if ($location->opening_hours)
                                <ul class="mt-5 space-y-2 border-t border-line pt-4 text-sm text-slate-600">
                                    @foreach ($location->opening_hours as $line)
                                        <li class="flex items-center gap-2"><x-lucide name="clock" class="size-4 shrink-0 text-slate-400" /> {{ $line }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($location->phone)
                                <p class="mt-3 flex items-center gap-2 text-sm text-slate-600"><x-lucide name="phone" class="size-4 text-slate-400" /> {{ $location->phone }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-line bg-white px-6 py-12 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-lucide name="map-pinned" class="size-6" /></span>
                <h3 class="mt-4 font-display text-lg font-bold text-ink-900">{{ __('Locations are being confirmed') }}</h3>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ __('Tell us where your parcel needs to go. We will confirm the available collection and delivery options before you book.') }}</p>
                <a href="{{ lroute('contact') }}" class="btn-ghost mt-5">{{ __('Ask our team') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>
        @endforelse
    </section>
@endsection
