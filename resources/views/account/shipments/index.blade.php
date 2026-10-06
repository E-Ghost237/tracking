@extends('layouts.account', ['title' => __('Shipments')])

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-extrabold">{{ __('My shipments') }}</h1>
        <a href="{{ lroute('account.shipments.create') }}" class="btn-primary"><x-lucide name="plus" class="size-4" /> {{ __('New shipment') }}</a>
    </div>

    <form method="GET" class="card mt-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number, recipient or city') }}" class="field lg:col-span-2" aria-label="{{ __('Search') }}">
        <select name="status" class="field" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\ShipmentStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="mode" class="field" aria-label="{{ __('Mode') }}">
            <option value="">{{ __('All modes') }}</option>
            @foreach (['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['mode'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-dark" type="submit">{{ __('Filter') }}</button>
    </form>

    <div class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="border-b border-line bg-surface text-left text-xs font-semibold tracking-wider text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">{{ __('Tracking number') }}</th>
                        <th class="px-5 py-3">{{ __('Route') }}</th>
                        <th class="px-5 py-3">{{ __('Mode') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($shipments as $shipment)
                        <tr class="transition hover:bg-surface/60">
                            <td class="px-5 py-4"><a href="{{ lroute('account.shipments.show', $shipment) }}" class="font-mono font-semibold text-ink-900 hover:text-brand-600">{{ $shipment->tracking_number ?? '—' }}</a></td>
                            <td class="px-5 py-4 text-slate-700">{{ $shipment->originLabel() }} → {{ $shipment->destinationLabel() }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ __($shipment->service) }}</td>
                            <td class="px-5 py-4"><x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></td>
                            <td class="px-5 py-4 text-slate-500">{{ $shipment->created_at->translatedFormat('j M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">{{ __('No shipments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6">{{ $shipments->links() }}</div>
@endsection
