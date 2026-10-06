@extends('layouts.app', ['title' => __('Contact'), 'description' => __('Contact our team in English or French.')])

@section('content')
    <x-page-header :eyebrow="__('Contact')" icon="mail" :title="__('Tell us what you need help with.')" :lead="__('Share a little context and, if you have one, include your order or tracking number. A member of our support team will read your message and reply in English or French, usually within one business day.')" />

    <div class="container-page grid gap-10 py-14 lg:grid-cols-3">
        <form method="POST" action="{{ lroute('contact.store') }}" class="card space-y-5 p-6 sm:p-8 lg:col-span-2">
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="border-b border-line pb-5">
                <p class="eyebrow eyebrow-rule">{{ __('A note to our team') }}</p>
                <h2 class="editorial-title mt-3 text-3xl">{{ __('A few details help us get you a useful answer.') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('Please do not include payment passwords or sensitive card information in your message.') }}</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="field-label">{{ __('Full name') }}</label>
                    <input id="name" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120" class="field" autocomplete="name">
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="field-label">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" required maxlength="190" class="field" autocomplete="email">
                    @error('email')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="phone" class="field-label">{{ __('Phone (optional)') }}</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" class="field" autocomplete="tel">
                    @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="tracking_number" class="field-label">{{ __('Tracking number (optional)') }}</label>
                    <input id="tracking_number" name="tracking_number" value="{{ old('tracking_number') }}" maxlength="40" class="field font-mono">
                    @error('tracking_number')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="subject" class="field-label">{{ __('Subject') }}</label>
                <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="160" class="field">
                @error('subject')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="message" class="field-label">{{ __('Message') }}</label>
                <textarea id="message" name="message" rows="6" required maxlength="5000" class="field">{{ old('message') }}</textarea>
                @error('message')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <x-captcha :challenge="$captcha" />
            <button type="submit" class="btn-primary">{{ __('Send message') }} <x-lucide name="arrow-right" class="size-4" /></button>
        </form>

        <aside class="space-y-4">
            <div class="card p-6">
                <p class="eyebrow">{{ __('Support') }}</p>
                <h2 class="mt-2 font-display text-lg font-bold">{{ __('Choose the easiest way to reach us') }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('For a faster reply, tell us what you expected to happen and what you see now. A tracking number or quote reference helps us find the right details.') }}</p>
                <ul class="mt-5 space-y-3 border-t border-line pt-4 text-sm text-slate-700">
                    <li class="flex items-center gap-3"><x-lucide name="mail" class="size-5 text-brand-500" /> <a class="link" href="mailto:{{ config('platform.brand.support_email') }}">{{ config('platform.brand.support_email') }}</a></li>
                    <li class="flex items-center gap-3"><x-lucide name="phone" class="size-5 text-brand-500" /> {{ config('platform.brand.support_phone') }}</li>
                    @if (config('platform.brand.whatsapp'))
                        <li class="flex items-center gap-3"><x-lucide name="message-circle" class="size-5 text-brand-500" /> <a class="link" href="https://wa.me/{{ preg_replace('/\D/', '', config('platform.brand.whatsapp')) }}" rel="noopener noreferrer" target="_blank">WhatsApp</a></li>
                    @endif
                    <li class="flex items-start gap-3"><x-lucide name="map-pin" class="size-5 shrink-0 text-brand-500" /> {{ config('platform.brand.address') }}</li>
                </ul>
            </div>
            <div class="rounded-[var(--radius-card)] border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                <p class="flex items-center gap-2 font-semibold"><x-lucide name="shield-check" class="size-5" /> {{ __('Stay safe') }}</p>
                <p class="mt-2 leading-6">{{ __('We never ask for payment by phone, social media or email links. Payment details only appear in your account, on your own order.') }}</p>
            </div>
        </aside>
    </div>
@endsection
