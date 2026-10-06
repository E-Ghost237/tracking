@extends('layouts.account', ['title' => __('Claims')])

@section('account')
    <h1 class="text-2xl font-extrabold">{{ __('Claims') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{!! __('Report a lost, damaged or delayed shipment. Read the :policy first.', ['policy' => '<a class="link" href="'.e(lroute('page.claims-policy')).'">'.e(__('claims procedure')).'</a>']) !!}</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ lroute('account.claims.store') }}" enctype="multipart/form-data" class="card space-y-4 p-6 xl:col-span-2">
            @csrf
            <h2 class="font-display text-lg font-bold">{{ __('Open a claim') }}</h2>
            <label class="block text-sm font-medium text-ink-900">{{ __('Shipment') }}
                <select name="shipment_id" required class="field mt-1.5">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($shipments as $shipment)<option value="{{ $shipment->public_id }}">{{ $shipment->tracking_number }}</option>@endforeach
                </select>
                @error('shipment_id')<span class="field-error block">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Type') }}
                <select name="type" required class="field mt-1.5">
                    <option value="lost">{{ __('Lost') }}</option>
                    <option value="damaged">{{ __('Damaged') }}</option>
                    <option value="delayed">{{ __('Delayed') }}</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('What happened?') }}<textarea name="description" rows="4" required minlength="10" maxlength="5000" class="field mt-1.5">{{ old('description') }}</textarea>@error('description')<span class="field-error block">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Amount claimed (USD)') }}<input name="amount_claimed" type="number" min="0" step="0.01" class="field mt-1.5" value="{{ old('amount_claimed') }}"></label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Photos or documents (up to 5)') }}<input name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1.5 block w-full text-sm">@error('photos.*')<span class="field-error block">{{ $message }}</span>@enderror</label>
            <button class="btn-primary" type="submit">{{ __('Submit claim') }}</button>
        </form>
        <div class="space-y-3 xl:col-span-3">
            @forelse ($claims as $claim)
                <article class="card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-ink-900">{{ __(\App\Models\Claim::TYPES[$claim->type] ?? $claim->type) }} · <span class="font-mono text-sm">{{ $claim->shipment?->tracking_number }}</span></p>
                        <x-status-badge :status="$claim->status" :label="__(\App\Models\Claim::STATUSES[$claim->status] ?? $claim->status)" />
                    </div>
                    @if ($claim->decision)<p class="mt-2 text-sm text-slate-600">{{ $claim->decision }}</p>@endif
                    <ol class="mt-3 space-y-1 text-xs text-slate-500">
                        @foreach ($claim->logs as $log)
                            <li>{{ $log->created_at?->translatedFormat('j M Y') }} — {{ __(\Illuminate\Support\Str::headline($log->action)) }}</li>
                        @endforeach
                    </ol>
                </article>
            @empty
                <div class="card p-10 text-center text-slate-500">{{ __('No claims.') }}</div>
            @endforelse
        </div>
    </div>
@endsection
