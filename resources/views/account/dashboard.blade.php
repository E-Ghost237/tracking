@extends('layouts.account', ['title' => __('Dashboard')])

@php
    /*
     * The dashboard answers three questions in order: what needs my attention,
     * where are my parcels, and what happens next. Counts come from the
     * controller; nothing here is estimated.
     */
    $steps = [
        ['calculator', __('Get a quote'), __('Enter the route and the packed size to see the price and the delivery window.'), lroute('quote')],
        ['package', __('Book the shipment'), __('Add both parties and the customs details. Your progress is saved at every step.'), lroute('account.shipments.create')],
        ['shield-check', __('Pay and upload proof'), __('Transfer the amount, upload your receipt, and a person verifies it.'), lroute('account.orders')],
        ['radar', __('Follow every scan'), __('Your tracking number is issued once payment is approved, then each handoff is logged.'), lroute('track')],
    ];

    $activityLabels = [
        'order.created' => __('Order created'),
        'payment.method_selected' => __('Payment method selected'),
        'payment.proof_submitted' => __('Proof of payment submitted'),
        'security.2fa_enabled' => __('Two-factor authentication enabled'),
        'account.email_verified' => __('Email address confirmed'),
    ];
@endphp

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Hello, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]) }}</h1>
            <p class="mt-1.5 text-sm text-slate-600">{{ __('Here is what is happening with your shipments.') }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ lroute('account.shipments.create') }}" class="btn-primary !py-2"><x-lucide name="plus" class="size-4" /> {{ __('New shipment') }}</a>
            <a href="{{ lroute('track') }}" class="btn-ghost !py-2"><x-lucide name="radar" class="size-4" /> {{ __('Track a parcel') }}</a>
        </div>
    </div>

    {{-- Figures first: what needs action, and how much of it --}}
    <dl class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">
        @foreach ([
            ['package', __('Active shipments'), $active->count(), __('In transit, at customs or out for delivery')],
            ['hourglass', __('Awaiting payment'), $awaiting->count(), __('Orders that still need a payment or a proof')],
            ['shield-check', __('Payments under review'), $underReview->count(), __('With a verifier, usually under :minutes minutes', ['minutes' => (int) config('platform.settings.review_target_minutes')])],
        ] as [$icon, $label, $count, $hint])
            <div class="bg-white p-5">
                <dt class="flex items-center gap-2 text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                    <x-lucide :name="$icon" class="size-4 text-slate-500" /> {{ $label }}
                </dt>
                <dd class="mt-2.5 text-3xl font-bold text-ink-950 tabular">{{ $count }}</dd>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ $hint }}</p>
            </div>
        @endforeach
    </dl>

    {{-- Anything with a deadline goes above the fold of the content --}}
    @if ($awaiting->isNotEmpty())
        <section class="mt-9">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">{{ __('Waiting on you') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('Complete the payment and upload your proof to release the label and tracking number.') }}</p>
                </div>
                <a href="{{ lroute('account.orders') }}" class="btn-ghost !py-2">{{ __('All orders') }}</a>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($awaiting as $order)
                    <article class="card flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="font-semibold text-ink-950">{{ $order->number }}</p>
                                <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                            </div>
                            <p class="mt-1.5 text-sm text-slate-600">
                                {{ $order->shipment?->originLabel() }} <span class="text-slate-500">→</span> {{ $order->shipment?->destinationLabel() }}
                            </p>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ __('Payment reference') }} <span class="font-mono font-semibold text-ink-900">{{ $order->payment_reference }}</span>
                                <span class="mx-1.5 text-slate-300">·</span>
                                {{ __('Amount') }} <span class="font-semibold text-ink-900 tabular">{{ \App\Support\Money::format($order->total, $order->currency) }}</span>
                            </p>
                        </div>

                        @if ($order->expires_at)
                            <div x-data="countdown" data-until="{{ $order->expires_at->toIso8601String() }}" class="shrink-0 text-sm">
                                <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Time left to pay') }}</p>
                                <p class="mt-1 font-mono font-semibold tabular" :class="urgent ? 'text-red-700' : 'text-ink-950'" x-text="remaining"></p>
                            </div>
                        @endif

                        <a href="{{ lroute('account.orders.pay', $order) }}" class="btn-primary shrink-0 !py-2 sm:self-center">{{ __('Pay now') }}</a>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($underReview->isNotEmpty())
        <section class="mt-9">
            <h2 class="text-lg font-bold">{{ __('Payments under review') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('A verifier checks the amount, the date and the reference against your receipt. Target review time: :minutes minutes during staffed hours (:hours).', ['minutes' => (int) config('platform.settings.review_target_minutes'), 'hours' => config('platform.settings.staffed_hours')]) }}</p>
            <div class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">
                @foreach ($underReview as $order)
                    <a href="{{ lroute('account.orders.pay', $order) }}" class="flex flex-wrap items-center gap-4 p-5 transition hover:bg-surface">
                        <span class="grid size-10 place-items-center rounded-[4px] bg-amber-50 text-amber-700"><x-lucide name="hourglass" class="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink-950">{{ $order->number }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ $order->shipment?->originLabel() }} <span class="text-slate-500">→</span> {{ $order->shipment?->destinationLabel() }} · <span class="tabular">{{ \App\Support\Money::format($order->total, $order->currency) }}</span></p>
                        </div>
                        <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                        <x-lucide name="chevron-right" class="size-4 shrink-0 text-slate-500" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-9">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">{{ __('Active shipments') }}</h2>
            <a href="{{ lroute('account.shipments') }}" class="link text-sm">{{ __('View all') }}</a>
        </div>

        @if ($active->isEmpty())
            <div class="card mt-4 p-8 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="package" class="size-6" /></span>
                <p class="mt-4 font-semibold text-ink-950">{{ __('No active shipments') }}</p>
                <p class="mx-auto mt-1.5 max-w-md text-sm leading-6 text-slate-600">{{ __('When you book a shipment it appears here with its tracking number, its current status and the documents you can download.') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <a href="{{ lroute('quote') }}" class="btn-primary !py-2">{{ __('Get a quote') }}</a>
                    <a href="{{ lroute('services') }}" class="btn-ghost !py-2">{{ __('Compare services') }}</a>
                </div>
            </div>
        @else
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($active as $shipment)
                    @php
                        $progress = (int) round(($shipment->progress ?: $shipment->status->defaultProgress()) * 100);
                        $last = $shipment->events->first();
                    @endphp
                    <a href="{{ lroute('account.shipments.show', $shipment) }}" class="card card-hover p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-bold break-all text-ink-950">{{ $shipment->tracking_number }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ __($shipment->service) }}</p>
                            </div>
                            <x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" />
                        </div>

                        <p class="mt-3.5 text-sm text-ink-900">
                            {{ $shipment->originLabel() }} <span class="text-slate-500">→</span> {{ $shipment->destinationLabel() }}
                        </p>

                        <div class="mt-4">
                            <div class="h-1 overflow-hidden rounded-full bg-surface" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div>
                            </div>
                            <p class="mt-2.5 text-xs text-slate-600">
                                @if ($last)
                                    <span class="font-medium text-ink-900">{{ $last->label }}</span> · <span class="tabular">{{ $last->occurred_at->diffForHumans() }}</span>
                                @else
                                    {{ __('Waiting for the first scan.') }}
                                @endif
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- What happens next, for anyone who has not shipped before --}}
    <section class="mt-9">
        <h2 class="text-lg font-bold">{{ __('How a shipment works from here') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $index => [$icon, $heading, $copy, $href])
                <a href="{{ $href }}" class="card card-hover flex flex-col p-5">
                    <span class="flex items-center gap-2 text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">
                        <span class="grid size-6 place-items-center rounded-[4px] bg-ink-900 text-[11px] font-bold text-white tabular">{{ $index + 1 }}</span>
                        {{ __('Step :number', ['number' => $index + 1]) }}
                    </span>
                    <span class="mt-3 flex items-center gap-2 text-sm font-bold text-ink-950"><x-lucide :name="$icon" class="size-4 text-ink-600" /> {{ $heading }}</span>
                    <span class="mt-1.5 flex-1 text-sm leading-6 text-slate-600">{{ $copy }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($activity->isNotEmpty())
        <section class="mt-9">
            <h2 class="text-lg font-bold">{{ __('Recent account activity') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every money, security and status change is written to an append-only trail. These are the most recent entries on your account.') }}</p>
            <ul class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">
                @foreach ($activity as $entry)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 text-sm">
                        <span class="font-medium text-ink-950">{{ $activityLabels[$entry->action] ?? $entry->action }}</span>
                        @if ($entry->object_type)
                            <span class="text-xs text-slate-600">{{ $entry->object_type }} <span class="font-mono">{{ \Illuminate\Support\Str::limit($entry->object_id, 12) }}</span></span>
                        @endif
                        <span class="ml-auto text-xs text-slate-600 tabular">{{ $entry->created_at->translatedFormat('j M Y, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
