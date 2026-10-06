@extends('layouts.app', ['title' => __('Get a quote'), 'description' => __('Instant air, sea and road freight prices with transit times.')])

@section('content')
    <x-page-header :eyebrow="__('Rate calculator')" icon="calculator" :title="__('Price your shipment in seconds')"
                   :lead="__('Chargeable weight is the larger of the actual weight and the volume (L × W × H ÷ 5000). The final price is confirmed at booking.')" />

    <div x-data="quoteForm" data-auth="{{ auth()->check() ? 'true' : 'false' }}" data-login-url="{{ lroute('login') }}"
         @place-selected="onPlace($event.detail)" class="container-page grid gap-8 py-12 lg:grid-cols-12">

        <form @submit.prevent="submit()" class="card space-y-8 p-6 sm:p-8 lg:col-span-7" novalidate>
            <fieldset>
                <legend class="flex items-center gap-2 font-display text-lg font-bold text-ink-900"><span class="grid size-7 place-items-center rounded-full bg-ink-900 text-xs text-white">1</span> {{ __('Route') }}</legend>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <x-place-field field="origin" :label="__('From')" :initial="is_string(request('from')) ? \Illuminate\Support\Str::limit(request('from'), 80, '') : ''" />
                    <x-place-field field="destination" :label="__('To')" :initial="is_string(request('to')) ? \Illuminate\Support\Str::limit(request('to'), 80, '') : (is_string(request('to_city')) ? \Illuminate\Support\Str::limit(request('to_city'), 80, '') : '')" />
                </div>
                <p class="field-error" x-show="fieldError('origin.city') || fieldError('destination.city')" x-text="fieldError('origin.city') || fieldError('destination.city')"></p>
            </fieldset>

            <fieldset>
                <legend class="flex items-center gap-2 font-display text-lg font-bold text-ink-900"><span class="grid size-7 place-items-center rounded-full bg-ink-900 text-xs text-white">2</span> {{ __('Packages') }}</legend>
                <div class="mt-5 space-y-3">
                    <template x-for="(pkg, index) in packages" :key="index">
                        <div class="grid grid-cols-2 gap-3 rounded-2xl border border-line bg-surface/50 p-4 sm:grid-cols-[repeat(4,1fr)_auto]">
                            <label class="text-xs font-medium text-slate-600">{{ __('Weight (kg)') }}
                                <input x-model="pkg.weight_kg" type="number" min="0.1" max="3000" step="0.1" required class="field mt-1">
                            </label>
                            <label class="text-xs font-medium text-slate-600">{{ __('Length (cm)') }}
                                <input x-model="pkg.length_cm" type="number" min="1" max="600" required class="field mt-1">
                            </label>
                            <label class="text-xs font-medium text-slate-600">{{ __('Width (cm)') }}
                                <input x-model="pkg.width_cm" type="number" min="1" max="600" required class="field mt-1">
                            </label>
                            <label class="text-xs font-medium text-slate-600">{{ __('Height (cm)') }}
                                <input x-model="pkg.height_cm" type="number" min="1" max="600" required class="field mt-1">
                            </label>
                            <button type="button" @click="removePackage(index)" x-show="packages.length > 1" class="col-span-2 mt-auto grid h-[42px] place-items-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-red-600 sm:col-span-1" aria-label="{{ __('Remove package') }}">
                                <x-lucide name="trash-2" class="size-4" />
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="addPackage()" class="btn-ghost !py-2 text-sm"><x-lucide name="plus" class="size-4" /> {{ __('Add a package') }}</button>
                </div>
            </fieldset>

            <fieldset>
                <legend class="flex items-center gap-2 font-display text-lg font-bold text-ink-900"><span class="grid size-7 place-items-center rounded-full bg-ink-900 text-xs text-white">3</span> {{ __('Service') }}</legend>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['air' => ['plane', __('Air')], 'express' => ['zap', __('Express')], 'sea' => ['ship', __('Sea')], 'road' => ['truck', __('Road')]] as $mode => [$icon, $label])
                        <button type="button" @click="setMode('{{ $mode }}')" :aria-pressed="mode === '{{ $mode }}'"
                                class="flex flex-col items-center gap-2 rounded-2xl border-2 p-4 text-sm font-semibold transition"
                                :class="mode === '{{ $mode }}' ? 'border-brand-500 bg-brand-50 text-ink-900' : 'border-line text-slate-600 hover:border-slate-300'">
                            <x-lucide :name="$icon" class="size-6" /> {{ $label }}
                        </button>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="declared" class="field-label">{{ __('Declared value (USD)') }}</label>
                        <input id="declared" x-model="declaredValue" type="number" min="0" max="1000000" step="0.01" class="field" placeholder="0.00">
                    </div>
                    <label class="flex cursor-pointer items-center gap-3 self-end rounded-xl border border-line p-3">
                        <input type="checkbox" x-model="insurance" class="size-5 rounded border-line text-brand-500 focus:ring-brand-500">
                        <span class="text-sm"><span class="font-semibold text-ink-900">{{ __('Add insurance') }}</span><br><span class="text-slate-500">{{ __('Covers loss and damage up to the declared value.') }}</span></span>
                    </label>
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center gap-4 border-t border-line pt-6">
                <button type="submit" class="btn-primary !px-8" :disabled="loading">
                    <span x-show="!loading">{{ __('Calculate price') }}</span>
                    <span x-show="loading" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Calculating') }}</span>
                </button>
                <p x-show="error" x-cloak class="text-sm text-red-600" x-text="error" role="alert"></p>
            </div>
        </form>

        <aside class="space-y-6 lg:col-span-5">
            <div x-ref="result" class="card overflow-hidden">
                <template x-if="!result">
                    <div class="p-8 text-center">
                        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-500"><x-lucide name="receipt" class="size-7" /></span>
                        <p class="mt-4 font-display text-lg font-bold text-ink-900">{{ __('Your quote') }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ __('Fill in the route and packages to see the price, transit time and delivery network.') }}</p>
                    </div>
                </template>
                <template x-if="result">
                    <div>
                        <div class="bg-gradient-to-br from-ink-900 to-ink-800 p-6 text-white">
                            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase">{{ __('Estimated price') }} · <span x-text="modeLabel(result.mode)"></span></p>
                            <p class="mt-2 font-display text-4xl font-extrabold"><span x-text="result.price_range.min_formatted"></span><span class="text-xl text-slate-400" x-show="result.price_range.max > result.price_range.min"> – <span x-text="result.price_range.max_formatted"></span></span></p>
                            <p class="mt-2 text-sm text-slate-300">{{ __('Reference') }} <span class="font-mono" x-text="result.reference"></span></p>
                        </div>
                        <dl class="divide-y divide-line text-sm">
                            <div class="flex justify-between p-4"><dt class="text-slate-500">{{ __('Transit time') }}</dt><dd class="font-semibold text-ink-900"><span x-text="result.transit_days.min"></span>–<span x-text="result.transit_days.max"></span> {{ __('days') }}</dd></div>
                            <div class="flex justify-between p-4"><dt class="text-slate-500">{{ __('Distance') }}</dt><dd class="font-semibold text-ink-900"><span x-text="result.distance_km.toLocaleString()"></span> km</dd></div>
                            <div class="flex justify-between p-4"><dt class="text-slate-500">{{ __('Chargeable weight') }}</dt><dd class="font-semibold text-ink-900"><span x-text="result.chargeable_weight_kg"></span> kg</dd></div>
                            <div class="flex justify-between gap-6 p-4"><dt class="text-slate-500">{{ __('Delivery network') }}</dt><dd class="text-right font-semibold text-ink-900" x-text="result.network_label"></dd></div>
                        </dl>
                        <div class="border-t border-line bg-surface/60 p-4 text-xs leading-5 text-slate-500" x-text="result.note"></div>
                        <div class="flex flex-wrap gap-3 border-t border-line p-4">
                            <button type="button" class="btn-primary flex-1" @click="book()" :disabled="booking">{{ __('Book this shipment') }} <x-lucide name="arrow-right" class="size-4" /></button>
                        </div>
                        <p class="px-4 pb-4 text-xs text-slate-500">{{ __('Valid for 7 days. Saved to your account when you sign in.') }}</p>
                    </div>
                </template>
            </div>

            <div class="overflow-hidden rounded-[1.75rem] bg-ink-950">
                <div x-data="globe" data-network="false" data-distance="3" class="relative aspect-[4/3]">
                    <div x-ref="canvas" class="absolute inset-0"></div>
                    <div x-show="fallback" x-cloak class="absolute inset-0 flex items-center p-4"><img src="/images/world-map.svg" alt="{{ __('World map') }}" class="w-full rounded-xl"></div>
                </div>
            </div>
        </aside>
    </div>
@endsection
