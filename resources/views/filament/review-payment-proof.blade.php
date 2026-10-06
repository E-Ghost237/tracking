<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-5">
        {{-- Proof (zoom, rotate) --}}
        <div class="space-y-4 lg:col-span-3">
            <x-filament::section>
                <x-slot name="heading">Proof files</x-slot>
                <x-slot name="description">Links expire after 5 minutes. Images were re-encoded to strip metadata.</x-slot>
                <div class="space-y-6">
                    @foreach ($fileLinks as $file)
                        <div x-data="{ zoom: 1, rotate: 0 }" class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                                <span class="font-medium">{{ $file['name'] }}</span>
                                <x-filament::badge :color="$file['scan'] === 'clean' ? 'success' : ($file['scan'] === 'infected' ? 'danger' : 'warning')">scan: {{ $file['scan'] }}</x-filament::badge>
                                <span class="font-mono text-xs text-gray-500">sha256 {{ $file['sha'] }}…</span>
                                @if (str_starts_with($file['mime'], 'image/') && $file['url'])
                                    <span class="ml-auto flex gap-1">
                                        <x-filament::icon-button icon="heroicon-o-magnifying-glass-plus" label="Zoom in" x-on:click="zoom = Math.min(zoom + 0.25, 3)" />
                                        <x-filament::icon-button icon="heroicon-o-magnifying-glass-minus" label="Zoom out" x-on:click="zoom = Math.max(zoom - 0.25, 0.5)" />
                                        <x-filament::icon-button icon="heroicon-o-arrow-path" label="Rotate" x-on:click="rotate = (rotate + 90) % 360" />
                                    </span>
                                @endif
                            </div>
                            @if (! $file['url'])
                                <p class="text-sm text-danger-600">This file failed the security scan and was removed.</p>
                            @elseif (str_starts_with($file['mime'], 'image/'))
                                <div class="max-h-[640px] overflow-auto rounded-lg bg-gray-50 dark:bg-white/5">
                                    <img src="{{ $file['url'] }}" alt="Proof of payment" class="mx-auto origin-center transition-transform duration-200"
                                         x-bind:style="{ transform: 'scale(' + zoom + ') rotate(' + rotate + 'deg)' }">
                                </div>
                            @else
                                <x-filament::button tag="a" :href="$file['url']" target="_blank" rel="noopener" icon="heroicon-o-document-text">Open PDF in a new tab</x-filament::button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Order and checks --}}
        <div class="space-y-4 lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">Payment</x-slot>
                <div class="mb-3 flex flex-wrap gap-2">
                    <x-filament::badge :color="$proof->status->color()">{{ $proof->status->label() }}</x-filament::badge>
                    @if ($overview['overdue'])<x-filament::badge color="danger">Waiting {{ $overview['age_minutes'] }} min</x-filament::badge>@endif
                    @if ($overview['two_person'])<x-filament::badge color="warning">Two approvals required</x-filament::badge>@endif
                    @if ($proof->is_duplicate)<x-filament::badge color="danger">Possible duplicate</x-filament::badge>@endif
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">Amount due</dt><dd class="text-right font-semibold">{{ $overview['due'] }}</dd>
                    <dt class="text-gray-500">Declared paid</dt><dd @class(['text-right font-semibold', 'text-success-600' => $overview['matches'], 'text-danger-600' => ! $overview['matches']])>{{ $overview['declared'] }}</dd>
                    <dt class="text-gray-500">Method</dt><dd class="text-right">{{ $overview['method'] }}</dd>
                    @if ($overview['rate'])<dt class="text-gray-500">Locked rate</dt><dd class="text-right">1 USD = {{ $overview['rate'] }}</dd>@endif
                    <dt class="text-gray-500">Reference to find</dt><dd class="text-right font-mono font-bold">{{ $overview['reference'] }}</dd>
                    <dt class="text-gray-500">Payer name</dt><dd class="text-right">{{ $proof->payer_name }}</dd>
                    <dt class="text-gray-500">Payment date</dt><dd @class(['text-right', 'text-danger-600' => ! $overview['date_ok']])>{{ $proof->paid_on->toFormattedDateString() }}</dd>
                    <dt class="text-gray-500">Order created</dt><dd class="text-right">{{ $overview['order_created']->toDayDateTimeString() }}</dd>
                    <dt class="text-gray-500">Transaction ID</dt><dd class="text-right font-mono">{{ $proof->transaction_id ?? '—' }}</dd>
                    @if ($proof->giftCard)
                        <dt class="text-gray-500">Gift card</dt><dd class="text-right">{{ $proof->giftCard->brand }} {{ $proof->giftCard->maskedCode() }}</dd>
                    @endif
                </dl>
                @if ($proof->customer_note)
                    <p class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><span class="font-medium">Customer note:</span> {{ $proof->customer_note }}</p>
                @endif
                @if ($proof->is_duplicate)
                    <p class="mt-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-500/10">Same file or transaction ID already used on: {{ implode(', ', $proof->duplicate_of ?? []) }}</p>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Order and customer</x-slot>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">Order</dt><dd class="text-right">{{ $proof->order->number }}</dd>
                    <dt class="text-gray-500">Order total</dt><dd class="text-right">{{ $overview['order_total'] }}</dd>
                    <dt class="text-gray-500">Route</dt><dd class="text-right">{{ $proof->order->shipment?->originLabel() }} → {{ $proof->order->shipment?->destinationLabel() }}</dd>
                    <dt class="text-gray-500">Customer</dt><dd class="text-right">{{ $proof->order->user->name }}<br><span class="text-xs text-gray-500">{{ $proof->order->user->email }}</span></dd>
                    <dt class="text-gray-500">Account age</dt><dd class="text-right">{{ $overview['account_age_days'] }} days {{ $overview['verified'] ? '· verified' : '· NOT verified' }}</dd>
                    <dt class="text-gray-500">Paid orders</dt><dd class="text-right">{{ $overview['paid_orders'] }}</dd>
                    <dt class="text-gray-500">Rejected attempts</dt><dd class="text-right">{{ $overview['rejected_attempts'] }}</dd>
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Decision history</x-slot>
                @forelse ($proof->reviews as $review)
                    <div class="border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5">
                        <span class="font-medium">{{ str_replace('_', ' ', $review->decision->value) }}</span>
                        by {{ $review->reviewer?->name ?? 'system' }} · {{ $review->decided_at->diffForHumans() }}
                        @if ($review->reason_code)<span class="text-gray-500">({{ $review->reason_code }})</span>@endif
                        @if ($review->note)<p class="text-gray-600 dark:text-gray-400">{{ $review->note }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No decision yet.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
