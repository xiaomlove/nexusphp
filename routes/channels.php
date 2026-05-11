<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Per-user notification stream. Used by App\Events\NotificationReceived
 * to push new-PM badges / toasts to a single user's open tabs.
 */
Broadcast::channel('notifications.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
