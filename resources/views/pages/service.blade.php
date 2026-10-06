@php
    $content = [
        'air' => ['plane', __('Air freight'), __('Fast, reliable air cargo between Africa, Europe and North America.'), __('We consolidate shipments at our hubs and fly them on scheduled airline capacity. On arrival, we clear customs and hand over to the last-mile network of the destination country.')],
        'sea' => ['ship', __('Sea freight'), __('Economical ocean shipping for heavy and bulky goods.'), __('Your goods share container space with other shipments (LCL). We handle loading, export and import formalities, and final delivery from the port of arrival.')],
        'road' => ['truck', __('Road freight'), __('Door-to-door trucking on the same continent.'), __('Road freight is offered when origin and destination are on the same landmass and within our road range. For longer routes, the quote tool suggests air or sea.')],
        'express' => ['zap', __('Express'), __('Priority air service for urgent shipments.'), __('Express shipments get priority at every step: first available flight, priority customs handling and a faster last mile.')],
    ][$code];
@endphp
@extends('layouts.app', ['title' => $content[1], 'description' => $content[2]])

@section('content')
    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="pointer-events-none absolute inset-0 grid-bg"></div>
        <div class="pointer-events-none absolute -right-20 -bottom-20 text-white/[0.04]"><x-lucide :name="$content[0]" class="size-[420px]" /></div>
        <div class="container-page relative py-20">
            <a href="{{ lroute('services') }}" class="inline-flex items-center gap-1 text-sm text-slate-400 hover:text-white"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('All services') }}</a>
            <span class="mt-6 grid size-14 place-items-center rounded-2xl bg-brand-500 text-white"><x-lucide :name="$content[0]" class="size-7" /></span>
            <h1 class="mt-6 text-4xl font-extrabold !text-white sm:text-5xl">{{ $content[1] }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-slate-300">{{ $content[2] }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-primary">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                <a href="{{ lroute('rates') }}" class="btn-light">{{ __('See rates') }}</a>
            </div>
        </div>
    </section>
    <div class="container-page grid gap-12 py-16 lg:grid-cols-3">
        <div class="prose-content lg:col-span-2">
            <h2>{{ __('How it works') }}</h2>
            <p>{{ $content[3] }}</p>
            <h2>{{ __('What you need') }}</h2>
            <ul>
                <li>{{ __('Sender and recipient details with a phone number') }}</li>
                <li>{{ __('A description and value of the contents for customs') }}</li>
                <li>{{ __('Goods packed according to our packing guide') }}</li>
            </ul>
            <p><a href="{{ lroute('page.prohibited-items') }}">{{ __('Check the prohibited items list') }}</a> · <a href="{{ lroute('page.packing') }}">{{ __('Packing guide') }}</a> · <a href="{{ lroute('page.customs') }}">{{ __('Customs guide') }}</a></p>
        </div>
        <aside class="card h-fit p-6">
            <h2 class="text-lg font-bold">{{ __('Price factors') }}</h2>
            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                <li class="flex gap-2"><x-lucide name="scale" class="size-4 shrink-0 text-brand-500" /> {{ __('Chargeable weight: the larger of actual and volumetric weight') }}</li>
                <li class="flex gap-2"><x-lucide name="route" class="size-4 shrink-0 text-brand-500" /> {{ __('Origin and destination zones') }}</li>
                <li class="flex gap-2"><x-lucide name="shield-check" class="size-4 shrink-0 text-brand-500" /> {{ __('Optional insurance on the declared value') }}</li>
                <li class="flex gap-2"><x-lucide name="receipt" class="size-4 shrink-0 text-brand-500" /> {{ __('Fuel and handling surcharges') }}</li>
            </ul>
            <p class="mt-6 rounded-xl bg-surface p-3 text-xs text-slate-500">{{ __('Mode multiplier') }}: × {{ rtrim(rtrim(number_format($mode->multiplier, 2), '0'), '.') }}</p>
        </aside>
    </div>
@endsection
