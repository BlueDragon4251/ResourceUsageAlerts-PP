<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use App\Models\Server;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertChannelType;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;

class RuleTemplateService
{
    private const TEMPLATES = [
        'cpu_warning' => [
            'name' => 'CPU Warning',
            'metric' => AlertMetric::CPU_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'threshold' => 80,
            'duration_minutes' => 5,
        ],
        'cpu_critical' => [
            'name' => 'CPU Critical',
            'metric' => AlertMetric::CPU_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => 95,
            'duration_minutes' => 2,
        ],
        'ram_warning' => [
            'name' => 'RAM Warning',
            'metric' => AlertMetric::RAM_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'threshold' => 85,
            'duration_minutes' => 5,
        ],
        'ram_critical' => [
            'name' => 'RAM Critical',
            'metric' => AlertMetric::RAM_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => 95,
            'duration_minutes' => 2,
        ],
        'disk_warning' => [
            'name' => 'Disk Warning',
            'metric' => AlertMetric::DISK_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'threshold' => 85,
            'duration_minutes' => 10,
        ],
        'disk_critical' => [
            'name' => 'Disk Critical',
            'metric' => AlertMetric::DISK_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => 95,
            'duration_minutes' => 5,
        ],
        'swap_warning' => [
            'name' => 'Swap Usage Warning',
            'metric' => AlertMetric::SWAP_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'threshold' => 50,
            'duration_minutes' => 5,
        ],
        'network_in_warning' => [
            'name' => 'Network In Warning',
            'metric' => AlertMetric::NETWORK_IN,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'threshold' => 80,
            'duration_minutes' => 5,
        ],
        'server_offline' => [
            'name' => 'Server Offline',
            'metric' => AlertMetric::SERVER_OFFLINE,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => null,
            'duration_minutes' => 0,
        ],
        'server_crashed' => [
            'name' => 'Server Crashed',
            'metric' => AlertMetric::SERVER_CRASHED,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => null,
            'duration_minutes' => 0,
        ],
        'node_offline' => [
            'name' => 'Node Offline',
            'metric' => AlertMetric::NODE_OFFLINE,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => null,
            'duration_minutes' => 0,
        ],
        'backup_failed' => [
            'name' => 'Backup Failed',
            'metric' => AlertMetric::BACKUP_FAILED,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::CRITICAL,
            'threshold' => null,
            'duration_minutes' => 0,
        ],
    ];

    public function getTemplates(): array
    {
        return self::TEMPLATES;
    }

    public function createFromTemplate(string $templateKey, Server $server, ?int $userId = null): ?ResourceAlertRule
    {
        $template = self::TEMPLATES[$templateKey] ?? null;
        if (! $template) {
            return null;
        }

        return ResourceAlertRule::query()->create([
            'name' => $template['name'].' - '.$server->name,
            'metric' => $template['metric'],
            'operator' => $template['operator'],
            'severity' => $template['severity'],
            'scope' => AlertScope::SERVER,
            'server_id' => $server->id,
            'threshold' => $template['threshold'],
            'duration_minutes' => $template['duration_minutes'],
            'cooldown_minutes' => 15,
            'enabled' => true,
            'user_id' => $userId ?? $server->owner_id,
        ]);
    }

    public function createBulkForServers(string $templateKey, array $serverIds, ?int $userId = null): int
    {
        $count = 0;
        foreach ($serverIds as $serverId) {
            $server = Server::find($serverId);
            if (! $server) {
                continue;
            }
            if ($this->createFromTemplate($templateKey, $server, $userId)) {
                $count++;
            }
        }

        return $count;
    }

    public function duplicateRule(ResourceAlertRule $rule, Server $targetServer): ResourceAlertRule
    {
        return ResourceAlertRule::query()->create([
            'name' => $rule->name.' (Copy)',
            'metric' => $rule->metric,
            'operator' => $rule->operator,
            'severity' => $rule->severity,
            'scope' => AlertScope::SERVER,
            'server_id' => $targetServer->id,
            'node_id' => null,
            'threshold' => $rule->threshold,
            'duration_minutes' => $rule->duration_minutes,
            'cooldown_minutes' => $rule->cooldown_minutes,
            'enabled' => false,
            'user_id' => $targetServer->owner_id,
        ]);
    }

    public function exportRules(): string
    {
        $rules = ResourceAlertRule::query()->get()->map(fn (ResourceAlertRule $rule): array => [
            'name' => $rule->name,
            'scope' => $rule->scope->value,
            'metric' => $rule->metric->value,
            'operator' => $rule->operator->value,
            'threshold' => $rule->threshold,
            'duration_minutes' => $rule->duration_minutes,
            'cooldown_minutes' => $rule->cooldown_minutes,
            'severity' => $rule->severity->value,
            'channels' => $rule->channels,
            'config' => $rule->config,
            'enabled' => false,
        ])->all();

        return json_encode(['format' => 'resource-alert-rules-v1', 'rules' => $rules], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function importRules(string $json): int
    {
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (($payload['format'] ?? null) !== 'resource-alert-rules-v1' || ! is_array($payload['rules'] ?? null)) {
            throw new \InvalidArgumentException('Unsupported rule template format.');
        }

        $count = 0;
        foreach ($payload['rules'] as $data) {
            if (! is_array($data) || ! AlertMetric::tryFrom((string) ($data['metric'] ?? '')) || ! AlertOperator::tryFrom((string) ($data['operator'] ?? ''))) {
                continue;
            }
            ResourceAlertRule::query()->create([
                'name' => substr((string) ($data['name'] ?? 'Imported rule'), 0, 255),
                'scope' => AlertScope::GLOBAL,
                'metric' => $data['metric'],
                'operator' => $data['operator'],
                'threshold' => $data['threshold'] ?? null,
                'duration_minutes' => max(0, (int) ($data['duration_minutes'] ?? 5)),
                'cooldown_minutes' => max(0, (int) ($data['cooldown_minutes'] ?? 30)),
                'severity' => AlertSeverity::tryFrom((string) ($data['severity'] ?? 'warning')) ?? AlertSeverity::WARNING,
                'channels' => array_values(array_intersect((array) ($data['channels'] ?? []), array_column(AlertChannelType::cases(), 'value'))),
                'config' => is_array($data['config'] ?? null) ? $data['config'] : [],
                'enabled' => false,
            ]);
            $count++;
        }

        return $count;
    }
}
