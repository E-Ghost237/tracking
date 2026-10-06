@extends('layouts.account', ['title' => __('Addresses')])

@section('account')
    <div x-data="addressBook" @place-selected="onPlace($event.detail)">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ __('Address book') }}</h1>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Save senders and recipients you use often. Once an address is saved you can pick it in the booking wizard instead of typing it again. A pin is stored only when you save an address, and it is never shown publicly.') }}</p>
            </div>
            <button type="button" class="btn-primary" @click="create()" x-show="!editing"><x-lucide name="plus" class="size-4" /> {{ __('Add an address') }}</button>
        </div>

        <template x-if="editing">
        <div class="card mt-6 grid gap-6 p-6 lg:grid-cols-2">
            <form @submit.prevent="save()" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium text-ink-900">{{ __('Label') }}<input x-model="editing.label" maxlength="60" class="field mt-1.5" placeholder="{{ __('e.g. Home, Office') }}"></label>
                    <label class="text-sm font-medium text-ink-900">{{ __('Full name') }}<input x-model="editing.name" maxlength="120" class="field mt-1.5"><span class="field-error block" x-text="fieldError('name')"></span></label>
                </div>
                <x-place-field field="address" :label="__('City')" />
                <label class="block text-sm font-medium text-ink-900">{{ __('Street address') }}<input x-model="editing.line1" maxlength="200" class="field mt-1.5"><span class="field-error block" x-text="fieldError('line1')"></span></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-sm font-medium text-ink-900">{{ __('Apartment, suite (optional)') }}<input x-model="editing.line2" maxlength="200" class="field mt-1.5"></label>
                    <label class="text-sm font-medium text-ink-900">{{ __('Postal code') }}<input x-model="editing.postal_code" maxlength="20" class="field mt-1.5"></label>
                    <label class="text-sm font-medium text-ink-900">{{ __('Phone') }}<input x-model="editing.phone" maxlength="30" class="field mt-1.5"><span class="field-error block" x-text="fieldError('phone')"></span></label>
                    <label class="text-sm font-medium text-ink-900">{{ __('Email') }}<input x-model="editing.email" type="email" maxlength="190" class="field mt-1.5"></label>
                </div>
                <p class="text-xs text-slate-500" x-show="editing.lat !== null">{{ __('Pin') }}: <span class="font-mono" x-text="editing.lat + ', ' + editing.lon"></span></p>
                <p class="field-error" x-text="fieldError('city') || fieldError('country')"></p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="editing.is_default_sender" class="size-4 rounded border-line text-brand-500"> {{ __('Default sender') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="editing.is_default_recipient" class="size-4 rounded border-line text-brand-500"> {{ __('Default recipient') }}</label>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="btn-primary" :disabled="saving">{{ __('Save address') }}</button>
                    <button type="button" class="btn-ghost" @click="cancel()">{{ __('Cancel') }}</button>
                </div>
            </form>
            <div class="overflow-hidden rounded-[6px] bg-ink-950">
                <div x-data="globe" data-picker="true" data-network="false" data-autorotate="false" data-distance="2.6" class="relative aspect-square">
                    {{-- Map canvas: the same point can be typed as a city name, so the canvas is
                         decorative and the picker stays usable without it. --}}
                    <div x-ref="canvas" class="absolute inset-0 cursor-crosshair" aria-hidden="true"></div>
                    <p class="pointer-events-none absolute inset-x-0 bottom-3 px-4 text-center text-xs leading-5 text-slate-300">{{ __('Drag to spin the globe, click to move the pin. The pin is saved with the address so the driver can find the door.') }}</p>
                </div>
            </div>
        </div>
        </template>

        <div class="mt-6 grid gap-4 md:grid-cols-2" x-show="!editing">
            <template x-if="loading"><div class="skeleton h-32 md:col-span-2"></div></template>
            <template x-for="address in addresses" :key="address.public_id">
                <article class="card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-ink-950" x-text="address.label || address.name"></p>
                            <p class="text-sm text-slate-600" x-text="address.name"></p>
                        </div>
                        <div class="flex gap-1">
                            <span class="badge bg-ink-50 text-ink-800" x-show="address.is_default_sender"><x-lucide name="arrow-up-right" class="size-3" /> {{ __('Default sender') }}</span>
                            <span class="badge bg-ink-50 text-ink-800" x-show="address.is_default_recipient"><x-lucide name="arrow-down-left" class="size-3" /> {{ __('Default recipient') }}</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-600"><span x-text="address.line1"></span>, <span x-text="address.city"></span> <span x-text="address.postal_code"></span> · <span x-text="address.country"></span></p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="edit(address)"><x-lucide name="pencil" class="size-3.5" /> {{ __('Edit') }}</button>
                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs hover:!text-red-600" @click="remove(address)"><x-lucide name="trash-2" class="size-3.5" /> {{ __('Delete') }}</button>
                    </div>
                </article>
            </template>
            <div x-show="!loading && addresses.length === 0" class="card p-8 text-center md:col-span-2">
                <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="map-pin" class="size-6" /></span>
                <p class="mt-4 font-semibold text-ink-950">{{ __('No saved addresses yet') }}</p>
                <p class="mx-auto mt-1.5 max-w-lg text-sm leading-6 text-slate-600">{{ __('Save the pickup and delivery addresses you use most and the booking wizard fills them in for you.') }}</p>
            </div>
        </div>
    </div>
@endsection
