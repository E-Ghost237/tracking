@extends('layouts.account', ['title' => $ticket->subject])

@section('account')
    <a href="{{ lroute('account.support') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-ink-900"><x-lucide name="chevron-right" class="size-4 rotate-180" /> {{ __('Support tickets') }}</a>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-extrabold">{{ $ticket->subject }}</h1>
        <x-status-badge :status="$ticket->status" :label="__(\App\Models\Ticket::STATUSES[$ticket->status] ?? $ticket->status)" />
    </div>
    @if ($ticket->shipment)<p class="mt-1 font-mono text-sm text-slate-500">{{ $ticket->shipment->tracking_number }}</p>@endif

    <div class="mt-6 space-y-4">
        @foreach ($ticket->messages as $message)
            <div @class(['flex', 'justify-end' => ! $message->is_staff])>
                <div @class(['max-w-[85%] rounded-2xl px-5 py-4 text-sm leading-6 whitespace-pre-line', 'bg-ink-900 text-white' => ! $message->is_staff, 'card text-slate-700' => $message->is_staff])>
                    <p class="mb-1 text-xs font-semibold opacity-70">{{ $message->is_staff ? config('platform.brand.name').' '.__('Support') : __('You') }} · {{ $message->created_at->translatedFormat('j M, H:i') }}</p>
                    {{ $message->body }}
                </div>
            </div>
        @endforeach
    </div>

    @if ($ticket->status !== 'closed')
        <form method="POST" action="{{ lroute('account.support.reply', $ticket) }}" class="card mt-6 space-y-3 p-5">
            @csrf
            <label for="reply" class="field-label">{{ __('Your reply') }}</label>
            <textarea id="reply" name="message" rows="4" required maxlength="5000" class="field"></textarea>
            @error('message')<p class="field-error">{{ $message }}</p>@enderror
            <button class="btn-primary" type="submit">{{ __('Send reply') }}</button>
        </form>
    @endif
@endsection
