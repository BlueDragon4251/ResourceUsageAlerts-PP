<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use App\Traits\EnvironmentWriterTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Minishlink\WebPush\VAPID;

class GeneratePushKeysCommand extends Command
{
    use EnvironmentWriterTrait;

    protected $signature = 'resource-alerts:push-keys {--force : Replace existing VAPID keys}';

    protected $description = 'Generate and store VAPID keys for Resource Usage Alerts browser push';

    public function handle(): int
    {
        if (!class_exists(VAPID::class)) {
            $this->error('The minishlink/web-push Composer package is not installed.');

            return self::FAILURE;
        }

        if (!$this->option('force') && filled(config('resourceusagealerts.vapid_private_key'))) {
            $this->error('VAPID keys already exist. Use --force to replace them.');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();
        $this->writeToEnvironment([
            'RESOURCE_USAGE_ALERTS_PUSH_ENABLED' => true,
            'RESOURCE_USAGE_ALERTS_VAPID_SUBJECT' => (string) config('app.url'),
            'RESOURCE_USAGE_ALERTS_VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'RESOURCE_USAGE_ALERTS_VAPID_PRIVATE_KEY' => 'encrypted:' . Crypt::encryptString($keys['privateKey']),
        ]);

        $this->info('Browser push VAPID keys were generated and stored.');

        return self::SUCCESS;
    }
}
