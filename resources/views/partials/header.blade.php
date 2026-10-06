@php
    /*
     * One header, one row: the brand, the service groups, the persistent tracking
     * field and a single account action. Contact details, the guides and the
     * remaining pages live inside the menus and in the footer, so nothing is
     * repeated in a second strip above the bar.
     */
    $shipMenu = [
        // Published windows, resolved by the view composer in AppServiceProvider.
        ['air', 'plane', __('Air freight'), __('Scheduled flights for parcels, samples and stock'), $transit['air'] ?? null],
        ['sea', 'ship', __('Sea freight'), __('Shared container space for larger, heavier loads'), $transit['sea'] ?? null],
        ['road', 'truck', __('Road freight'), __('Direct collection and delivery on land routes'), $transit['road'] ?? null],
        ['express', 'zap', __('Express'), __('Priority handling on the next available flight'), $transit['express'] ?? null],
    ];

    $networkMenu = [
        ['network', 'globe', __('Network and coverage'), __('Supported routes, hubs and handoff points')],
        ['locations', 'map-pinned', __('Pickup and drop-off'), __('Collection points and local offices')],
        ['status', 'triangle-alert', __('Service alerts'), __('Current route and carrier notices')],
    ];

    // Track has its own item in the bar, so it is not repeated here.
    $supportMenu = [
        ['help', 'life-buoy', __('Help center'), __('Answers on booking, payments and customs')],
        ['contact', 'headset', __('Contact us'), __('Replies in English and French, within one business day')],
        ['page.claims-policy', 'file-warning', __('Claims'), __('How to report loss or damage')],
        ['page.about', 'landmark', __('About us'), __('Who we are and how we work')],
    ];

    $current = request()->route()?->getName() ?? '';
    $isActive = function (array $routes) use ($current): bool {
        foreach ($routes as $route) {
            if ($current === app()->getLocale().'.'.$route || str_contains($current, '.'.$route.'.')) {
                return true;
            }
        }

        return false;
    };
