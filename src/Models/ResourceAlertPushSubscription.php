<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAlertPushSubscription extends Model
{
    protected $table = 'resource_alert_push_subscriptions';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['subscription'];

    protected function casts(): array
    {
        return [
            'subscription' => 'encrypted:array',
            'failure_count' => 'integer',
            'last_success_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
