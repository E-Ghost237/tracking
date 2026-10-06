<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Contracts\View\View;

class LocationsController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.locations', [
            'locations' => Location::query()->where('is_active', true)->orderBy('country')->orderBy('sort_order')->orderBy('name')->get()->groupBy('country'),
        ]);
    }
}
