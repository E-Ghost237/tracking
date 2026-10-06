@php
    $content = [
        'air' => [
            'plane', __('Air freight'), __('For parcels that have a little less time to spare.'),
            __('Air freight suits shipments where a shorter journey matters more than the lowest price per kilogram. It is a practical option for parcels, business samples and stock that needs to reach its destination on a tighter schedule. Whether you are sending important documents, high-value samples or time-critical inventory, air freight keeps your goods moving at the speed commercial aviation provides.'),
            __('We plan the shipment around scheduled air capacity, then coordinate the documentation, customs steps and local delivery handoff. You can follow the progress from collection through to the final scan, with updates at each key stage of the journey.'),
            'images/freight-air.jpg', __('Cargo aircraft climbing above an international airport at dusk'), __('3–7 days'), __('Parcels, samples and time-sensitive stock'),
            [__('A clear estimate before you book'), __('Updates at key handoffs'), __('Customs documentation guidance')],
        ],
        'sea' => [
            'ship', __('Sea freight'), __('More room for the things that make a long journey worthwhile.'),
            __('When your goods are heavy, bulky or moving on a planned schedule, shared container space can be a more economical choice than air. It is often used for furniture, equipment and regular business stock moving between continents. Sea freight works best when you can plan ahead and are not working against a tight delivery deadline.'),
            __('Your shipment is consolidated with other eligible cargo before departure. We coordinate export and import documentation, share progress updates while it is in transit and arrange the next delivery step once it reaches port. The process is designed to keep your goods moving smoothly from origin to final destination.'),
            'images/freight-sea.jpg', __('Container vessel travelling toward a port at sunrise'), __('25–45 days'), __('Furniture, equipment and planned stock'),
            [__('Shared container space for eligible goods'), __('A lower cost per kilogram on larger loads'), __('A useful option when delivery is not urgent')],
        ],
        'road' => [
            'truck', __('Road freight'), __('A direct option for supported journeys over land.'),
            __('Road freight connects collection and delivery points on established regional corridors. It is suited to eligible parcels and palletised goods where a flexible pickup and a door-to-door handoff are useful. For journeys within the same supported land area, road service can offer a straightforward way to move goods without the complexity of multiple handoffs.'),
            __('Road service is available when the origin and destination sit within the same supported land area. For a longer journey, the quote tool can help compare air or sea alternatives instead. We check route availability before confirming any booking, so you know the service is viable before you commit.'),
            'images/freight-road.jpg', __('Freight truck travelling through the evening on a highway'), __('2–10 days'), __('Regional parcels and palletised freight'),
            [__('Collection and delivery options on supported routes'), __('Suitable for selected pallet and parcel sizes'), __('Route checked before a booking is confirmed')],
        ],
        'express' => [
            'zap', __('Express'), __('For the documents and small shipments that cannot wait.'),
            __('Express is designed for eligible shipments where timing is the main consideration. Priority handling and the next available flight help reduce waiting between key stages of the journey. When you need a document, sample or small shipment to arrive as quickly as possible, express gives your goods the attention they need.'),
            __('After booking, we guide you through the required shipment details, coordinate the priority air movement and keep the delivery handoff visible. Transit estimates depend on route availability and destination processing, but the service is built around speed and visibility from start to finish.'),
            'images/freight-express.jpg', __('Express air cargo moving across a sunlit airport apron'), __('2–4 days'), __('Documents and smaller urgent goods'),
            [__('Priority handling at key handoffs'), __('The next available flight on eligible routes'), __('Clear updates through final delivery')],
        ],
    ][$code];
@endphp
@extends('layouts.app', ['title' => $content[1], 'description' => $content[2]])

