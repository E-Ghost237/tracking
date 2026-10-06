@extends('layouts.account', ['title' => __('Notifications')])

@section('account')
    <h1 class="text-2xl font-extrabold">{{ __('Notification preferences') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Security and payment emails are always sent. SMS and WhatsApp alerts are coming soon.') }}</p>
    <form method="POST" action="{{ lroute('account.notifications.update') }}" class="card mt-6 divide-y divide-line">
        @csrf
        @method('PUT')
        @foreach ($events as $event => $label)
            <label class="flex items-center justify-between gap-6 p-5">
                <span class="font-medium text-ink-900">{{ $label }}</span>
                <span class="flex items-center gap-2 text-sm text-slate-600">{{ __('Email') }}
                    <input type="checkbox" name="events[]" value="{{ $event }}" @checked($prefs[$event]['mail'] ?? true) class="size-5 rounded border-line text-brand-500 focus:ring-brand-500">
                </span>
            </label>
        @endforeach
        <div class="p-5"><button class="btn-primary" type="submit">{{ __('Save preferences') }}</button></div>
    </form>
@endsection
