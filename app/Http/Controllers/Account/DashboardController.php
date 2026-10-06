<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * FR-101: active shipments, orders awaiting payment, proofs under review, recent activity.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $awaiting = Order::query()->whereBelongsTo($user)
            ->whereIn('status', [OrderStatus::AwaitingPayment, OrderStatus::MethodSelected, OrderStatus::ProofRejected, OrderStatus::MoreInfoRequested, OrderStatus::PartiallyPaid])
            ->with('shipment')->latest()->limit(5)->get();

        $underReview = Order::query()->whereBelongsTo($user)
            ->whereIn('status', [OrderStatus::ProofSubmitted, OrderStatus::UnderReview])
            ->with('shipment')->latest()->limit(5)->get();

        $active = Shipment::query()->whereBelongsTo($user)->whereNotNull('released_at')
            ->whereNotIn('status', ['delivered', 'returned', 'cancelled'])
            ->with('events')->latest('released_at')->limit(6)->get();

        $activity = AuditLog::query()->where('user_id', $user->id)
            ->whereIn('action', ['order.created', 'payment.method_selected', 'payment.proof_submitted', 'security.2fa_enabled', 'account.email_verified'])
            ->latest('created_at')->limit(8)->get();

        return view('account.dashboard', compact('awaiting', 'underReview', 'active', 'activity'));
    }
}
