@extends('layouts.app', ['title' => __('Services'), 'description' => __('Compare air, sea, road and express freight, then choose the service that fits your shipment.')])

@section('content')
    <x-page-header :eyebrow="__('Our services')" icon="boxes" :title="__('The right route for what you are sending')" :lead="__('Every shipment has its own balance of urgency, size and budget. Compare the ways we move goods, see the usual transit window and choose a service that suits the job.')" />

    @php
        $details = [
            'air' => [
                'plane', __('Air freight'), __('A considered option for parcels, business samples and stock that should arrive sooner. We organise scheduled air capacity and coordinate the customs and local delivery steps that follow.'),
                'images/freight-air.jpg', __('Cargo aircraft lifting from an international airport at dusk'), __('3–7 days door to door'),
                [__('Useful when delivery time matters'), __('A clear route through customs and handoff'), __('Tracking from collection to delivery')],
            ],
            'sea' => [
                'ship', __('Sea freight'), __('Move larger or heavier goods without paying air-freight rates. Shared container space is a practical fit for furniture, equipment and planned stock replenishment.'),
                'images/freight-sea.jpg', __('Container ship nearing a port at sunrise'), __('25–45 days port to door'),
                [__('Shared container space for eligible cargo'), __('Lower cost per kilogram on larger loads'), __('Consolidation before the vessel departs')],
            ],
            'road' => [
                'truck', __('Road freight'), __('For eligible regional journeys on land, road service offers a direct and flexible way to move parcels or pallets between collection and delivery points.'),
                'images/freight-road.jpg', __('Long-haul freight truck on a highway at dusk'), __('2–10 days on supported routes'),
                [__('Suitable for parcels and palletised goods'), __('Flexible collection and delivery options'), __('Available on established land corridors')],
            ],
            'express' => [
                'zap', __('Express'), __('When a delivery date is driving the decision, express gives eligible small shipments priority handling and a place on the next available flight.'),
                'images/freight-express.jpg', __('Priority air cargo being loaded at an airport at dawn'), __('2–4 days on supported routes'),
                [__('Priority handling at key handoffs'), __('A good fit for documents and urgent items'), __('Customs coordination and last-mile delivery')],
            ],
        ];
    @endphp

    <section class="container-page py-12 sm:py-16">
        <div class="mb-8 flex flex-col gap-4 border-b border-line pb-7 sm:flex-row sm:items-end sm:justify-between" data-reveal>
            <p class="max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">{{ __('Transit times are typical estimates, not guarantees. The final quote reflects your route, shipment dimensions and any service options you select.') }}</p>
            <p class="shrink-0 text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">{{ __('Air / Ocean / Ground') }}</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($details as $code => [$icon, $name, $text, $image, $alt, $transit, $points])
                <article class="group card card-hover flex flex-col overflow-hidden bg-white" data-reveal="{{ $loop->index * 90 }}">
                    <div class="service-photo relative aspect-[1.9] overflow-hidden bg-ink-900">
                        <img src="/{{ $image }}" alt="{{ $alt }}" class="size-full object-cover" loading="lazy" width="1376" height="768">
                        <span class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-md bg-ink-950/80 px-3 py-2 text-[11px] font-semibold tracking-[0.14em] text-white uppercase backdrop-blur-sm">
                            <x-lucide :name="$icon" class="size-4 text-brand-300" /> {{ sprintf('%02d', $loop->iteration) }} / {{ $name }}
                        </span>
                    </div>
                    <div class="grid flex-1 gap-8 p-6 sm:grid-cols-[1fr_auto] sm:p-8">
                        <div>
                            <h2 class="font-display text-2xl font-bold">{{ $name }}</h2>
                            <p class="mt-3 max-w-xl text-sm leading-7 text-slate-600">{{ $text }}</p>
                            <ul class="mt-5 space-y-2 text-sm text-ink-900">
                                @foreach ($points as $point)
                                    <li class="flex items-start gap-2.5"><x-lucide name="check" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ $point }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="flex flex-row items-center justify-between gap-4 border-t border-line pt-5 sm:min-w-36 sm:flex-col sm:items-end sm:justify-between sm:border-t-0 sm:border-l sm:pt-0 sm:pl-6">
                            <div class="sm:text-right">
                                <p class="text-[10px] font-semibold tracking-[0.14em] text-slate-500 uppercase">{{ __('Typical transit') }}</p>
                                <p class="mt-1 font-display text-lg font-bold text-ink-900">{{ $transit }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 sm:flex-col">
                                <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$code)]) }}" class="btn-dark !px-4 !py-2.5">{{ __('Service details') }} <x-lucide name="arrow-right" class="size-4" /></a>
                                <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-ghost !px-4 !py-2.5">{{ __('Get a quote') }}</a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="container-page pb-20 sm:pb-24">
        <div class="relative grid gap-6 overflow-hidden rounded-2xl bg-ink-900 px-6 py-9 text-white sm:grid-cols-[1fr_auto] sm:items-center sm:px-10">
            <div class="pointer-events-none absolute right-0 top-0 h-full w-1/2 opacity-20" aria-hidden="true">
                <img src="/images/freight-road.jpg" alt="" class="size-full object-cover object-center">
            </div>
            <div class="relative max-w-2xl">
                <p class="eyebrow !text-brand-300">{{ __('Not sure which one to choose?') }}</p>
                <h2 class="editorial-title mt-3 text-3xl !text-white sm:text-4xl">{{ __('Start with your shipment, not a shipping label.') }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-300">{{ __('Share the route, weight and dimensions. The quote tool helps you compare an available service and its estimated delivery window before you commit.') }}</p>
            </div>
            <a href="{{ lroute('quote') }}" class="btn-primary relative">{{ __('Build a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
        </div>
    </section>
@endsection
