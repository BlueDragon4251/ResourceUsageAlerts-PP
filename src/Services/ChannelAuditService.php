<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use Throwable;

class ChannelAuditService
{
    public function record(ResourceAlertChannel $channel, string $action): void
    {
        if (! Schema::hasTable('resource_alert_channel_audits')) {
            return;
        }

        try {
            $changed = array_keys($channel->getChanges());
            $changed = array_values(array_diff($changed, ['updated_at', 'created_at', 'config']));
            if ($channel->wasChanged('config') || $action === 'created') {
                $changed[] = 'config:'.implode(',', array_keys((array) $channel->config));
            }

            DB::table('resource_alert_channel_audits')->insert([
                'channel_id' => $channel->id,
                'actor_user_id' => function_exists('user') ? user()?->id : null,
                'server_id' => $channel->server_id,
                'action' => $action,
                'channel_type' => $channel->type instanceof \BackedEnum ? $channel->type->value : (string) $channel->type,
                'changed_fields' => json_encode(array_values(array_unique($changed)), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
