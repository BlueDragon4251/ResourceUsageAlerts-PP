<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceAlertNotificationGroup extends Model
{
    protected $table = 'resource_alert_notification_groups';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'channel_ids' => 'array',
            'recipient_user_ids' => 'array',
            'shared' => 'boolean',
        ];
    }
}
