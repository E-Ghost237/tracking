@extends('layouts.account', ['title' => __('Orders and invoices')])

@section('account')
    <h1 class="text-2xl font-extrabold">{{ __('Orders and invoices') }}</h1>
    <div class="mt-6 space-y-4">
        @forelse ($orders as $order)
            <article class="card p-5">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink-900">{{ $order->number }}</p>
                        <p class="text-sm text-slate-500">{{ $order->created_at->translatedFormat('j M Y') }} · {{ $order->shipment?->originLabel() }} → {{ $order->shipment?->destinationLabel() }}</p>
                    </div>
                    <p class="font-display text-lg font-bold text-ink-900">{{ \App\Support\Money::format($order->total, $order->currency) }}</p>
                    <x-status-badge :status="$order->status->value" :label="$order->status->label()" />
                    @if ($order->status->isOpen())
                        <a href="{{ lroute('account.orders.pay', $order) }}" class="btn-primary !py-2">{{ in_array($order->status->value, ['proof_submitted', 'under_review']) ? __('View status') : __('Pay now') }}</a>
                    @endif
                </div>
                @if ($order->invoices->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                        @foreach ($order->invoices as $invoice)
                            <a href="{{ lroute('account.orders.invoice', ['order' => $order->public_id, 'invoice' => $invoice->public_id]) }}" class="inline-flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-xs font-medium text-ink-900 hover:border-brand-300">
                                <x-lucide name="file-down" class="size-3.5 text-brand-500" /> {{ $invoice->type === 'credit_note' ? __('Credit note') : __('Invoice') }} {{ $invoice->number }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="card p-10 text-center text-slate-500">{{ __('No orders yet.') }}</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
