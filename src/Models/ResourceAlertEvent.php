<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;

class ResourceAlertEvent extends Model
{
    protected $table = 'resource_alert_events';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'metric' => AlertMetric::class,
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'value' => 'decimal:4',
            'threshold' => 'decimal:4',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'notification_count' => 'integer',
            'context' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ResourceAlertRule::class, 'rule_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::OPEN);
    }
}
