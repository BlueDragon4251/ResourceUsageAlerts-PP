<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use Illuminate\Console\Command;
use PelicanPlugins\ResourceUsageAlerts\Services\RuleTemplateService;

class TransferAlertRulesCommand extends Command
{
    protected $signature = 'resource-alerts:rules {action : export or import} {file}';

    protected $description = 'Export or import portable alert rule templates.';

    public function handle(RuleTemplateService $templates): int
    {
        $file = (string) $this->argument('file');
        if ($this->argument('action') === 'export') {
            file_put_contents($file, $templates->exportRules());
            $this->info('Rules exported.');

            return self::SUCCESS;
        }
        if ($this->argument('action') === 'import' && is_file($file)) {
            $this->info($templates->importRules((string) file_get_contents($file)).' rule(s) imported disabled.');

            return self::SUCCESS;
        }
        $this->error('Use export or import with a valid file.');

        return self::FAILURE;
    }
}
