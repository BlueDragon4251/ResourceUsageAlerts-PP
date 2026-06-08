<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;

class ResourceAlertSample extends Model
{
    protected $table = 'resource_alert_samples';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'metric' => AlertMetric::class,
            'value' => 'decimal:4',
            'sampled_at' => 'datetime',
            'context' => 'array',
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
}
