@props(['status', 'label', 'tone' => null])
@php
    /*
     * Status colour is semantic and limited to three families plus neutral:
     * green for completed, amber for attention, red for stopped, navy for steps
     * that are simply in progress or waiting on the customer.
     */
    $tone ??= match ($status) {
        'delivered', 'paid', 'approved', 'answered' => 'bg-emerald-50 text-emerald-800 ring-emerald-700/20',
        'delayed', 'at_customs', 'more_info_requested', 'partially_paid', 'more_info', 'first_approved' => 'bg-amber-50 text-amber-900 ring-amber-700/20',
        'returned', 'cancelled', 'expired', 'proof_rejected', 'rejected' => 'bg-red-50 text-red-800 ring-red-700/20',
        'awaiting_payment', 'method_selected', 'closed', 'draft' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        default => 'bg-ink-50 text-ink-800 ring-ink-600/20',
    };
@endphp
<span {{ $attributes->class(['badge ring-1 ring-inset', $tone]) }}>{{ $label }}</span>
