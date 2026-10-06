@extends('layouts.account', ['title' => __('Notifications')])

@section('account')
    <h1 class="text-2xl font-bold">{{ __('Notification preferences') }}</h1>
    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Choose which events reach your mailbox. Security and payment emails are always sent — they carry the payment reference and the document links, so they cannot be switched off. SMS and WhatsApp alerts are coming soon.') }}</p>
    <form method="POST" action="{{ lroute('account.notifications.update') }}" class="card mt-6 divide-y divide-line">
        @csrf
        @method('PUT')
        @foreach ($events as $event => $label)
            <label class="flex items-center justify-between gap-6 p-5 transition-colors hover:bg-surface/70">
                <span class="font-medium text-ink-950">{{ $label }}</span>
                <span class="flex items-center gap-2 text-sm text-slate-600">{{ __('Email') }}
                    <input type="checkbox" name="events[]" value="{{ $event }}" @checked($prefs[$event]['mail'] ?? true) class="size-5 rounded border-line text-brand-500 focus:ring-brand-500">
                </span>
            </label>
        @endforeach
        <div class="flex flex-wrap items-center gap-4 p-5">
            <button class="btn-primary" type="submit">{{ __('Save preferences') }}</button>
            <p class="text-xs leading-5 text-slate-600">{{ __('Changes apply to the next event. Emails already queued are not recalled.') }}</p>
        </div>
    </form>
@endsection
