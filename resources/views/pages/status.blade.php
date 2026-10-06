@extends('layouts.app', ['title' => __('Service alerts'), 'description' => __('Delays, holidays and weather affecting deliveries.')])

@section('content')
    <x-page-header :eyebrow="__('Service alerts')" icon="triangle-alert" :title="__('Service status')" :lead="__('Delays, public holidays and weather events that may affect transit times.')" />
    <div class="container-page py-14">
        @forelse ($alerts as $alert)
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
                    <p class="font-display text-lg font-bold">{{ __('All services are running normally') }}</p>
                    <p class="text-sm text-slate-600">{{ __('There are no active alerts right now.') }}</p>
                </div>
            </div>
        @endforelse
    </div>
@endsection
