<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\PushNotifier;

class NotificationSend extends Command
{
    protected $signature = 'havun:notify
                            {project : Target project name (e.g., Herdenkingsportaal, HavunAdmin)}
                            {message : Notification message (supports Markdown)}
                            {--type=info : Notification type (info, api_change, breaking_change, test_result, deployment, request)}
                            {--priority=normal : Priority level (urgent, high, normal, low)}
                            {--action : Mark as requiring action}
                            {--deadline= : Deadline for action (Y-m-d format)}';

    protected $description = 'Send real-time push notification to another project';

    public function handle(): int
    {
        $project = $this->argument('project');
        $message = $this->argument('message');

        $data = [
            'type' => $this->option('type'),
            'message' => $message,
            'priority' => $this->option('priority'),
            'action_required' => $this->option('action'),
            'deadline' => $this->option('deadline'),
        ];

        try {
            $notifier = app(PushNotifier::class);
            $success = $notifier->send($project, $data);

            if ($success) {
                $this->info("✅ Notification sent to {$project}");
                $this->line("   📧 Type: {$data['type']}");
                $this->line("   📊 Priority: {$data['priority']}");

                if ($data['action_required']) {
                    $this->line("   ⚠️  Action required!");
                }

                if ($data['deadline']) {
                    $this->line("   ⏰ Deadline: {$data['deadline']}");
                }

                $this->newLine();
                $this->line("💡 Tip: The target project will see this notification instantly");
                $this->line("   if they're running: npm run notify:{$project}");

                return self::SUCCESS;
            } else {
                $this->error("❌ Failed to send notification to {$project}");
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
