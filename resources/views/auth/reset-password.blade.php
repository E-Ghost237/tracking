@extends('layouts.app', ['title' => __('Choose a new password'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Choose a new password')">
        <form method="POST" action="{{ lroute('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="field-label">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required maxlength="190" autocomplete="username" class="field" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="field-label">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password" class="field" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="field-label">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required maxlength="128" autocomplete="new-password" class="field">
            </div>
            <button type="submit" class="btn-primary w-full">{{ __('Reset password') }}</button>
        </form>
    </x-auth-shell>
@endsection
