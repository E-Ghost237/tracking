@extends('layouts.app', ['noindex' => true, 'bodyClass' => 'bg-surface'])

@php
    $links = [
        ['account.dashboard', 'layout-dashboard', __('Dashboard')],
        ['account.shipments', 'package', __('Shipments')],
        ['account.orders', 'receipt', __('Orders and invoices')],
        ['account.quotes', 'calculator', __('Saved quotes')],
        ['account.addresses', 'map-pin', __('Addresses')],
        ['account.support', 'life-buoy', __('Support')],
        ['account.claims', 'file-warning', __('Claims')],
        ['account.notifications', 'bell', __('Notifications')],
        ['account.profile', 'settings', __('Profile and security')],
    ];
    $current = request()->route()?->getName() ?? '';
    $isActive = fn (string $route): bool => $current === app()->getLocale().'.'.$route
        || ($route !== 'account.dashboard' && str_starts_with($current, app()->getLocale().'.'.$route.'.'));
@endphp

@section('content')
    <div class="container-page grid gap-8 py-8 lg:grid-cols-[248px_1fr] lg:gap-10 lg:py-10">
        {{-- Account navigation: a plain list on a hairline, not a floating panel --}}
        <aside class="lg:sticky lg:top-[calc(var(--header-h)+1.5rem)] lg:h-fit">
            <div class="flex items-center gap-3 border-b border-line pb-4">
                <span class="grid size-10 place-items-center rounded-full bg-ink-900 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-ink-950">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-600">{{ auth()->user()->email }}</p>
                </div>
            </div>

            <nav class="mt-3 flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="{{ __('Account') }}">
                @foreach ($links as [$route, $icon, $label])
                    @php($active = $isActive($route))
                    <a href="{{ lroute($route) }}" @if ($active) aria-current="page" @endif
                       @class(['flex shrink-0 items-center gap-3 rounded-[4px] px-3 py-2.5 text-sm font-medium transition',
                               'bg-ink-900 text-white' => $active,
                               'text-slate-600 hover:bg-white hover:text-ink-950' => ! $active])>
                        <x-lucide :name="$icon" class="size-[18px]" /> {{ $label }}
                    </a>
                @endforeach
                <form method="POST" action="{{ lroute('logout') }}" class="lg:mt-2 lg:border-t lg:border-line lg:pt-2">
                    @csrf
                    <button type="submit" class="flex w-full shrink-0 items-center gap-3 rounded-[4px] px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-red-700">
                        <x-lucide name="log-out" class="size-[18px]" /> {{ __('Sign out') }}
                    </button>
                </form>
            </nav>

            @if (auth()->user()->hasPermission(\App\Support\Permissions::ADMIN_ACCESS))
                <a href="/{{ trim(config('platform.admin.path'), '/') }}" class="btn-dark mt-3 w-full !py-2"><x-lucide name="layout-dashboard" class="size-4" /> {{ __('Back-office') }}</a>
            @endif

            {{-- A way to reach a person from anywhere in the account --}}
            <div class="mt-5 border-t border-line pt-5 text-sm">
                <p class="font-semibold text-ink-950">{{ __('Need a hand?') }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ __('Payments, customs documents or a delayed shipment — a person answers in English and French.') }}</p>
                <div class="mt-3 space-y-1.5">
                    <a href="{{ lroute('contact') }}" class="flex items-center gap-2 text-xs font-medium text-ink-900 hover:text-brand-600">
                        <x-lucide name="headset" class="size-3.5 text-slate-500" /> {{ __('Contact us') }}
                    </a>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('platform.brand.support_phone')) }}" class="flex items-center gap-2 text-xs font-medium text-ink-900 hover:text-brand-600">
                        <x-lucide name="phone" class="size-3.5 text-slate-500" /> {{ config('platform.brand.support_phone') }}
                    </a>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            @if (! auth()->user()->hasVerifiedEmail())
                <div class="mb-6 flex flex-col gap-3 rounded-[6px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center">
                    <x-lucide name="mail" class="size-5 shrink-0" />
                    <p class="flex-1">{{ __('Please confirm your email address to book and pay.') }}</p>
                    <form method="POST" action="{{ lroute('account.verification.send') }}">@csrf<button class="font-semibold underline">{{ __('Resend the link') }}</button></form>
                </div>
            @endif
            @if ($errors->has('domain'))
                <div class="mb-6 rounded-[6px] border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('domain') }}</div>
            @endif
            @yield('account')
        </div>
    </div>
@endsection
