@extends('layouts.app', ['title' => __('Services'), 'description' => __('Compare air, sea, road and express freight, then choose the service that fits your shipment.')])

@php
    // Photographs and narrative per service; every figure shown (transit window,
    // rate factor, volumetric divisor) comes from the database, never from here.
    $divisor = fn (string $code) => (int) ($modes->firstWhere('code', $code)?->volumetric_divisor ?? 0);
    $services = [
        'air' => [
            'photo' => 'service_air', 'icon' => 'plane', 'name' => __('Air freight'),
            'summary' => __('Scheduled flights for parcels, business samples and stock that should arrive sooner.'),
            'fit' => __('Parcels, documents and time-sensitive stock'),
            'limits' => __('Charged on chargeable weight: the greater of the scale weight and the parcel volume.'),
            'volume' => $divisor('air')
                ? __('Volume is divided by :divisor to give volumetric weight.', ['divisor' => number_format($divisor('air'))])
                : null,
            'included' => [
                __('Export documentation prepared with you'),
                __('Customs coordination at both ends'),
                __('Local delivery by an established carrier'),
            ],
        ],
        'sea' => [
            'photo' => 'service_sea', 'icon' => 'ship', 'name' => __('Sea freight'),
            'summary' => __('Shared container space for furniture, equipment and planned stock replenishment.'),
            'fit' => __('Furniture, equipment and bulk stock'),
            'limits' => __('Bulky, low-density cargo is charged on volume rather than scale weight.'),
            'volume' => $divisor('sea')
                ? __('Volume is divided by :divisor, so volume counts more heavily than on air.', ['divisor' => number_format($divisor('sea'))])
                : null,
            'included' => [
                __('Consolidation before the vessel departs'),
                __('Import and export documentation'),
                __('A lower cost per kilogram on larger loads'),
            ],
        ],
        'road' => [
            'photo' => 'service_road', 'icon' => 'truck', 'name' => __('Road freight'),
            'summary' => __('Direct collection and delivery on supported land corridors.'),
            'fit' => __('Regional parcels and palletised freight'),
            'limits' => __('Only when origin and destination are on the same connected land area.'),
            'volume' => $divisor('road')
                ? __('Volume is divided by :divisor for this service.', ['divisor' => number_format($divisor('road'))])
                : null,
            'included' => [
                __('Collection from your address'),
                __('Direct delivery without a terminal handoff'),
                __('Route checked before your booking is confirmed'),
            ],
        ],
        'express' => [
            'photo' => 'service_express', 'icon' => 'zap', 'name' => __('Express'),
            'summary' => __('Priority handling for smaller shipments when a date is driving the decision.'),
            'fit' => __('Documents and smaller urgent goods'),
            'limits' => __('Availability is confirmed per route before the booking is accepted.'),
            'volume' => $divisor('express') === $divisor('air')
                ? __('Chargeable weight applies in the same way as air freight.')
                : ($divisor('express') ? __('Volume is divided by :divisor for this service.', ['divisor' => number_format($divisor('express'))]) : null),
            'included' => [
                __('Priority handling at key handoffs'),
                __('Customs coordination and local delivery'),
                __('Confirmed availability before you pay'),
            ],
        ],
    ];

    // Published windows come from the active rate card via TransitWindows.
    $window = fn (string $code) => $transit[$code] ?? __('Confirmed per route');
@endphp

