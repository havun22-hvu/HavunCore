<?php

namespace App\Console\Commands;

use App\Models\VaultSecret;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generates a VAPID keypair for Web Push and stores it in the Vault (idempotent).
 * Run once on the server after deploy. Use --rotate to replace existing keys
 * (which invalidates every current browser subscription).
 */
class VapidSetupCommand extends Command
{
    protected $signature = 'vapid:setup {--rotate : Overwrite existing keys (invalidates all current subscriptions)}';

    protected $description = 'Generate a VAPID keypair for Web Push and store it in the Vault (idempotent).';

    public function handle(): int
    {
        $existing = VaultSecret::where('key', 'vapid_private_key')->first();

        if ($existing && ! $this->option('rotate')) {
            $this->info('VAPID keys already present in Vault — nothing to do (use --rotate to replace).');
            $pub = VaultSecret::where('key', 'vapid_public_key')->first();
            if ($pub) {
                $this->line('Public key: '.$pub->getDecryptedValue());
            }

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();

        $this->put('vapid_public_key', $keys['publicKey'], false, 'VAPID public key for Web Push (safe to expose to clients)');
        $this->put('vapid_private_key', $keys['privateKey'], true, 'VAPID private key for Web Push — SECRET');

        $this->info($existing ? '✓ VAPID keys rotated.' : '✓ VAPID keys generated and stored in Vault.');
        $this->line('Public key: '.$keys['publicKey']);
        $this->comment('Frontend fetches this via GET /api/push/vapid-public-key.');
        if ($existing) {
            $this->warn('Rotated: existing browser subscriptions are now invalid and must re-subscribe.');
        }

        return self::SUCCESS;
    }

    private function put(string $key, string $value, bool $sensitive, string $description): void
    {
        $secret = VaultSecret::where('key', $key)->first();
        if ($secret) {
            $secret->value = $value;
            $secret->save();

            return;
        }

        VaultSecret::create([
            'key' => $key,
            'value' => $value,
            'category' => 'webpush',
            'description' => $description,
            'is_sensitive' => $sensitive,
        ]);
    }
}
