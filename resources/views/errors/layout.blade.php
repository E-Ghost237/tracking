@extends('layouts.app', ['title' => $title, 'noindex' => true])

@section('content')
    <section class="container-page flex min-h-[60vh] flex-col items-center justify-center py-20 text-center">
        <p class="font-display text-7xl font-extrabold text-brand-500">{{ $code }}</p>
        <h1 class="mt-4 text-3xl font-extrabold">{{ $title }}</h1>
        <p class="mt-3 max-w-md text-slate-600">{{ $message }}</p>
        <div class="mt-8 flex gap-3">
            <a href="{{ route(app()->getLocale().'.home') }}" class="btn-primary">{{ __('Back to home') }}</a>
            <a href="{{ route(app()->getLocale().'.track') }}" class="btn-ghost">{{ __('Track a parcel') }}</a>
        </div>
    </section>
@endsection
