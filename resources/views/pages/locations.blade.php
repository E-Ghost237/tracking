@extends('layouts.app', ['title' => __('Drop-off and pickup points'), 'description' => __('Find our hubs and drop-off points.')])

@section('content')
    <x-page-header :eyebrow="__('Locations')" icon="map-pinned" :title="__('Drop-off and pickup points')" :lead="__('Bring your parcels to one of our hubs or partner points, or book a pickup at your door.')" />

    <div class="container-page space-y-12 py-14">
        @forelse ($locations as $country => $items)
            <section>
                <h2 class="text-xl font-bold">{{ \App\Support\Geo::countryName($country) }}</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $location)
                        <article class="card p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-display font-bold text-ink-900">{{ $location->name }}</h3>
                                    <p class="text-sm text-slate-500">{{ $location->line1 }}{{ $location->line1 ? ', ' : '' }}{{ $location->city }}</p>
                                </div>
                                <span class="badge {{ $location->type === 'hub' ? 'bg-ink-900 text-white' : 'bg-brand-50 text-brand-700' }}">{{ $location->type === 'hub' ? __('Hub') : __('Drop-off point') }}</span>
                            </div>
                            @if ($location->opening_hours)
                                <ul class="mt-4 space-y-1 text-sm text-slate-600">
                                    @foreach ($location->opening_hours as $line)
                                        <li class="flex items-center gap-2"><x-lucide name="clock" class="size-4 text-slate-400" /> {{ $line }}</li>
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
            <p class="text-slate-600">{{ __('Locations will be published soon.') }}</p>
        @endforelse
    </div>
@endsection
