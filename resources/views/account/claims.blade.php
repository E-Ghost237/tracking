@extends('layouts.account', ['title' => __('Claims')])

@section('account')
    <h1 class="text-2xl font-bold">{{ __('Claims') }}</h1>
    <p class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-600">{!! __('Report a lost, damaged or delayed shipment. Read the :policy first — it lists what we need from you and how the decision is taken.', ['policy' => '<a class="link" href="'.e(lroute('page.claims-policy')).'">'.e(__('claims procedure')).'</a>']) !!}</p>

    <ol class="mt-6 grid gap-px overflow-hidden rounded-[6px] border border-line bg-line sm:grid-cols-3">
        <li class="bg-white p-5">
            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Step 1') }}</p>
            <p class="mt-1.5 text-sm font-bold text-ink-950">{{ __('Open the claim here') }}</p>
            <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('Pick the shipment, describe what happened and attach photos of the parcel, the label and the contents.') }}</p>
        </li>
        <li class="bg-white p-5">
            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Step 2') }}</p>
            <p class="mt-1.5 text-sm font-bold text-ink-950">{{ __('We investigate with the carrier') }}</p>
            <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('Every scan, handoff and customs entry on the shipment is reviewed. We come back to you on the ticket if we need anything else.') }}</p>
        </li>
        <li class="bg-white p-5">
            <p class="text-xs font-semibold tracking-[0.08em] text-slate-600 uppercase">{{ __('Step 3') }}</p>
            <p class="mt-1.5 text-sm font-bold text-ink-950">{{ __('Decision and settlement') }}</p>
            <p class="mt-1 text-sm leading-6 text-slate-600">{{ __('The outcome and the reason are recorded on the claim below. Approved amounts are settled against the invoice of the shipment.') }}</p>
        </li>
    </ol>

    <div class="mt-6 grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ lroute('account.claims.store') }}" enctype="multipart/form-data" class="card space-y-4 p-6 xl:col-span-2">
            @csrf
            <h2 class="text-lg font-bold">{{ __('Open a claim') }}</h2>
            <p class="text-sm leading-6 text-slate-600">{{ __('Only shipments that have already left our warehouse can be claimed. Give the facts as they are: the description and the photos are what the carrier reads.') }}</p>
            <label class="block text-sm font-medium text-ink-900">{{ __('Shipment') }}
                <select name="shipment_id" required class="field mt-1.5" @error('shipment_id') aria-invalid="true" aria-describedby="shipment_id-error" @enderror>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($shipments as $shipment)<option value="{{ $shipment->public_id }}">{{ $shipment->tracking_number }}</option>@endforeach
                </select>
                @error('shipment_id')<span class="field-error block" id="shipment_id-error">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Type') }}
                <select name="type" required class="field mt-1.5">
                    <option value="lost">{{ __('Lost') }}</option>
                    <option value="damaged">{{ __('Damaged') }}</option>
                    <option value="delayed">{{ __('Delayed') }}</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('What happened?') }}<textarea name="description" rows="4" required minlength="10" maxlength="5000" class="field mt-1.5" @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description') }}</textarea>@error('description')<span class="field-error block" id="description-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Amount claimed (USD)') }}<input name="amount_claimed" type="number" min="0" step="0.01" class="field mt-1.5" value="{{ old('amount_claimed') }}"></label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Photos or documents (up to 5)') }}<input name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1.5 block w-full text-sm" @error('photos.*') aria-invalid="true" aria-describedby="photos-error" @enderror>@error('photos.*')<span class="field-error block" id="photos-error">{{ $message }}</span>@enderror</label>
            <button class="btn-primary" type="submit">{{ __('Submit claim') }}</button>
        </form>
        <div class="space-y-3 xl:col-span-3">
            @forelse ($claims as $claim)
                <article class="card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-ink-950">{{ __(\App\Models\Claim::TYPES[$claim->type] ?? $claim->type) }} · <span class="font-mono text-sm">{{ $claim->shipment?->tracking_number }}</span></p>
                        <x-status-badge :status="$claim->status" :label="__(\App\Models\Claim::STATUSES[$claim->status] ?? $claim->status)" />
                    </div>
                    @if ($claim->decision)<p class="mt-2 text-sm text-slate-600">{{ $claim->decision }}</p>@endif
                    <ol class="mt-3 space-y-1 text-xs text-slate-600">
                        @foreach ($claim->logs as $log)
                            <li>{{ $log->created_at?->translatedFormat('j M Y') }} · {{ __(\Illuminate\Support\Str::headline($log->action)) }}</li>
                        @endforeach
                    </ol>
                </article>
            @empty
                <div class="card p-8 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="file-warning" class="size-6" /></span>
                    <p class="mt-4 font-semibold text-ink-950">{{ __('No claims') }}</p>
                    <p class="mx-auto mt-1.5 max-w-md text-sm leading-6 text-slate-600">{{ __('Nothing has been reported on this account. If a parcel arrives damaged or stops moving, open a claim and keep the packaging until we ask you to release it.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
