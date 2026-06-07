<?php
// Usage: php havun-health-alert.php "App Name" "url" "status" "down|up"
//
// SOURCE OF TRUTH: HavunCore/scripts/havun-health-alert.php (version controlled)
// DEPLOY TARGET:   /usr/local/bin/havun-health-alert.php on 188.245.159.115
// Sends Havun Health up/down alerts via HavunCore Laravel mail to havun22@gmail.com.
require '/var/www/havuncore/production/vendor/autoload.php';
$app = require '/var/www/havuncore/production/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$name = $argv[1] ?? 'Unknown';
$url = $argv[2] ?? '';
$status = $argv[3] ?? '000';
$type = $argv[4] ?? 'down';

$time = now()->format('d-m-Y H:i:s');

if ($type === 'down') {
    $subject = "[ALERT] {$name} DOWN (HTTP {$status})";
    $body = "[Havun Health] {$name} is DOWN\n\nURL: {$url}\nHTTP Status: {$status}\nTime: {$time}\n\nCheck:\n  ssh root@188.245.159.115\n  systemctl status nginx\n  tail -50 /var/log/nginx/error.log";
} else {
    $subject = "[OK] {$name} hersteld";
    $body = "[Havun Health] {$name} is HERSTELD\n\nURL: {$url}\nHTTP Status: {$status}\nTime: {$time}";
}

try {
    Illuminate\Support\Facades\Mail::raw($body, function($m) use ($subject) {
        $m->to('havun22@gmail.com')->subject($subject);
    });
    echo "Mail sent: {$subject}\n";
} catch (Throwable $e) {
    file_put_contents('/var/log/havun-health.log', date('Y-m-d H:i:s') . " [MAIL ERROR] " . $e->getMessage() . "\n", FILE_APPEND);
}
