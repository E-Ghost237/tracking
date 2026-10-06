@extends('layouts.account', ['title' => __('Orders and invoices')])

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('Orders and invoices') }}</h1>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Every order you place keeps its own reference and its own payment instructions. A person verifies each payment before the label and the tracking number are released.') }}</p>
        </div>
        <a href="{{ lroute('account.shipments.create') }}" class="btn-primary !py-2"><x-lucide name="plus" class="size-4" /> {{ __('New shipment') }}</a>
    </div>

    @if ($orders->isNotEmpty())
        <p class="mt-5 text-sm text-slate-600">
            <span class="font-semibold text-ink-950 tabular">{{ $orders->total() }}</span>
            {{ __('orders on this account') }}
        </p>

        <div class="mt-6 space-y-3">
            @foreach ($orders as $order)
                @php($open = $order->status->isOpen())
                <article class="card overflow-hidden">
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="font-semibold text-ink-950">{{ $order->number }}</p>
                                <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                            </div>

                            <p class="mt-1.5 text-sm text-slate-600">
                                {{ $order->shipment?->originLabel() }} <span class="text-slate-500">→</span> {{ $order->shipment?->destinationLabel() }}
                                <span class="mx-1.5 text-slate-300">·</span>
                                {{ $order->created_at->translatedFormat('j M Y') }}
                            </p>

                            <p class="mt-1 text-xs text-slate-600">
                                {{ __('Payment reference') }} <span class="font-mono font-semibold text-ink-900">{{ $order->payment_reference }}</span>
                                @if ($order->expires_at && $open)
                                    <span class="mx-1.5 text-slate-300">·</span>
                                    {{ __('Until :time UTC', ['time' => $order->expires_at->translatedFormat('j M, H:i')]) }}
                                @endif
                            </p>

                            @unless ($open)
                                <p class="mt-2 flex items-start gap-1.5 text-xs leading-5 text-slate-600">
                                    <x-lucide name="info" class="mt-0.5 size-3.5 shrink-0 text-slate-500" />
                                    <span>
                                        @if ($order->status === \App\Enums\OrderStatus::Paid)
                                            {{ __('Payment approved. Your label, tracking number and receipt are ready.') }}
                                        @else
                                            {{ __('This order is closed. Request a new quote to book the same route again.') }}
                                        @endif
                                    </span>
                                </p>
                            @endunless
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <p class="text-lg font-bold text-ink-950 tabular">{{ \App\Support\Money::format($order->total, $order->currency) }}</p>
                            @if ($open)
                                <a href="{{ lroute('account.orders.pay', $order) }}" class="btn-primary !py-2">
                                    {{ in_array($order->status->value, ['proof_submitted', 'under_review'], true) ? __('View status') : __('Pay now') }}
                                </a>
                            @elseif ($order->shipment)
                                <a href="{{ lroute('account.shipments.show', $order->shipment) }}" class="btn-ghost !py-2">{{ __('View shipment') }}</a>
                            @endif
                        </div>
                    </div>

                    @if ($order->invoices->isNotEmpty())
                        <dl class="grid gap-px border-t border-line bg-line sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($order->invoices as $invoice)
                                <a href="{{ lroute('account.orders.invoice', ['order' => $order->public_id, 'invoice' => $invoice->public_id]) }}"
                                   class="group flex items-center gap-3 bg-white px-5 py-3.5 text-sm transition hover:bg-surface">
                                    <x-lucide name="file-down" class="size-4 shrink-0 text-brand-600" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-ink-950">{{ $invoice->type === 'credit_note' ? __('Credit note') : __('Invoice') }} {{ $invoice->number }}</span>
                                        <span class="block text-xs text-slate-600">{{ __('Download PDF') }}</span>
                                    </span>
                                    <x-lucide name="download" class="size-4 shrink-0 text-slate-500 group-hover:text-ink-900" />
                                </a>
                            @endforeach
                        </dl>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @else
        <div class="card mt-6 p-8 text-center lg:p-10">
            <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="receipt" class="size-6" /></span>
            <p class="mt-4 font-semibold text-ink-950">{{ __('No orders yet') }}</p>
            <p class="mx-auto mt-1.5 max-w-lg text-sm leading-6 text-slate-600">{{ __('An order is created when you confirm a booking. It carries the price, the payment reference and the invoices for that shipment.') }}</p>
            <div class="mt-5 flex flex-wrap justify-center gap-3">
                <a href="{{ lroute('quote') }}" class="btn-primary !py-2">{{ __('Get a quote') }}</a>
                <a href="{{ lroute('account.shipments.create') }}" class="btn-ghost !py-2">{{ __('Start a shipment') }}</a>
            </div>
        </div>
    @endif

    {{-- How the money side works, in the order it happens --}}
    <section class="mt-9">
        <h2 class="text-lg font-bold">{{ __('How an order is settled') }}</h2>
        <div class="mt-4 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">
            <div class="bg-white p-5">
                <p class="flex items-center gap-2 text-sm font-bold text-ink-950"><x-lucide name="calculator" class="size-4 text-ink-600" /> {{ __('1. Price confirmed') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('The price comes from the live rate card for your route, weight and service. It is frozen on the order for the validity window shown above.') }}</p>
            </div>
            <div class="bg-white p-5">
                <p class="flex items-center gap-2 text-sm font-bold text-ink-950"><x-lucide name="shield-check" class="size-4 text-ink-600" /> {{ __('2. Payment verified by a person') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('You transfer the amount, then upload the receipt. A verifier matches the amount, the date and the reference. Target time: :minutes minutes during staffed hours (:hours).', ['minutes' => (int) config('platform.settings.review_target_minutes'), 'hours' => config('platform.settings.staffed_hours')]) }}</p>
            </div>
            <div class="bg-white p-5">
                <p class="flex items-center gap-2 text-sm font-bold text-ink-950"><x-lucide name="file-down" class="size-4 text-ink-600" /> {{ __('3. Documents released') }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Approval releases the shipping label, the tracking number and the receipt. The shipment then becomes visible in public tracking.') }}</p>
            </div>
        </div>
        <p class="mt-4 flex items-start gap-2 rounded-[4px] bg-surface px-4 py-3 text-xs leading-5 text-slate-600">
            <x-lucide name="triangle-alert" class="mt-0.5 size-3.5 shrink-0 text-amber-600" />
            <span>{{ __('Only pay for an order you created yourself. If someone asked you to pay for a parcel, or to buy gift cards to release a shipment, stop and contact us: it may be a scam.') }}</span>
        </p>
    </section>
@endsection
