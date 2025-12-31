<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudySessionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $type;        // 'started', 'stopped', 'completed'
    public int $studentId;
    public string $studentName;
    public ?string $subjectName;
    public ?string $taskDescription;
    public ?int $minutesPlanned;
    public ?int $minutesActual;
    public ?string $startedAt;
    public ?string $stoppedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(
        string $type,
        int $studentId,
        string $studentName,
        ?string $subjectName = null,
        ?string $taskDescription = null,
        ?int $minutesPlanned = null,
        ?int $minutesActual = null,
        ?string $startedAt = null,
        ?string $stoppedAt = null
    ) {
        $this->type = $type;
        $this->studentId = $studentId;
        $this->studentName = $studentName;
        $this->subjectName = $subjectName;
        $this->taskDescription = $taskDescription;
        $this->minutesPlanned = $minutesPlanned;
        $this->minutesActual = $minutesActual;
        $this->startedAt = $startedAt;
        $this->stoppedAt = $stoppedAt;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to student's own channel (for multi-device sync)
        // Mentors subscribe via API endpoint that returns their student IDs
        return [
            new PrivateChannel('student.' . $this->studentId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'student_id' => $this->studentId,
            'student_name' => $this->studentName,
            'subject_name' => $this->subjectName,
            'task_description' => $this->taskDescription,
            'minutes_planned' => $this->minutesPlanned,
            'minutes_actual' => $this->minutesActual,
            'started_at' => $this->startedAt,
            'stopped_at' => $this->stoppedAt,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
