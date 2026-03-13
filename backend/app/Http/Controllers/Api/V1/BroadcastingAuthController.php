<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * Broadcasting auth endpoint for Sanctum Bearer token clients (mobile apps).
 *
 * Standard Laravel broadcasting auth (/broadcasting/auth) relies on session/cookie.
 * This controller provides the same functionality but works with Bearer tokens.
 */
class BroadcastingAuthController extends BaseApiController
{
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'channel_name' => ['required', 'string'],
            'socket_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $channelName = $request->input('channel_name');

        // Strip private- or presence- prefix for channel auth check
        $normalizedChannel = preg_replace('/^(private|presence)-/', '', $channelName);

        // Check if user is authorized for this channel
        $authorized = $this->authorizeChannel($user, $normalizedChannel);

        if (!$authorized) {
            return $this->error('Forbidden', 403);
        }

        // Generate the auth response for the broadcasting driver
        $auth = Broadcast::driver()->validAuthenticationResponse($request, $authorized);

        return response()->json($auth);
    }

    /**
     * Check channel authorization against defined broadcast channels.
     */
    private function authorizeChannel($user, string $channel): mixed
    {
        // sync.{userId} — user can only access their own channel
        if (preg_match('/^sync\.(.+)$/', $channel, $matches)) {
            return $user->id === $matches[1] ? ['id' => $user->id, 'name' => $user->name] : false;
        }

        // presence.sync — any authenticated user
        if ($channel === 'presence.sync') {
            return ['id' => $user->id, 'name' => $user->name];
        }

        return false;
    }
}
