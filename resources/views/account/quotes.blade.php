@extends('layouts.account', ['title' => __('Saved quotes')])

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-extrabold">{{ __('Saved quotes') }}</h1>
        <a href="{{ lroute('quote') }}" class="btn-primary"><x-lucide name="calculator" class="size-4" /> {{ __('New quote') }}</a>
    </div>
    <div class="mt-6 space-y-3">
        @forelse ($quotes as $quote)
            <article class="card flex flex-wrap items-center gap-4 p-5">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm font-bold text-ink-900">{{ $quote->reference }}</p>
                    <p class="text-sm text-slate-600">{{ $quote->origin['city'] }}, {{ $quote->origin['country'] }} → {{ $quote->destination['city'] }}, {{ $quote->destination['country'] }} · {{ ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')][$quote->mode] ?? $quote->mode }} · {{ $quote->chargeable_weight_kg }} kg</p>
                </div>
                <p class="font-display text-lg font-bold">{{ \App\Support\Money::format($quote->total, $quote->currency) }}</p>
                @if ($quote->booked_at)
                    <span class="badge bg-emerald-50 text-emerald-700">{{ __('Booked') }}</span>
                @elseif ($quote->isExpired())
                    <span class="badge bg-slate-100 text-slate-600">{{ __('Expired') }}</span>
                @else
                    <span class="text-xs text-slate-500">{{ __('Valid until :date', ['date' => $quote->expires_at->translatedFormat('j M')]) }}</span>
                    <form method="POST" action="{{ lroute('account.quotes.book', $quote) }}">@csrf<button class="btn-dark !py-2">{{ __('Book') }}</button></form>
                @endif
            </article>
        @empty
            <div class="card p-10 text-center text-slate-500">{{ __('No saved quotes yet.') }}</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $quotes->links() }}</div>
@endsection
