@php
    $brand = config('platform.brand.name');
    $pageTitle = trim(($title ?? '') !== '' ? $title.' · '.$brand : $brand.' · '.__('Air, sea and road freight with live tracking'));
    $pageDescription = $description ?? __('Track parcels, get instant freight quotes and book air, sea and road shipments between Africa, Europe and the United States.');
    $locale = app()->getLocale();
    $dark = $darkHeader ?? false;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#0a1628">
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
<body class="min-h-screen bg-white antialiased {{ $bodyClass ?? '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">{{ __('Skip to content') }}</a>

    @include('partials.header', ['dark' => $dark])

    @if (session('status'))
        <div class="container-page pt-24 {{ $dark ? 'absolute inset-x-0 z-40' : '' }}" role="status">
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm">
                <x-lucide name="circle-check" class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <p>{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <main id="main" @class(['pt-[72px]' => ! $dark])>
        @yield('content')
    </main>

    @include('partials.footer')

    <script type="application/json" id="i18n">@json(js_strings())</script>
    @stack('data')
</body>
</html>
