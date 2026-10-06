@extends('layouts.account', ['title' => __('Dashboard')])

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold">{{ __('Hello, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')]) }}</h1>
            <p class="mt-1 text-slate-600">{{ __('Here is what is happening with your shipments.') }}</p>
        </div>
        <a href="{{ lroute('account.shipments.create') }}" class="btn-primary"><x-lucide name="plus" class="size-4" /> {{ __('New shipment') }}</a>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['package', __('Active shipments'), $active->count(), 'text-sky-600 bg-sky-50'],
            ['hourglass', __('Awaiting payment'), $awaiting->count(), 'text-amber-600 bg-amber-50'],
            ['shield-check', __('Payments under review'), $underReview->count(), 'text-violet-600 bg-violet-50'],
        ] as [$icon, $label, $count, $tone])
            <div class="card flex items-center gap-4 p-5">
                <span class="grid size-12 place-items-center rounded-2xl {{ $tone }}"><x-lucide :name="$icon" class="size-6" /></span>
                <div>
                    <p class="font-display text-2xl font-extrabold text-ink-900">{{ $count }}</p>
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    @if ($awaiting->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">{{ __('Awaiting payment') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach ($awaiting as $order)
                    <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div class="flex-1">
                            <p class="font-semibold text-ink-900">{{ $order->number }} · {{ $order->shipment?->originLabel() }} → {{ $order->shipment?->destinationLabel() }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ __('Reference') }} <span class="font-mono">{{ $order->payment_reference }}</span> · {{ \App\Support\Money::format($order->total, $order->currency) }}</p>
                        </div>
                        @if ($order->expires_at)
                            <div x-data="countdown" data-until="{{ $order->expires_at->toIso8601String() }}" class="text-sm" :class="urgent ? 'text-red-600' : 'text-slate-600'">
                                <x-lucide name="timer" class="mr-1 inline size-4" /> <span class="font-mono" x-text="remaining"></span>
                            </div>
                        @endif
                        <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                        <a href="{{ lroute('account.orders.pay', $order) }}" class="btn-primary !py-2">{{ __('Pay now') }}</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($underReview->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-bold">{{ __('Payments under review') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach ($underReview as $order)
                    <a href="{{ lroute('account.orders.pay', $order) }}" class="card card-hover flex items-center gap-4 p-5">
                        <span class="grid size-10 place-items-center rounded-xl bg-violet-50 text-violet-600"><x-lucide name="hourglass" class="size-5" /></span>
                        <div class="flex-1">
                            <p class="font-semibold text-ink-900">{{ $order->number }}</p>
                            <p class="text-sm text-slate-500">{{ __('Our team is checking your proof of payment. Target review time: :minutes minutes during staffed hours.', ['minutes' => config('platform.settings.review_target_minutes')]) }}</p>
                        </div>
                        <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">{{ __('Active shipments') }}</h2>
            <a href="{{ lroute('account.shipments') }}" class="link text-sm">{{ __('View all') }}</a>
        </div>
        @if ($active->isEmpty())
            <div class="card mt-4 flex flex-col items-center p-10 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-500"><x-lucide name="package" class="size-7" /></span>
                <p class="mt-4 font-semibold text-ink-900">{{ __('No active shipments') }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ __('Book your first shipment in under five minutes.') }}</p>
                <a href="{{ lroute('quote') }}" class="btn-dark mt-5">{{ __('Get a quote') }}</a>
            </div>
        @else
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($active as $shipment)
                    <a href="{{ lroute('account.shipments.show', $shipment) }}" class="card card-hover p-5">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-mono text-sm font-bold text-ink-900">{{ $shipment->tracking_number }}</p>
                            <x-status-badge :status="$shipment->status->value" :label="$shipment->status->label()" />
                        </div>
                        <p class="mt-3 text-sm text-slate-600">{{ $shipment->originLabel() }} → {{ $shipment->destinationLabel() }}</p>
                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-gradient-to-r from-route-500 to-brand-500" style="width: {{ (int) round(($shipment->progress ?: $shipment->status->defaultProgress()) * 100) }}%"></div></div>
                        @if ($shipment->events->first())
                            <p class="mt-3 text-xs text-slate-500">{{ $shipment->events->first()->label }} · {{ $shipment->events->first()->occurred_at->diffForHumans() }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
