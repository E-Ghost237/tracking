<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Contracts\View\View;

class StatusController extends Controller
{
    public function __invoke(MarkdownRenderer $markdown): View
    {
        $alerts = Alert::query()->active()->where('locale', app()->getLocale())->latest('starts_at')->get();

        return view('pages.status', ['alerts' => $alerts, 'markdown' => $markdown]);
    }
}
