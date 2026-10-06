@extends('layouts.app', ['title' => __('Verify your email'), 'noindex' => true])

@section('content')
    <x-auth-shell :title="__('Check your inbox')" :lead="__('We sent a confirmation link to :email. You need a verified email to book and pay.', ['email' => auth()->user()->email])">
        <form method="POST" action="{{ lroute('account.verification.send') }}" class="space-y-4">
            @csrf
            <button type="submit" class="btn-primary w-full">{{ __('Resend the link') }}</button>
        </form>
        <form method="POST" action="{{ lroute('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn-ghost w-full">{{ __('Sign out') }}</button>
        </form>
    </x-auth-shell>
@endsection
