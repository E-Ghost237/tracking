@extends('layouts.app', ['title' => __('Sign in'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Welcome back')" :lead="__('Sign in to book shipments, pay and download your documents.')">
        <form method="POST" action="{{ lroute('login.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="field-label">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="190" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="field-label">{{ __('Password') }}</label>
                    <a href="{{ lroute('password.request') }}" class="mb-1.5 text-xs link">{{ __('Forgot password?') }}</a>
                </div>
                <input id="password" name="password" type="password" required autocomplete="current-password" maxlength="200" class="field" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>
            <x-captcha :challenge="$captcha" />
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line text-brand-500 focus:ring-brand-500"> {{ __('Keep me signed in') }}
            </label>
            <button type="submit" class="btn-primary w-full">{{ __('Sign in') }}</button>
            <p class="text-center text-sm text-slate-600">{{ __('New here?') }} <a href="{{ lroute('register') }}" class="link">{{ __('Create an account') }}</a></p>
        </form>
    </x-auth-shell>
@endsection
