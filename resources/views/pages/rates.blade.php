@extends('layouts.app', ['title' => __('Rates and transit times'), 'description' => __('Indicative freight prices and typical delivery times on our main lanes.')])

@section('content')
    <x-page-header :eyebrow="__('Price guide')" icon="receipt" :title="__('Rates and transit times')" :lead="__('Indicative door-to-door prices for one parcel on our main lanes, from the current rate card. Your exact price depends on dimensions and options.')">
        <a href="{{ lroute('quote') }}" class="btn-primary mt-8">{{ __('Get an exact quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
    </x-page-header>

    <div class="container-page py-14">
        <div class="card overflow-x-auto">
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
                    @forelse ($rows as $row)
                        <tr class="transition hover:bg-surface/60">
                            <td class="px-5 py-4 font-semibold text-ink-900">{{ $row['from'] }} → {{ $row['to'] }}</td>
                            <td class="px-5 py-4"><span class="inline-flex items-center gap-1.5 text-slate-700"><x-lucide :name="['air' => 'plane', 'sea' => 'ship', 'road' => 'truck'][$row['mode']]" class="size-4 text-brand-500" /> {{ ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road')][$row['mode']] }}</span></td>
                            @foreach ($weights as $kg)
                                <td class="px-5 py-4 text-right font-medium tabular-nums text-ink-900">{{ $row['prices'][$kg] ?? '—' }}</td>
                            @endforeach
                            <td class="px-5 py-4 text-right tabular-nums text-slate-600">{{ $row['transit'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($weights) + 3 }}" class="px-5 py-10 text-center text-slate-500">{{ __('Rates are being updated. Please use the quote tool.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-xs text-slate-500">{{ __('Prices in USD, including fuel and handling surcharges, excluding insurance, duties and taxes. Sample parcel 10 × 10 × 10 cm.') }}</p>
    </div>
@endsection
