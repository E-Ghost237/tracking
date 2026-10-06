@php
    $columns = [
        __('Freight services') => [
            ['services', __('All services')],
            ['services.show|air', __('Air freight')],
            ['services.show|sea', __('Sea freight')],
            ['services.show|road', __('Road freight')],
            ['services.show|express', __('Express')],
            ['rates', __('Rates and transit times')],
        ],
        __('Tracking and help') => [
            ['track', __('Track a shipment')],
            ['network', __('Network and coverage')],
            ['locations', __('Pickup and drop-off')],
            ['status', __('Service alerts')],
            ['help', __('Help center')],
            ['contact', __('Contact us')],
        ],
        __('Guides') => [
            ['page.customs', __('Customs guide')],
            ['page.packing', __('Packing guide')],
            ['page.prohibited-items', __('Prohibited items')],
            ['page.shipping-policy', __('Shipping policy')],
            ['page.claims-policy', __('Claims')],
            ['page.about', __('About us')],
        ],
        __('Legal') => [
            ['page.terms', __('Terms of service')],
            ['page.privacy', __('Privacy policy')],
            ['page.cookies', __('Cookie policy')],
            ['page.payment-terms', __('Payment terms')],
            ['page.refund-policy', __('Refund policy')],
        ],
    ];
@endphp
<footer class="bg-ink-950 text-slate-300">
    {{-- Contact band: the details people look for before they trust a shipment to a carrier. --}}
    <div class="border-b border-white/10">
        <div class="container-page grid gap-6 py-8 sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white"><x-lucide name="headset" class="size-4.5" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white">{{ __('Talk to our team') }}</p>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('platform.brand.support_phone')) }}" class="mt-0.5 block truncate text-sm text-slate-300 hover:text-white">{{ config('platform.brand.support_phone') }}</a>
                    @if (config('platform.brand.whatsapp'))
                        <a href="https://wa.me/{{ preg_replace('/\D/', '', config('platform.brand.whatsapp')) }}" rel="noopener noreferrer" target="_blank" class="text-sm text-slate-300 hover:text-white">{{ __('WhatsApp') }}</a>
                    @endif
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white"><x-lucide name="mail" class="size-4.5" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white">{{ __('Email') }}</p>
                    <a href="mailto:{{ config('platform.brand.support_email') }}" class="mt-0.5 block truncate text-sm text-slate-300 hover:text-white">{{ config('platform.brand.support_email') }}</a>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white"><x-lucide name="clock" class="size-4.5" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white">{{ __('Support hours') }}</p>
                    <p class="mt-0.5 text-sm text-slate-300">{{ config('platform.settings.staffed_hours') }}</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-white/10 text-white"><x-lucide name="map-pin" class="size-4.5" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-white">{{ __('Office') }}</p>
                    <p class="mt-0.5 text-sm text-slate-300">{{ config('platform.brand.address') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-page grid gap-10 py-12 lg:grid-cols-[1.3fr_2.7fr] lg:gap-14">
        <div>
            <a href="{{ lroute('home') }}" class="flex items-center gap-2.5">
                <span class="grid size-8 place-items-center rounded-[4px] bg-brand-500 text-white">
                    <x-lucide name="navigation" class="size-4 rotate-45" />
                </span>
                <span class="font-display text-lg font-bold tracking-tight text-white">{{ config('platform.brand.name') }}</span>
            </a>
            <p class="mt-4 max-w-sm text-sm leading-6 text-slate-400">
                {{ __('Air, sea, road and express freight between the United States, Europe and supported destinations worldwide. Quote, book, pay and follow every handoff in one place.') }}
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ lroute('quote') }}" class="btn-primary !py-2">{{ __('Get a quote') }}</a>
                <a href="{{ lroute('track') }}" class="btn-light !py-2">{{ __('Track a shipment') }}</a>
            </div>
            {{-- Account entry point: the header carries a single action, so the footer holds the other one --}}
            <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <a href="{{ lroute('login') }}" class="font-medium text-white transition hover:text-brand-300">{{ __('Sign in') }}</a>
                <span class="text-white/20" aria-hidden="true">·</span>
                <a href="{{ lroute('register') }}" class="font-medium text-white transition hover:text-brand-300">{{ __('Create account') }}</a>
            </p>
        </div>

        <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
            @foreach ($columns as $heading => $links)
                <div>
                    <h2 class="text-xs font-semibold tracking-[0.08em] text-white uppercase">{{ $heading }}</h2>
                    <ul class="mt-3.5 space-y-2.5 text-sm">
                        @foreach ($links as [$route, $label])
                            @php
                                [$name, $mode] = array_pad(explode('|', $route, 2), 2, null);
                                $href = $name === 'services.show'
                                    ? lroute($name, ['mode' => trans('routes.mode_'.$mode)])
                                    : lroute($name);
                            @endphp
                            <li><a href="{{ $href }}" class="text-slate-400 transition hover:text-white">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-page flex flex-col gap-3 py-6 text-xs text-slate-400 md:flex-row md:items-center md:justify-between">
            <p>© {{ now()->year }} {{ config('platform.brand.legal_name') }}. {{ __('All rights reserved.') }}</p>
            <p class="max-w-3xl md:text-right">
                {{ __('USPS, UPS, FedEx and other carrier names are trademarks of their respective owners. They are used only to describe the networks that may handle a leg of a shipment and to recognise their tracking numbers. This platform is not affiliated with, endorsed by or sponsored by these carriers.') }}
            </p>
        </div>
    </div>
</footer>
