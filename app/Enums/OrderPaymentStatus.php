<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Selected = 'selected';
    case Superseded = 'superseded';
    case ProofSubmitted = 'proof_submitted';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
