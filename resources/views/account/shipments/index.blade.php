@extends('layouts.account', ['title' => __('Shipments')])

@php
    $hasFilters = collect($filters ?? [])->filter()->isNotEmpty();
@endphp

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('My shipments') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Search by tracking number, recipient or destination city. A shipment appears here as soon as the booking is saved; tracking scans start once payment is approved.') }}</p>
        </div>
        <a href="{{ lroute('account.shipments.create') }}" class="btn-primary !py-2"><x-lucide name="plus" class="size-4" /> {{ __('New shipment') }}</a>
    </div>

    {{-- Filters sit on one hairline row instead of in a bordered box --}}
    <form method="GET" class="mt-6 border-b border-line pb-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="lg:col-span-2">
                <span class="field-label">{{ __('Search') }}</span>
                <span class="relative block">
                    <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number, recipient or city') }}" class="field !pl-9">
                </span>
            </label>
            <label>
                <span class="field-label">{{ __('Status') }}</span>
                <select name="status" class="field">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\ShipmentStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="field-label">{{ __('Mode') }}</span>
                <select name="mode" class="field">
                    <option value="">{{ __('All modes') }}</option>
                    @foreach (['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-3">
            <button class="btn-dark !py-2" type="submit">{{ __('Filter') }}</button>
            @if ($hasFilters)
                <a href="{{ lroute('account.shipments') }}" class="btn-ghost !py-2">{{ __('Clear') }}</a>
            @endif
            <p class="text-xs text-slate-600">
                @if ($hasFilters)
                    {{ __(':count shipments match', ['count' => $shipments->total()]) }}
                @else
                    {{ __(':count shipments in total', ['count' => $shipments->total()]) }}
                @endif
            </p>
        </div>
    </form>

    @if ($shipments->isNotEmpty())
        <div class="mt-6 overflow-hidden rounded-[6px] border border-line bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <caption class="sr-only">{{ __('My shipments') }}</caption>
                    <thead class="border-b border-line bg-surface text-left text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-3">{{ __('Tracking number') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('Route') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('Mode') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('Status') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('Booked') }}</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($shipments as $shipment)
                            <tr class="transition-colors hover:bg-surface/70">
                                <td class="px-5 py-4">
                                    @if ($shipment->isReleased())
                                        <a href="{{ lroute('account.shipments.show', $shipment) }}" class="font-mono font-semibold text-ink-950 hover:text-brand-700">{{ $shipment->tracking_number }}</a>
                                    @else
                                        <span class="text-slate-600">{{ __('Pending') }}</span>
                                        @if ($shipment->order && $shipment->order->status->isOpen())
                                            <a href="{{ lroute('account.orders.pay', $shipment->order) }}" class="mt-0.5 block text-xs font-medium text-brand-700 hover:underline">{{ __('Payment pending') }}</a>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-700">{{ $shipment->originLabel() }} <span class="text-slate-500">→</span> {{ $shipment->destinationLabel() }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ __($shipment->service) }}</td>
                                <td class="px-5 py-4"><x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></td>
                                <td class="px-5 py-4 text-slate-600 tabular">{{ $shipment->created_at->translatedFormat('j M Y') }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ lroute('account.shipments.show', $shipment) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-ink-900 hover:text-brand-700">
                                        {{ __('Open') }} <x-lucide name="chevron-right" class="size-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $shipments->links() }}</div>
    @else
        <div class="card mt-6 p-8 text-center lg:p-10">
            <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="package" class="size-6" /></span>
            <p class="mt-4 font-semibold text-ink-950">
                {{ $hasFilters ? __('No shipment matches these filters') : __('No shipments yet') }}
            </p>
            <p class="mx-auto mt-1.5 max-w-lg text-sm leading-6 text-slate-600">
                {{ $hasFilters
                    ? __('Try a shorter search term, or clear the filters to see every booking on this account.')
                    : __('Book your first shipment and it will appear here with its price, its documents and every scan as it travels.') }}
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                @if ($hasFilters)
                    <a href="{{ lroute('account.shipments') }}" class="btn-ghost !py-2">{{ __('Clear the filters') }}</a>
                @endif
                <a href="{{ lroute('account.shipments.create') }}" class="btn-primary !py-2">{{ __('New shipment') }}</a>
                <a href="{{ lroute('quote') }}" class="btn-ghost !py-2">{{ __('Get a quote') }}</a>
            </div>
        </div>
    @endif

    {{-- What the status word actually means, so the table needs no legend hunting --}}
    <section class="mt-9">
        <h2 class="text-lg font-bold">{{ __('Reading your shipment status') }}</h2>
        <dl class="mt-4 grid gap-x-8 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold text-ink-950">{{ __('Pending') }}</dt>
                <dd class="mt-0.5 text-sm leading-6 text-slate-600">{{ __('Booked and priced, waiting for the payment to be verified. No tracking number is issued yet.') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-950">{{ __('In transit') }}</dt>
                <dd class="mt-0.5 text-sm leading-6 text-slate-600">{{ __('The parcel has been picked up and is moving along the corridor. Each handoff adds a scan with its place.') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-950">{{ __('At customs') }}</dt>
                <dd class="mt-0.5 text-sm leading-6 text-slate-600">{{ __('Held by the customs authority of the destination country. We publish the scan we receive; clearance is decided by them.') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold text-ink-950">{{ __('Delivered') }}</dt>
                <dd class="mt-0.5 text-sm leading-6 text-slate-600">{{ __('Handed to the recipient or to their address. The proof of delivery becomes available in the shipment documents.') }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-sm text-slate-600">
            {!! __('Full definitions, limits and the claims procedure are in the :link.', ['link' => '<a class="link" href="'.e(lroute('help')).'">'.e(__('help centre')).'</a>']) !!}
        </p>
    </section>
@endsection
