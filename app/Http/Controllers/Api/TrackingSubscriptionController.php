<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\TrackingSubscriptionService;
use App\Services\Tracking\CarrierDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingSubscriptionController extends Controller
{
    public function __invoke(Request $request, CarrierDetector $detector, TrackingSubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9\- ]+$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $subscriptions->subscribe($detector->canonical($data['number']), $data['email'], app()->getLocale());

        return response()->json(['message' => __('Check your inbox and confirm your email to receive tracking alerts.')], 202);
    }
}
