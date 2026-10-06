@extends('layouts.app', ['title' => __('Help center'), 'description' => __('Answers about tracking, payments, customs and claims.')])

@section('content')
    <div x-data="faqSearch">
        <x-page-header :eyebrow="__('Help center')" icon="life-buoy" :title="__('How can we help?')">
            <div class="relative mt-8 max-w-xl">
                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                <label for="faq-search" class="sr-only">{{ __('Search the help center') }}</label>
                <input id="faq-search" x-model="query" type="search" class="field h-14 !rounded-2xl !pl-12 text-base" placeholder="{{ __('Search: customs, payment proof, delivery time…') }}">
            </div>
        </x-page-header>

        <div class="container-page grid gap-10 py-14 lg:grid-cols-4">
            <nav class="hidden lg:block" aria-label="{{ __('Topics') }}">
                <ul class="sticky top-24 space-y-1 text-sm">
                    @foreach ($faqs as $category => $items)
                        <li><a href="#faq-{{ \Illuminate\Support\Str::slug($category) }}" class="block rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-surface hover:text-ink-900">{{ __('faq.'.$category) }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="space-y-12 lg:col-span-3">
                @foreach ($faqs as $category => $items)
                    <section id="faq-{{ \Illuminate\Support\Str::slug($category) }}" class="scroll-mt-24">
                        <h2 class="text-xl font-bold">{{ __('faq.'.$category) }}</h2>
                        <div class="mt-4 divide-y divide-line rounded-2xl border border-line">
                            @foreach ($items as $faq)
                                <details class="group p-5 [&_summary::-webkit-details-marker]:hidden" x-show="matches($el.dataset.text)" data-text="{{ $faq['question'].' '.$faq['text'] }}">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink-900">
                                        {{ $faq['question'] }}
                                        <x-lucide name="chevron-down" class="size-5 shrink-0 text-slate-400 transition group-open:rotate-180" />
                                    </summary>
                                    <div class="prose-content mt-3">{!! $faq['html'] !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                <div class="card flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-lucide name="headset" class="size-6" /></span>
                    <div class="flex-1">
                        <p class="font-display font-bold text-ink-900">{{ __('Still need help?') }}</p>
                        <p class="text-sm text-slate-600">{{ __('Write to us and we will reply within one business day.') }}</p>
                    </div>
                    <a href="{{ lroute('contact') }}" class="btn-dark">{{ __('Contact us') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
