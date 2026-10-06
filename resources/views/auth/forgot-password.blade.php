@extends('layouts.app', ['title' => __('Reset your password'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Reset your password')" :lead="__('Enter your email and we will send you a link to choose a new password. The link works once and expires after 60 minutes.')">
        <form method="POST" action="{{ lroute('password.email') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="field-label">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <x-captcha :challenge="$captcha" />
            <button type="submit" class="btn-primary w-full">{{ __('Send reset link') }}</button>
            <p class="text-center text-sm"><a href="{{ lroute('login') }}" class="link">{{ __('Back to sign in') }}</a></p>
        </form>
    </x-auth-shell>
@endsection
