@extends('layouts.account', ['title' => __('Payment')])

@php
    $payable = $order->status->acceptsMethodSelection() || $order->status->acceptsProof();
    $payable = $payable && ! ($order->expires_at?->isPast() ?? false);
    $icons = ['cashapp' => 'credit-card', 'zelle' => 'credit-card', 'venmo' => 'credit-card', 'chime' => 'credit-card', 'apple_pay' => 'credit-card', 'google_pay' => 'credit-card', 'gift_card' => 'receipt', 'iban' => 'warehouse', 'paypal' => 'credit-card', 'custom' => 'credit-card'];
@endphp

@section('account')
    <div x-data="payPage" data-order="{{ $order->public_id }}" data-selected="{{ $selected }}" data-payable="{{ $payable ? 'true' : 'false' }}">
        <a href="{{ lroute('account.orders') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-900"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('Orders') }}</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold">{{ __('Pay for order :number', ['number' => $order->number]) }}</h1>
                <p class="mt-1 text-slate-600">{{ $order->shipment?->originLabel() }} → {{ $order->shipment?->destinationLabel() }}</p>
            </div>
            <x-status-badge :status="$order->status->value" :label="$order->status->label()" class="!px-3 !py-1.5 !text-sm" />
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="card p-5">
                <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Shipment price') }}</p>
                <p class="mt-1 font-display text-2xl font-extrabold text-ink-900">{{ \App\Support\Money::format($order->subtotal, $order->currency) }}</p>
                @if ($order->fee > 0)<p class="text-xs text-slate-500">+ {{ \App\Support\Money::format($order->fee, $order->currency) }} {{ __('payment fee') }}</p>@endif
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Payment reference') }}</p>
                <p class="mt-1 font-mono text-2xl font-extrabold text-brand-600">{{ $order->payment_reference }}</p>
                <p class="text-xs text-slate-500">{{ __('Write it in the payment note.') }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold tracking-wider text-slate-500 uppercase">{{ __('Time left to pay') }}</p>
                @if ($payable && $order->expires_at)
                    <p x-data="countdown" data-until="{{ $order->expires_at->toIso8601String() }}" class="mt-1 font-mono text-2xl font-extrabold" :class="urgent ? 'text-red-600' : 'text-ink-900'" x-text="remaining">--:--:--</p>
                    <p class="text-xs text-slate-500">{{ __('Until :time UTC', ['time' => $order->expires_at->translatedFormat('j M, H:i')]) }}</p>
                @else
                    <p class="mt-1 font-display text-2xl font-extrabold text-ink-900">—</p>
                @endif
            </div>
        </div>

        @if ($order->status === \App\Enums\OrderStatus::Paid)
            <div class="mt-6 flex items-center gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                <x-lucide name="circle-check" class="size-7 text-emerald-600" />
                <div class="flex-1">
                    <p class="font-semibold text-emerald-900">{{ __('Payment approved') }}</p>
                    <p class="text-sm text-emerald-800">{{ __('Receipt :number. Your label and tracking number are ready.', ['number' => $order->receipt_number]) }}</p>
                </div>
                @if ($order->shipment)<a href="{{ lroute('account.shipments.show', $order->shipment) }}" class="btn-dark !py-2">{{ __('View shipment') }}</a>@endif
            </div>
        @elseif (in_array($order->status, [\App\Enums\OrderStatus::ProofSubmitted, \App\Enums\OrderStatus::UnderReview], true))
            <div class="mt-6 flex items-center gap-4 rounded-2xl border border-violet-200 bg-violet-50 p-5">
                <x-lucide name="hourglass" class="size-7 text-violet-600" />
                <div>
                    <p class="font-semibold text-violet-900">{{ __('Payment under review') }}</p>
                    <p class="text-sm text-violet-800">{{ __('Thank you. A payment verifier is checking your proof. Target review time: :minutes minutes during staffed hours (:hours). We will email you as soon as it is done.', ['minutes' => config('platform.settings.review_target_minutes'), 'hours' => config('platform.settings.staffed_hours')]) }}</p>
                </div>
            </div>
        @elseif (! $payable)
            <div class="mt-6 rounded-2xl border border-line bg-white p-5 text-sm text-slate-600">{{ __('This order can no longer be paid. Please request a new quote.') }}</div>
        @endif

        @if (in_array($order->status, [\App\Enums\OrderStatus::ProofRejected, \App\Enums\OrderStatus::MoreInfoRequested, \App\Enums\OrderStatus::PartiallyPaid], true))
            @php($lastReview = $order->proofs->sortByDesc('submitted_at')->first()?->reviews->last())
            <div class="mt-6 flex gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <x-lucide name="triangle-alert" class="size-6 shrink-0 text-amber-600" />
                <div>
                    <p class="font-semibold">{{ $order->status === \App\Enums\OrderStatus::PartiallyPaid ? __('A balance is still due') : ($order->status === \App\Enums\OrderStatus::ProofRejected ? __('Your proof was not accepted') : __('We need more information')) }}</p>
                    @if ($lastReview)
                        <p class="mt-1">{{ $lastReview->reason_code && isset(\App\Enums\ReviewDecision::rejectReasons()[$lastReview->reason_code]) ? \App\Enums\ReviewDecision::rejectReasons()[$lastReview->reason_code] : '' }} {{ $lastReview->note }}</p>
                    @endif
                    <p class="mt-1">{{ __('You can upload a new proof or choose another payment method below.') }}</p>
                </div>
            </div>
        @endif

        @if ($payable)
            <section class="mt-10">
                <h2 class="text-lg font-bold">{{ __('1. Choose how to pay') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Account details appear only after you choose a method, and only on this page.') }}</p>
                @if ($methods->isEmpty())
                    <p class="mt-4 rounded-2xl border border-line bg-white p-5 text-sm text-slate-600">{{ __('No payment method is available for this order right now. Please contact support.') }}</p>
                @endif
                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($methods as $method)
                        <button type="button" data-method="{{ $method['id'] }}" @click="select($el.dataset.method)" :disabled="selecting"
                                class="group flex items-center gap-4 rounded-2xl border-2 bg-white p-4 text-left transition hover:-translate-y-0.5 disabled:opacity-60"
                                :class="selected === '{{ $method['id'] }}' ? 'border-brand-500 shadow-[0_10px_30px_-15px_rgb(255_107_44/0.6)]' : 'border-line hover:border-slate-300'">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl transition" :class="selected === '{{ $method['id'] }}' ? 'bg-brand-500 text-white' : 'bg-surface text-ink-900'">
                                <x-lucide :name="$icons[$method['kind']] ?? 'credit-card'" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink-900">{{ $method['name'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $method['currency'] }} · {{ __('fee') }} {{ $method['fee'] }}</span>
                            </span>
                            <x-lucide name="circle-check" class="size-5 text-brand-500" x-show="selected === '{{ $method['id'] }}'" />
                        </button>
                    @endforeach
                </div>
                <p x-show="error && !details" x-cloak class="mt-4 text-sm text-red-600" x-text="error" role="alert"></p>
            </section>

            <section x-ref="details" x-show="details" x-cloak class="mt-10 scroll-mt-24">
                <h2 class="text-lg font-bold">{{ __('2. Send the payment') }}</h2>
                <div class="mt-5 grid gap-6 lg:grid-cols-5">
                    <div class="card overflow-hidden lg:col-span-3">
                        <div class="flex items-center justify-between bg-ink-900 p-5 text-white">
                            <div>
                                <p class="text-xs tracking-wider text-slate-400 uppercase">{{ __('Amount to send') }}</p>
                                <p class="font-display text-3xl font-extrabold" x-text="details && details.amount_due.formatted"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs tracking-wider text-slate-400 uppercase">{{ __('Reference') }}</p>
                                <p class="font-mono text-lg font-bold text-brand-300" x-text="details && details.reference"></p>
                            </div>
                        </div>
                        <dl class="divide-y divide-line">
                            <template x-for="(field, index) in (details ? details.fields : [])" :key="index">
                                <div class="flex items-center gap-4 p-4">
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-xs font-semibold tracking-wider text-slate-500 uppercase" x-text="field.label"></dt>
                                        <dd class="mt-0.5 font-mono text-sm break-all text-ink-900" x-show="field.type !== 'link' && field.type !== 'qr'" x-text="field.value"></dd>
                                        <dd class="mt-0.5 text-sm" x-show="field.type === 'link'"><a :href="field.value" target="_blank" rel="noopener noreferrer nofollow" class="link break-all" x-text="field.value"></a></dd>
                                        <dd class="mt-2" x-show="field.type === 'qr'"><img :src="field.value" alt="{{ __('Payment QR code') }}" class="size-40 rounded-xl border border-line" x-show="field.value && field.value.startsWith('data:image/')"></dd>
                                    </div>
                                    <button type="button" x-show="field.type === 'copy' || field.type === 'text'" @click="copy(field.value)" class="btn-ghost !px-3 !py-1.5 text-xs">
                                        <x-lucide name="copy" class="size-3.5" />
                                        <span x-show="copied !== field.value">{{ __('Copy') }}</span>
                                        <span x-show="copied === field.value" x-cloak>{{ __('Copied') }}</span>
                                    </button>
                                </div>
                            </template>
                        </dl>
                    </div>
                    <div class="space-y-4 lg:col-span-2">
                        <div class="card p-5">
                            <p class="font-display font-semibold text-ink-900">{{ __('Steps') }}</p>
                            <ol class="mt-3 space-y-3 text-sm text-slate-700">
                                <template x-for="(stepText, index) in (details ? details.steps : [])" :key="index">
                                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700" x-text="index + 1"></span><span x-text="stepText"></span></li>
                                </template>
                            </ol>
                            <p class="mt-4 rounded-xl bg-surface p-3 text-sm whitespace-pre-line text-slate-600" x-show="details && details.instructions" x-text="details && details.instructions"></p>
                            <p class="mt-3 text-xs text-slate-500" x-show="details && details.exchange_rate">{{ __('Exchange rate locked for this order') }}: 1 USD = <span x-text="details && details.exchange_rate"></span> <span x-text="details && details.method.currency"></span></p>
                        </div>
                        <div class="rounded-[var(--radius-card)] border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
                            <p class="flex items-center gap-1.5 font-semibold"><x-lucide name="shield-check" class="size-4" /> {{ __('Before you pay') }}</p>
                            <p class="mt-1">{{ __('Only pay for a shipment you booked yourself. If someone asked you to pay for a parcel or to buy gift cards, stop and contact us: it may be a scam.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section x-show="details" x-cloak class="mt-10">
                <h2 class="text-lg font-bold">{{ __('3. Upload your proof of payment') }}</h2>
                <div x-show="uploaded" x-cloak class="mt-5 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                    <x-lucide name="circle-check" class="size-6 text-emerald-600" /> {{ __('Thank you. Your proof was received and will be reviewed shortly.') }}
                </div>
                <form x-show="!uploaded" @submit.prevent="submitProof()" class="card mt-5 space-y-5 p-6" novalidate>
                    <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                         class="rounded-2xl border-2 border-dashed p-6 text-center transition" :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-line'">
                        <x-lucide name="upload" class="mx-auto size-8 text-brand-500" />
                        <p class="mt-2 text-sm font-medium text-ink-900">{{ __('Drag your screenshot or receipt here') }}</p>
                        <p class="text-xs text-slate-500">{{ __('JPG, PNG, WebP, HEIC or PDF · up to 8 MB · up to 3 files') }}</p>
                        <label class="btn-ghost mt-4 cursor-pointer !py-2 text-sm">
                            {{ __('Choose files') }}
                            <input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" @change="pickFiles($event)">
                        </label>
                        <ul class="mt-4 space-y-2 text-left" x-show="files.length">
                            <template x-for="(item, index) in files" :key="index">
                                <li class="flex items-center gap-3 rounded-xl bg-surface px-3 py-2 text-sm">
                                    <x-lucide name="file-text" class="size-4 text-slate-500" />
                                    <span class="flex-1 truncate" x-text="item.name"></span>
                                    <span class="text-xs text-slate-500" x-text="item.size"></span>
                                    <button type="button" @click="removeFile(index)" class="text-slate-400 hover:text-red-600" aria-label="{{ __('Remove file') }}"><x-lucide name="x" class="size-4" /></button>
                                </li>
                            </template>
                        </ul>
                        <p class="field-error" x-show="fileError || uploadError('files')" x-text="fileError || uploadError('files')"></p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium text-ink-900">{{ __('Amount paid') }} (<span x-text="details && details.amount_due.currency"></span>)
                            <input x-model="form.amount_paid" type="number" min="0" step="0.01" required class="field mt-1.5">
                            <span class="field-error block" x-text="uploadError('amount_paid')"></span>
                        </label>
                        <label class="text-sm font-medium text-ink-900">{{ __('Payment date') }}
                            <input x-model="form.paid_on" type="date" required class="field mt-1.5" max="{{ now()->toDateString() }}">
                            <span class="field-error block" x-text="uploadError('paid_on')"></span>
                        </label>
                        <label class="text-sm font-medium text-ink-900">{{ __('Name of the payer') }}
                            <input x-model="form.payer_name" maxlength="120" required class="field mt-1.5" autocomplete="name">
                            <span class="field-error block" x-text="uploadError('payer_name')"></span>
                        </label>
                        <label class="text-sm font-medium text-ink-900">{{ __('Transaction ID') }} <span class="font-normal text-slate-500" x-show="!(details && details.method.requires_transaction_id)">({{ __('optional') }})</span>
                            <input x-model="form.transaction_id" maxlength="120" class="field mt-1.5 font-mono">
                            <span class="field-error block" x-text="uploadError('transaction_id')"></span>
                        </label>
                    </div>

                    <fieldset x-show="isGiftCard()" class="space-y-3 rounded-2xl border border-red-200 bg-red-50/50 p-5">
                        <legend class="px-1 text-sm font-semibold text-red-800">{{ __('Gift card details') }}</legend>
                        <p class="text-xs leading-5 text-red-800">{{ __('Gift cards are accepted as payment for shipping services only and are non-refundable once redeemed. Never buy gift cards because a stranger, a seller or a “customs officer” asked you to.') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="text-sm font-medium text-ink-900">{{ __('Brand') }}
                                <select x-model="giftCard.brand" class="field mt-1.5">
                                    <option value="">{{ __('Choose…') }}</option>
                                    <template x-for="brand in (details && details.gift_card ? details.gift_card.brands : [])" :key="brand"><option :value="brand" x-text="brand"></option></template>
                                </select>
                            </label>
                            <label class="text-sm font-medium text-ink-900">{{ __('Card value') }}<input x-model="giftCard.amount" type="number" min="1" step="0.01" class="field mt-1.5"></label>
                            <label class="text-sm font-medium text-ink-900">{{ __('Card code') }}<input x-model="giftCard.code" maxlength="80" autocomplete="off" class="field mt-1.5 font-mono"></label>
                            <label class="text-sm font-medium text-ink-900">{{ __('PIN (if any)') }}<input x-model="giftCard.pin" maxlength="20" autocomplete="off" class="field mt-1.5 font-mono"></label>
                        </div>
                    </fieldset>

                    <label class="block text-sm font-medium text-ink-900">{{ __('Note (optional)') }}
                        <textarea x-model="form.note" rows="2" maxlength="1000" class="field mt-1.5"></textarea>
                    </label>

                    <p x-show="error && details" x-cloak class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error" role="alert"></p>

                    <button type="submit" class="btn-primary" :disabled="uploading">
                        <span x-show="!uploading">{{ __('Submit proof of payment') }}</span>
                        <span x-show="uploading" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Uploading securely') }}</span>
                    </button>
                </form>
            </section>
        @endif

        <section class="mt-12" x-show="history.length" x-cloak>
            <h2 class="text-lg font-bold">{{ __('Proof history') }}</h2>
            <div class="card mt-4 divide-y divide-line">
                <template x-for="proof in history" :key="proof.id">
                    <div class="flex flex-wrap items-center gap-4 p-4 text-sm">
                        <span class="font-semibold text-ink-900" x-text="proof.method"></span>
                        <span class="text-slate-600" x-text="proof.amount_paid"></span>
                        <span class="text-slate-500" x-text="when(proof.submitted_at)"></span>
                        <span class="ml-auto badge bg-surface text-slate-700" x-text="proof.status_label"></span>
                        <p class="basis-full text-slate-600" x-show="proof.reason" x-text="proof.reason"></p>
                    </div>
                </template>
            </div>
        </section>

        @if ($order->status->canTransitionTo(\App\Enums\OrderStatus::Cancelled) && in_array($order->shipment?->status?->value, ['awaiting_payment', 'payment_under_review', 'ready'], true))
            <form method="POST" action="{{ lroute('account.orders.cancel', $order) }}" class="mt-12 border-t border-line pt-6">
                @csrf
                <button type="submit" class="text-sm font-medium text-slate-500 hover:text-red-600">{{ __('Cancel this order') }}</button>
            </form>
        @endif
    </div>
@endsection

@push('data')
    <script type="application/json" id="payment-methods">@json($methods)</script>
@endpush
