<footer class="relative overflow-hidden bg-ink-950 text-slate-300">
    <div class="pointer-events-none absolute inset-0 grid-bg opacity-60"></div>
    <div class="container-page relative grid gap-12 py-16 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <a href="{{ lroute('home') }}" class="flex items-center gap-2.5">
                <span class="grid size-9 place-items-center rounded-xl bg-brand-500 text-white">
                    <x-lucide name="navigation" class="size-[18px] rotate-45" />
                </span>
                <span class="font-display text-xl font-extrabold text-white">{{ config('platform.brand.name') }}</span>
            </a>
            <p class="mt-5 max-w-sm text-sm leading-6 text-slate-400">
                {{ __('Air, sea and road freight for the routes that matter to you. Get a clear quote, dependable updates and support from a real person when you need it.') }}
            </p>
            <div class="mt-6 space-y-2 text-sm">
                <a href="mailto:{{ config('platform.brand.support_email') }}" class="flex items-center gap-2 hover:text-white"><x-lucide name="mail" class="size-4 text-brand-400" /> {{ config('platform.brand.support_email') }}</a>
                <p class="flex items-center gap-2"><x-lucide name="phone" class="size-4 text-brand-400" /> {{ config('platform.brand.support_phone') }}</p>
            </div>
        </div>

        @php
            $columns = [
                __('Ship') => [['quote', __('Get a quote')], ['services', __('Services')], ['rates', __('Rates and transit times')], ['locations', __('Drop-off points')], ['page.packing', __('Packing guide')]],
                __('Track') => [['track', __('Track a parcel')], ['network', __('Network and coverage')], ['status', __('Service alerts')], ['page.customs', __('Customs guide')], ['page.prohibited-items', __('Prohibited items')]],
                __('Company') => [['page.about', __('About us')], ['contact', __('Contact')], ['help', __('Help center')], ['page.claims-policy', __('Claims')]],
                __('Legal') => [['page.terms', __('Terms of service')], ['page.privacy', __('Privacy policy')], ['page.cookies', __('Cookie policy')], ['page.shipping-policy', __('Shipping policy')], ['page.payment-terms', __('Payment terms')], ['page.refund-policy', __('Refund policy')]],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-8 sm:grid-cols-4 lg:col-span-8">
            @foreach ($columns as $heading => $links)
                <div>
                    <h2 class="font-display text-sm font-semibold tracking-wide text-white">{{ $heading }}</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($links as [$route, $label])
                            <li><a href="{{ lroute($route) }}" class="text-slate-400 transition hover:text-white">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <div class="relative border-t border-white/10">
        <div class="container-page flex flex-col gap-4 py-6 text-xs text-slate-500 md:flex-row md:items-center md:justify-between">
            <p>© {{ now()->year }} {{ config('platform.brand.legal_name') }}. {{ __('All rights reserved.') }}</p>
            <p class="max-w-3xl md:text-right">
                {{ __('USPS, UPS and FedEx are trademarks of their respective owners. Their names are used only to describe the delivery networks that handle the last mile and to recognise their tracking numbers. :brand is not affiliated with, endorsed or sponsored by these carriers.', ['brand' => config('platform.brand.name')]) }}
            </p>
        </div>
    </div>
</footer>
