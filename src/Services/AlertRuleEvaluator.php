<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Jobs\SendAlertNotificationJob;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class AlertRuleEvaluator
{
    public function evaluateRule(ResourceAlertRule $rule): ?ResourceAlertEvent
    {
        return $this->evaluateRuleTargets($rule)->first();
    }

    /**
     * @return Collection<int, ResourceAlertEvent>
     */
    public function evaluateRuleTargets(ResourceAlertRule $rule): Collection
    {
        if (!$rule->enabled) {
            return collect();
        }

        $events = collect();
        foreach ($this->targetsFor($rule) as $target) {
            $event = $this->evaluateTarget($rule, $target['server_id'], $target['node_id'], $target['user_id']);
            if ($event) {
                $events->push($event);
            }
        }

        $rule->forceFill(['last_checked_at' => now()])->save();

        return $events;
    }

    public function isConditionMet(ResourceAlertRule $rule, mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        if ($rule->metric->isBoolean()) {
            return (float) $value >= 1;
        }

        if ($rule->threshold === null) {
            return false;
        }

        $actual = (float) $value;
        $threshold = (float) $rule->threshold;

        return match ($rule->operator) {
            AlertOperator::GT => $actual > $threshold,
            AlertOperator::GTE => $actual >= $threshold,
            AlertOperator::LT => $actual < $threshold,
            AlertOperator::LTE => $actual <= $threshold,
            AlertOperator::EQ => abs($actual - $threshold) < 0.0001,
            AlertOperator::NEQ => abs($actual - $threshold) >= 0.0001,
        };
    }

    public function hasConditionPersisted(ResourceAlertRule $rule): bool
    {
        $target = $this->targetsFor($rule)->first();
        if (!$target) {
            return false;
        }

        return $this->hasTargetConditionPersisted($rule, $target['server_id'], $target['node_id']);
    }

    public function shouldNotify(ResourceAlertEvent $event): bool
    {
        if (!$event->last_notified_at) {
            return true;
        }

        return $event->last_notified_at->lte(now()->subMinutes($event->rule->cooldown_minutes));
    }

    public function resolveIfRecovered(ResourceAlertRule $rule): void
    {
        foreach ($this->targetsFor($rule) as $target) {
            $sample = $this->latestSample($rule, $target['server_id'], $target['node_id']);
            if ($sample && !$this->isConditionMet($rule, $sample->value)) {
                $this->resolveOpenEvent($rule, $target['server_id'], $target['node_id'], $sample);
            }
        }
    }

    private function evaluateTarget(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId, ?int $userId): ?ResourceAlertEvent
    {
        $sample = $this->latestSample($rule, $serverId, $nodeId);
        if (!$sample || $sample->value === null) {
            return null;
        }

        if (!$this->isConditionMet($rule, $sample->value)) {
            return $this->resolveOpenEvent($rule, $serverId, $nodeId, $sample);
        }

        if (!$this->hasTargetConditionPersisted($rule, $serverId, $nodeId)) {
            return null;
        }

        $created = false;
        $event = DB::transaction(function () use ($rule, $sample, $serverId, $nodeId, $userId, &$created): ResourceAlertEvent {
            ResourceAlertRule::query()->whereKey($rule->id)->lockForUpdate()->first();

            $event = ResourceAlertEvent::query()
                ->where('rule_id', $rule->id)
                ->where('status', AlertStatus::OPEN)
                ->where('server_id', $serverId)
                ->where('node_id', $nodeId)
                ->lockForUpdate()
                ->first();

            if (!$event) {
                $created = true;
                $event = new ResourceAlertEvent([
                    'rule_id' => $rule->id,
                    'server_id' => $serverId,
                    'node_id' => $nodeId,
                    'user_id' => $userId,
                    'metric' => $rule->metric,
                    'severity' => $rule->severity,
                    'status' => AlertStatus::OPEN,
                    'threshold' => $rule->metric->isBoolean() ? null : $rule->threshold,
                    'triggered_at' => now(),
                ]);
            }

            $event->fill([
                'value' => $sample->value,
                'context' => $sample->context,
            ])->save();

            return $event->fresh(['rule', 'server', 'node', 'user']);
        });

        if ($created || $this->shouldNotify($event)) {
            $event->forceFill(['last_notified_at' => now()])->save();
            SendAlertNotificationJob::dispatch($event->id, false);
        }

        return $event;
    }

    private function hasTargetConditionPersisted(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): bool
    {
        $samples = $this->sampleQuery($rule, $serverId, $nodeId)
            ->where('sampled_at', '>=', now()->subMinutes($rule->duration_minutes))
            ->oldest('sampled_at')
            ->get();

        return $this->samplesMeetDuration($rule, $samples, now());
    }

    /**
     * @param  Collection<int, ResourceAlertSample>  $samples
     */
    public function samplesMeetDuration(ResourceAlertRule $rule, Collection $samples, Carbon $now): bool
    {
        if ($samples->isEmpty() || $samples->contains(fn (ResourceAlertSample $sample) => !$this->isConditionMet($rule, $sample->value))) {
            return false;
        }

        if ($rule->duration_minutes === 0) {
            return true;
        }

        $interval = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5));
        $oldestAllowed = $now->copy()->subMinutes($rule->duration_minutes)->addMinutes($interval + 1);

        return $samples->first()->sampled_at->lte($oldestAllowed);
    }

    private function resolveOpenEvent(
        ResourceAlertRule $rule,
        ?int $serverId,
        ?int $nodeId,
        ResourceAlertSample $sample
    ): ?ResourceAlertEvent {
        $event = ResourceAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('status', AlertStatus::OPEN)
            ->where('server_id', $serverId)
            ->where('node_id', $nodeId)
            ->first();

        if (!$event) {
            return null;
        }

        $event->forceFill([
            'status' => AlertStatus::RESOLVED,
            'resolved_at' => now(),
            'value' => $sample->value,
            'context' => $sample->context,
        ])->save();

        SendAlertNotificationJob::dispatch($event->id, true);

        return $event->fresh(['rule', 'server', 'node', 'user']);
    }

    private function latestSample(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): ?ResourceAlertSample
    {
        return $this->sampleQuery($rule, $serverId, $nodeId)->latest('sampled_at')->first();
    }

    private function sampleQuery(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): Builder
    {
        return ResourceAlertSample::query()
            ->where('metric', $rule->metric)
            ->when($serverId !== null, fn (Builder $query) => $query->where('server_id', $serverId))
            ->when($serverId === null, fn (Builder $query) => $query->whereNull('server_id'))
            ->when($nodeId !== null, fn (Builder $query) => $query->where('node_id', $nodeId))
            ->when($nodeId === null, fn (Builder $query) => $query->whereNull('node_id'));
    }

    /**
     * @return Collection<int, array{server_id: ?int, node_id: ?int, user_id: ?int}>
     */
    private function targetsFor(ResourceAlertRule $rule): Collection
    {
        if ($rule->scope === AlertScope::NODE || $rule->metric === AlertMetric::NODE_OFFLINE) {
            $nodes = match ($rule->scope) {
                AlertScope::NODE => Node::query()->whereKey($rule->node_id)->get(),
                AlertScope::GLOBAL => Node::query()->get(),
                default => collect(),
            };

            return $nodes->map(fn (Node $node) => [
                'server_id' => null,
                'node_id' => $node->id,
                'user_id' => $rule->user_id,
            ]);
        }

        $servers = Server::query();
        match ($rule->scope) {
            AlertScope::SERVER => $servers->whereKey($rule->server_id),
            AlertScope::NODE => $servers->where('node_id', $rule->node_id),
            AlertScope::USER => $servers->where('owner_id', $rule->user_id),
            AlertScope::GLOBAL => null,
        };

        return $servers->get(['id', 'node_id', 'owner_id'])->map(fn (Server $server) => [
            'server_id' => $server->id,
            'node_id' => null,
            'user_id' => $rule->scope === AlertScope::USER ? $rule->user_id : $server->owner_id,
        ]);
    }
}
