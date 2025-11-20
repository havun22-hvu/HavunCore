<?php

namespace Havun\Core\Commands;

use Illuminate\Console\Command;
use Havun\Core\Services\PushNotifier;

class NotificationCheck extends Command
{
    protected $signature = 'havun:check-notifications
                            {--history : Show notification history instead of pending}
                            {--limit=10 : Number of notifications to show}';

    protected $description = 'Check for pending notifications or view history';

    public function handle(): int
    {
        $notifier = app(PushNotifier::class);

        if ($this->option('history')) {
            return $this->showHistory($notifier);
        }

        return $this->checkPending($notifier);
    }

    private function checkPending(PushNotifier $notifier): int
    {
        $count = $notifier->getPendingCount();

        if ($count === 0) {
            $this->info('📭 No pending notifications');
            $this->line('');
            $this->line('💡 Tip: Start the notification watcher for real-time alerts:');
            $this->line('   npm run notify:' . config('app.name', 'HavunCore'));
            return self::SUCCESS;
        }

        $this->warn("📨 You have {$count} pending notification(s)");
        $this->line('');
        $this->line('🔔 Start the notification watcher to see them:');
        $this->line('   cd D:\\GitHub\\havun-mcp');
        $this->line('   npm run notify:' . config('app.name', 'HavunCore'));
        $this->line('');
        $this->line('Or view history:');
        $this->line('   php artisan havun:check-notifications --history');

        return self::SUCCESS;
    }

    private function showHistory(PushNotifier $notifier): int
    {
        $limit = (int) $this->option('limit');
        $notifications = $notifier->getHistory($limit);

        if (empty($notifications)) {
            $this->info('📭 No notification history');
            return self::SUCCESS;
        }

        $this->info("📋 Last {$limit} notifications:");
        $this->newLine();

        foreach ($notifications as $index => $notification) {
            $this->displayNotification($notification, $index + 1);
        }

        return self::SUCCESS;
    }

    private function displayNotification(array $notification, int $index): void
    {
        $priorityEmoji = match($notification['priority'] ?? 'normal') {
            'urgent' => '🔴',
            'high' => '🟠',
            'normal' => '🟡',
            'low' => '🟢',
            default => '⚪',
        };

        $typeEmoji = match($notification['type'] ?? 'info') {
            'api_change' => '🔧',
            'breaking_change' => '🚨',
            'test_result' => '🧪',
            'deployment' => '🚀',
            'request' => '📬',
            'confirmation' => '✅',
            default => '📨',
        };

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("{$index}. {$typeEmoji} {$priorityEmoji} From: {$notification['from']}");
        $this->line("   Type: {$notification['type']}");
        $this->line("   Time: " . date('Y-m-d H:i:s', strtotime($notification['timestamp'])));

        if ($notification['action_required'] ?? false) {
            $this->line("   ⚠️  Action Required");
        }

        if (!empty($notification['deadline'])) {
            $this->line("   ⏰ Deadline: {$notification['deadline']}");
        }

        $this->newLine();
        $this->line($notification['message']);
        $this->newLine();
    }
}
