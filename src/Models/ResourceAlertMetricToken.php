<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAlertMetricToken extends Model
{
    protected $table = 'resource_alert_metric_tokens';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'allowed_metrics' => 'array', 'last_used_at' => 'datetime',
            'expires_at' => 'datetime', 'enabled' => 'boolean',
        ];
    }

    public function setPlainTokenAttribute(string $token): void
    {
        $this->attributes['token_hash'] = hash('sha256', $token);
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
