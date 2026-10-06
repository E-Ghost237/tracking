@extends('layouts.account', ['title' => $shipment->tracking_number ?? __('Shipment')])

@section('account')
    @php($released = $shipment->isReleased())
    <a href="{{ lroute('account.shipments') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-900"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('My shipments') }}</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __($shipment->service) }}</p>
            <h1 class="mt-1 font-mono text-2xl font-extrabold">{{ $released ? $shipment->tracking_number : __('Tracking number pending') }}</h1>
            <p class="mt-1 text-slate-600">{{ $shipment->originLabel() }} → {{ $shipment->destinationLabel() }}</p>
        </div>
        <div class="flex items-center gap-2">
            <x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" class="!px-3 !py-1.5 !text-sm" />
            @if ($released)
                <a href="{{ lroute('track', ['number' => $shipment->tracking_number]) }}" class="btn-ghost !py-2"><x-lucide name="radar" class="size-4" /> {{ __('Public tracking') }}</a>
            @endif
        </div>
    </div>

    @if (! $released)
        <div class="mt-6 flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center">
            <x-lucide name="hourglass" class="size-6 shrink-0 text-amber-600" />
            <p class="flex-1 text-sm text-amber-900">{{ __('Your label and tracking number are released as soon as your payment is approved.') }}</p>
            @if ($shipment->order && $shipment->order->status->isOpen())
                <a href="{{ lroute('account.orders.pay', $shipment->order) }}" class="btn-primary !py-2">{{ __('Go to payment') }}</a>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="card p-6">
                <h2 class="font-display text-lg font-bold">{{ __('Timeline') }}</h2>
                @if ($released && $shipment->events->isNotEmpty())
                    <ol class="mt-5">
                        @foreach ($shipment->events as $event)
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                <div class="relative flex flex-col items-center">
                                    <span @class(['relative z-10 mt-1 size-3 rounded-full', 'bg-brand-500 timeline-dot' => $loop->first, 'bg-slate-300' => ! $loop->first])></span>
                                    @unless ($loop->last)<span class="absolute top-4 bottom-[-4px] w-px bg-line"></span>@endunless
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-ink-900">{{ $event->source === 'system' ? __($event->label) : $event->label }}</p>
                                    <p class="text-xs text-slate-500">{{ $event->place }} · {{ $event->occurred_at->translatedFormat('j M Y, H:i') }} UTC</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="mt-3 text-sm text-slate-500">{{ __('Tracking events will appear here once your shipment is released.') }}</p>
                @endif
            </section>

            <section class="card p-6">
                <h2 class="font-display text-lg font-bold">{{ __('Packages') }}</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-500 uppercase"><tr><th class="py-2">{{ __('Description') }}</th><th class="py-2">{{ __('Category') }}</th><th class="py-2 text-right">{{ __('Weight') }}</th><th class="py-2 text-right">{{ __('Dimensions') }}</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($shipment->packages as $package)
                                <tr>
                                    <td class="py-2.5 text-ink-900">{{ $package->description }}</td>
                                    <td class="py-2.5 text-slate-600">{{ __('category.'.$package->category) }}</td>
                                    <td class="py-2.5 text-right tabular-nums">{{ number_format($package->weight_g / 1000, 2) }} kg</td>
                                    <td class="py-2.5 text-right tabular-nums">{{ $package->length_mm / 10 }} × {{ $package->width_mm / 10 }} × {{ $package->height_mm / 10 }} cm</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="card p-6">
                <h2 class="font-display text-lg font-bold">{{ __('Documents') }}</h2>
                @if ($released)
                    <ul class="mt-4 space-y-2">
                        @foreach ($shipment->documents->where('is_released', true) as $document)
                            <li>
                                <a href="{{ lroute('account.shipments.document', ['shipment' => $shipment->public_id, 'type' => $document->type]) }}" class="flex items-center gap-3 rounded-xl border border-line p-3 text-sm font-medium text-ink-900 transition hover:border-brand-300 hover:bg-brand-50/40">
                                    <x-lucide name="file-down" class="size-5 text-brand-500" />
                                    <span class="flex-1">{{ ['label' => __('Shipping label'), 'invoice' => __('Invoice'), 'commercial_invoice' => __('Commercial invoice'), 'receipt' => __('Payment receipt'), 'pod' => __('Proof of delivery')][$document->type] ?? $document->type }}</span>
                                    @unless ($document->file_id)<span class="text-xs text-slate-400">{{ __('Preparing…') }}</span>@endunless
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 flex items-center gap-2 text-sm text-slate-500"><x-lucide name="lock" class="size-4" /> {{ __('Available after payment approval.') }}</p>
                @endif
            </section>

            <section class="card p-6 text-sm">
                <h2 class="font-display text-lg font-bold">{{ __('Parties') }}</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Sender') }}</p>
                        <p class="mt-1 text-ink-900">{{ $shipment->sender['name'] ?? '' }}</p>
                        <p class="text-slate-600">{{ $shipment->origin['line1'] ?? '' }}, {{ $shipment->origin['city'] ?? '' }} {{ $shipment->origin['country'] ?? '' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Recipient') }}</p>
                        <p class="mt-1 text-ink-900">{{ $shipment->recipient['name'] ?? '' }}</p>
                        <p class="text-slate-600">{{ $shipment->destination['line1'] ?? '' }}, {{ $shipment->destination['city'] ?? '' }} {{ $shipment->destination['country'] ?? '' }}</p>
                    </div>
                </div>
            </section>

            <section class="card p-6 text-sm">
                <h2 class="font-display text-lg font-bold">{{ __('Payment') }}</h2>
                @if ($shipment->order)
                    <div class="mt-3 flex items-center justify-between">
                        <span class="text-slate-600">{{ $shipment->order->number }}</span>
                        <x-status-badge :status="$shipment->order->status->value" :label="$shipment->order->status->label()" />
                    </div>
                    <p class="mt-2 font-display text-xl font-bold text-ink-900">{{ \App\Support\Money::format($shipment->order->total, $shipment->order->currency) }}</p>
                @endif
                <a href="{{ lroute('account.support', ['shipment' => $shipment->public_id]) }}" class="btn-ghost mt-5 w-full"><x-lucide name="life-buoy" class="size-4" /> {{ __('Get help with this shipment') }}</a>
            </section>
        </div>
    </div>
@endsection
