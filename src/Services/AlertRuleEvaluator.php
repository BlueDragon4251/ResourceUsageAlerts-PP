<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Jobs\SendAlertNotificationJob;
use PelicanPlugins\ResourceUsageAlerts\Listeners\AutoRestartServerListener;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class AlertRuleEvaluator
{
    public function __construct(private readonly ?MaintenanceWindowService $maintenance = null) {}

    public function evaluateRule(ResourceAlertRule $rule): ?ResourceAlertEvent
    {
        return $this->evaluateRuleTargets($rule)->first();
    }

    /**
     * @return Collection<int, ResourceAlertEvent>
     */
    public function evaluateRuleTargets(ResourceAlertRule $rule): Collection
    {
        if (! $rule->enabled) {
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
        if (! is_numeric($value)) {
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
        if (! $target) {
            return false;
        }

        return $this->hasTargetConditionPersisted($rule, $target['server_id'], $target['node_id']);
    }

    public function shouldNotify(ResourceAlertEvent $event): bool
    {
        if (! $event->last_notified_at) {
            return true;
        }

        return $event->last_notified_at->lte(now()->subMinutes($event->rule->cooldown_minutes));
    }

    public function resolveIfRecovered(ResourceAlertRule $rule): void
    {
        foreach ($this->targetsFor($rule) as $target) {
            $sample = $this->latestSample($rule, $target['server_id'], $target['node_id']);
            if ($sample
                && $this->isSampleFresh($sample)
                && ! $this->conditionMetForTarget($rule, $target['server_id'], $target['node_id'])) {
                $this->resolveOpenEvent($rule, $target['server_id'], $target['node_id'], $sample);
            }
        }
    }

    private function evaluateTarget(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId, ?int $userId): ?ResourceAlertEvent
    {
        if (($this->maintenance ?? new MaintenanceWindowService)->activeFor($serverId, $nodeId, $userId)) {
            return null;
        }

        $sample = $this->latestSample($rule, $serverId, $nodeId);
        if (! $sample || ! $this->isSampleFresh($sample) || $sample->value === null) {
            return null;
        }

        if (! $this->conditionMetForTarget($rule, $serverId, $nodeId)) {
            return $this->resolveOpenEvent($rule, $serverId, $nodeId, $sample);
        }

        if (! $this->hasTargetConditionPersisted($rule, $serverId, $nodeId)) {
            return null;
        }

        $created = false;
        $event = DB::transaction(function () use ($rule, $sample, $serverId, $nodeId, $userId, &$created): ResourceAlertEvent {
            ResourceAlertRule::query()->whereKey($rule->id)->lockForUpdate()->first();

            $event = ResourceAlertEvent::query()
                ->where('rule_id', $rule->id)
                ->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])
                ->where('server_id', $serverId)
                ->where('node_id', $nodeId)
                ->lockForUpdate()
                ->first();

            if (! $event) {
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
                'context' => array_merge($event->context ?? [], $sample->context ?? [], [
                    'trigger_value' => $event->context['trigger_value'] ?? $sample->value,
                    'last_value' => $sample->value,
                ]),
            ])->save();

            return $event->fresh(['rule', 'server', 'node', 'user']);
        });

        // Only notify for newly created events or when cooldown expired.
        // Acknowledged events won't trigger re-notification via shouldNotify() check.
        if ($created || $this->shouldNotify($event)) {
            $event->forceFill(['last_notified_at' => now()])->save();
            SendAlertNotificationJob::dispatch($event->id, false);
        }
        if ($created) {
            app(AutoRestartServerListener::class)->handle($event);
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
        if ($samples->isEmpty() || $samples->contains(fn (ResourceAlertSample $sample) => ! $this->isConditionMet($rule, $sample->value))) {
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
            ->whereIn('status', [AlertStatus::OPEN, AlertStatus::ACKNOWLEDGED])
            ->where('server_id', $serverId)
            ->where('node_id', $nodeId)
            ->first();

        if (! $event) {
            return null;
        }

        $event->forceFill([
            'status' => AlertStatus::RESOLVED,
            'resolved_at' => now(),
            'value' => $sample->value,
            'context' => array_merge($event->context ?? [], $sample->context ?? [], [
                'recovery_value' => $sample->value,
                'duration_seconds' => $event->triggered_at?->diffInSeconds(now()),
            ]),
        ])->save();

        SendAlertNotificationJob::dispatch($event->id, true);

        return $event->fresh(['rule', 'server', 'node', 'user']);
    }

    private function latestSample(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): ?ResourceAlertSample
    {
        return $this->sampleQuery($rule, $serverId, $nodeId)->latest('sampled_at')->first();
    }

    /** @return array<int, array<string, mixed>> */
    public function dryRun(ResourceAlertRule $rule): array
    {
        return $this->targetsFor($rule)->map(function (array $target) use ($rule): array {
            $sample = $this->latestSample($rule, $target['server_id'], $target['node_id']);

            return $target + [
                'value' => $sample?->value,
                'sampled_at' => $sample?->sampled_at?->toIso8601String(),
                'fresh' => $sample ? $this->isSampleFresh($sample) : false,
                'would_trigger' => $sample ? $this->conditionMetForTarget($rule, $target['server_id'], $target['node_id']) : false,
            ];
        })->all();
    }

    public function isSampleFresh(ResourceAlertSample $sample): bool
    {
        if ($sample->sampled_at === null) {
            return false;
        }

        $maximumAge = max(1, (int) config('resourceusagealerts.poll_interval_minutes', 5))
            + max(0, (int) config('resourceusagealerts.stale_metric_grace_minutes', 2));

        return $sample->sampled_at->gte(now()->subMinutes($maximumAge));
    }

    public function conditionMetForTarget(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): bool
    {
        $primary = $this->latestSample($rule, $serverId, $nodeId);
        if (! $primary || ! $this->isSampleFresh($primary) || ! $this->isConditionMet($rule, $primary->value)) {
            return false;
        }

        if ($this->anomalyConfigured($rule) && ! $this->isAnomaly($rule, $serverId, $nodeId, $primary)) {
            return false;
        }

        $conditions = array_values(array_filter(
            (array) data_get($rule->config, 'conditions', []),
            fn (mixed $condition): bool => is_array($condition) && is_string($condition['metric'] ?? null)
        ));
        if ($conditions === []) {
            return true;
        }

        $results = collect($conditions)->map(function (array $condition) use ($serverId, $nodeId): bool {
            $metric = AlertMetric::tryFrom((string) $condition['metric']);
            $operator = AlertOperator::tryFrom((string) ($condition['operator'] ?? '>='));
            if (! $metric || ! $operator) {
                return false;
            }

            $sample = ResourceAlertSample::query()
                ->where('metric', $metric)
                ->when($serverId !== null, fn (Builder $query) => $query->where('server_id', $serverId))
                ->when($serverId === null, fn (Builder $query) => $query->whereNull('server_id'))
                ->when($nodeId !== null, fn (Builder $query) => $query->where('node_id', $nodeId))
                ->when($nodeId === null, fn (Builder $query) => $query->whereNull('node_id'))
                ->latest('sampled_at')
                ->first();
            if (! $sample || ! $this->isSampleFresh($sample)) {
                return false;
            }

            $conditionRule = new ResourceAlertRule;
            $conditionRule->forceFill([
                'metric' => $metric,
                'operator' => $operator,
                'threshold' => $condition['threshold'] ?? null,
            ]);

            return $this->isConditionMet($conditionRule, $sample->value);
        });

        return strtolower((string) data_get($rule->config, 'condition_logic', 'and')) === 'or'
            ? $results->contains(true)
            : ! $results->contains(false);
    }

    public function isAnomaly(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId, ResourceAlertSample $latest): bool
    {
        $window = max(15, (int) data_get($rule->config, 'anomaly.window_minutes', 60));
        $minimumSamples = max(3, (int) data_get($rule->config, 'anomaly.minimum_samples', 6));
        $multiplier = max(0.5, (float) data_get($rule->config, 'anomaly.standard_deviations', 3));
        $values = $this->sampleQuery($rule, $serverId, $nodeId)
            ->where('sampled_at', '>=', now()->subMinutes($window))
            ->whereKeyNot($latest->id)
            ->pluck('value')
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->values();
        if ($values->count() < $minimumSamples) {
            return false;
        }

        $mean = $values->avg();
        $variance = $values->map(fn (float $value): float => ($value - $mean) ** 2)->avg();
        $deviation = sqrt($variance);

        return abs((float) $latest->value - $mean) >= max(0.0001, $deviation * $multiplier);
    }

    private function anomalyConfigured(ResourceAlertRule $rule): bool
    {
        return filter_var(data_get($rule->config, 'anomaly.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function sampleQuery(ResourceAlertRule $rule, ?int $serverId, ?int $nodeId): Builder
    {
        return ResourceAlertSample::query()
            ->where('metric', $rule->metric)
            ->when(
                $rule->metric === AlertMetric::CUSTOM && filled(data_get($rule->config, 'custom_metric_name')),
                fn (Builder $query) => $query->where('context->name', (string) data_get($rule->config, 'custom_metric_name'))
            )
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
        if ($rule->scope === AlertScope::GLOBAL && in_array($rule->metric, [
            AlertMetric::QUEUE_FAILED_JOBS,
            AlertMetric::QUEUE_OLDEST_JOB_AGE,
            AlertMetric::CUSTOM,
        ], true)) {
            return collect([['server_id' => null, 'node_id' => null, 'user_id' => null]]);
        }

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
