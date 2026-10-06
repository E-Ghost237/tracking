<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TransportMode;
use Illuminate\Contracts\View\View;

class ServicesController extends Controller
{
    public const MODES = ['air', 'sea', 'road', 'express'];

    public function index(): View
    {
        return view('pages.services', ['modes' => TransportMode::query()->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function show(string $mode): View
    {
        $code = collect(self::MODES)->first(fn (string $m) => trans('routes.mode_'.$m) === $mode || $m === $mode);
        abort_if($code === null, 404);

        return view('pages.service', [
            'mode' => TransportMode::query()->where('code', $code)->firstOrFail(),
            'code' => $code,
        ]);
    }
}
