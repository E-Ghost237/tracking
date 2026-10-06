@props(['eyebrow' => null, 'title', 'lead' => null, 'icon' => null, 'photo' => null])
@php
    /*
     * Sub-page masthead: a navy band that anchors every page, with an optional
     * photograph behind a dark wash. The photo is decorative here — the heading
     * carries the meaning — so it is hidden from assistive technology.
     */
    $photo_key = $photo;
    $photo = $photo ? media($photo) : null;
@endphp
<section class="page-masthead relative isolate overflow-hidden">
    @if ($photo)
        {{-- The masthead photo is the largest paint on a sub-page, so it is served as
             WebP from the same registry as every other photograph and preloaded. --}}
        <div class="absolute inset-0 -z-20" aria-hidden="true">
            <x-photo :key="$photo_key" class="size-full object-cover object-center" sizes="100vw" priority />
        </div>
        <div class="absolute inset-0 -z-10 bg-ink-950/85" aria-hidden="true"></div>
    @endif

    <div class="container-page py-10 sm:py-12 lg:py-14">
        @if ($eyebrow)
            <p class="eyebrow !text-slate-300">@if ($icon)<x-lucide :name="$icon" class="size-4" />@endif {{ $eyebrow }}</p>
        @endif
        <h1 class="mt-3 max-w-4xl text-[1.75rem] leading-[1.12] font-bold text-white sm:text-4xl lg:text-[2.6rem]">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-4 max-w-2xl text-[15px] leading-7 text-slate-300 sm:text-base">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
