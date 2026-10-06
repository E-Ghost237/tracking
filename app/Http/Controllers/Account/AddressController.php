<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Geo;
use Illuminate\Contracts\View\View;

class AddressController extends Controller
{
    public function __invoke(): View
    {
        return view('account.addresses', ['countries' => Geo::countries()]);
    }
}
