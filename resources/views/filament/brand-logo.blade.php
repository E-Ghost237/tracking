{{--
    The back-office brand lockup. It is the same mark as the public header
    (navy tile, 45° navigation glyph, wordmark), so staff and customers see one
    product. Colours come from the `--cv-*` tokens in the admin theme, which is
    why no colour utility appears here.
--}}
<span class="flex items-center gap-2.5">
    <span class="fi-logo-mark">
        <x-lucide name="navigation" class="size-4 rotate-45" />
    </span>
    <span>{{ config('platform.brand.name') }}</span>
</span>
