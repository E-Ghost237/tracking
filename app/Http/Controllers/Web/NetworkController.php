<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Contracts\View\View;

class NetworkController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.network', [
            'hubs' => Location::query()->where('is_active', true)->where('type', 'hub')->orderBy('sort_order')->get(),
        ]);
    }
}
