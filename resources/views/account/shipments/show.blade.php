@extends('layouts.account', ['title' => $shipment->tracking_number ?? __('Shipment')])

@php
    $released = $shipment->isReleased();
    $progress = (int) round(($shipment->progress ?: $shipment->status->defaultProgress()) * 100);
    $documents = [
        'label' => [__('Shipping label'), 'tag'],
        'invoice' => [__('Invoice'), 'receipt'],
        'commercial_invoice' => [__('Commercial invoice'), 'file-text'],
        'receipt' => [__('Payment receipt'), 'file-check'],
        'pod' => [__('Proof of delivery'), 'package-check'],
    ];
@endphp

@section('account')
    <nav class="text-sm" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ lroute('account.shipments') }}" class="inline-flex items-center gap-1 text-slate-600 hover:text-ink-950">
            <x-lucide name="chevron-left" class="size-4" /> {{ __('My shipments') }}
        </a>
    </nav>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __($shipment->service) }} · {{ $shipment->mode ? __($shipment->mode) : '' }}</p>
            <h1 class="mt-1 font-mono text-2xl font-bold break-all">{{ $released ? $shipment->tracking_number : __('Tracking number pending') }}</h1>
            <p class="mt-1.5 text-sm text-slate-600">{{ $shipment->originLabel() }} <span class="text-slate-500">→</span> {{ $shipment->destinationLabel() }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" class="!px-3 !py-1.5 !text-sm" />
            @if ($released)
                <a href="{{ lroute('track', ['number' => $shipment->tracking_number]) }}" class="btn-ghost !py-2"><x-lucide name="radar" class="size-4" /> {{ __('Public tracking') }}</a>
            @endif
            <a href="{{ lroute('account.support', ['shipment' => $shipment->public_id]) }}" class="btn-ghost !py-2"><x-lucide name="life-buoy" class="size-4" /> {{ __('Get help') }}</a>
        </div>
    </div>

    {{-- One progress rail for the whole corridor --}}
    <div class="card mt-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-semibold text-ink-950">{{ $shipment->status->label() }}</p>
            <p class="text-xs text-slate-600 tabular">{{ $progress }}%</p>
        </div>
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Shipment progress') }}">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div>
        </div>
        @php($latestEvent = $shipment->events->first())
        @if ($latestEvent)
            <p class="mt-3 text-sm text-slate-600">
                {{ $latestEvent->source === 'system' ? __($latestEvent->label) : $latestEvent->label }}
                <span class="mx-1.5 text-slate-300">·</span>
                {{ $latestEvent->place }}
                <span class="mx-1.5 text-slate-300">·</span>
                <span class="tabular">{{ $latestEvent->occurred_at->translatedFormat('j M Y, H:i') }} UTC</span>
            </p>
        @endif
    </div>

    @if (! $released)
        <div class="mt-6 flex flex-col gap-4 rounded-[6px] border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center">
            <x-lucide name="hourglass" class="size-6 shrink-0 text-amber-700" />
            <div class="flex-1 text-sm leading-6 text-amber-900">
                <p class="font-semibold">{{ __('Waiting for payment approval') }}</p>
                <p class="mt-0.5">{{ __('Your label and tracking number are released as soon as a verifier approves your payment. You can upload the proof or choose another method at any time.') }}</p>
            </div>
            @if ($shipment->order && $shipment->order->status->isOpen())
                <a href="{{ lroute('account.orders.pay', $shipment->order) }}" class="btn-primary !py-2">{{ __('Go to payment') }}</a>
            @endif
        </div>
    @endif

    <div class="mt-8 grid gap-8 xl:grid-cols-3">
        <div class="space-y-8 xl:col-span-2">
            <section>
                <h2 class="text-lg font-bold">{{ __('Journey') }}</h2>
                @if ($released && $shipment->events->isNotEmpty())
                    <ol class="mt-5">
                        @foreach ($shipment->events as $event)
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                <div class="relative flex flex-col items-center">
                                    <span @class(['relative z-10 mt-1 size-3 rounded-full', 'bg-brand-500 timeline-dot' => $loop->first, 'bg-slate-300' => ! $loop->first])></span>
                                    @unless ($loop->last)<span class="absolute top-4 bottom-[-4px] w-px bg-line"></span>@endunless
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-3">
                                        <p class="text-sm font-semibold text-ink-950">{{ $event->source === 'system' ? __($event->label) : $event->label }}</p>
                                        <p class="text-xs text-slate-600 tabular">{{ $event->occurred_at->translatedFormat('j M Y, H:i') }} UTC</p>
                                    </div>
                                    <p class="mt-0.5 text-xs text-slate-600">
                                        {{ $event->place }}
                                        @if ($event->source === 'carrier')
                                            <span class="mx-1.5 text-slate-300">·</span> {{ __('Reported by the carrier') }}
                                        @elseif ($event->source === 'partner')
                                            <span class="mx-1.5 text-slate-300">·</span> {{ __('Reported by our partner carrier') }}
                                        @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @elseif ($released)
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ __('No scan has been recorded yet. The first one usually appears when the parcel is picked up.') }}</p>
                @else
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ __('Tracking events will appear here once your shipment is released.') }}</p>
                @endif
            </section>

            <section>
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ __('Packages') }}</h2>
                    <p class="text-sm text-slate-600"><span class="tabular">{{ $shipment->packages->count() }}</span> {{ __('items') }}</p>
                </div>
                <div class="mt-4 overflow-hidden rounded-[6px] border border-line bg-white">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <caption class="sr-only">{{ __('Packages') }}</caption>
                            <thead class="border-b border-line bg-surface text-left text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                                <tr>
                                    <th scope="col" class="px-4 py-3">{{ __('Description') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('Category') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right">{{ __('Weight') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right">{{ __('Dimensions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @forelse ($shipment->packages as $package)
                                    <tr>
                                        <td class="px-4 py-3 text-ink-950">{{ $package->description }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ __('category.'.$package->category) }}</td>
                                        <td class="px-4 py-3 text-right tabular">{{ number_format($package->weight_g / 1000, 2) }} kg</td>
                                        <td class="px-4 py-3 text-right tabular">{{ $package->length_mm / 10 }} × {{ $package->width_mm / 10 }} × {{ $package->height_mm / 10 }} cm</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-600">{{ __('No package line recorded on this shipment.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="space-y-8">
            <section>
                <h2 class="text-lg font-bold">{{ __('Documents') }}</h2>
                @if ($released)
                    @php($releasedDocs = $shipment->documents->where('is_released', true))
                    @if ($releasedDocs->isEmpty())
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ __('No document has been generated for this shipment yet.') }}</p>
                    @else
                        <ul class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">
                            @foreach ($releasedDocs as $document)
                                <li>
                                    <a href="{{ lroute('account.shipments.document', ['shipment' => $shipment->public_id, 'type' => $document->type]) }}" class="group flex items-center gap-3 px-4 py-3.5 text-sm transition hover:bg-surface">
                                        <x-lucide :name="$documents[$document->type][1] ?? 'file-down'" class="size-4 shrink-0 text-brand-600" />
                                        <span class="min-w-0 flex-1 font-medium text-ink-950">{{ $documents[$document->type][0] ?? $document->type }}</span>
                                        @if ($document->file_id)
                                            <x-lucide name="download" class="size-4 shrink-0 text-slate-500 group-hover:text-ink-900" />
                                        @else
                                            <span class="text-xs text-slate-500">{{ __('Preparing…') }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="mt-3 flex items-start gap-2 text-sm leading-6 text-slate-600">
                        <x-lucide name="lock" class="mt-0.5 size-4 shrink-0 text-slate-500" />
                        <span>{{ __('The label, the commercial invoice and the receipt are released with the payment approval.') }}</span>
                    </p>
                @endif
            </section>

            <section>
                <h2 class="text-lg font-bold">{{ __('Parties') }}</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div class="border-b border-line pb-4">
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Sender') }}</dt>
                        <dd class="mt-1 font-medium text-ink-950">{{ $shipment->sender['name'] ?? '' }}</dd>
                        <dd class="text-slate-600">{{ $shipment->origin['line1'] ?? '' }}</dd>
                        <dd class="text-slate-600">{{ $shipment->origin['city'] ?? '' }} {{ $shipment->origin['postal_code'] ?? '' }} · {{ $shipment->origin['country'] ?? '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Recipient') }}</dt>
                        <dd class="mt-1 font-medium text-ink-950">{{ $shipment->recipient['name'] ?? '' }}</dd>
                        <dd class="text-slate-600">{{ $shipment->destination['line1'] ?? '' }}</dd>
                        <dd class="text-slate-600">{{ $shipment->destination['city'] ?? '' }} {{ $shipment->destination['postal_code'] ?? '' }} · {{ $shipment->destination['country'] ?? '' }}</dd>
                    </div>
                </dl>
            </section>

            @if ($shipment->order)
                <section>
                    <h2 class="text-lg font-bold">{{ __('Payment') }}</h2>
                    <div class="mt-4 rounded-[6px] border border-line bg-white p-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-600">{{ $shipment->order->number }}</span>
                            <x-status-badge :status="$shipment->order->status->value" :label="$shipment->order->status->label()" />
                        </div>
                        <p class="mt-2 text-xl font-bold text-ink-950 tabular">{{ \App\Support\Money::format($shipment->order->total, $shipment->order->currency) }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Payment reference') }} <span class="font-mono font-semibold text-ink-900">{{ $shipment->order->payment_reference }}</span></p>
                        <a href="{{ lroute('account.orders.pay', $shipment->order) }}" class="btn-ghost mt-4 w-full !py-2">{{ __('Open the order') }}</a>
                    </div>
                </section>
            @endif

            <section class="rounded-[6px] bg-surface p-4 text-sm leading-6 text-slate-600">
                <p class="font-semibold text-ink-950">{{ __('Something wrong?') }}</p>
                <p class="mt-1">{{ __('Open a claim for loss, damage or delay, or send us a message and we will answer on this shipment.') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ lroute('account.claims') }}" class="btn-ghost !py-2">{{ __('Open a claim') }}</a>
                </div>
            </section>
        </div>
    </div>
@endsection
