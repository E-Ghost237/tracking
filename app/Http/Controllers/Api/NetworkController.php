<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

/**
 * Hubs, sample lanes and regional last-mile networks for the 3D globe (FR-40).
 */
class NetworkController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $hubs = Location::query()->where('is_active', true)->where('type', 'hub')->orderBy('sort_order')->get();

        $byCity = $hubs->keyBy('city');
        $lanes = [];
        foreach (config('network.lanes') as [$from, $to, $mode]) {
            if (isset($byCity[$from], $byCity[$to])) {
                $lanes[] = [
                    'from' => ['city' => $from, 'lat' => $byCity[$from]->lat, 'lon' => $byCity[$from]->lon],
                    'to' => ['city' => $to, 'lat' => $byCity[$to]->lat, 'lon' => $byCity[$to]->lon],
                    'mode' => $mode,
                ];
            }
        }

        return response()->json([
            'hubs' => $hubs->map(fn (Location $hub) => [
                'name' => $hub->name, 'city' => $hub->city, 'country' => $hub->country, 'lat' => $hub->lat, 'lon' => $hub->lon, 'modes' => $hub->modes,
            ])->values(),
            'lanes' => $lanes,
            'regions' => [
                ['code' => 'us', 'label' => __('United States'), 'network' => __('USPS and UPS last-mile delivery'), 'lat' => 39.5, 'lon' => -98.35],
                ['code' => 'europe', 'label' => __('Europe'), 'network' => __('FedEx last-mile delivery'), 'lat' => 50.1, 'lon' => 9.7],
                ['code' => 'africa', 'label' => __('Africa'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 4.0, 'lon' => 15.0],
                ['code' => 'asia', 'label' => __('Asia and Middle East'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 25.0, 'lon' => 70.0],
                ['code' => 'americas', 'label' => __('Canada and Latin America'), 'network' => __(':brand network and local partners', ['brand' => config('platform.brand.name')]), 'lat' => 10.0, 'lon' => -70.0],
            ],
        ])->header('Cache-Control', 'public, max-age=600');
    }
}
