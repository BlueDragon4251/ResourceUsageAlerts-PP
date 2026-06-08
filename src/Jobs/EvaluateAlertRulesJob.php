<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;
use Throwable;

class EvaluateAlertRulesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(AlertRuleEvaluator $evaluator): void
    {
        ResourceAlertRule::query()
            ->enabled()
            ->orderBy('id')
            ->chunkById((int) config('resourceusagealerts.chunk_size', 100), function ($rules) use ($evaluator): void {
                foreach ($rules as $rule) {
                    try {
                        $evaluator->evaluateRuleTargets($rule);
                    } catch (Throwable $exception) {
                        Log::warning('Resource Usage Alerts rule evaluation failed.', [
                            'rule_id' => $rule->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function uniqueId(): string
    {
        return 'resource-usage-alerts:evaluate';
    }
}
