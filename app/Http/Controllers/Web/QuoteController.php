<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Geo;
use Illuminate\Contracts\View\View;

class QuoteController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.quote', ['countries' => Geo::countries()]);
    }
}
