@extends('layouts.app', ['title' => $page->title, 'description' => $page->summary, 'noindex' => $preview ?? false])

@section('content')
    @if ($preview ?? false)
        <div class="border-b border-amber-300 bg-amber-100 py-2.5 text-center text-sm font-semibold text-ink-900">{{ __('Preview: this version is not published yet.') }}</div>
    @endif
    <x-page-header :eyebrow="in_array($slug, ['customs', 'packing', 'about']) ? __('Guide') : __('Legal')" :title="$page->title" :lead="$page->summary" />
    <div class="container-page grid gap-12 py-14 lg:grid-cols-4">
        <article class="prose-content lg:col-span-3">{!! $html !!}</article>
        <aside class="h-fit space-y-4 rounded-[6px] border border-line bg-white p-5 text-sm lg:sticky lg:top-[calc(var(--header-h)+1.5rem)]">
            <div>
                <p class="text-[10px] font-semibold tracking-[0.15em] text-slate-500 uppercase">{{ __('Last updated') }}</p>
                <p class="mt-1 font-medium text-ink-900">{{ $page->updated_at?->translatedFormat('j F Y') }}</p>
            </div>
            <div class="border-t border-line pt-4">
                <p class="font-display font-bold text-ink-900">{{ __('Need a hand with a shipment?') }}</p>
                <p class="mt-2 leading-6 text-slate-600">{{ __('Our team can help you understand the next step or point you to the right guide.') }}</p>
                <a href="{{ lroute('contact') }}" class="link mt-3 inline-flex items-center gap-1">{{ __('Ask our team') }} <x-lucide name="arrow-right" class="size-4" /></a>
            </div>
        </aside>
    </div>
@endsection
