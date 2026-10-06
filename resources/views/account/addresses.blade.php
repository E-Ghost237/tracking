@extends('layouts.account', ['title' => __('Addresses')])

@section('account')
    <div x-data="addressBook" @place-selected="onPlace($event.detail)">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold">{{ __('Address book') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ __('Save senders and recipients you use often. Pins are stored only when you save an address.') }}</p>
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
            <div class="overflow-hidden rounded-2xl bg-ink-950">
                <div x-data="globe" data-picker="true" data-network="false" data-autorotate="false" data-distance="2.6" class="relative aspect-square">
                    <div x-ref="canvas" class="absolute inset-0 cursor-crosshair"></div>
                    <p class="pointer-events-none absolute inset-x-0 bottom-3 text-center text-xs text-slate-300">{{ __('Click the globe to adjust the pin') }}</p>
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
                            <p class="font-display font-bold text-ink-900" x-text="address.label || address.name"></p>
                            <p class="text-sm text-slate-600" x-text="address.name"></p>
                        </div>
                        <div class="flex gap-1">
                            <span class="badge bg-brand-50 text-brand-700" x-show="address.is_default_sender">{{ __('Sender') }}</span>
                            <span class="badge bg-sky-50 text-sky-700" x-show="address.is_default_recipient">{{ __('Recipient') }}</span>
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-slate-600"><span x-text="address.line1"></span>, <span x-text="address.city"></span> <span x-text="address.postal_code"></span> · <span x-text="address.country"></span></p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs" @click="edit(address)"><x-lucide name="pencil" class="size-3.5" /> {{ __('Edit') }}</button>
                        <button type="button" class="btn-ghost !px-3 !py-1.5 text-xs hover:!text-red-600" @click="remove(address)"><x-lucide name="trash-2" class="size-3.5" /> {{ __('Delete') }}</button>
                    </div>
                </article>
            </template>
            <p x-show="!loading && addresses.length === 0" class="card p-10 text-center text-slate-500 md:col-span-2">{{ __('No saved addresses yet.') }}</p>
        </div>
    </div>
@endsection
