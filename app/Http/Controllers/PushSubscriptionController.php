<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000', 'url'],
            'p256dh' => ['nullable', 'string', 'max:255'],
            'auth' => ['nullable', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:32'],
        ]);

        $userId = (int) $request->user('nexus-web')?->id;
        if ($userId <= 0) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $hash = PushSubscription::hashEndpoint($data['endpoint']);
        $sub = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'userid' => $userId,
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['p256dh'] ?? '',
                'auth' => $data['auth'] ?? '',
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'last_seen_at' => Carbon::now(),
            ],
        );

        return response()->json(['ok' => true, 'id' => $sub->id]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        $userId = (int) $request->user('nexus-web')?->id;
        if ($userId <= 0) {
            return response()->json(['ok' => false, 'error' => 'unauthenticated'], 401);
        }

        $hash = PushSubscription::hashEndpoint($data['endpoint']);
        $deleted = PushSubscription::query()
            ->where('userid', $userId)
            ->where('endpoint_hash', $hash)
            ->delete();

        return response()->json(['ok' => true, 'deleted' => (int) $deleted]);
    }
}