@section('content')
    <section class="relative isolate overflow-hidden bg-ink-950 text-white">
        <div class="absolute inset-y-0 right-0 -z-20 w-full overflow-hidden lg:w-[58%]">
            <img src="/{{ $content[5] }}" alt="{{ $content[6] }}" class="size-full object-cover object-center" fetchpriority="high" width="1376" height="768">
            <div class="absolute inset-0 bg-gradient-to-r from-ink-950 via-ink-950/75 to-ink-950/20 lg:from-ink-950 lg:via-ink-950/55 lg:to-ink-950/15"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-ink-950/55 via-transparent to-ink-950/20"></div>
        </div>
        <div class="container-page relative grid min-h-[580px] items-end gap-10 py-12 sm:py-16 lg:min-h-[620px] lg:grid-cols-12 lg:items-center lg:py-20">
            <div class="lg:col-span-7">
                <a href="{{ lroute('services') }}" class="inline-flex items-center gap-2 text-sm font-medium text-white/70 transition hover:text-white"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('All services') }}</a>
                <p class="eyebrow eyebrow-rule mt-10 !text-brand-300"><x-lucide :name="$content[0]" class="size-4" /> {{ __('Freight service') }}</p>
                <h1 class="editorial-title mt-4 max-w-3xl text-5xl leading-[0.98] !text-white sm:text-6xl lg:text-7xl">{{ $content[1] }}</h1>
                <p class="mt-5 max-w-xl text-lg leading-8 text-slate-200">{{ $content[2] }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-primary">{{ __('Get a quote') }} <x-lucide name="arrow-right" class="size-4" /></a>
                    <a href="{{ lroute('rates') }}" class="btn-light">{{ __('See rates') }}</a>
                </div>
                <p class="mt-5 text-sm text-slate-300 lg:hidden">{{ __('Typical transit') }}: <strong class="text-white">{{ $content[7] }}</strong></p>
            </div>
            <aside class="hidden lg:col-span-5 lg:block">
                <div class="ml-auto max-w-xs rounded-xl border border-white/15 bg-ink-950/75 p-5 shadow-xl backdrop-blur-md">
                    <p class="text-[10px] font-semibold tracking-[0.15em] text-slate-400 uppercase">{{ __('Typical transit window') }}</p>
                    <p class="editorial-title mt-1 text-4xl !text-white">{{ $content[7] }}</p>
                    <div class="mt-5 border-t border-white/15 pt-4">
                        <p class="text-[10px] font-semibold tracking-[0.15em] text-slate-400 uppercase">{{ __('A good fit for') }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-100">{{ $content[8] }}</p>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <div class="container-page grid gap-12 py-14 sm:py-16 lg:grid-cols-12 lg:gap-16">
        <article class="prose-content lg:col-span-7">
            <p class="eyebrow eyebrow-rule">{{ __('What to expect') }}</p>
            <h2 class="editorial-title mt-4 text-4xl">{{ __('A route planned around your shipment') }}</h2>
            <p>{{ $content[3] }}</p>
            <p>{{ $content[4] }}</p>

            <h2>{{ __('Before you book') }}</h2>
            <p>{{ __('A little preparation helps prevent avoidable delays. Have the sender and recipient details ready, describe each item accurately and make sure the parcel is packed for the handling it will receive on this route. Good preparation at the start saves time and reduces the chance of problems later in the journey.') }}</p>
            <ul>
                <li>{{ __('Sender and recipient names, addresses and phone numbers') }}</li>
                <li>{{ __('A clear contents description and a realistic declared value') }}</li>
                <li>{{ __('Parcel dimensions and weight, measured after packing') }}</li>
                <li>{{ __('Any permits or supporting documents required for the goods') }}</li>
            </ul>
            <p>{{ __('Transit windows are estimates and may change with flight or vessel schedules, customs processing and local delivery conditions. Your quote shows the current estimate for the route you enter, based on the information available at the time.') }}</p>
            <p><a href="{{ lroute('page.prohibited-items') }}">{{ __('Check restricted and prohibited goods') }}</a> · <a href="{{ lroute('page.packing') }}">{{ __('Read the packing guide') }}</a> · <a href="{{ lroute('page.customs') }}">{{ __('Prepare for customs') }}</a></p>
        </article>

        <aside class="space-y-5 lg:col-span-5">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-surface px-6 py-5">
                    <p class="eyebrow">{{ __('At a glance') }}</p>
                    <h2 class="mt-2 font-display text-xl font-bold">{{ $content[8] }}</h2>
                </div>
                <ul class="space-y-4 p-6 text-sm text-slate-700">
                    @foreach ($content[9] as $point)
                        <li class="flex items-start gap-3"><span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide name="check" class="size-3.5" /></span> <span>{{ $point }}</span></li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-6 py-4 text-xs leading-5 text-slate-500">
                    {{ __('The actual price depends on route, chargeable weight and available options. The quote tool explains the estimate before you book.') }}
                </div>
            </div>
            <div class="rounded-xl border border-line bg-white p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="font-display text-base font-bold text-ink-900">{{ __('How the price is shaped') }}</h2>
                    <x-lucide name="receipt" class="size-5 text-brand-500" />
                </div>
                <ul class="mt-4 space-y-3 text-sm text-slate-600">
                    <li class="flex gap-3"><x-lucide name="scale" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Chargeable weight, based on actual or volumetric weight') }}</li>
                    <li class="flex gap-3"><x-lucide name="route" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('The origin, destination and chosen transport mode') }}</li>
                    <li class="flex gap-3"><x-lucide name="shield-check" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Optional insurance against the declared value') }}</li>
                    <li class="flex gap-3"><x-lucide name="receipt" class="mt-0.5 size-4 shrink-0 text-brand-500" /> {{ __('Applicable fuel, handling or destination charges') }}</li>
                </ul>
                <p class="mt-5 rounded-lg bg-surface px-3 py-2.5 text-xs text-slate-500">{{ __('Service rate factor') }}: × {{ rtrim(rtrim(number_format($mode->multiplier, 2), '0'), '.') }}</p>
                <a href="{{ lroute('quote') }}?mode={{ $code }}" class="btn-primary mt-5 w-full">{{ __('Price this route') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>
        </aside>
    </div>
@endsection
