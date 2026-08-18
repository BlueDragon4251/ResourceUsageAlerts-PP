<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Models;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAlertMaintenanceWindow extends Model
{
    protected $table = 'resource_alert_maintenance_windows';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'recurrence' => 'array',
            'enabled' => 'boolean',
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
}
