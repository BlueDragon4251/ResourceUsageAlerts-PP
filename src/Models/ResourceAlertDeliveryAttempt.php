<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceAlertDeliveryAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'resource_alert_delivery_attempts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }
}
