<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $locations = Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $locations->map(fn (Location $l) => [
            'id' => $l->public_id,
            'name' => $l->name,
            'type' => $l->type,
            'line1' => $l->line1,
            'city' => $l->city,
            'country' => $l->country,
            'country_name' => Geo::countryName($l->country),
            'lat' => $l->lat,
            'lon' => $l->lon,
            'phone' => $l->phone,
            'opening_hours' => $l->opening_hours,
            'modes' => $l->modes,
        ])])->header('Cache-Control', 'public, max-age=600');
    }
}
