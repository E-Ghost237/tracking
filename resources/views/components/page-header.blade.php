@props(['eyebrow' => null, 'title', 'lead' => null, 'icon' => null])
<section class="relative overflow-hidden border-b border-line bg-gradient-to-b from-surface to-white">
    <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-brand-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 left-10 size-72 rounded-full bg-route-400/10 blur-3xl"></div>
    <div class="container-page relative py-14 sm:py-16">
        @if ($eyebrow)
            <p class="eyebrow">@if ($icon)<x-lucide :name="$icon" class="size-4" />@endif {{ $eyebrow }}</p>
        @endif
        <h1 class="mt-3 max-w-3xl text-3xl font-extrabold sm:text-5xl">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
