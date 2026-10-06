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
@endphp

@section('content')
    <div class="container-page grid gap-8 py-8 lg:grid-cols-[250px_1fr] lg:py-10">
        <aside class="lg:sticky lg:top-24 lg:h-fit">
            <div class="card p-3">
                <div class="flex items-center gap-3 px-3 py-3">
                    <span class="grid size-10 place-items-center rounded-full bg-ink-900 font-display text-sm font-bold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <nav class="mt-1 flex gap-1 overflow-x-auto lg:flex-col" aria-label="{{ __('Account') }}">
                    @foreach ($links as [$route, $icon, $label])
                        @php($active = $current === app()->getLocale().'.'.$route || ($route !== 'account.dashboard' && str_starts_with($current, app()->getLocale().'.'.$route.'.')))
                        <a href="{{ lroute($route) }}" @if ($active) aria-current="page" @endif
                           @class(['flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition', 'bg-ink-900 text-white' => $active, 'text-slate-600 hover:bg-surface hover:text-ink-900' => ! $active])>
                            <x-lucide :name="$icon" class="size-[18px]" /> {{ $label }}
                        </a>
                    @endforeach
                    <form method="POST" action="{{ lroute('logout') }}" class="lg:mt-2 lg:border-t lg:border-line lg:pt-2">
                        @csrf
                        <button type="submit" class="flex w-full shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-red-50 hover:text-red-700">
                            <x-lucide name="log-out" class="size-[18px]" /> {{ __('Sign out') }}
                        </button>
                    </form>
                </nav>
            </div>
            @if (auth()->user()->hasPermission(\App\Support\Permissions::ADMIN_ACCESS))
                <a href="/{{ trim(config('platform.admin.path'), '/') }}" class="btn-dark mt-3 w-full"><x-lucide name="layout-dashboard" class="size-4" /> {{ __('Back-office') }}</a>
            @endif
        </aside>

        <div class="min-w-0">
            @if (! auth()->user()->hasVerifiedEmail())
                <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center">
                    <x-lucide name="mail" class="size-5 shrink-0" />
                    <p class="flex-1">{{ __('Please confirm your email address to book and pay.') }}</p>
                    <form method="POST" action="{{ lroute('account.verification.send') }}">@csrf<button class="font-semibold underline">{{ __('Resend the link') }}</button></form>
                </div>
            @endif
            @if ($errors->has('domain'))
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first('domain') }}</div>
            @endif
            @yield('account')
        </div>
    </div>
@endsection
