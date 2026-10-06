<?php

namespace App\Http\Controllers\Api;

use App\Contracts\GeocodingProvider;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Geo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side geocoding proxy (FR-41 to FR-44): provider keys never reach the browser.
 */
class GeocodeController extends Controller
{
    public function search(Request $request, GeocodingProvider $geocoder): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json(['results' => $geocoder->search($data['q'], app()->getLocale(), 8)]);
    }

    public function reverse(Request $request, GeocodingProvider $geocoder): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $data['lat'];
        $lon = (float) $data['lon'];
        $place = $geocoder->reverse($lat, $lon, app()->getLocale());

        $nearest = null;
        foreach (Location::query()->where('is_active', true)->where('type', 'hub')->get() as $hub) {
            $distance = Geo::distanceKm($lat, $lon, $hub->lat, $hub->lon);
            if ($nearest === null || $distance < $nearest['distance_km']) {
                $nearest = ['name' => $hub->name, 'city' => $hub->city, 'country' => $hub->country, 'distance_km' => $distance];
            }
        }

        return response()->json([
            'point' => ['lat' => round($lat, 5), 'lon' => round($lon, 5)],
            'place' => $place,
            'network' => $place ? ['region' => Geo::networkRegion($place['country']), 'label' => Geo::networkLabel($place['country'])] : null,
            'nearest_hub' => $nearest,
        ]);
    }
}
