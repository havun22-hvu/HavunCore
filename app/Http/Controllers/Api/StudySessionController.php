<?php

namespace App\Http\Controllers\Api;

use App\Events\StudySessionUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudySessionController extends Controller
{
    /**
     * Receive session update from Studieplanner-api and broadcast to mentors.
     *
     * Expected payload:
     * {
     *   "type": "started|stopped|completed",
     *   "student_id": 123,
     *   "student_name": "Jan",
     *   "subject_name": "Wiskunde",
     *   "task_description": "Hoofdstuk 3 oefeningen",
     *   "minutes_planned": 30,
     *   "minutes_actual": 25,
     *   "started_at": "2024-12-27T14:00:00+01:00",
     *   "stopped_at": "2024-12-27T14:25:00+01:00"
     * }
     */
    public function broadcast(Request $request): JsonResponse
    {
        // Validate API key from Studieplanner
        $apiKey = $request->header('X-Api-Key');
        $expectedKey = config('services.studieplanner.api_key');

        if (!$expectedKey || $apiKey !== $expectedKey) {
            Log::warning('StudySession broadcast: invalid API key', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:started,stopped,completed',
            'student_id' => 'required|integer',
            'student_name' => 'required|string',
            'subject_name' => 'nullable|string',
            'task_description' => 'nullable|string',
            'minutes_planned' => 'nullable|integer',
            'minutes_actual' => 'nullable|integer',
            'started_at' => 'nullable|string',
            'stopped_at' => 'nullable|string',
        ]);

        // Broadcast the event
        event(new StudySessionUpdated(
            type: $validated['type'],
            studentId: $validated['student_id'],
            studentName: $validated['student_name'],
            subjectName: $validated['subject_name'] ?? null,
            taskDescription: $validated['task_description'] ?? null,
            minutesPlanned: $validated['minutes_planned'] ?? null,
            minutesActual: $validated['minutes_actual'] ?? null,
            startedAt: $validated['started_at'] ?? null,
            stoppedAt: $validated['stopped_at'] ?? null,
        ));

        Log::info('StudySession broadcast sent', [
            'type' => $validated['type'],
            'student_id' => $validated['student_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event broadcasted',
        ]);
    }

    /**
     * Get Reverb connection credentials for frontend clients.
     * Returns app key and host info (not the secret).
     */
    public function credentials(Request $request): JsonResponse
    {
        return response()->json([
            'app_key' => config('reverb.apps.apps.0.key'),
            'host' => config('reverb.apps.apps.0.options.host'),
            'port' => config('reverb.apps.apps.0.options.port'),
            'scheme' => config('reverb.apps.apps.0.options.scheme'),
        ]);
    }
}
