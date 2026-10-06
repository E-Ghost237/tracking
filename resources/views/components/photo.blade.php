@props([
    'key',
    'alt' => null,
    'priority' => false,
    'sizes' => null,
    'class' => 'size-full object-cover',
])

@php
    /*
     * The single image primitive for the public site. It resolves a key from
     * config/media.php, serves the WebP with the JPEG as fallback, always sets
     * intrinsic width and height (so nothing shifts while loading) and defaults
     * to lazy loading. Pass priority for above-the-fold imagery.
     *
     * The class and any extra attributes land on the <img>, so utilities such as
     * object-cover, object-right or aspect-[16/9] behave as expected.
     */
    $photo = media($key);
@endphp

{{--
    A priority photo is above the fold, so the browser is told about it before it
    reaches the markup: `@once` keeps a page with several priority photos (a hero
    plus a masthead) from emitting the same preload twice.
--}}
@if ($priority)
    @once
        @push('preload')
            <link rel="preload" as="image" type="image/webp"
                  imagesrcset="{{ $photo['srcset'] }}"
                  @if ($sizes ?? $photo['sizes']) imagesizes="{{ $sizes ?? $photo['sizes'] }}" @endif
                  fetchpriority="high">
        @endpush
    @endonce
@endif
<picture>
    <source type="image/webp" srcset="{{ $photo['srcset'] }}" @if ($sizes ?? $photo['sizes']) sizes="{{ $sizes ?? $photo['sizes'] }}" @endif>
    <img src="{{ $photo['jpg'] }}"
         alt="{{ $alt ?? $photo['alt'] }}"
         width="{{ $photo['width'] }}"
         height="{{ $photo['height'] }}"
         @if ($priority) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif
         {{ $attributes->class($class) }}>
</picture>
