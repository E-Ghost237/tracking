@php
    $brand = config('platform.brand.name');
    $pageTitle = trim(($title ?? '') !== '' ? $title.' · '.$brand : $brand.' · '.__('Air, sea, road and express freight with live tracking'));
    $pageDescription = $description ?? __('Track parcels, compare air, sea and road services, and request a quote for routes across North America, Europe and supported destinations worldwide.');
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#061526">
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach (config('platform.locales') as $alt)
        <link rel="alternate" hreflang="{{ $alt }}" href="{{ alternate_url($alt) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ alternate_url('en') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/freight-air.jpg') }}">
    <meta property="og:image:alt" content="{{ __('Air, sea and road freight across a connected global network') }}">
    <meta property="og:locale" content="{{ $locale === 'fr' ? 'fr_FR' : 'en_US' }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @stack('preload')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $organization = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $brand,
            'url' => config('app.url'),
            'email' => config('platform.brand.support_email'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    @endphp
    <script type="application/ld+json" nonce="{{ request()->attributes->get('csp_nonce') }}">{!! $organization !!}</script>
    @stack('head')
</head>
<body class="min-h-screen bg-paper antialiased {{ $bodyClass ?? '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-[4px] focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>

    @include('partials.header')

    <main id="main" class="pt-[var(--header-h)]">
        @if (session('status'))
            <div class="container-page pt-6" role="status">
                <div class="flex items-start gap-3 rounded-[4px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <x-lucide name="circle-check" class="mt-0.5 size-5 shrink-0 text-emerald-700" />
                    <p>{{ session('status') }}</p>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    @include('partials.footer')

    <script type="application/json" id="i18n">@json(js_strings())</script>
    @stack('data')
</body>
</html>
