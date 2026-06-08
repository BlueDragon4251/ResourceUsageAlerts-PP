<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Console\Command;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;
use PelicanPlugins\ResourceUsageAlerts\Services\ResourceSampleService;
use Throwable;

class CheckResourceAlertsCommand extends Command
{
    protected $signature = 'resource-alerts:check';

    protected $description = 'Collect resource samples and evaluate Resource Usage Alert rules.';

    public function handle(ResourceSampleService $samples, AlertRuleEvaluator $evaluator): int
    {
        $servers = 0;
        $nodes = 0;
        $errors = 0;
        $beforeOpen = ResourceAlertEvent::query()->where('status', AlertStatus::OPEN)->count();
        $beforeResolved = ResourceAlertEvent::query()->where('status', AlertStatus::RESOLVED)->count();

        Server::query()->each(function (Server $server) use ($samples, &$servers, &$errors): void {
            try {
                $samples->collectServerSamples($server);
                $servers++;
            } catch (Throwable) {
                $errors++;
            }
        });
        Node::query()->each(function (Node $node) use ($samples, &$nodes, &$errors): void {
            try {
                $samples->collectNodeSamples($node);
                $nodes++;
            } catch (Throwable) {
                $errors++;
            }
        });
        ResourceAlertRule::query()->enabled()->each(function (ResourceAlertRule $rule) use ($evaluator, &$errors): void {
            try {
                $evaluator->evaluateRuleTargets($rule);
            } catch (Throwable) {
                $errors++;
            }
        });

        $this->table(['Checked servers', 'Checked nodes', 'Triggered alerts', 'Resolved alerts', 'Errors'], [[
            $servers,
            $nodes,
            max(0, ResourceAlertEvent::query()->where('status', AlertStatus::OPEN)->count() - $beforeOpen),
            max(0, ResourceAlertEvent::query()->where('status', AlertStatus::RESOLVED)->count() - $beforeResolved),
            $errors,
        ]]);

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
