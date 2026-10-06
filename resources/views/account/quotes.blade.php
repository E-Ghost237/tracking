@extends('layouts.account', ['title' => __('Saved quotes')])

@section('account')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-bold">{{ __('Saved quotes') }}</h1>
        <a href="{{ lroute('quote') }}" class="btn-primary"><x-lucide name="calculator" class="size-4" /> {{ __('New quote') }}</a>
    </div>
    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Quotes are kept with the price, the route and the weight they were calculated from. A quote can be booked while it is valid; after that, calculate it again so the price follows the current rate card.') }}</p>
    <div class="mt-6 space-y-3">
        @forelse ($quotes as $quote)
            <article class="card flex flex-wrap items-center gap-4 p-5">
                <div class="min-w-0 flex-1">
                    <p class="font-mono text-sm font-bold text-ink-950">{{ $quote->reference }}</p>
                    <p class="text-sm text-slate-600">{{ $quote->origin['city'] }}, {{ $quote->origin['country'] }} → {{ $quote->destination['city'] }}, {{ $quote->destination['country'] }} · {{ ['air' => __('Air'), 'sea' => __('Sea'), 'road' => __('Road'), 'express' => __('Express')][$quote->mode] ?? $quote->mode }} · {{ $quote->chargeable_weight_kg }} kg</p>
                </div>
                <p class="text-lg font-bold text-ink-950 tabular">{{ \App\Support\Money::format($quote->total, $quote->currency) }}</p>
                @if ($quote->booked_at)
                    <span class="badge bg-emerald-50 text-emerald-800">{{ __('Booked') }}</span>
                @elseif ($quote->isExpired())
                    <span class="badge bg-slate-100 text-slate-700">{{ __('Expired') }}</span>
                @else
                    <span class="text-xs text-slate-600 tabular">{{ __('Valid until :date', ['date' => $quote->expires_at->translatedFormat('j M')]) }}</span>
                    <form method="POST" action="{{ lroute('account.quotes.book', $quote) }}">@csrf<button class="btn-dark !py-2">{{ __('Book') }}</button></form>
                @endif
            </article>
        @empty
            <div class="card p-8 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="calculator" class="size-6" /></span>
                <p class="mt-4 font-semibold text-ink-950">{{ __('No saved quotes yet') }}</p>
                <p class="mx-auto mt-1.5 max-w-md text-sm leading-6 text-slate-600">{{ __('Calculate a price on the quote page: each calculation is saved here with the route and the weight, so you can compare services later.') }}</p>
                <a href="{{ lroute('quote') }}" class="btn-primary mt-5 !py-2">{{ __('Calculate a price') }}</a>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $quotes->links() }}</div>
@endsection
