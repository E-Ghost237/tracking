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
                <span @class(['grid size-11 shrink-0 place-items-center rounded-[4px]', 'bg-red-50 text-red-700' => $alert->severity === 'critical', 'bg-amber-50 text-amber-700' => $alert->severity === 'warning', 'bg-ink-50 text-ink-700' => $alert->severity === 'info'])>
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
                <span class="grid size-12 place-items-center rounded-[8px] bg-emerald-50 text-emerald-600"><x-lucide name="circle-check" class="size-6" /></span>
                <div>
                    <p class="font-display text-lg font-bold">{{ __('No active route notices right now') }}</p>
                    <p class="text-sm text-slate-600">{{ __('There are no current delays or service changes to share. We will post an update here when a route needs your attention.') }}</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Levels, notification and next steps: the page answers "so what do I do?"
         as well as "what changed?". --}}
    <section class="bg-surface py-12 lg:py-14">
        <div class="container-page grid gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="lg:col-span-7">
                <h2 class="text-2xl font-bold sm:text-3xl">{{ __('What each notice level means') }}</h2>
                <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Notices carry one of three levels. The level tells you whether this is something to know about or something to act on.') }}</p>

                <div class="mt-6 overflow-hidden rounded-[6px] border border-line">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ __('Service notice levels and what they mean for your shipment') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-white text-left text-xs text-slate-600">
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('Level') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('What it means') }}</th>
                                <th scope="col" class="px-4 py-3 font-medium">{{ __('What to do') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line bg-white">
                            @foreach ([
                                ['info', __('Info'), __('A change worth knowing about, such as new opening hours or a schedule update.'), __('Nothing, unless your delivery date is tight.')],
                                ['warning', __('Warning'), __('A route is running slower than usual, or a step in the journey is taking longer.'), __('Allow more time, or ask us for a different service on the same route.')],
                                ['critical', __('Critical'), __('A route is not running as normal and bookings on it are affected.'), __('Contact us before you book, and check an existing shipment before promising a date.')],
                            ] as [$tone, $level, $meaning, $action])
                                <tr>
                                    <th scope="row" class="px-4 py-3.5 text-left font-semibold text-ink-950">
                                        <span @class([
                                            'badge',
                                            'bg-ink-50 text-ink-700' => $tone === 'info',
                                            'bg-amber-50 text-amber-900' => $tone === 'warning',
                                            'bg-red-50 text-red-700' => $tone === 'critical',
                                        ])>{{ $level }}</span>
                                    </th>
                                    <td class="px-4 py-3.5 text-slate-700">{{ $meaning }}</td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $action }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <h3 class="mt-9 text-lg font-bold">{{ __('If your shipment is affected') }}</h3>
                <ol class="mt-4 space-y-3 text-sm text-slate-700">
                    @foreach ([
                        __('Open the tracking page and read the last scan: it shows where the shipment is and when it was recorded.'),
                        __('Check whether the delivery window in your quote still holds. If a notice on this page covers your route, expect the wider end of it.'),
                        __('Write to us with the tracking number if you need a decision today, and tell us the date the shipment has to arrive.'),
                        __('If you have not booked yet, price the same route on another service before you commit — the quote tool shows what is available.'),
                    ] as $index => $step)
                        <li class="flex items-start gap-3">
                            <span class="grid size-6 shrink-0 place-items-center rounded-[3px] bg-ink-900 text-xs font-bold text-white tabular">{{ $index + 1 }}</span>
                            {{ $step }}
                        </li>
                    @endforeach
                </ol>

                <p class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <a href="{{ lroute('track') }}" class="link inline-flex items-center gap-1">{{ __('Track a shipment') }} <x-lucide name="arrow-right" class="size-3.5" /></a>
                    <a href="{{ lroute('contact') }}" class="link inline-flex items-center gap-1">{{ __('Contact our team') }} <x-lucide name="arrow-right" class="size-3.5" /></a>
                </p>
            </div>

            <aside class="lg:col-span-5">
                <div class="card p-6">
                    <h2 class="text-lg font-bold">{{ __('How you hear about a change') }}</h2>
                    <ul class="mt-5 space-y-4 text-sm">
                        @foreach ([
                            ['triangle-alert', __('This page'), __('Every active notice is published here, newest first, and removed once it no longer applies.')],
                            ['mail', __('Email on your own shipments'), __('Subscribe from the tracking result for a specific shipment and we email you when its status changes.')],
                            ['headset', __('A direct message when it matters'), __('If a notice affects a shipment you have already booked, our team contacts you with the options rather than leaving you to find out.')],
                        ] as [$icon, $heading, $text])
                            <li class="flex gap-3.5">
                                <span class="grid size-9 shrink-0 place-items-center rounded-[4px] bg-ink-50 text-ink-700">
                                    <x-lucide :name="$icon" class="size-4" />
                                </span>
                                <span>
                                    <span class="block font-semibold text-ink-950">{{ $heading }}</span>
                                    <span class="mt-0.5 block leading-6 text-slate-600">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-5 border-t border-line pt-4 text-sm leading-6 text-slate-600">{{ __('We publish what we know, when we know it. A notice stays up while the situation lasts, and we would rather tell you about a risk early than explain a delay afterwards.') }}</p>
                </div>
            </aside>
        </div>
    </section>
@endsection
