@extends('layouts.app', ['title' => __('Rates and transit times'), 'description' => __('Compare sample freight prices and typical delivery windows before requesting a route-specific quote.')])

@php
    $modeLabels = ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road')];
    $modeIcons = ['air' => 'plane', 'sea' => 'ship', 'road' => 'truck'];
    // Published windows come from the active rate card, never from this template.
    $windowLabel = fn (string $mode) => $transit[$mode] ?? __('Confirmed per route');

    // One card per lane so the table has a readable mobile form.
    $lanes = collect($rows)->groupBy(fn ($row) => $row['from'].' → '.$row['to']);
@endphp

@section('content')
    <x-page-header photo="editorial_documents" :eyebrow="__('Price guide')" icon="receipt"
                   :title="__('Know the likely cost before you commit')"
                   :lead="__('Use these sample prices and delivery windows as a starting point. Your own quote is calculated from the route, service and packed dimensions you enter, so you can compare options before placing a booking.')">
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Build a route-specific quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
            <a href="{{ lroute('network') }}" class="btn-light">{{ __('See the corridors we run') }}</a>
        </div>
    </x-page-header>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-2xl">
                    <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Sample lane prices') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Indicative prices for a compact parcel on selected routes, in US dollars. Fuel and handling are included where listed; duties and taxes are shown separately when they apply.') }}</p>
                </div>
                <span class="badge bg-surface text-slate-700">{{ __('Current rate card') }}</span>
            </div>

            @if ($rows === [])
                <div class="mt-7 rounded-[6px] border border-dashed border-line px-6 py-12 text-center">
                    <h3 class="text-lg font-bold">{{ __('Rates are being updated') }}</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-slate-600">{{ __('Use the quote tool for a route-specific price while the public table is refreshed.') }}</p>
                    <a href="{{ lroute('quote') }}" class="btn-primary mt-5">{{ __('Get a quote') }}</a>
                </div>
            @else
                {{-- Desktop and tablet: the full table --}}
                <div class="mt-7 hidden overflow-hidden rounded-[6px] border border-line md:block">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-sm">
                            <caption class="sr-only">{{ __('Indicative prices by lane, service and parcel weight') }}</caption>
                            <thead>
                                <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                    <th scope="col" class="px-5 py-3 font-medium">{{ __('Lane') }}</th>
                                    <th scope="col" class="px-5 py-3 font-medium">{{ __('Service') }}</th>
                                    @foreach ($weights as $kg)
                                        <th scope="col" class="px-5 py-3 text-right font-medium">{{ $kg }} kg</th>
                                    @endforeach
                                    <th scope="col" class="px-5 py-3 text-right font-medium">{{ __('Transit') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($rows as $row)
                                    <tr>
                                        <th scope="row" class="px-5 py-3.5 text-left font-semibold text-ink-950">{{ $row['from'] }} → {{ $row['to'] }}</th>
                                        <td class="px-5 py-3.5">
                                            <span class="inline-flex items-center gap-2 text-slate-700">
                                                <x-lucide :name="$modeIcons[$row['mode']]" class="size-4 text-ink-600" />
                                                {{ $modeLabels[$row['mode']] }}
                                            </span>
                                        </td>
                                        @foreach ($weights as $kg)
                                            <td class="px-5 py-3.5 text-right text-ink-900 tabular">{{ $row['prices'][$kg] ?? '—' }}</td>
                                        @endforeach
                                        <td class="px-5 py-3.5 text-right text-slate-700 tabular">{{ $row['transit'] }} {{ __('days') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Phones: one card per lane, prices listed line by line --}}
                <div class="mt-7 space-y-4 md:hidden">
                    @foreach ($lanes as $lane => $services)
                        <div class="card overflow-hidden">
                            <div class="border-b border-line bg-surface px-4 py-3">
                                <p class="font-bold text-ink-950">{{ $lane }}</p>
                            </div>
                            <ul class="divide-y divide-line">
                                @foreach ($services as $service)
                                    <li class="p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="inline-flex items-center gap-2 text-sm font-semibold text-ink-950">
                                                <x-lucide :name="$modeIcons[$service['mode']]" class="size-4 text-ink-600" />
                                                {{ $modeLabels[$service['mode']] }}
                                            </span>
                                            <span class="text-xs text-slate-600 tabular">{{ $service['transit'] }} {{ __('days') }}</span>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                            @foreach ($weights as $kg)
                                                <div class="rounded-[4px] bg-surface px-2.5 py-2">
                                                    <dt class="text-xs text-slate-600">{{ $kg }} kg</dt>
                                                    <dd class="mt-0.5 font-semibold text-ink-900 tabular">{{ $service['prices'][$kg] ?? '—' }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs leading-5 text-slate-600">{{ __('Prices are shown in USD for a sample parcel measuring 10 × 10 × 10 cm. Insurance, import duties and taxes are calculated separately when they apply.') }}</p>
        </div>
    </section>

    {{-- How a price is built --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">{{ __('How a price is built') }}</h2>
            <div class="mt-7 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['scale', __('Weight and dimensions'), __('Chargeable weight compares the scale weight with the parcel volume (L × W × H ÷ the divisor for the selected service), and the greater value is used.')],
                    ['route', __('Route and service'), __('Origin, destination and the transport mode shape the base rate, the fuel component and the transit window.')],
                    ['receipt', __('Options and duties'), __('Insurance, handling and destination charges are listed separately in the quote, so nothing is hidden in the total.')],
                ] as [$icon, $heading, $text])
                    <div class="card p-5">
                        <span class="grid size-10 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide :name="$icon" class="size-5" /></span>
                        <h3 class="mt-3.5 text-base font-bold">{{ $heading }}</h3>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ $text }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-7 grid gap-5 rounded-[6px] border border-line bg-white p-6 sm:grid-cols-[1.6fr_1fr] sm:items-center sm:p-8">
                <div>
                    <h3 class="text-lg font-bold">{{ __('Transit windows by service') }}</h3>
                    <p class="mt-1.5 text-sm leading-6 text-slate-600">{{ __('Typical door-to-door estimates for the lanes above. Customs processing and local delivery can extend these on individual shipments.') }}</p>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-center">
                    @foreach ($modeLabels as $mode => $label)
                        <div class="rounded-[4px] bg-surface px-3 py-3">
                            <dt class="flex items-center justify-center gap-1.5 text-xs text-slate-600">
                                <x-lucide :name="$modeIcons[$mode]" class="size-3.5" /> {{ $label }}
                            </dt>
                            <dd class="mt-1 text-sm font-bold text-ink-950 whitespace-nowrap">{{ $windowLabel($mode) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-6 rounded-[6px] bg-ink-900 p-7 text-white sm:p-9">
                <div>
                    <h2 class="text-2xl font-bold text-white">{{ __('Want the exact price for your parcel?') }}</h2>
                    <p class="mt-2 max-w-xl text-[15px] leading-7 text-slate-300">{{ __('Enter the route, weight and dimensions to see the price, the transit window and the delivery network for your specific shipment.') }}</p>
                </div>
                <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Build a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>
        </div>
    </section>
@endsection