@section('content')
    <x-page-header photo="service_air" :eyebrow="__('Our services')" icon="boxes"
                   :title="__('The right route for what you are sending')"
                   :lead="__('Every shipment has its own balance of urgency, size and budget. Compare the ways we move goods, see the usual transit window and choose a service that suits the job.')">
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ lroute('quote') }}" class="btn-primary">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
            <a href="{{ lroute('rates') }}" class="btn-light">{{ __('See rates and transit times') }}</a>
        </div>
    </x-page-header>

    {{-- Service cards --}}
    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($services as $code => $service)
                    <article class="card card-hover flex flex-col overflow-hidden">
                        <x-photo :key="$service['photo']" class="h-52 w-full object-cover" sizes="(min-width: 640px) 50vw, 100vw" />
                        <div class="flex flex-1 flex-col p-6">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <h2 class="flex items-center gap-2.5 text-xl font-bold">
                                    <x-lucide :name="$service['icon']" class="size-5 text-ink-700" />
                                    {{ $service['name'] }}
                                </h2>
                                <span class="badge bg-surface text-slate-700">{{ $window($code) }}</span>
                            </div>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $service['summary'] }}</p>

                            <dl class="mt-5 space-y-3 border-t border-line pt-4 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('A good fit for') }}</dt>
                                    <dd class="mt-1 text-ink-900">{{ $service['fit'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('How it is charged') }}</dt>
                                    <dd class="mt-1 text-ink-900">{{ $service['limits'] }}</dd>
                                </div>
                            </dl>

                            <ul class="mt-5 space-y-2 text-sm text-slate-700">
                                @foreach ($service['included'] as $point)
                                    <li class="flex items-start gap-2.5">
                                        <x-lucide name="check" class="mt-0.5 size-4 shrink-0 text-emerald-700" />
                                        {{ $point }}
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-6 flex flex-wrap gap-3 border-t border-line pt-5">
                                <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$code)]) }}" class="btn-dark !py-2">{{ __('Service details') }}</a>
                                <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-ghost !py-2">{{ __('Get a quote') }}</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="mt-5 text-xs leading-5 text-slate-600">{{ __('Transit windows are the ranges published on our current rate card for each service. Your own quote shows the window for your exact route.') }}</p>
        </div>
    </section>

    {{-- Comparison table --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page">
            <h2 class="text-2xl font-bold sm:text-3xl">{{ __('Compare at a glance') }}</h2>
            <p class="mt-2.5 max-w-2xl text-[15px] leading-7 text-slate-600">{{ __('Transit windows are typical door-to-door estimates for the lanes we publish. The final quote reflects your route, dimensions and any options you select.') }}</p>

            <div class="mt-7 overflow-hidden rounded-[6px] border border-line bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <caption class="sr-only">{{ __('Comparison of services by transit time, best fit, how they are charged and quote') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Service') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Published transit') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Best for') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('How it is charged') }}</th>
                                <th scope="col" class="px-5 py-3 font-medium">{{ __('Quote') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($services as $code => $service)
                                <tr>
                                    <th scope="row" class="px-5 py-4 text-left font-semibold text-ink-950">
                                        <span class="flex items-center gap-2">
                                            <x-lucide :name="$service['icon']" class="size-4 text-ink-600" />
                                            {{ $service['name'] }}
                                        </span>
                                    </th>
                                    <td class="px-5 py-4 text-slate-700 tabular">{{ $window($code) }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $service['fit'] }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $service['volume'] ?? '—' }}</td>
                                    <td class="px-5 py-4"><a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-link">{{ __('Quote this') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- Support band --}}
    <section class="bg-white py-12 lg:py-14">
        <div class="container-page">
            <div class="grid gap-8 rounded-[6px] border border-line p-7 sm:p-9 lg:grid-cols-[1.5fr_1fr] lg:items-center">
                <div>
                    <h2 class="text-2xl font-bold">{{ __('Not sure which service fits?') }}</h2>
                    <p class="mt-2.5 max-w-xl text-[15px] leading-7 text-slate-600">{{ __('Send us the route and a short description of what you are shipping. We will suggest the service, the likely transit window and anything you need to prepare — before you book.') }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ lroute('contact') }}" class="btn-primary">{{ __('Ask our team') }} <x-lucide name="arrow-right" class="size-4" /></a>
                        <a href="{{ lroute('page.packing') }}" class="btn-ghost">{{ __('Read the packing guide') }}</a>
                    </div>
                </div>
                <div class="overflow-hidden rounded-[6px]">
                    <x-photo key="editorial_hub" class="aspect-[16/10] w-full object-cover" sizes="(min-width: 1024px) 33vw, 100vw" />
                </div>
            </div>
        </div>
    </section>
@endsection
