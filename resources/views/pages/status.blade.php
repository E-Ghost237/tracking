@extends('layouts.app', ['title' => __('Service alerts'), 'description' => __('Current service notices about route changes, weather and carrier delays.')])

@section('content')
    @php
        $visibleAlerts = $alerts->reject(function ($alert) {
            $title = mb_strtolower($alert->title);

            return str_starts_with($title, 'public holiday in ') || str_starts_with($title, 'jour férié ');
        });
    @endphp

    <x-page-header :eyebrow="__('Service notices')" icon="triangle-alert" :title="__('Updates that may affect your route.')" :lead="__('This page collects current notices from our operations team, including carrier delays, weather disruptions and changes to a service. Check back before booking if your delivery date is important. We update this page as conditions change, so a quick look here can save surprises later.')" />
    <div class="container-page py-14">
        @forelse ($visibleAlerts as $alert)
            <article class="card mb-4 flex gap-4 p-6">
                <span @class(['grid size-11 shrink-0 place-items-center rounded-xl', 'bg-red-50 text-red-600' => $alert->severity === 'critical', 'bg-amber-50 text-amber-600' => $alert->severity === 'warning', 'bg-sky-50 text-sky-600' => $alert->severity === 'info'])>
                    <x-lucide :name="$alert->severity === 'info' ? 'info' : 'triangle-alert'" class="size-5" />
                </span>
                <div>
                    <h2 class="text-lg font-bold">{{ $alert->title }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $alert->region }} @if ($alert->starts_at) · {{ $alert->starts_at->translatedFormat('j F Y') }} @endif @if ($alert->ends_at) – {{ $alert->ends_at->translatedFormat('j F Y') }} @endif</p>
                    <div class="prose-content mt-2">{!! $markdown->toHtml($alert->body) !!}</div>
                </div>
            </article>
        @empty
            <div class="card flex items-center gap-4 p-8">
                <span class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600"><x-lucide name="circle-check" class="size-6" /></span>
                <div>
                    <p class="font-display text-lg font-bold">{{ __('No active route notices right now') }}</p>
                    <p class="text-sm text-slate-600">{{ __('There are no current delays or service changes to share. We will post an update here when a route needs your attention.') }}</p>
                </div>
            </div>
        @endforelse
    </div>
@endsection
