@extends('layouts.account', ['title' => __('Support')])

@section('account')
    <h1 class="text-2xl font-bold">{{ __('Support tickets') }}</h1>
    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-slate-600">{{ __('One thread per question, kept with the shipment it belongs to. Write in English or French — the same team reads both.') }}</p>
    <div class="mt-6 grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ lroute('account.support.store') }}" class="card space-y-4 p-6 xl:col-span-2">
            @csrf
            <h2 class="text-lg font-bold">{{ __('New request') }}</h2>
            <p class="text-sm leading-6 text-slate-600">{{ __('Link the shipment when your question is about a booking, a payment or a delay. It saves a round trip: the ticket opens with the shipment already attached.') }}</p>
            <label class="block text-sm font-medium text-ink-900">{{ __('Shipment (optional)') }}
                <select name="shipment_id" class="field mt-1.5">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($shipments as $shipment)
                        <option value="{{ $shipment->public_id }}" @selected($preselect === $shipment->public_id)>{{ $shipment->tracking_number }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Subject') }}<input name="subject" value="{{ old('subject') }}" required maxlength="160" class="field mt-1.5" @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>@error('subject')<span class="field-error block" id="subject-error">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Message') }}<textarea name="message" rows="5" required maxlength="5000" class="field mt-1.5" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>@error('message')<span class="field-error block" id="message-error">{{ $message }}</span>@enderror</label>
            <button class="btn-primary" type="submit">{{ __('Send') }}</button>
        </form>
        <div class="space-y-3 xl:col-span-3">
            @forelse ($tickets as $ticket)
                <a href="{{ lroute('account.support.show', $ticket) }}" class="card card-hover flex items-center gap-4 p-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-[4px] bg-surface text-ink-700"><x-lucide name="message-circle" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink-950">{{ $ticket->subject }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ __('Last message :time', ['time' => $ticket->updated_at->diffForHumans()]) }}</p>
                    </div>
                    <x-status-badge :status="$ticket->status" :label="__(\App\Models\Ticket::STATUSES[$ticket->status] ?? $ticket->status)" />
                </a>
            @empty
                <div class="card p-8 text-center">
                    <span class="mx-auto grid size-12 place-items-center rounded-[4px] bg-ink-50 text-ink-700"><x-lucide name="headset" class="size-6" /></span>
                    <p class="mt-4 font-semibold text-ink-950">{{ __('No tickets yet') }}</p>
                    <p class="mx-auto mt-1.5 max-w-md text-sm leading-6 text-slate-600">{{ __('Use the form for anything about a booking, a payment, a document or a shipment. For urgent pickup questions, the contact number in the sidebar is answered during staffed hours.') }}</p>
                </div>
            @endforelse
            {{ $tickets->links() }}
        </div>
    </div>
@endsection
