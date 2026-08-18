<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Services\ChannelAuditService;

class ResourceAlertChannel extends Model
{
    protected $table = 'resource_alert_channels';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['config'];

    protected static function booted(): void
    {
        static::created(fn (self $channel) => app(ChannelAuditService::class)->record($channel, 'created'));
        static::updated(fn (self $channel) => app(ChannelAuditService::class)->record($channel, 'updated'));
        static::deleted(fn (self $channel) => app(ChannelAuditService::class)->record($channel, 'deleted'));
    }

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

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeForEvent(Builder $query, ResourceAlertEvent $event): Builder
    {
        return $query->where(function (Builder $scope) use ($event): void {
            $scope->whereNull('server_id');
            if ($event->server_id !== null) {
                $scope->orWhere('server_id', $event->server_id);
            }
        });
    }
}
