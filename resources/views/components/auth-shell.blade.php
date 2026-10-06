@props(['title', 'lead' => null])
<section class="relative min-h-[calc(100vh-72px)] bg-surface">
    <div class="container-page grid min-h-[calc(100vh-72px)] items-center gap-12 py-12 lg:grid-cols-2">
        <div class="mx-auto w-full max-w-md">
            <h1 class="text-3xl font-bold">{{ $title }}</h1>
            @if ($lead)
                <p class="mt-2 text-slate-600">{{ $lead }}</p>
            @endif
            <div class="card mt-8 p-6 sm:p-8">
                @if ($errors->has('domain'))
                    <p class="mb-4 rounded-[4px] bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('domain') }}</p>
                @endif
                {{ $slot }}
            </div>

            {{-- Reassurance under the form, where the doubt happens --}}
            <ul class="mt-6 space-y-2 text-sm text-slate-600">
                <li class="flex items-start gap-2">
                    <x-lucide name="shield-check" class="mt-0.5 size-4 shrink-0 text-ink-700" />
                    <span>{{ __('Payments are verified by a person before any label or tracking number is released.') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-lucide name="headset" class="mt-0.5 size-4 shrink-0 text-ink-700" />
                    <span>{{ __('Stuck? Write to us in English or French and a person answers.') }} <a href="{{ lroute('contact') }}" class="link">{{ __('Contact us') }}</a></span>
                </li>
            </ul>
        </div>

        <div class="relative hidden h-full min-h-[560px] overflow-hidden rounded-[6px] bg-ink-950 lg:block">
            <x-photo key="editorial_hub" :alt="__('Photo: freight handling at a distribution hub')" class="absolute inset-0 size-full object-cover" sizes="(min-width: 1024px) 50vw, 100vw" />
            <div class="absolute inset-0 bg-gradient-to-t from-ink-950 via-ink-950/85 to-ink-950/45" aria-hidden="true"></div>
            <div class="relative flex h-full flex-col justify-between p-10 text-white">
                <p class="eyebrow !text-brand-300">{{ config('platform.brand.name') }}</p>
                <div>
                    <p class="text-3xl leading-tight font-bold">{{ __('Book, pay and track every shipment from one account.') }}</p>
                    <ul class="mt-8 space-y-4 text-sm text-slate-200">
                        <li class="flex items-center gap-3"><x-lucide name="calculator" class="size-5 text-route-400" /> {{ __('Saved quotes and addresses') }}</li>
                        <li class="flex items-center gap-3"><x-lucide name="shield-check" class="size-5 text-route-400" /> {{ __('Payments verified by our team') }}</li>
                        <li class="flex items-center gap-3"><x-lucide name="file-down" class="size-5 text-route-400" /> {{ __('Labels, invoices and receipts') }}</li>
                        <li class="flex items-center gap-3"><x-lucide name="lock" class="size-5 text-route-400" /> {{ __('Optional two-factor authentication') }}</li>
                    </ul>
                    <p class="mt-8 border-t border-white/15 pt-6 text-xs leading-5 text-slate-300">
                        {{ __('Every price comes from the live rate card for your route, and every status comes from a real scan — never from an estimate.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
