@extends('layouts.account', ['title' => __('Profile and security')])

@section('account')
    <h1 class="text-2xl font-bold">{{ __('Profile and security') }}</h1>
    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Your contact details, your password, the second factor on your account and the history of sign-ins. Every change here is written to the audit trail.') }}</p>

    @if ($forceSetup && ! $user->hasTwoFactorEnabled())
        <div class="mt-6 rounded-[6px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ __('Staff accounts must enable two-factor authentication before using the back-office.') }}</div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ lroute('account.profile.update') }}" class="card space-y-4 p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-bold">{{ __('Profile') }}</h2>
            <label class="block text-sm font-medium text-ink-900">{{ __('Full name') }}<input name="name" value="{{ old('name', $user->name) }}" maxlength="120" required class="field mt-1.5" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<span class="field-error block" id="name-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Email') }}<input value="{{ $user->email }}" disabled class="field mt-1.5"></label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Phone') }}<input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" class="field mt-1.5" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>@error('phone')<span class="field-error block" id="phone-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Language') }}
                <select name="locale" class="field mt-1.5">
                    <option value="en" @selected($user->locale === 'en')>English</option>
                    <option value="fr" @selected($user->locale === 'fr')>Français</option>
                </select>
            </label>
            <button class="btn-primary" type="submit">{{ __('Save profile') }}</button>
        </form>

        <form method="POST" action="{{ lroute('account.profile.password') }}" class="card space-y-4 p-6">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-bold">{{ __('Password') }}</h2>
            <label class="block text-sm font-medium text-ink-900">{{ __('Current password') }}<input name="current_password" type="password" required autocomplete="current-password" class="field mt-1.5" @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>@error('current_password')<span class="field-error block" id="current_password-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('New password') }}<input name="password" type="password" required minlength="10" autocomplete="new-password" class="field mt-1.5" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>@error('password')<span class="field-error block" id="password-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Confirm password') }}<input name="password_confirmation" type="password" required autocomplete="new-password" class="field mt-1.5"></label>
            <button class="btn-dark" type="submit">{{ __('Change password') }}</button>
        </form>

        <section class="card space-y-4 p-6 xl:col-span-2" id="two-factor">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold">{{ __('Two-factor authentication') }}</h2>
                @if ($user->hasTwoFactorEnabled())
                    <span class="badge bg-emerald-50 text-emerald-800"><x-lucide name="shield-check" class="size-3.5" /> {{ __('On') }}</span>
                @else
                    <span class="badge bg-slate-100 text-slate-700">{{ __('Off') }}</span>
                @endif
            </div>
            <p class="text-sm text-slate-600">{{ __('Protect your account with a code from an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…).') }}</p>

            @if ($recoveryCodes)
                <div class="rounded-[6px] bg-emerald-50 p-5">
                    <p class="font-semibold text-emerald-900">{{ __('Your recovery codes') }}</p>
                    <p class="mt-1 text-sm text-emerald-800">{{ __('Each code works once. Store them somewhere safe: they are shown only now.') }}</p>
                    <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                        @foreach ($recoveryCodes as $code)<li class="rounded-[4px] bg-white px-3 py-1.5 text-center">{{ $code }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if (! $user->hasTwoFactorEnabled())
                @if ($setup && ($setup['expires'] ?? 0) > now()->timestamp)
                    <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center">
                        <img src="data:image/svg+xml;base64,{{ base64_encode($setup['qr']) }}" alt="{{ __('QR code for your authenticator app') }}" width="176" height="176" class="size-44 rounded-[6px] border border-line bg-white p-2">
                        <div class="space-y-3 text-sm">
                            <p>{{ __('Scan this QR code with your app, or enter this key:') }}</p>
                            <p class="rounded-[4px] bg-surface px-3 py-2 font-mono text-xs break-all">{{ trim(chunk_split($setup['secret'], 4, ' ')) }}</p>
                            <form method="POST" action="{{ lroute('account.profile.2fa.confirm') }}" class="flex gap-2">
                                @csrf
                                <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required class="field max-w-40 font-mono" placeholder="123456" aria-label="{{ __('Code') }}" @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                                <button class="btn-primary" type="submit">{{ __('Confirm') }}</button>
                            </form>
                            @error('code')<p class="field-error" id="code-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ lroute('account.profile.2fa.start') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="text-sm font-medium text-ink-900">{{ __('Current password') }}<input name="current_password" type="password" required autocomplete="current-password" class="field mt-1.5" @error('current_password') aria-invalid="true" aria-describedby="current_password-error-2" @enderror></label>
                        <button class="btn-primary" type="submit">{{ __('Set up two-factor authentication') }}</button>
                    </form>
                    @error('current_password')<p class="field-error" id="current_password-error-2">{{ $message }}</p>@enderror
                @endif
            @elseif (! $user->isStaff())
                <form method="POST" action="{{ lroute('account.profile.2fa.disable') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('DELETE')
                    <label class="text-sm font-medium text-ink-900">{{ __('Current password') }}<input name="current_password" type="password" required class="field mt-1.5"></label>
                    <label class="text-sm font-medium text-ink-900">{{ __('Code') }}<input name="code" inputmode="numeric" maxlength="20" required class="field mt-1.5 font-mono" @error('code') aria-invalid="true" aria-describedby="code-error-2" @enderror></label>
                    <button class="btn-ghost" type="submit">{{ __('Turn off') }}</button>
                </form>
                @error('code')<p class="field-error" id="code-error-2">{{ $message }}</p>@enderror
            @endif
        </section>

        <section class="card p-6">
            <h2 class="text-lg font-bold">{{ __('Recent sign-ins') }}</h2>
            <ul class="mt-4 divide-y divide-line text-sm">
                @foreach ($logins as $login)
                    <li class="flex items-center gap-3 py-2.5">
                        <x-lucide :name="$login->success ? 'circle-check' : 'circle-x'" @class(['size-4', 'text-emerald-500' => $login->success, 'text-red-500' => ! $login->success]) />
                        <span class="flex-1 truncate text-slate-600" title="{{ $login->user_agent }}">{{ \Illuminate\Support\Str::limit($login->user_agent, 50) }}</span>
                        <span class="font-mono text-xs text-slate-500">{{ $login->ip }}</span>
                        <span class="text-xs text-slate-500">{{ $login->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card space-y-4 p-6">
            <h2 class="text-lg font-bold">{{ __('Your data') }}</h2>
            <form method="POST" action="{{ lroute('account.profile.export') }}">
                @csrf
                <button class="btn-ghost" type="submit"><x-lucide name="download" class="size-4" /> {{ __('Download my data') }}</button>
            </form>
            <form method="POST" action="{{ lroute('account.profile.delete') }}" class="space-y-3 border-t border-line pt-4">
                @csrf
                <p class="text-sm text-slate-600">{{ __('Request deletion of your account. Records we must keep by law (invoices) are retained and anonymised.') }}</p>
                @if ($user->deletion_requested_at)
                    <p class="text-sm font-medium text-amber-700">{{ __('Deletion requested on :date.', ['date' => $user->deletion_requested_at->translatedFormat('j M Y')]) }}</p>
                @else
                    <input name="current_password" type="password" required class="field" placeholder="{{ __('Current password') }}" aria-label="{{ __('Current password') }}">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm" value="1" required class="size-4 rounded border-line text-red-600"> {{ __('I understand this cannot be undone') }}</label>
                    <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="submit">{{ __('Request account deletion') }}</button>
                @endif
            </form>
        </section>
    </div>
@endsection
