<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;

class ResourceAlertRule extends Model
{
    use SoftDeletes;

    protected $table = 'resource_alert_rules';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            if ($rule->metric instanceof AlertMetric && $rule->metric->isBoolean()) {
                $rule->threshold = null;
                $rule->operator = AlertOperator::GTE;
            }

            match ($rule->scope) {
                AlertScope::GLOBAL => $rule->forceFill(['server_id' => null, 'node_id' => null, 'user_id' => null]),
                AlertScope::NODE => $rule->forceFill(['server_id' => null, 'user_id' => null]),
                AlertScope::SERVER => $rule->forceFill(['node_id' => null, 'user_id' => null]),
                AlertScope::USER => $rule->forceFill(['server_id' => null, 'node_id' => null]),
                default => null,
            };
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => AlertScope::class,
            'metric' => AlertMetric::class,
            'operator' => AlertOperator::class,
            'severity' => AlertSeverity::class,
            'threshold' => 'decimal:4',
            'duration_minutes' => 'integer',
            'cooldown_minutes' => 'integer',
            'channels' => 'array',
            'config' => 'array',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ResourceAlertEvent::class, 'rule_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /** @return float[] */
    public function recentValues(int $limit = 20): array
    {
        $query = ResourceAlertSample::query()->where('metric', $this->metric->value);
        if ($this->server_id !== null) {
            $query->where('server_id', $this->server_id);
        }
        if ($this->node_id !== null) {
            $query->where('node_id', $this->node_id);
        }

        return $query->latest('sampled_at')->limit(max(2, min(100, $limit)))
            ->pluck('value')->reverse()->map(fn (mixed $value): float => (float) $value)->values()->all();
    }
}
