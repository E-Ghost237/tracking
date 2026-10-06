@extends('layouts.app', ['title' => __('Rates and transit times'), 'description' => __('Compare sample freight prices and typical delivery windows before requesting a route-specific quote.')])

@section('content')
    @php
        $featuredLanes = ['Lagos → London', 'New York → Abidjan', 'Paris → Brussels'];
        $publicRows = collect($rows)->filter(fn ($row) => in_array($row['from'].' → '.$row['to'], $featuredLanes, true))->values();
    @endphp

    <x-page-header :eyebrow="__('Price guide')" icon="receipt" :title="__('Know the likely cost before you commit.')" :lead="__('Use these sample prices and delivery windows as a starting point. Your own quote is calculated from the route, service and packed dimensions you enter, so you can compare options before placing a booking.')">
        <a href="{{ lroute('quote') }}" class="btn-primary mt-7">{{ __('Build a route-specific quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
    </x-page-header>

    <div class="container-page py-12 sm:py-16">
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['scale', __('Weight and dimensions'), __('We use chargeable weight, which compares the scale weight with the parcel’s volume.')],
                ['route', __('Route and service'), __('The origin, destination and transport mode all shape the final estimate.')],
                ['receipt', __('Clear before booking'), __('Review the estimate and included options before you decide how to proceed.')],
            ] as [$icon, $heading, $text])
                <div class="flex gap-3 rounded-xl border border-line bg-white p-4" data-reveal="{{ $loop->index * 70 }}">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-lucide :name="$icon" class="size-5" /></span>
                    <div><h2 class="font-display text-sm font-bold text-ink-900">{{ $heading }}</h2><p class="mt-1 text-xs leading-5 text-slate-600">{{ $text }}</p></div>
                </div>
            @endforeach
        </div>
        <div class="card overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-line bg-surface px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div><h2 class="font-display font-bold text-ink-900">{{ __('Sample lane prices') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('Illustrative rates for a compact parcel on selected routes') }}</p></div>
                <span class="badge w-fit bg-white text-slate-600 ring-1 ring-line">{{ __('USD') }} · {{ __('Current rate card') }}</span>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <caption class="sr-only">{{ __('Indicative prices by lane, mode and weight') }}</caption>
                <thead>
                    <tr class="border-b border-line bg-surface text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">
                        <th scope="col" class="px-5 py-4">{{ __('Lane') }}</th>
                        <th scope="col" class="px-5 py-4">{{ __('Mode') }}</th>
                        @foreach ($weights as $kg)
                            <th scope="col" class="px-5 py-4 text-right">{{ $kg }} kg</th>
                        @endforeach
                        <th scope="col" class="px-5 py-4 text-right">{{ __('Days') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($publicRows as $row)
                        <tr class="transition hover:bg-surface/60">
                            <td class="px-5 py-4 font-semibold text-ink-900">{{ $row['from'] }} → {{ $row['to'] }}</td>
                            <td class="px-5 py-4"><span class="inline-flex items-center gap-1.5 text-slate-700"><x-lucide :name="['air' => 'plane', 'sea' => 'ship', 'road' => 'truck'][$row['mode']]" class="size-4 text-brand-500" /> {{ ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road')][$row['mode']] }}</span></td>
                            @foreach ($weights as $kg)
                                <td class="px-5 py-4 text-right font-medium tabular-nums text-ink-900">{{ $row['prices'][$kg] ?? __('Not available') }}</td>
                            @endforeach
                            <td class="px-5 py-4 text-right tabular-nums text-slate-600">{{ $row['transit'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($weights) + 3 }}" class="px-5 py-10 text-center text-slate-500">{{ __('Rates are being updated. Please use the quote tool.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
        <p class="mt-4 text-xs leading-5 text-slate-500">{{ __('Prices are shown in USD for a sample parcel measuring 10 × 10 × 10 cm. Fuel and handling are included where listed. Insurance, import duties and taxes are shown separately when they apply.') }}</p>
    </div>
@endsection
