<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Console\Commands;

use Illuminate\Console\Command;

class CheckAlertTranslationsCommand extends Command
{
    protected $signature = 'resource-alerts:translations';

    protected $description = 'Check Resource Usage Alerts translation keys and encoding.';

    public function handle(): int
    {
        $base = plugin_path('resourceusagealerts', 'lang');
        $english = $this->flatten(require $base.'/en/strings.php');
        $german = $this->flatten(require $base.'/de/strings.php');
        $missingGerman = array_diff_key($english, $german);
        $missingEnglish = array_diff_key($german, $english);
        $mojibake = array_filter($english + $german, fn (mixed $value): bool => is_string($value) && preg_match('/Ã.|Â.|â€/', $value) === 1);
        foreach (['Missing German' => $missingGerman, 'Missing English' => $missingEnglish, 'Encoding issues' => $mojibake] as $label => $items) {
            $this->line($label.': '.($items === [] ? 'none' : implode(', ', array_keys($items))));
        }

        return $missingGerman === [] && $missingEnglish === [] && $mojibake === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];
        foreach ($values as $key => $value) {
            $path = ltrim($prefix.'.'.$key, '.');
            $flat += is_array($value) ? $this->flatten($value, $path) : [$path => $value];
        }

        return $flat;
    }
}
