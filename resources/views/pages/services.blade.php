@extends('layouts.app', ['title' => __('Services'), 'description' => __('Air, sea, road and express freight services.')])

@section('content')
    <x-page-header :eyebrow="__('Services')" icon="boxes" :title="__('Freight for every size, budget and deadline')" :lead="__('Consolidated air and sea freight, road transport across continents and express delivery, with door-to-door options.')" />

    @php
        $details = [
            'air' => ['plane', __('Air freight'), __('Daily consolidations from our hubs to Europe and North America. Best for parcels from 1 to 500 kg.'), [__('3–7 days door to door'), __('Customs clearance included'), __('Tracking from pickup to delivery')]],
            'sea' => ['ship', __('Sea freight'), __('Shared (LCL) container space for heavy and bulky goods: furniture, vehicles parts, stock.'), [__('25–45 days port to door'), __('Lowest cost per kilogram'), __('Warehouse consolidation')]],
            'road' => ['truck', __('Road freight'), __('Regional road transport within Europe, North America and across West and Central Africa.'), [__('2–10 days'), __('Pallets and parcels'), __('Pickup at your door')]],
            'express' => ['zap', __('Express'), __('Priority handling on the next available flight with last-mile delivery by our carrier partners.'), [__('2–4 days'), __('Priority customs handling'), __('Ideal for documents and urgent goods')]],
        ];
    @endphp

    <div class="container-page grid gap-6 py-16 md:grid-cols-2">
        @foreach ($details as $code => [$icon, $name, $text, $points])
            <article class="group card card-hover relative overflow-hidden p-8" data-reveal="{{ $loop->index * 90 }}">
                <div class="pointer-events-none absolute -top-10 -right-10 text-ink-900/[0.04] transition group-hover:text-brand-500/10"><x-lucide :name="$icon" class="size-56" /></div>
                <span class="grid size-14 place-items-center rounded-2xl bg-ink-900 text-white transition group-hover:bg-brand-500"><x-lucide :name="$icon" class="size-7" /></span>
                <h2 class="mt-6 text-2xl font-bold">{{ $name }}</h2>
                <p class="mt-3 text-slate-600">{{ $text }}</p>
                <ul class="mt-6 space-y-2 text-sm">
                    @foreach ($points as $point)
                        <li class="flex items-center gap-2 text-ink-900"><x-lucide name="circle-check" class="size-4 text-emerald-500" /> {{ $point }}</li>
                    @endforeach
                </ul>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$code)]) }}" class="btn-dark">{{ __('Learn more') }}</a>
                    <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-ghost">{{ __('Get a quote') }}</a>
                </div>
            </article>
        @endforeach
    </div>
@endsection
