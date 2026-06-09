<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAlertNotificationChannel extends Model
{
    protected $table = 'resource_alert_notification_channels';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    public function supportsAlert(string $severity): bool
    {
        $minSeverity = $this->config['minimum_severity'] ?? 'warning';
        $severityOrder = ['info' => 0, 'warning' => 1, 'critical' => 2];
        return ($severityOrder[$severity] ?? 0) >= ($severityOrder[$minSeverity] ?? 1);
    }
}