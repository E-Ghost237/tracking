@extends('layouts.app', ['title' => $page->title, 'description' => $page->summary, 'noindex' => $preview ?? false])

@section('content')
    @if ($preview ?? false)
        <div class="bg-amber-400 py-2 text-center text-sm font-semibold text-ink-900">{{ __('Preview — this version is not published yet.') }}</div>
    @endif
    <x-page-header :eyebrow="in_array($slug, ['customs', 'packing', 'about']) ? __('Guide') : __('Legal')" :title="$page->title" :lead="$page->summary" />
    <div class="container-page grid gap-12 py-14 lg:grid-cols-4">
        <article class="prose-content lg:col-span-3">{!! $html !!}</article>
        <aside class="space-y-3 text-sm">
            <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Last updated') }}</p>
            <p class="text-slate-700">{{ $page->updated_at?->translatedFormat('j F Y') }}</p>
            <a href="{{ lroute('contact') }}" class="link inline-flex items-center gap-1">{{ __('Questions? Contact us') }} <x-lucide name="arrow-right" class="size-4" /></a>
        </aside>
    </div>
@endsection
