@props(['eyebrow' => null, 'title', 'lead' => null, 'icon' => null])
<section class="page-masthead relative isolate overflow-hidden border-b border-line">
    <span class="pointer-events-none absolute inset-y-0 left-0 w-1 bg-brand-500" aria-hidden="true"></span>
    <div class="container-page relative py-14 sm:py-16 lg:py-20">
        @if ($eyebrow)
            <p class="eyebrow eyebrow-rule">@if ($icon)<x-lucide :name="$icon" class="size-4" />@endif {{ $eyebrow }}</p>
        @endif
        <h1 class="editorial-title mt-4 max-w-4xl text-4xl leading-[1.02] text-ink-900 sm:text-6xl">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg sm:leading-8">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
