@extends('layouts.account', ['title' => __('New shipment')])

@php
    $steps = [__('Route'), __('Packages'), __('Service'), __('Parties'), __('Review')];
@endphp

@section('account')
    <div x-data="wizard" @place-selected="onPlace($event.detail)">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ __('New shipment') }}</h1>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Five short steps: the route, the packages, the service, the people involved, then a review. Your progress is saved after every step, so you can close the page and come back to it.') }}</p>
            </div>
            @if ($drafts->isNotEmpty() && ! $draft)
                <details class="relative">
                    <summary class="btn-ghost list-none !py-2 text-sm"><x-lucide name="refresh-cw" class="size-4" /> {{ __('Resume a draft') }}</summary>
                    <ul class="absolute right-0 z-20 mt-2 w-72 rounded-[6px] border border-line bg-white p-2 shadow-[var(--shadow-lift)]">
                        @foreach ($drafts as $item)
                            <li><a href="{{ lroute('account.shipments.create', ['draft' => $item->public_id]) }}" class="block rounded-[4px] px-3 py-2 text-sm hover:bg-surface"><span class="font-medium text-ink-950">{{ __('Step :step of 5', ['step' => min(5, $item->step)]) }}</span> · <span class="text-slate-600">{{ $item->updated_at->diffForHumans() }}</span></a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        {{-- Stepper --}}
        <ol class="mt-8 grid grid-cols-5 gap-2" aria-label="{{ __('Booking steps') }}">
            @foreach ($steps as $index => $label)
                <li>
                    <button type="button" @click="goTo({{ $index + 1 }})" :disabled="{{ $index + 1 }} > furthest" class="group w-full text-left disabled:cursor-not-allowed"
                            :aria-current="step === {{ $index + 1 }} ? 'step' : null">
                        <span class="block h-1 transition-colors" :class="step >= {{ $index + 1 }} ? 'bg-brand-500' : 'bg-line'"></span>
                        <span class="mt-2 hidden items-center gap-1.5 text-xs font-semibold sm:flex" :class="step === {{ $index + 1 }} ? 'text-ink-950' : 'text-slate-500'">
                            <span class="grid size-5 place-items-center rounded-[3px] text-[10px] tabular" :class="step > {{ $index + 1 }} ? 'bg-emerald-700 text-white' : (step === {{ $index + 1 }} ? 'bg-ink-900 text-white' : 'bg-line text-slate-600')">{{ $index + 1 }}</span>
                            {{ $label }}
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="card mt-6 p-6 sm:p-8">
            <p x-show="quoteReference" x-cloak class="mb-6 inline-flex items-center gap-2 rounded-[3px] bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700"><x-lucide name="receipt" class="size-3.5" /> {{ __('From quote') }} <span class="font-mono" x-text="quoteReference"></span></p>

            {{-- Step 1: Route --}}
            <section x-show="step === 1">
                <h2 class="text-xl font-bold">{{ __('Where is it going?') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Both cities matter: the city pair fixes the corridor, the delivery window and the price band. Use the real pickup and delivery addresses so the carrier can find them.') }}</p>
                <div class="mt-6 grid gap-8 lg:grid-cols-2">
                    @foreach (['origin' => [__('Pickup address'), 'sender'], 'destination' => [__('Delivery address'), 'recipient']] as $field => [$heading, $party])
                        <fieldset class="space-y-4">
                            <legend class="flex w-full items-center justify-between font-semibold text-ink-950">
                                {{ $heading }}
                                @if ($addresses->isNotEmpty())
                                    <select class="field !w-auto !py-1.5 text-xs" @change="useAddress('{{ $field }}', $event.target.value)" aria-label="{{ __('Use a saved address') }}">
                                        <option value="">{{ __('Saved addresses') }}</option>
                                        @foreach ($addresses as $address)
                                            <option value="{{ $address->public_id }}">{{ $address->label ?: $address->name }} · {{ $address->city }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </legend>
                            <x-place-field :field="$field" :label="__('City')" />
                            <p class="field-error" x-show="fieldError('{{ $field }}.city') || fieldError('{{ $field }}.lat')" x-text="fieldError('{{ $field }}.city') || fieldError('{{ $field }}.lat')"></p>
                            <div>
                                <label class="field-label" for="{{ $field }}-line1">{{ __('Street address') }}</label>
                                <input id="{{ $field }}-line1" x-model="form.route.{{ $field }}.line1" maxlength="200" class="field" autocomplete="address-line1">
                                <p class="field-error" x-show="fieldError('{{ $field }}.line1')" x-text="fieldError('{{ $field }}.line1')"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="field-label" for="{{ $field }}-line2">{{ __('Apartment, suite (optional)') }}</label>
                                    <input id="{{ $field }}-line2" x-model="form.route.{{ $field }}.line2" maxlength="200" class="field">
                                </div>
                                <div>
                                    <label class="field-label" for="{{ $field }}-postal">{{ __('Postal code') }}</label>
                                    <input id="{{ $field }}-postal" x-model="form.route.{{ $field }}.postal_code" maxlength="20" class="field">
                                    <p class="field-error" x-show="fieldError('{{ $field }}.postal_code')" x-text="fieldError('{{ $field }}.postal_code')"></p>
                                </div>
                            </div>
                            <p class="flex items-center gap-1.5 text-xs text-slate-500" x-show="form.route.{{ $field }}.lat !== null">
                                <x-lucide name="map-pin" class="size-3.5 text-emerald-500" />
                                <span x-text="form.route.{{ $field }}.city + ', ' + form.route.{{ $field }}.country"></span>
                            </p>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            {{-- Step 2: Packages --}}
            <section x-show="step === 2" x-cloak>
                <h2 class="text-xl font-bold">{{ __('What are you sending?') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{!! __('Some goods cannot be carried. See the :link.', ['link' => '<a class="link" target="_blank" rel="noopener noreferrer" href="'.e(lroute('page.prohibited-items')).'">'.e(__('prohibited items list')).'</a>']) !!}</p>
                <div class="mt-6 space-y-4">
                    <template x-for="(pkg, index) in form.packages" :key="index">
                        <div class="rounded-[6px] border border-line p-5">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-ink-950">{{ __('Package') }} <span class="tabular" x-text="index + 1"></span></p>
                                <button type="button" @click="removePackage(index)" x-show="form.packages.length > 1" class="text-sm text-slate-500 hover:text-red-600">{{ __('Remove') }}</button>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <label class="text-sm font-medium text-ink-900 sm:col-span-2">{{ __('Description of contents') }}
                                    <input x-model="pkg.description" maxlength="200" class="field mt-1.5" placeholder="{{ __('e.g. clothes and shoes') }}">
                                    <span class="field-error block" x-text="fieldError('packages.' + index + '.description')"></span>
                                </label>
                                <label class="text-sm font-medium text-ink-900">{{ __('Category') }}
                                    <select x-model="pkg.category" class="field mt-1.5">
                                        <option value="">{{ __('Choose…') }}</option>
                                        @foreach ($categories as $code => $category)
                                            <option value="{{ $code }}" @disabled($category['prohibited'] ?? false)>{{ __('category.'.$code) }}{{ ($category['prohibited'] ?? false) ? ' · '.__('not accepted') : (($category['restricted'] ?? false) ? ' · '.__('restrictions apply') : '') }}</option>
                                        @endforeach
                                    </select>
                                    <span class="field-error block" x-text="fieldError('packages.' + index + '.category')"></span>
                                </label>
                                <label class="text-sm font-medium text-ink-900">{{ __('Value (USD)') }}
                                    <input x-model="pkg.value" type="number" min="0" max="100000" step="0.01" class="field mt-1.5">
                                </label>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <label class="text-xs font-medium text-slate-600">{{ __('Weight (kg)') }}<input x-model="pkg.weight_kg" type="number" min="0.1" max="3000" step="0.1" class="field mt-1"></label>
                                <label class="text-xs font-medium text-slate-600">{{ __('Length (cm)') }}<input x-model="pkg.length_cm" type="number" min="1" max="600" class="field mt-1"></label>
                                <label class="text-xs font-medium text-slate-600">{{ __('Width (cm)') }}<input x-model="pkg.width_cm" type="number" min="1" max="600" class="field mt-1"></label>
                                <label class="text-xs font-medium text-slate-600">{{ __('Height (cm)') }}<input x-model="pkg.height_cm" type="number" min="1" max="600" class="field mt-1"></label>
                            </div>
                            <p class="field-error" x-text="fieldError('packages.' + index + '.weight_kg') || fieldError('packages.' + index + '.length_cm')"></p>
                        </div>
                    </template>
                    <button type="button" @click="addPackage()" class="btn-ghost !py-2 text-sm"><x-lucide name="plus" class="size-4" /> {{ __('Add a package') }}</button>
                    <p class="text-sm text-slate-600">{{ __('Total') }}: <strong x-text="totalWeight() + ' kg'"></strong> · <strong x-text="'$' + totalValue()"></strong></p>
                </div>
            </section>

            {{-- Step 3: Service --}}
            <section x-show="step === 3" x-cloak>
                <h2 class="text-xl font-bold">{{ __('Choose your service') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('Live prices for your route and packages. The window shown is the published transit range for the service you pick.') }}</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <template x-if="pricing">
                        <div class="skeleton h-28 sm:col-span-2"></div>
                    </template>
                    <template x-for="option in options" :key="option.mode">
                        <button type="button" @click="option.available && (form.service.mode = option.mode)" :disabled="!option.available"
                                class="rounded-[6px] border p-5 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                :class="form.service.mode === option.mode ? 'border-brand-500 bg-brand-50/60' : 'border-line hover:border-slate-400'">
                            <div class="flex items-start justify-between gap-3">
                                <p class="font-bold text-ink-950" x-text="modeLabel(option.mode)"></p>
                                <p class="text-lg font-bold text-ink-950 tabular" x-show="option.available" x-text="option.total_formatted"></p>
                            </div>
                            <p class="mt-2 text-sm text-slate-600" x-show="option.available"><span x-text="option.transit_min_days"></span>–<span x-text="option.transit_max_days"></span> {{ __('days') }} · <span x-text="option.chargeable_weight_kg"></span> kg {{ __('chargeable') }}</p>
                            <p class="mt-2 text-sm text-slate-500" x-show="!option.available" x-text="option.reason"></p>
                        </button>
                    </template>
                </div>
                <label class="mt-5 flex cursor-pointer items-center gap-3 rounded-[6px] border border-line p-4">
                    <input type="checkbox" :checked="form.service.insurance" @change="toggleInsurance()" class="size-5 rounded border-line text-brand-500 focus:ring-brand-500">
                    <span class="text-sm"><span class="font-semibold text-ink-900">{{ __('Insure my shipment') }}</span><br><span class="text-slate-500">{{ __('Coverage for loss and damage up to the declared value.') }}</span></span>
                </label>
                <p class="mt-3 text-sm text-slate-600" x-show="selectedOption()"><span x-text="selectedOption() && selectedOption().network_label"></span></p>
            </section>

            {{-- Step 4: Parties --}}
            <section x-show="step === 4" x-cloak>
                <h2 class="text-xl font-bold">{{ __('Sender and recipient') }}</h2>
                <div class="mt-6 grid gap-8 lg:grid-cols-2">
                    @foreach (['sender' => __('Sender'), 'recipient' => __('Recipient')] as $party => $heading)
                        <fieldset class="space-y-3">
                            <legend class="font-semibold text-ink-950">{{ $heading }}</legend>
                            <label class="block text-sm font-medium text-ink-900">{{ __('Full name') }}<input x-model="form.parties.{{ $party }}.name" maxlength="120" class="field mt-1.5" autocomplete="{{ $party === 'sender' ? 'name' : 'off' }}">
                                <span class="field-error block" x-text="fieldError('{{ $party }}.name')"></span></label>
                            <label class="block text-sm font-medium text-ink-900">{{ __('Company (optional)') }}<input x-model="form.parties.{{ $party }}.company" maxlength="120" class="field mt-1.5"></label>
                            <label class="block text-sm font-medium text-ink-900">{{ __('Phone') }}<input x-model="form.parties.{{ $party }}.phone" type="tel" maxlength="30" class="field mt-1.5" placeholder="+33 6 12 34 56 78">
                                <span class="field-error block" x-text="fieldError('{{ $party }}.phone')"></span></label>
                            <label class="block text-sm font-medium text-ink-900">{{ __('Email (optional)') }}<input x-model="form.parties.{{ $party }}.email" type="email" maxlength="190" class="field mt-1.5">
                                <span class="field-error block" x-text="fieldError('{{ $party }}.email')"></span></label>
                        </fieldset>
                    @endforeach
                </div>
                <fieldset class="mt-8 rounded-[6px] bg-surface p-5" x-show="isInternational()">
                    <legend class="px-1 font-semibold text-ink-950">{{ __('Customs details') }}</legend>
                    <p class="mt-1 mb-3 text-xs leading-5 text-slate-600">{{ __('A commercial invoice is generated from these fields. A vague description or a missing HS code is the most common reason a parcel waits at customs.') }}</p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-sm font-medium text-ink-900 sm:col-span-2">{{ __('Contents') }}<input x-model="form.parties.customs.contents" maxlength="300" class="field mt-1.5"></label>
                        <label class="text-sm font-medium text-ink-900">{{ __('HS code (optional)') }}<input x-model="form.parties.customs.hs_code" maxlength="14" class="field mt-1.5 font-mono">
                            <span class="field-error block" x-text="fieldError('customs.hs_code')"></span></label>
                        <label class="text-sm font-medium text-ink-900">{{ __('Reason for export') }}
                            <select x-model="form.parties.customs.reason" class="field mt-1.5">
                                @foreach (['personal' => __('Personal effects'), 'gift' => __('Gift'), 'sale' => __('Sale of goods'), 'documents' => __('Documents'), 'sample' => __('Sample'), 'return' => __('Return')] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </fieldset>
            </section>

            {{-- Step 5: Review --}}
            <section x-show="step === 5" x-cloak>
                <h2 class="text-xl font-bold">{{ __('Review and confirm') }}</h2>
                <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded-[6px] border border-line p-4"><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('From') }}</dt><dd class="mt-1 text-ink-900" x-text="form.route.origin.line1 + ', ' + form.route.origin.city + ' ' + form.route.origin.country"></dd><dd class="text-slate-600" x-text="form.parties.sender.name"></dd></div>
                    <div class="rounded-[6px] border border-line p-4"><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('To') }}</dt><dd class="mt-1 text-ink-900" x-text="form.route.destination.line1 + ', ' + form.route.destination.city + ' ' + form.route.destination.country"></dd><dd class="text-slate-600" x-text="form.parties.recipient.name"></dd></div>
                    <div class="rounded-[6px] border border-line p-4"><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Packages') }}</dt><dd class="mt-1 text-ink-900"><span x-text="form.packages.length"></span> · <span x-text="totalWeight() + ' kg'"></span></dd></div>
                    <div class="rounded-[6px] border border-line p-4"><dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Service') }}</dt><dd class="mt-1 text-ink-900" x-text="modeLabel(form.service.mode)"></dd><dd class="text-slate-600" x-show="form.service.insurance">{{ __('Insured') }}</dd></div>
                </dl>
                <div class="mt-6 flex items-center justify-between rounded-[6px] bg-ink-950 p-5 text-white" x-show="selectedOption()">
                    <span class="text-sm text-ink-100">{{ __('Total to pay') }}</span>
                    <span class="text-2xl font-bold tabular" x-text="selectedOption() && selectedOption().total_formatted"></span>
                </div>
                <label class="mt-6 flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" x-model="form.review.accepted_terms" class="mt-0.5 size-5 rounded border-line text-brand-500 focus:ring-brand-500">
                    <span>{!! __('I confirm the contents are accurately described, contain no prohibited items, and I accept the :terms and :shipping.', ['terms' => '<a class="link" target="_blank" rel="noopener noreferrer" href="'.e(lroute('page.terms')).'">'.e(__('terms of service')).'</a>', 'shipping' => '<a class="link" target="_blank" rel="noopener noreferrer" href="'.e(lroute('page.shipping-policy')).'">'.e(__('shipping policy')).'</a>']) !!}</span>
                </label>
            </section>

            <p x-show="error" x-cloak class="mt-6 rounded-[4px] bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error" role="alert"></p>

            <div class="mt-8 flex items-center justify-between border-t border-line pt-6">
                <button type="button" class="btn-ghost" @click="back()" x-show="step > 1"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('Back') }}</button>
                <span x-show="step === 1"></span>
                <button type="button" class="btn-primary" @click="next()" x-show="step < 5" :disabled="saving">
                    <span x-show="!saving">{{ __('Save and continue') }}</span>
                    <span x-show="saving" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Saving') }}</span>
                </button>
                <button type="button" class="btn-primary" @click="book()" x-show="step === 5" x-cloak :disabled="booking">
                    <span x-show="!booking">{{ __('Continue to payment') }} </span>
                    <span x-show="booking" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Creating order') }}</span>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('data')
    @php
        $draftData = $draft ? ['id' => $draft->public_id, 'step' => $draft->step, 'data' => $draft->data] : null;
        $addressBook = $addresses->map(fn ($a) => $a->only(['public_id', 'label', 'name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country', 'phone', 'email', 'lat', 'lon']))->values();
    @endphp
    @if ($draftData)
        <script type="application/json" id="draft-data">@json($draftData)</script>
    @endif
    <script type="application/json" id="address-book">@json($addressBook)</script>
@endpush
