@extends('layouts.app', ['title' => __('Help center'), 'description' => __('Answers about tracking, payments, customs and claims.')])

@section('content')
    <div x-data="faqSearch">
        <x-page-header photo="service_express" :eyebrow="__('Help center')" icon="life-buoy" :title="__('A good answer should make the next step clearer.')" :lead="__('Browse practical guidance on booking, packaging, tracking, payments and delivery. Search a topic below, or open a question to see the details. If your situation is different, our team can help you work through it. The help center covers the most common questions, but we are always happy to assist with anything not listed here.')">
            <div class="relative mt-8 max-w-xl">
                <x-lucide name="search" class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-500" />
                <label for="faq-search" class="sr-only">{{ __('Search the help center') }}</label>
                <input id="faq-search" x-model="query" type="search" class="field h-14 !pl-12 text-base" placeholder="{{ __('Search: customs, payment proof, delivery time…') }}">
            </div>
        </x-page-header>

        <div class="container-page grid gap-10 py-14 lg:grid-cols-4">
            <nav class="hidden lg:block" aria-label="{{ __('Topics') }}">
                <ul class="sticky top-[calc(var(--header-h)+1.5rem)] space-y-1 text-sm">
                    @foreach ($faqs as $category => $items)
                        <li><a href="#faq-{{ \Illuminate\Support\Str::slug($category) }}" class="block rounded-[4px] px-3 py-2 font-medium text-slate-600 hover:bg-surface hover:text-ink-900">{{ __('faq.'.$category) }}</a></li>
                    @endforeach
                </ul>
            </nav>
            <div class="space-y-12 lg:col-span-3">
                @foreach ($faqs as $category => $items)
                    <section id="faq-{{ \Illuminate\Support\Str::slug($category) }}" class="scroll-mt-[calc(var(--header-h)+1rem)]">
                        <h2 class="text-xl font-bold">{{ __('faq.'.$category) }}</h2>
                        <div class="mt-4 divide-y divide-line rounded-[6px] border border-line">
                            @foreach ($items as $faq)
                                <details class="group p-5 [&_summary::-webkit-details-marker]:hidden" x-show="matches($el.dataset.text)" data-text="{{ $faq['question'].' '.$faq['text'] }}">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink-900">
                                        {{ $faq['question'] }}
                                        <x-lucide name="chevron-down" class="size-5 shrink-0 text-slate-500 transition group-open:rotate-180" />
                                    </summary>
                                    <div class="prose-content mt-3">{!! $faq['html'] !!}</div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                {{-- Where to go first, so the obvious questions never need a ticket --}}
                <section class="scroll-mt-[calc(var(--header-h)+1rem)]">
                    <h2 class="text-xl font-bold">{{ __('Where to look first') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('Most questions are answered by a page that already has your details in front of it. These are the quickest routes.') }}</p>
                    <div class="mt-4 overflow-hidden rounded-[6px] border border-line">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[620px] text-sm">
                                <caption class="sr-only">{{ __('Which page answers which question') }}</caption>
                                <thead>
                                    <tr class="border-b border-line bg-surface text-left text-xs text-slate-600">
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Your question') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Where it is answered') }}</th>
                                        <th scope="col" class="px-4 py-3 font-medium">{{ __('You will need') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ([
                                        [__('Where is my parcel right now?'), __('The tracking page shows every scan with its time and source.'), __('Your tracking number'), lroute('track')],
                                        [__('What will this route cost?'), __('The quote tool prices your route, weight and service before you book.'), __('Route, weight and dimensions'), lroute('quote')],
                                        [__('Has my payment been approved?'), __('Your order page shows the payment state and the proof you uploaded.'), __('Your order number'), lroute('account.orders')],
                                        [__('Which documents does customs need?'), __('The customs guide covers declarations, values and duties.'), __('What you are sending'), lroute('page.customs')],
                                        [__('Something arrived damaged'), __('Open a claim and attach photographs of the parcel and its contents.'), __('Tracking number and photos'), lroute('account.claims')],
                                        [__('How long will it take?'), __('Published transit windows per service, in the price guide.'), __('Route and service'), lroute('rates')],
                                    ] as [$question, $answer, $needs, $href])
                                        <tr>
                                            <th scope="row" class="px-4 py-3.5 text-left font-semibold text-ink-950">
                                                <a href="{{ $href }}" class="link">{{ $question }}</a>
                                            </th>
                                            <td class="px-4 py-3.5 text-slate-700">{{ $answer }}</td>
                                            <td class="px-4 py-3.5 text-slate-600">{{ $needs }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                {{-- What to include, so the first reply can be the useful one --}}
                <section class="scroll-mt-[calc(var(--header-h)+1rem)]">
                    <h2 class="text-xl font-bold">{{ __('Before you write to us') }}</h2>
                    <p class="mt-2.5 text-[15px] leading-7 text-slate-600">{{ __('A message with the right details is usually answered in one exchange instead of three. Include what applies to you.') }}</p>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            __('The tracking number or order number, if the question is about a specific shipment.'),
                            __('What you expected to happen, and what you see instead.'),
                            __('For a damage or shortage: photographs of the parcel, the label and the contents.'),
                            __('For a payment question: the method you used, the reference you entered and the date you paid.'),
                        ] as $item)
                            <li class="flex items-start gap-2.5 rounded-[6px] border border-line p-3.5 text-sm text-slate-700">
                                <x-lucide name="file-check" class="mt-0.5 size-4 shrink-0 text-emerald-700" />
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ __('Please never send payment passwords, card numbers or one-time codes. Our team will not ask for them, and we do not need them to help you.') }}</p>
                </section>

                {{-- Hours and languages: a factual promise instead of a vague one --}}
                <section class="scroll-mt-[calc(var(--header-h)+1rem)]">
                    <h2 class="text-xl font-bold">{{ __('When we answer') }}</h2>
                    <dl class="mt-4 divide-y divide-line border-y border-line text-sm">
                        @foreach ([
                            [__('Staffed hours'), config('platform.settings.staffed_hours')],
                            [__('Typical first reply'), __('Within one business day, and usually much sooner during staffed hours.')],
                            [__('Languages'), __('English and French, on every channel.')],
                            [__('Urgent shipment issues'), __('Write “urgent” in the subject and include the tracking number; those messages are read first.')],
                        ] as [$term, $detail])
                            <div class="flex flex-col gap-1 py-3.5 sm:flex-row sm:gap-6">
                                <dt class="w-[190px] shrink-0 font-semibold text-ink-950">{{ $term }}</dt>
                                <dd class="text-slate-700">{{ $detail }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <div class="card flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center">
                    <span class="grid size-12 place-items-center rounded-[4px] bg-brand-50 text-brand-600"><x-lucide name="headset" class="size-6" /></span>
                    <div class="flex-1">
                        <p class="font-display font-bold text-ink-900">{{ __('Still need help?') }}</p>
                        <p class="text-sm text-slate-600">{{ __('Write to us and we will reply within one business day. For urgent shipment issues, include your tracking number so we can look up the details quickly.') }}</p>
                    </div>
                    <a href="{{ lroute('contact') }}" class="btn-dark">{{ __('Contact us') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection
