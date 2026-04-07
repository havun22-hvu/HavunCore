<?php

namespace Tests\Unit;

use App\Events\StudySessionUpdated;
use Tests\TestCase;

class StudySessionUpdatedTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $event = new StudySessionUpdated(
            type: 'started',
            studentId: 42,
            studentName: 'Jan',
            subjectName: 'Wiskunde',
            taskDescription: 'Hoofdstuk 3',
            minutesPlanned: 30,
        );

        $this->assertEquals('started', $event->type);
        $this->assertEquals(42, $event->studentId);
        $this->assertEquals('Jan', $event->studentName);
        $this->assertEquals('Wiskunde', $event->subjectName);
        $this->assertEquals('Hoofdstuk 3', $event->taskDescription);
        $this->assertEquals(30, $event->minutesPlanned);
    }

    public function test_broadcast_on_returns_private_student_channel(): void
    {
        $event = new StudySessionUpdated('started', 5, 'Test');

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertEquals('private-student.5', $channels[0]->name);
    }

    public function test_broadcast_as_returns_session_updated(): void
    {
        $event = new StudySessionUpdated('stopped', 1, 'Test');

        $this->assertEquals('session.updated', $event->broadcastAs());
    }

    public function test_broadcast_with_returns_correct_data(): void
    {
        $event = new StudySessionUpdated(
            type: 'completed',
            studentId: 10,
            studentName: 'Piet',
            subjectName: 'Engels',
            taskDescription: 'Vocabulary',
            minutesPlanned: 45,
            minutesActual: 40,
            startedAt: '2026-04-08T18:00:00+00:00',
            stoppedAt: '2026-04-08T18:40:00+00:00',
        );

        $data = $event->broadcastWith();

        $this->assertEquals('completed', $data['type']);
        $this->assertEquals(10, $data['student_id']);
        $this->assertEquals('Piet', $data['student_name']);
        $this->assertEquals('Engels', $data['subject_name']);
        $this->assertEquals('Vocabulary', $data['task_description']);
        $this->assertEquals(45, $data['minutes_planned']);
        $this->assertEquals(40, $data['minutes_actual']);
        $this->assertArrayHasKey('timestamp', $data);
    }
}
