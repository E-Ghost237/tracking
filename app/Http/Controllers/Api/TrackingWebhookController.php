<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTrackingWebhook;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound aggregator webhooks (section 9.6): signature verified, stored raw, processed by a
 * queued job, idempotent on the provider event id.
 */
class TrackingWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('platform.tracking.webhook_secret');
        if ($secret === '' || $provider !== (string) config('platform.tracking.provider')) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Not found.']], 404);
        }

        $body = $request->getContent();
        if (strlen($body) > 512 * 1024) {
            return response()->json(['error' => ['code' => 'payload_too_large', 'message' => 'Payload too large.']], 413);
        }

        $signature = (string) $request->header('aftership-hmac-sha256', $request->header('X-Signature', ''));
        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid signature.']], 401);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['error' => ['code' => 'invalid_payload', 'message' => 'Invalid payload.']], 400);
        }

        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? hash('sha256', $body));

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'event_id' => mb_substr($eventId, 0, 120),
                'signature_valid' => true,
                'payload' => $payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessTrackingWebhook::dispatch($event->id);

        return response()->json(['status' => 'accepted'], 202);
    }
}
