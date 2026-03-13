<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channels for real-time sync via Laravel Reverb.
| Each user subscribes to their own channel to receive sync updates.
|
*/

// Private user sync channel — only the authenticated user can listen
Broadcast::channel('sync.{userId}', function (User $user, string $userId) {
    return (string) $user->id === $userId;
});

// Presence channel for online status (optional future use)
Broadcast::channel('presence.sync', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
