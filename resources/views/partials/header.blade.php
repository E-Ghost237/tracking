@php
    $nav = [
        ['label' => __('Track'), 'route' => 'track', 'icon' => 'radar'],
        ['label' => __('Get a quote'), 'route' => 'quote', 'icon' => 'calculator'],
        ['label' => __('Services'), 'route' => 'services', 'icon' => 'boxes'],
        ['label' => __('Network'), 'route' => 'network', 'icon' => 'globe'],
        ['label' => __('Rates'), 'route' => 'rates', 'icon' => 'receipt'],
        ['label' => __('Help'), 'route' => 'help', 'icon' => 'life-buoy'],
    ];
    $current = request()->route()?->getName() ?? '';
@endphp
<header x-data="siteHeader"
        class="fixed inset-x-0 top-0 z-50 transition-colors duration-300"
        :class="scrolled || open ? 'bg-white/90 shadow-[0_1px_0_rgb(10_22_40/0.06)] backdrop-blur-xl {{ $dark ? 'is-solid' : '' }}' : '{{ $dark ? 'bg-transparent' : 'bg-white/90 backdrop-blur-xl shadow-[0_1px_0_rgb(10_22_40/0.06)]' }}'">
    <div class="container-page flex h-[72px] items-center justify-between gap-6">
        <a href="{{ lroute('home') }}" class="group flex items-center gap-2.5" aria-label="{{ config('platform.brand.name') }}, {{ __('Home') }}">
            <span class="grid size-9 place-items-center rounded-xl bg-brand-500 text-white shadow-[0_6px_16px_-6px_rgb(197_71_39/0.65)] transition group-hover:rotate-[-4deg]">
                <x-lucide name="navigation" class="size-[18px] rotate-45" />
            </span>
            <span class="font-display text-[19px] font-extrabold tracking-tight"
                  :class="scrolled || open || {{ $dark ? 'false' : 'true' }} ? 'text-ink-900' : 'text-white'">{{ config('platform.brand.name') }}</span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('Main') }}">
            @foreach ($nav as $item)
                @php($active = str_ends_with($current, '.'.$item['route']) || str_contains($current, '.'.$item['route'].'.'))
                <a href="{{ lroute($item['route']) }}"
                   @if ($active) aria-current="page" @endif
                   class="rounded-full px-3.5 py-2 text-sm font-medium transition"
                   :class="scrolled || {{ $dark ? 'false' : 'true' }} ? '{{ $active ? 'bg-ink-900/5 text-ink-900' : 'text-slate-600 hover:text-ink-900 hover:bg-ink-900/5' }}' : '{{ $active ? 'bg-white/15 text-white' : 'text-white/80 hover:text-white hover:bg-white/10' }}'">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ alternate_url(app()->getLocale() === 'fr' ? 'en' : 'fr') }}"
               hreflang="{{ app()->getLocale() === 'fr' ? 'en' : 'fr' }}"
               class="hidden items-center gap-1.5 rounded-full px-3 py-2 text-xs font-semibold tracking-wide uppercase transition sm:inline-flex"
               :class="scrolled || {{ $dark ? 'false' : 'true' }} ? 'text-slate-600 hover:bg-ink-900/5' : 'text-white/80 hover:bg-white/10'"
               aria-label="{{ app()->getLocale() === 'fr' ? 'English' : 'Français' }}">
                <x-lucide name="languages" class="size-4" />
                {{ app()->getLocale() === 'fr' ? 'EN' : 'FR' }}
            </a>
            @auth
                <a href="{{ lroute('account.dashboard') }}" class="hidden sm:inline-flex btn-dark !py-2">
                    <x-lucide name="user" class="size-4" /> {{ __('My account') }}
                </a>
            @else
                <a href="{{ lroute('login') }}" class="hidden rounded-full px-3.5 py-2 text-sm font-semibold transition sm:inline-flex"
                   :class="scrolled || {{ $dark ? 'false' : 'true' }} ? 'text-ink-900 hover:bg-ink-900/5' : 'text-white hover:bg-white/10'">{{ __('Sign in') }}</a>
                <a href="{{ lroute('quote') }}" class="hidden btn-primary !py-2 sm:inline-flex">{{ __('Ship now') }}</a>
            @endauth
            <button type="button" class="grid size-10 place-items-center rounded-full transition lg:hidden"
                    :class="scrolled || open || {{ $dark ? 'false' : 'true' }} ? 'text-ink-900 hover:bg-ink-900/5' : 'text-white hover:bg-white/10'"
                    @click="toggle()" :aria-expanded="open" aria-controls="mobile-menu" aria-label="{{ __('Menu') }}">
                <x-lucide name="menu" class="size-6" x-show="!open" />
                <span x-show="open" x-cloak><x-lucide name="x" class="size-6" /></span>
            </button>
        </div>
    </div>

    <div id="mobile-menu" x-show="open" x-cloak x-transition.opacity class="border-t border-line bg-white lg:hidden">
        <nav class="container-page grid gap-1 py-4" aria-label="{{ __('Mobile') }}">
            @foreach ($nav as $item)
                <a href="{{ lroute($item['route']) }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-base font-medium text-ink-900 hover:bg-surface">
                    <x-lucide :name="$item['icon']" class="size-5 text-brand-500" /> {{ $item['label'] }}
                </a>
            @endforeach
            <div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-4">
                @auth
                    <a href="{{ lroute('account.dashboard') }}" class="btn-dark col-span-2">{{ __('My account') }}</a>
                @else
                    <a href="{{ lroute('login') }}" class="btn-ghost">{{ __('Sign in') }}</a>
                    <a href="{{ lroute('register') }}" class="btn-primary">{{ __('Create account') }}</a>
                @endauth
                <a href="{{ alternate_url(app()->getLocale() === 'fr' ? 'en' : 'fr') }}" class="btn-ghost col-span-2">
                    <x-lucide name="languages" class="size-4" /> {{ app()->getLocale() === 'fr' ? 'English' : 'Français' }}
                </a>
            </div>
        </nav>
    </div>
</header>
