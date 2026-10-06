@extends('layouts.app', ['title' => __('Two-factor authentication'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Two-factor authentication')" :lead="__('Enter the 6-digit code from your authenticator app, or one of your recovery codes.')">
        <form method="POST" action="{{ lroute('two-factor.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="code" class="field-label">{{ __('Code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="20" class="field text-center font-mono text-2xl tracking-[0.4em]">
                @error('code')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Verify') }}</button>
        </form>
    </x-auth-shell>
@endsection
