@props(['title', 'lead' => null])
<section class="relative min-h-[calc(100vh-72px)] bg-surface">
    <div class="container-page grid min-h-[calc(100vh-72px)] items-center gap-12 py-12 lg:grid-cols-2">
        <div class="mx-auto w-full max-w-md">
            <h1 class="text-3xl font-extrabold">{{ $title }}</h1>
            @if ($lead)
                <p class="mt-2 text-slate-600">{{ $lead }}</p>
            @endif
            <div class="card mt-8 p-6 sm:p-8">
                @if ($errors->has('domain'))
                    <p class="mb-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('domain') }}</p>
                @endif
                {{ $slot }}
            </div>
        </div>
        <div class="relative hidden h-full min-h-[560px] overflow-hidden rounded-[2rem] bg-ink-950 p-10 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute inset-0 grid-bg"></div>
            <div class="pointer-events-none absolute -right-16 -bottom-16 text-white/[0.05]"><x-lucide name="globe" class="size-[420px]" /></div>
            <p class="eyebrow relative !text-brand-300">{{ config('platform.brand.name') }}</p>
            <div class="relative">
                <p class="font-display text-3xl leading-tight font-extrabold">{{ __('Book, pay and track every shipment from one account.') }}</p>
                <ul class="mt-8 space-y-4 text-sm text-slate-300">
                    <li class="flex items-center gap-3"><x-lucide name="calculator" class="size-5 text-route-400" /> {{ __('Saved quotes and addresses') }}</li>
                    <li class="flex items-center gap-3"><x-lucide name="shield-check" class="size-5 text-route-400" /> {{ __('Payments verified by our team') }}</li>
                    <li class="flex items-center gap-3"><x-lucide name="file-down" class="size-5 text-route-400" /> {{ __('Labels, invoices and receipts') }}</li>
                    <li class="flex items-center gap-3"><x-lucide name="lock" class="size-5 text-route-400" /> {{ __('Optional two-factor authentication') }}</li>
                </ul>
            </div>
        </div>
    </div>
</section>
