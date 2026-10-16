<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAcademicWebhook;
use App\Models\WebhookReceipt;
use App\Support\Academic\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima webhook Academic (`POST /webhooks/academic`). Tanda tangan HMAC diperiksa pada badan mentah; event
 * dicatat di `webhook_receipts` (event_id unik) lalu diproses di antrean. Event yang sama dibalas 200 tanpa
 * diproses ulang.
 */
class AcademicWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('academic.enabled'), 404);

        $rawBody = $request->getContent();

        if (! WebhookSignature::verify($rawBody, $request->header('X-Aksaraloka-Timestamp'), $request->header('X-Aksaraloka-Signature'))) {
            return response()->json(['message' => 'Tanda tangan tidak valid.'], 401);
        }

        $body = json_decode($rawBody, true);
        $body = is_array($body) ? $body : [];

        $eventId = (string) ($request->header('X-Aksaraloka-Event-Id') ?: ($body['id'] ?? ''));
        $type = (string) ($request->header('X-Aksaraloka-Event') ?: ($body['type'] ?? ''));

        if ($eventId === '' || strlen($eventId) > 64 || $type === '' || strlen($type) > 60) {
            return response()->json(['message' => 'Event tidak valid.'], 422);
        }

        $isNew = WebhookReceipt::query()->insertOrIgnore([
            'event_id' => $eventId,
            'type' => $type,
            'received_at' => now(),
        ]) === 1;

        if (! $isNew) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessAcademicWebhook::dispatch($eventId, $type, (array) ($body['data'] ?? []));

        return response()->json(['status' => 'accepted']);
    }
}
