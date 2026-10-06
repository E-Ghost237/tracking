@extends('layouts.account', ['title' => __('Support')])

@section('account')
    <h1 class="text-2xl font-extrabold">{{ __('Support tickets') }}</h1>
    <div class="mt-6 grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ lroute('account.support.store') }}" class="card space-y-4 p-6 xl:col-span-2">
            @csrf
            <h2 class="font-display text-lg font-bold">{{ __('New request') }}</h2>
            <label class="block text-sm font-medium text-ink-900">{{ __('Shipment (optional)') }}
                <select name="shipment_id" class="field mt-1.5">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($shipments as $shipment)
                        <option value="{{ $shipment->public_id }}" @selected($preselect === $shipment->public_id)>{{ $shipment->tracking_number }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Subject') }}<input name="subject" value="{{ old('subject') }}" required maxlength="160" class="field mt-1.5">@error('subject')<span class="field-error block">{{ $message }}</span>@enderror</label>
            <label class="block text-sm font-medium text-ink-900">{{ __('Message') }}<textarea name="message" rows="5" required maxlength="5000" class="field mt-1.5">{{ old('message') }}</textarea>@error('message')<span class="field-error block">{{ $message }}</span>@enderror</label>
            <button class="btn-primary" type="submit">{{ __('Send') }}</button>
        </form>
        <div class="space-y-3 xl:col-span-3">
            @forelse ($tickets as $ticket)
                <a href="{{ lroute('account.support.show', $ticket) }}" class="card card-hover flex items-center gap-4 p-5">
                    <span class="grid size-10 place-items-center rounded-xl bg-surface text-slate-600"><x-lucide name="message-circle" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink-900">{{ $ticket->subject }}</p>
                        <p class="text-xs text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</p>
                    </div>
                    <x-status-badge :status="$ticket->status" :label="__(\App\Models\Ticket::STATUSES[$ticket->status] ?? $ticket->status)" />
                </a>
            @empty
                <div class="card p-10 text-center text-slate-500">{{ __('No tickets yet.') }}</div>
            @endforelse
            {{ $tickets->links() }}
        </div>
    </div>
@endsection
