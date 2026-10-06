@extends('layouts.account', ['title' => __('Payment')])

@php
    $payable = $order->status->acceptsMethodSelection() || $order->status->acceptsProof();
    $payable = $payable && ! ($order->expires_at?->isPast() ?? false);
    $icons = ['cashapp' => 'smartphone', 'zelle' => 'smartphone', 'venmo' => 'smartphone', 'chime' => 'smartphone', 'apple_pay' => 'smartphone', 'google_pay' => 'smartphone', 'gift_card' => 'gift', 'iban' => 'landmark', 'paypal' => 'credit-card', 'custom' => 'credit-card'];
    $proofStatuses = [\App\Enums\OrderStatus::ProofRejected, \App\Enums\OrderStatus::MoreInfoRequested, \App\Enums\OrderStatus::PartiallyPaid];
@endphp

@section('account')
    <div x-data="payPage" data-order="{{ $order->public_id }}" data-selected="{{ $selected }}" data-payable="{{ $payable ? 'true' : 'false' }}">
        <nav class="text-sm" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ lroute('account.orders') }}" class="inline-flex items-center gap-1 text-slate-600 hover:text-ink-950">
                <x-lucide name="chevron-left" class="size-4" /> {{ __('Orders') }}
            </a>
        </nav>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ __('Pay for order :number', ['number' => $order->number]) }}</h1>
                <p class="mt-1.5 text-sm text-slate-600">{{ $order->shipment?->originLabel() }} <span class="text-slate-500">→</span> {{ $order->shipment?->destinationLabel() }}</p>
            </div>
            <x-status-badge :status="$order->status->value" :label="$order->status->label()" class="!px-3 !py-1.5 !text-sm" />
        </div>

        {{-- Figures --}}
        <dl class="mt-7 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">
            <div class="bg-white p-5">
                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Shipment price') }}</dt>
                <dd class="mt-2 text-2xl font-bold text-ink-950 tabular">{{ \App\Support\Money::format($order->subtotal, $order->currency) }}</dd>
                @if ($order->fee > 0)
                    <p class="mt-1 text-xs text-slate-600 tabular">+ {{ \App\Support\Money::format($order->fee, $order->currency) }} {{ __('payment fee') }}</p>
                @endif
                <p class="mt-1 text-xs text-slate-600">{{ __('Total due') }} <span class="font-semibold text-ink-900 tabular">{{ \App\Support\Money::format($order->total, $order->currency) }}</span></p>
            </div>
            <div class="bg-white p-5">
                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Payment reference') }}</dt>
                <dd class="mt-2 font-mono text-2xl font-bold break-all text-brand-700">{{ $order->payment_reference }}</dd>
                <p class="mt-1 text-xs text-slate-600">{{ __('Write it in the payment note so the verifier can match your transfer.') }}</p>
            </div>
            <div class="bg-white p-5">
                <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Time left to pay') }}</dt>
                @if ($payable && $order->expires_at)
                    <dd x-data="countdown" data-until="{{ $order->expires_at->toIso8601String() }}" class="mt-2">
                        <span class="font-mono text-2xl font-bold tabular" :class="urgent ? 'text-red-700' : 'text-ink-950'" x-text="remaining">--:--:--</span>
                    </dd>
                    <p class="mt-1 text-xs text-slate-600">{{ __('Until :time UTC', ['time' => $order->expires_at->translatedFormat('j M, H:i')]) }}</p>
                @else
                    <dd class="mt-2 text-2xl font-bold text-ink-950">—</dd>
                @endif
            </div>
        </dl>

        {{-- State of the order, one message at a time --}}
        @if ($order->status === \App\Enums\OrderStatus::Paid)
            <div class="mt-6 flex flex-col gap-4 rounded-[6px] border border-emerald-200 bg-emerald-50 p-5 sm:flex-row sm:items-center">
                <x-lucide name="circle-check" class="size-7 shrink-0 text-emerald-700" />
                <div class="flex-1">
                    <p class="font-semibold text-emerald-900">{{ __('Payment approved') }}</p>
                    <p class="mt-0.5 text-sm leading-6 text-emerald-900">{{ __('Receipt :number. Your label and tracking number are ready.', ['number' => $order->receipt_number]) }}</p>
                </div>
                @if ($order->shipment)<a href="{{ lroute('account.shipments.show', $order->shipment) }}" class="btn-dark !py-2">{{ __('View shipment') }}</a>@endif
            </div>
        @elseif (in_array($order->status, [\App\Enums\OrderStatus::ProofSubmitted, \App\Enums\OrderStatus::UnderReview], true))
            <div class="mt-6 flex flex-col gap-4 rounded-[6px] border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-start">
                <x-lucide name="hourglass" class="size-7 shrink-0 text-amber-700" />
                <div class="text-sm leading-6 text-amber-900">
                    <p class="font-semibold">{{ __('Payment under review') }}</p>
                    <p class="mt-0.5">{{ __('Thank you. A payment verifier is checking your proof. Target review time: :minutes minutes during staffed hours (:hours). We will email you as soon as it is done.', ['minutes' => (int) config('platform.settings.review_target_minutes'), 'hours' => config('platform.settings.staffed_hours')]) }}</p>
                    <p class="mt-1">{{ __('Nothing else is needed from you right now. If a verifier needs a clearer image, they will ask from this page and by email.') }}</p>
                </div>
            </div>
        @elseif (! $payable)
            <div class="mt-6 flex flex-col gap-4 rounded-[6px] border border-line bg-white p-5 sm:flex-row sm:items-center">
                <x-lucide name="clock" class="size-6 shrink-0 text-slate-500" />
                <div class="flex-1 text-sm leading-6 text-slate-600">
                    <p class="font-semibold text-ink-950">{{ __('This order can no longer be paid') }}</p>
                    <p class="mt-0.5">{{ __('The validity window has closed and the reserved space on the route was released. Request a fresh quote to book the same shipment again — prices follow the live rate card.') }}</p>
                </div>
                <a href="{{ lroute('quote') }}" class="btn-primary !py-2">{{ __('Get a new quote') }}</a>
            </div>
        @endif

        @if (in_array($order->status, $proofStatuses, true))
            @php($lastReview = $order->proofs->sortByDesc('submitted_at')->first()?->reviews->last())
            <div class="mt-6 flex gap-4 rounded-[6px] border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                <x-lucide name="triangle-alert" class="size-6 shrink-0 text-amber-700" />
                <div>
                    <p class="font-semibold">{{ $order->status === \App\Enums\OrderStatus::PartiallyPaid ? __('A balance is still due') : ($order->status === \App\Enums\OrderStatus::ProofRejected ? __('Your proof was not accepted') : __('We need more information')) }}</p>
                    @if ($lastReview)
                        @php($reason = $lastReview->reason_code && isset(\App\Enums\ReviewDecision::rejectReasons()[$lastReview->reason_code]) ? \App\Enums\ReviewDecision::rejectReasons()[$lastReview->reason_code] : null)
                        <p class="mt-1">@if ($reason)<span class="font-medium">{{ $reason }}.</span> @endif{{ $lastReview->note }}</p>
                    @endif
                    <p class="mt-1">{{ __('You can upload a new proof or choose another payment method below. Nothing is lost: your booking stays reserved while the order is open.') }}</p>
                </div>
            </div>
        @endif

        @if ($payable)
            {{-- 1. Method --}}
            <section class="mt-10">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-line pb-4">
                    <div>
                        <p class="eyebrow">{{ __('Step 1 of 3') }}</p>
                        <h2 class="mt-1 text-lg font-bold">{{ __('Choose how to pay') }}</h2>
                    </div>
                    <p class="max-w-md text-xs leading-5 text-slate-600">{{ __('Account details appear only after you choose a method, and only on this page. Nothing is sent by email.') }}</p>
                </div>

                @if ($methods->isEmpty())
                    <p class="mt-5 rounded-[6px] border border-line bg-white p-5 text-sm leading-6 text-slate-600">{{ __('No payment method is available for this order right now. Please contact support and we will open one for your route.') }}</p>
                @endif

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($methods as $method)
                        <button type="button" data-method="{{ $method['id'] }}" @click="select($el.dataset.method)" :disabled="selecting"
                                class="flex items-center gap-4 rounded-[6px] border bg-white p-4 text-left transition-colors disabled:opacity-60"
                                :class="selected === '{{ $method['id'] }}' ? 'border-brand-500' : 'border-line hover:border-slate-400'">
                            <span class="grid size-11 shrink-0 place-items-center rounded-[4px] transition-colors" :class="selected === '{{ $method['id'] }}' ? 'bg-brand-500 text-white' : 'bg-surface text-ink-900'">
                                <x-lucide :name="$icons[$method['kind']] ?? 'credit-card'" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-ink-950">{{ $method['name'] }}</span>
                                <span class="block text-xs text-slate-600">{{ $method['currency'] }} · {{ __('fee') }} {{ $method['fee'] }}</span>
                            </span>
                            <x-lucide name="circle-check" class="size-5 shrink-0 text-brand-600" x-show="selected === '{{ $method['id'] }}'" />
                        </button>
                    @endforeach
                </div>
                <p x-show="error && !details" x-cloak class="mt-4 text-sm text-red-700" x-text="error" role="alert"></p>
            </section>

            {{-- 2. Send --}}
            <section x-ref="details" x-show="details" x-cloak class="mt-10 scroll-mt-24">
                <div class="border-b border-line pb-4">
                    <p class="eyebrow">{{ __('Step 2 of 3') }}</p>
                    <h2 class="mt-1 text-lg font-bold">{{ __('Send the payment') }}</h2>
                </div>

                <div class="mt-5 grid gap-6 lg:grid-cols-5">
                    <div class="overflow-hidden rounded-[6px] border border-line bg-white lg:col-span-3">
                        <div class="flex flex-wrap items-end justify-between gap-4 bg-ink-950 p-5 text-white">
                            <div>
                                <p class="text-xs font-semibold tracking-[0.08em] text-ink-200 uppercase">{{ __('Amount to send') }}</p>
                                <p class="mt-1 text-3xl font-bold tabular" x-text="details && details.amount_due.formatted"></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold tracking-[0.08em] text-ink-200 uppercase">{{ __('Reference') }}</p>
                                <p class="mt-1 font-mono text-lg font-bold text-brand-300" x-text="details && details.reference"></p>
                            </div>
                        </div>
                        <dl class="divide-y divide-line">
                            <template x-for="(field, index) in (details ? details.fields : [])" :key="index">
                                <div class="flex items-start gap-4 p-4">
                                    <div class="min-w-0 flex-1">
                                        <dt class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase" x-text="field.label"></dt>
                                        <dd class="mt-1 font-mono text-sm break-all text-ink-950" x-show="field.type !== 'link' && field.type !== 'qr'" x-text="field.value"></dd>
                                        {{-- Rendered only for their own type: a hidden <img :src> would still make the browser request the value. --}}
                                        <template x-if="field.type === 'link'">
                                            <dd class="mt-1 text-sm"><a :href="safeLink(field.value)" target="_blank" rel="noopener noreferrer nofollow" class="link break-all" x-text="field.value"></a></dd>
                                        </template>
                                        <template x-if="field.type === 'qr' && isQrImage(field.value)">
                                            <dd class="mt-2"><img :src="field.value" alt="{{ __('Payment QR code') }}" width="160" height="160" loading="lazy" class="size-40 rounded-[4px] border border-line bg-white"></dd>
                                        </template>
                                    </div>
                                    <button type="button" x-show="field.type === 'copy' || field.type === 'text'" @click="copy(field.value)" class="btn-ghost shrink-0 !px-3 !py-1.5 text-xs">
                                        <x-lucide name="copy" class="size-3.5" />
                                        <span x-show="copied !== field.value">{{ __('Copy') }}</span>
                                        <span x-show="copied === field.value" x-cloak>{{ __('Copied') }}</span>
                                    </button>
                                </div>
                            </template>
                        </dl>
                    </div>

                    <div class="space-y-4 lg:col-span-2">
                        <div class="rounded-[6px] border border-line bg-white p-5">
                            <p class="font-semibold text-ink-950">{{ __('Steps') }}</p>
                            <ol class="mt-3 space-y-3 text-sm text-slate-700">
                                <template x-for="(stepText, index) in (details ? details.steps : [])" :key="index">
                                    <li class="flex gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-[4px] bg-ink-900 text-xs font-bold text-white tabular" x-text="index + 1"></span><span class="leading-6" x-text="stepText"></span></li>
                                </template>
                            </ol>
                            <p class="mt-4 rounded-[4px] bg-surface p-3 text-sm leading-6 whitespace-pre-line text-slate-700" x-show="details && details.instructions" x-text="details && details.instructions"></p>
                            <p class="mt-3 text-xs text-slate-600" x-show="details && details.exchange_rate">{{ __('Exchange rate locked for this order') }}: 1 USD = <span x-text="details && details.exchange_rate"></span> <span x-text="details && details.method.currency"></span></p>
                            <p class="mt-3 flex items-start gap-2 text-xs leading-5 text-slate-600">
                                <x-lucide name="clock" class="mt-0.5 size-3.5 shrink-0 text-slate-500" />
                                <span>{{ __('Send the exact amount with the reference. A transfer without the reference takes longer to match, and a different amount creates a balance.') }}</span>
                            </p>
                        </div>

                        <div class="rounded-[6px] bg-surface p-4 text-xs leading-5 text-slate-700">
                            <p class="flex items-center gap-1.5 font-semibold text-ink-950"><x-lucide name="shield-check" class="size-4 text-amber-700" /> {{ __('Before you pay') }}</p>
                            <p class="mt-1.5">{{ __('Only pay for a shipment you booked yourself. If someone asked you to pay for a parcel or to buy gift cards, stop and contact us: it may be a scam.') }}</p>
                            <p class="mt-1.5">{{ __('We never ask for a payment to a personal account, and we never ask for your password or a code by phone.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 3. Proof --}}
            <section x-show="details" x-cloak class="mt-10">
                <div class="border-b border-line pb-4">
                    <p class="eyebrow">{{ __('Step 3 of 3') }}</p>
                    <h2 class="mt-1 text-lg font-bold">{{ __('Upload your proof of payment') }}</h2>
                    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('A screenshot or a photo of the receipt is enough. The verifier reads the amount, the date, the reference and the payer name — make sure all four are visible.') }}</p>
                </div>

                <div x-show="uploaded" x-cloak class="mt-5 flex items-center gap-3 rounded-[6px] border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-900">
                    <x-lucide name="circle-check" class="size-6 shrink-0 text-emerald-700" />
                    <span>{{ __('Thank you. Your proof was received and will be reviewed shortly. You will get an email when the decision is made.') }}</span>
                </div>

                <form x-show="!uploaded" @submit.prevent="submitProof()" class="mt-5 space-y-5 rounded-[6px] border border-line bg-white p-6" novalidate>
                    <div @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                         class="rounded-[6px] border border-dashed p-6 text-center transition-colors" :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300'">
                        <x-lucide name="upload" class="mx-auto size-8 text-brand-600" />
                        <p class="mt-2 text-sm font-medium text-ink-950">{{ __('Drag your screenshot or receipt here') }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ __('JPG, PNG, WebP, HEIC or PDF · up to 8 MB · up to 3 files') }}</p>
                        <label class="btn-ghost mt-4 cursor-pointer !py-2 text-sm">
                            {{ __('Choose files') }}
                            <input type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf" @change="pickFiles($event)">
                        </label>
                        <ul class="mt-4 space-y-2 text-left" x-show="files.length">
                            <template x-for="(item, index) in files" :key="index">
                                <li class="flex items-center gap-3 rounded-[4px] bg-surface px-3 py-2 text-sm">
                                    <x-lucide name="file-text" class="size-4 text-slate-500" />
                                    <span class="flex-1 truncate" x-text="item.name"></span>
                                    <span class="text-xs text-slate-600 tabular" x-text="item.size"></span>
                                    <button type="button" @click="removeFile(index)" class="text-slate-500 hover:text-red-700" aria-label="{{ __('Remove file') }}"><x-lucide name="x" class="size-4" /></button>
                                </li>
                            </template>
                        </ul>
                        <p class="field-error" x-show="fileError || uploadError('files')" x-text="fileError || uploadError('files')"></p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="field-label">{{ __('Amount paid') }} (<span x-text="details && details.amount_due.currency"></span>)</span>
                            <input x-model="form.amount_paid" type="number" min="0" step="0.01" required class="field tabular">
                            <span class="field-error block" x-text="uploadError('amount_paid')"></span>
                        </label>
                        <label class="block">
                            <span class="field-label">{{ __('Payment date') }}</span>
                            <input x-model="form.paid_on" type="date" required class="field" max="{{ now()->toDateString() }}">
                            <span class="field-error block" x-text="uploadError('paid_on')"></span>
                        </label>
                        <label class="block">
                            <span class="field-label">{{ __('Name of the payer') }}</span>
                            <input x-model="form.payer_name" maxlength="120" required class="field" autocomplete="name">
                            <span class="field-error block" x-text="uploadError('payer_name')"></span>
                        </label>
                        <label class="block">
                            <span class="field-label">{{ __('Transaction ID') }} <span class="font-normal text-slate-500" x-show="!(details && details.method.requires_transaction_id)">({{ __('optional') }})</span></span>
                            <input x-model="form.transaction_id" maxlength="120" class="field font-mono">
                            <span class="field-error block" x-text="uploadError('transaction_id')"></span>
                        </label>
                    </div>

                    <fieldset x-show="isGiftCard()" class="space-y-3 rounded-[6px] bg-red-50 p-5">
                        <legend class="px-1 text-sm font-semibold text-red-800">{{ __('Gift card details') }}</legend>
                        <p class="text-xs leading-5 text-red-800">{{ __('Gift cards are accepted as payment for shipping services only and are non-refundable once redeemed. Never buy gift cards because a stranger, a seller or a “customs officer” asked you to.') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="field-label">{{ __('Brand') }}</span>
                                <select x-model="giftCard.brand" class="field">
                                    <option value="">{{ __('Choose…') }}</option>
                                    <template x-for="brand in (details && details.gift_card ? details.gift_card.brands : [])" :key="brand"><option :value="brand" x-text="brand"></option></template>
                                </select>
                            </label>
                            <label class="block"><span class="field-label">{{ __('Card value') }}</span><input x-model="giftCard.amount" type="number" min="1" step="0.01" class="field tabular"></label>
                            <label class="block"><span class="field-label">{{ __('Card code') }}</span><input x-model="giftCard.code" maxlength="80" autocomplete="off" class="field font-mono"></label>
                            <label class="block"><span class="field-label">{{ __('PIN (if any)') }}</span><input x-model="giftCard.pin" maxlength="20" autocomplete="off" class="field font-mono"></label>
                        </div>
                    </fieldset>

                    <label class="block">
                        <span class="field-label">{{ __('Note (optional)') }}</span>
                        <textarea x-model="form.note" rows="2" maxlength="1000" class="field"></textarea>
                    </label>

                    <p x-show="error && details" x-cloak class="rounded-[4px] bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error" role="alert"></p>

                    <button type="submit" class="btn-primary" :disabled="uploading">
                        <span x-show="!uploading">{{ __('Submit proof of payment') }}</span>
                        <span x-show="uploading" x-cloak class="flex items-center gap-2"><x-lucide name="loader" class="size-4 animate-spin" /> {{ __('Uploading securely') }}</span>
                    </button>
                    <p class="text-xs text-slate-600">{{ __('Files are stored privately and are visible only to the verifier handling your order.') }}</p>
                </form>
            </section>
        @endif

        <section class="mt-12" x-show="history.length" x-cloak>
            <h2 class="text-lg font-bold">{{ __('Proof history') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('Every proof you sent, with the decision it received.') }}</p>
            <div class="mt-4 divide-y divide-line overflow-hidden rounded-[6px] border border-line bg-white">
                <template x-for="proof in history" :key="proof.id">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 p-4 text-sm">
                        <span class="font-semibold text-ink-950" x-text="proof.method"></span>
                        <span class="text-slate-600 tabular" x-text="proof.amount_paid"></span>
                        <span class="text-slate-600 tabular" x-text="when(proof.submitted_at)"></span>
                        <span class="ml-auto badge bg-surface text-slate-700" x-text="proof.status_label"></span>
                        <p class="basis-full text-slate-600" x-show="proof.reason" x-text="proof.reason"></p>
                    </div>
                </template>
            </div>
        </section>

        @if ($order->status->canTransitionTo(\App\Enums\OrderStatus::Cancelled) && in_array($order->shipment?->status?->value, ['awaiting_payment', 'payment_under_review', 'ready'], true))
            <form method="POST" action="{{ lroute('account.orders.cancel', $order) }}" class="mt-12 border-t border-line pt-6">
                @csrf
                <p class="text-sm text-slate-600">{{ __('Cancelling releases the space reserved on the route. This cannot be undone once the shipment has been picked up.') }}</p>
                <button type="submit" class="mt-3 text-sm font-medium text-slate-600 underline-offset-4 hover:text-red-700 hover:underline">{{ __('Cancel this order') }}</button>
            </form>
        @endif
    </div>
@endsection

@push('data')
    <script type="application/json" id="payment-methods">@json($methods)</script>
@endpush
