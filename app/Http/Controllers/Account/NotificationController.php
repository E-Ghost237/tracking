<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Notification preferences per event and channel (FR-105).
 */
class NotificationController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.notifications', [
            'events' => NotificationService::optionalCustomerEvents(),
            'prefs' => $request->user()->notification_prefs ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $events = array_keys(NotificationService::optionalCustomerEvents());
        $request->validate(['events' => ['nullable', 'array'], 'events.*' => ['string']]);

        $enabled = array_intersect($events, (array) $request->input('events', []));
        $prefs = [];
        foreach ($events as $event) {
            $prefs[$event] = ['mail' => in_array($event, $enabled, true)];
        }

        $request->user()->forceFill(['notification_prefs' => $prefs])->save();

        return back()->with('status', __('Your notification preferences were saved.'));
    }
}