@endphp
{{--
    The desktop panels open on hover and on keyboard focus through CSS
    (.nav-group / .nav-panel in resources/css/app.css), so a menu is never left
    standing open and the header works even before JavaScript arrives. Alpine
    only keeps aria-expanded honest, closes the drawer and blurs the trigger on
    Escape.
--}}
<header x-data="siteHeader" @keydown.escape="closeAll()" class="fixed inset-x-0 top-0 z-50 border-b border-line bg-white">
    <div class="container-page flex h-[60px] items-center justify-between gap-3 lg:h-16 lg:gap-4">
        <a href="{{ lroute('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ config('platform.brand.name') }}, {{ __('Home') }}">
            <span class="grid size-8 place-items-center rounded-[4px] bg-ink-900 text-white">
                <x-lucide name="navigation" class="size-4 rotate-45" />
            </span>
            <span class="font-display text-lg font-bold tracking-tight text-ink-950">{{ config('platform.brand.name') }}</span>
        </a>

        <nav class="hidden h-full items-center gap-0.5 lg:flex" aria-label="{{ __('Main') }}">
            {{-- Ship: the four modes, the comparison page and the planning tools --}}
            <div class="nav-group" @mouseenter="open('ship')" @mouseleave="scheduleClose('ship')">
                <button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-2 text-sm font-semibold transition {{ $isActive(['quote', 'services', 'rates']) ? 'text-brand-600' : 'text-ink-900 hover:bg-surface' }}"
                        :aria-expanded="isOpen('ship')" aria-haspopup="true" aria-controls="menu-ship">
                    {{ __('Ship') }} <x-lucide name="chevron-down" class="size-4" />
                </button>
                <div id="menu-ship" class="nav-panel left-0 w-[640px] rounded-[6px] border border-line bg-white p-5 shadow-[var(--shadow-lg)]"
                     @mouseenter="open('ship')" @mouseleave="scheduleClose('ship')">
                    <div class="grid grid-cols-[1.35fr_1fr] gap-5">
                        <ul class="space-y-0.5">
                            @foreach ($shipMenu as [$slug, $icon, $name, $text, $window])
                                <li>
                                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$slug)]) }}" class="flex gap-3 rounded-[4px] p-2.5 transition hover:bg-surface">
                                        <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide :name="$icon" class="size-4.5" /></span>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-2 text-sm font-semibold text-ink-950">{{ $name }}@if ($window)<span class="text-xs font-medium text-slate-500 tabular">{{ $window }}</span>@endif</span>
                                            <span class="mt-0.5 block text-xs leading-5 text-slate-600">{{ $text }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                            <li class="mt-1 border-t border-line pt-2">
                                <a href="{{ lroute('services') }}" class="flex items-center gap-2 rounded-[4px] p-2.5 text-sm font-semibold text-ink-900 transition hover:bg-surface">
                                    <x-lucide name="boxes" class="size-4 text-slate-500" />
                                    {{ __('Compare all services') }}
                                    <x-lucide name="arrow-right" class="ml-auto size-4 text-slate-500" />
                                </a>
                            </li>
                        </ul>
                        <div class="border-l border-line pl-5">
                            <p class="text-xs font-semibold tracking-[0.08em] text-slate-500 uppercase">{{ __('Tools') }}</p>
                            <ul class="mt-2.5 space-y-2 text-sm">
                                <li><a href="{{ lroute('quote') }}" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600"><x-lucide name="calculator" class="size-4 text-slate-500" /> {{ __('Get a quote') }}</a></li>
                                <li><a href="{{ lroute('rates') }}" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600"><x-lucide name="receipt" class="size-4 text-slate-500" /> {{ __('Rates and transit times') }}</a></li>
                                <li><a href="{{ lroute('page.packing') }}" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600"><x-lucide name="box" class="size-4 text-slate-500" /> {{ __('Packing guide') }}</a></li>
                                <li><a href="{{ lroute('page.customs') }}" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600"><x-lucide name="file-text" class="size-4 text-slate-500" /> {{ __('Customs guide') }}</a></li>
                                <li><a href="{{ lroute('page.prohibited-items') }}" class="flex items-center gap-2 font-medium text-ink-900 hover:text-brand-600"><x-lucide name="triangle-alert" class="size-4 text-slate-500" /> {{ __('Prohibited items') }}</a></li>
                            </ul>
                            <a href="{{ lroute('quote') }}" class="btn-primary mt-4 w-full !py-2">{{ __('Get a quote') }}</a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Track --}}
            <a href="{{ lroute('track') }}" @if ($isActive(['track'])) aria-current="page" @endif
               class="rounded-[4px] px-3 py-2 text-sm font-semibold transition {{ $isActive(['track']) ? 'text-brand-600' : 'text-ink-900 hover:bg-surface' }}">{{ __('Track') }}</a>

            {{-- Rates --}}
            <a href="{{ lroute('rates') }}" @if ($isActive(['rates'])) aria-current="page" @endif
               class="rounded-[4px] px-3 py-2 text-sm font-semibold transition {{ $isActive(['rates']) ? 'text-brand-600' : 'text-ink-900 hover:bg-surface' }}">{{ __('Rates') }}</a>

            {{-- Network --}}
            <div class="nav-group" @mouseenter="open('network')" @mouseleave="scheduleClose('network')">
                <button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-2 text-sm font-semibold transition {{ $isActive(['network', 'locations']) ? 'text-brand-600' : 'text-ink-900 hover:bg-surface' }}"
                        :aria-expanded="isOpen('network')" aria-haspopup="true" aria-controls="menu-network">
                    {{ __('Network') }} <x-lucide name="chevron-down" class="size-4" />
                </button>
                <div id="menu-network" class="nav-panel left-0 w-[380px] rounded-[6px] border border-line bg-white p-3 shadow-[var(--shadow-lg)]"
                     @mouseenter="open('network')" @mouseleave="scheduleClose('network')">
                    <ul class="space-y-0.5">
                        @foreach ($networkMenu as [$route, $icon, $name, $text])
                            <li>
                                <a href="{{ lroute($route) }}" class="flex gap-3 rounded-[4px] p-2.5 transition hover:bg-surface">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide :name="$icon" class="size-4.5" /></span>
                                    <span>
                                        <span class="block text-sm font-semibold text-ink-950">{{ $name }}</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-slate-600">{{ $text }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Support: the answers, the team and the two policy pages people ask for --}}
            <div class="nav-group" @mouseenter="open('support')" @mouseleave="scheduleClose('support')">
                <button type="button" class="flex items-center gap-1.5 rounded-[4px] px-3 py-2 text-sm font-semibold transition {{ $isActive(['help', 'contact', 'page.claims-policy', 'page.about']) ? 'text-brand-600' : 'text-ink-900 hover:bg-surface' }}"
                        :aria-expanded="isOpen('support')" aria-haspopup="true" aria-controls="menu-support">
                    {{ __('Support') }} <x-lucide name="chevron-down" class="size-4" />
                </button>
                <div id="menu-support" class="nav-panel right-0 w-[420px] rounded-[6px] border border-line bg-white p-3 shadow-[var(--shadow-lg)]"
                     @mouseenter="open('support')" @mouseleave="scheduleClose('support')">
                    <ul class="space-y-0.5">
                        @foreach ($supportMenu as [$route, $icon, $name, $text])
                            <li>
                                <a href="{{ lroute($route) }}" class="flex gap-3 rounded-[4px] p-2.5 transition hover:bg-surface">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide :name="$icon" class="size-4.5" /></span>
                                    <span>
                                        <span class="block text-sm font-semibold text-ink-950">{{ $name }}</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-slate-600">{{ $text }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    {{-- Contact details sit in the menu as well as the footer, so the header never needs a second row --}}
                    <div class="mt-2 border-t border-line px-2.5 pt-3 text-xs text-slate-600">
                        <p class="flex items-center gap-1.5"><x-lucide name="mail" class="size-3.5 text-slate-500" /> <a class="font-medium text-ink-900 hover:text-brand-600" href="mailto:{{ config('platform.brand.support_email') }}">{{ config('platform.brand.support_email') }}</a></p>
                        <p class="mt-1.5 flex items-center gap-1.5"><x-lucide name="phone" class="size-3.5 text-slate-500" /> <a class="font-medium text-ink-900 hover:text-brand-600" href="tel:{{ preg_replace('/[^0-9+]/', '', config('platform.brand.support_phone')) }}">{{ config('platform.brand.support_phone') }}</a></p>
                        <p class="mt-1.5 flex items-center gap-1.5"><x-lucide name="clock" class="size-3.5 text-slate-500" /> {{ config('platform.settings.staffed_hours') }}</p>
                    </div>
                </div>
            </div>
        </nav>

        <div class="flex items-center gap-1.5 lg:gap-2">
            {{-- Tracking field: available on every page --}}
            <form method="GET" action="{{ lroute('track') }}" class="relative hidden xl:block">
                <label for="header-track" class="sr-only">{{ __('Tracking number') }}</label>
                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                <input id="header-track" name="numbers" type="search" autocomplete="off" spellcheck="false"
                       class="h-10 w-56 rounded-[4px] border border-slate-300 bg-surface pr-3 pl-9 text-sm text-ink-900 transition placeholder:text-slate-500 hover:border-slate-400 focus:border-ink-900 focus:bg-white focus:ring-2 focus:ring-ink-900/10 focus:outline-none"
                       placeholder="{{ __('Tracking number') }}">
            </form>

            <a href="{{ alternate_url(app()->getLocale() === 'fr' ? 'en' : 'fr') }}"
               hreflang="{{ app()->getLocale() === 'fr' ? 'en' : 'fr' }}"
               class="hidden items-center gap-1.5 rounded-[4px] px-2.5 py-2 text-xs font-semibold tracking-wide text-ink-700 uppercase transition hover:bg-surface lg:inline-flex"
               aria-label="{{ app()->getLocale() === 'fr' ? 'English' : 'Français' }}">
                <x-lucide name="languages" class="size-4" />
                {{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}
            </a>

            @auth
                <a href="{{ lroute('account.dashboard') }}" class="hidden items-center gap-2 rounded-[4px] px-3 py-2 text-sm font-semibold text-ink-900 transition hover:bg-surface lg:inline-flex">
                    <x-lucide name="layout-dashboard" class="size-4" /> {{ __('Account') }}
                </a>
            @else
                <a href="{{ lroute('login') }}" class="hidden rounded-[4px] px-3 py-2 text-sm font-semibold text-ink-900 transition hover:bg-surface lg:inline-flex">{{ __('Sign in') }}</a>
            @endauth

            <a href="{{ lroute('quote') }}" class="btn-primary !py-2">{{ __('Get a quote') }}</a>

            <button type="button" class="grid size-10 place-items-center rounded-[4px] text-ink-900 transition hover:bg-surface lg:hidden"
                    @click="toggleMobile()" :aria-expanded="mobileOpen" aria-controls="mobile-menu" aria-label="{{ __('Menu') }}">
                <x-lucide name="menu" class="size-5" x-show="!mobileOpen" />
                <span x-show="mobileOpen" x-cloak><x-lucide name="x" class="size-5" /></span>
            </button>
        </div>
    </div>

    {{-- Mobile and tablet navigation: everything the bar hides, in one column --}}
    <div id="mobile-menu" x-show="mobileOpen" x-cloak class="max-h-[calc(100vh-var(--header-h))] overflow-y-auto border-t border-line bg-white lg:hidden">
        <div class="container-page py-4">
            <form method="GET" action="{{ lroute('track') }}" class="relative mb-4">
                <label for="mobile-track" class="sr-only">{{ __('Tracking number') }}</label>
                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-500" />
                <input id="mobile-track" name="numbers" type="search" autocomplete="off" spellcheck="false"
                       class="h-12 w-full rounded-[4px] border border-slate-300 bg-surface pr-4 pl-10 text-sm text-ink-900 placeholder:text-slate-500 focus:border-ink-900 focus:bg-white focus:outline-none"
                       placeholder="{{ __('Enter a tracking number') }}">
            </form>

            <div class="grid grid-cols-2 gap-2">
                @foreach ($shipMenu as [$slug, $icon, $name, $text, $transit])
                    <a href="{{ lroute('services.show', ['mode' => trans('routes.mode_'.$slug)]) }}" class="flex items-center gap-2.5 rounded-[4px] border border-line px-3 py-2.5 text-sm font-semibold text-ink-900">
                        <x-lucide :name="$icon" class="size-4 text-ink-600" /> {{ $name }}
                    </a>
                @endforeach
            </div>

            <nav class="mt-4 divide-y divide-line border-t border-line" aria-label="{{ __('Mobile') }}">
                @foreach ([['track', __('Track a shipment')], ['quote', __('Get a quote')], ['rates', __('Rates and transit times')], ['network', __('Network and coverage')], ['locations', __('Pickup and drop-off')], ['help', __('Help center')], ['status', __('Service alerts')], ['contact', __('Contact us')], ['page.about', __('About us')]] as [$route, $label])
                    <a href="{{ lroute($route) }}" class="flex items-center justify-between py-3 text-sm font-medium text-ink-900">
                        {{ $label }} <x-lucide name="chevron-right" class="size-4 text-slate-500" />
                    </a>
                @endforeach
            </nav>

            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                @auth
                    <a href="{{ lroute('account.dashboard') }}" class="btn-dark flex-1">{{ __('My account') }}</a>
                @else
                    <a href="{{ lroute('login') }}" class="btn-ghost flex-1">{{ __('Sign in') }}</a>
                    <a href="{{ lroute('register') }}" class="btn-dark flex-1">{{ __('Create account') }}</a>
                @endauth
                <a href="{{ alternate_url(app()->getLocale() === 'fr' ? 'en' : 'fr') }}" class="btn-ghost">
                    <x-lucide name="languages" class="size-4" /> {{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}
                </a>
            </div>

            <div class="mt-4 space-y-2 text-sm text-slate-600">
                <p class="flex items-center gap-2"><x-lucide name="phone" class="size-4 text-slate-500" /> <a class="font-medium text-ink-900" href="tel:{{ preg_replace('/[^0-9+]/', '', config('platform.brand.support_phone')) }}">{{ config('platform.brand.support_phone') }}</a></p>
                @if (config('platform.brand.whatsapp'))
                    <p class="flex items-center gap-2"><x-lucide name="message-circle" class="size-4 text-slate-500" /> <a class="font-medium text-ink-900" href="https://wa.me/{{ preg_replace('/\D/', '', config('platform.brand.whatsapp')) }}" rel="noopener noreferrer" target="_blank">{{ __('WhatsApp') }}</a></p>
                @endif
                <p class="flex items-center gap-2"><x-lucide name="mail" class="size-4 text-slate-500" /> <a class="font-medium text-ink-900" href="mailto:{{ config('platform.brand.support_email') }}">{{ config('platform.brand.support_email') }}</a></p>
            </div>
        </div>
    </div>
</header>
