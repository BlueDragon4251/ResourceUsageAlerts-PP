<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;

class ResourceAlertChannel extends Model
{
    protected $table = 'resource_alert_channels';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'type' => AlertChannelType::class,
            'config' => 'encrypted:array',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
