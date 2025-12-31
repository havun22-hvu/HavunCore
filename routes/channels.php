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

// Private channel for mentor to receive student session updates
// Format: mentor.{mentorId}
Broadcast::channel('mentor.{mentorId}', function ($user, $mentorId) {
    // User must be the mentor themselves
    return (int) $user->id === (int) $mentorId;
});

// Private channel for student session updates (student can also listen)
// Format: student.{studentId}
Broadcast::channel('student.{studentId}', function ($user, $studentId) {
    return (int) $user->id === (int) $studentId;
});
