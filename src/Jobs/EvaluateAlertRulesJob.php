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
use PelicanPlugins\ResourceUsageAlerts\Services\RuntimeHealthService;
use Throwable;

class EvaluateAlertRulesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(AlertRuleEvaluator $evaluator, RuntimeHealthService $health): void
    {
        $startedAt = microtime(true);
        $processed = 0;
        $errors = 0;
        ResourceAlertRule::query()
            ->enabled()
            ->orderBy('id')
            ->chunkById((int) config('resourceusagealerts.chunk_size', 100), function ($rules) use ($evaluator, &$processed, &$errors): void {
                foreach ($rules as $rule) {
                    try {
                        $evaluator->evaluateRuleTargets($rule);
                        $processed++;
                    } catch (Throwable $exception) {
                        $errors++;
                        Log::warning('Resource Usage Alerts rule evaluation failed.', [
                            'rule_id' => $rule->id,
                            'exception' => $exception::class,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });
        $health->mark('evaluation', $processed, $errors, (int) ((microtime(true) - $startedAt) * 1000));
    }

    public function uniqueId(): string
    {
        return 'resource-usage-alerts:evaluate';
    }
}
