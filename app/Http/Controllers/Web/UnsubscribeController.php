<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\RedirectResponse;

class UnsubscribeController extends Controller
{
    public function __invoke(string $user, string $event): RedirectResponse
    {
        $model = User::query()->where('public_id', $user)->firstOrFail();
        abort_unless(array_key_exists($event, NotificationService::optionalCustomerEvents()), 404);

        $prefs = $model->notification_prefs ?? [];
        $prefs[$event]['mail'] = false;
        $model->forceFill(['notification_prefs' => $prefs])->save();

        return redirect()->route($model->preferredLocale().'.home')->with('status', __('You have been unsubscribed from these emails.', [], $model->preferredLocale()));
    }
}
