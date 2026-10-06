@extends('layouts.app', ['title' => __('Create an account'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Create your account')" :lead="__('It takes a minute. You need a verified email to book and pay.')">
        <form method="POST" action="{{ lroute('register.store') }}" class="space-y-5">
            @csrf
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div>
                <label for="name" class="field-label">{{ __('Full name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" class="field">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="field-label">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email" class="field">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="field-label">{{ __('Phone (optional)') }}</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="field">
                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="password" class="field-label">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password" class="field">
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required maxlength="128" autocomplete="new-password" class="field">
                </div>
            </div>
            <p class="-mt-2 text-xs text-slate-500">{{ __('At least 10 characters with upper and lower case letters and a number. Passwords found in known data breaches are refused.') }}</p>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror
            <x-captcha :challenge="$captcha" />
            <label class="flex items-start gap-2 text-sm text-slate-600">
                <input type="checkbox" name="terms" value="1" required class="mt-0.5 size-4 rounded border-line text-brand-500 focus:ring-brand-500" @checked(old('terms'))>
                <span>{!! __('I accept the :terms and the :privacy.', ['terms' => '<a class="link" href="'.e(lroute('page.terms')).'" target="_blank">'.e(__('terms of service')).'</a>', 'privacy' => '<a class="link" href="'.e(lroute('page.privacy')).'" target="_blank">'.e(__('privacy policy')).'</a>']) !!}</span>
            </label>
            @error('terms')<p class="field-error">{{ $message }}</p>@enderror
            <button type="submit" class="btn-primary w-full">{{ __('Create account') }}</button>
            <p class="text-center text-sm text-slate-600">{{ __('Already have an account?') }} <a href="{{ lroute('login') }}" class="link">{{ __('Sign in') }}</a></p>
        </form>
    </x-auth-shell>
@endsection
