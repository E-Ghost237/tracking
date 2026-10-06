@props(['status', 'label', 'tone' => null])
@php
    $tone ??= match ($status) {
        'delivered', 'paid', 'approved', 'answered' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'delayed', 'at_customs', 'more_info_requested', 'partially_paid', 'more_info', 'first_approved' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        'returned', 'cancelled', 'expired', 'proof_rejected', 'rejected' => 'bg-red-50 text-red-700 ring-red-600/20',
        'awaiting_payment', 'method_selected', 'closed' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        default => 'bg-sky-50 text-sky-800 ring-sky-600/20',
    };
@endphp
<span {{ $attributes->class(['badge ring-1 ring-inset', $tone]) }}>{{ $label }}</span>
