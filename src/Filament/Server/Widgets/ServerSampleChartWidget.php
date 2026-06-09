<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Filament\Server\Widgets;

use App\Models\Server;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;

class ServerSampleChartWidget extends Widget
{
    protected static string $view = 'resourceusagealerts::widgets.alert-sample-chart';

    public string $metric = 'cpu_percent';

    public float $threshold = 0;

    /** @var array<int, string> */
    public array $labels = [];

    /** @var array<int, float> */
    public array $values = [];

    /** @var array<int, float> */
    public array $thresholdLine = [];

    public ?string $lastValue = null;

    public function mount(?string $metric = null): void
    {
        $this->metric = $metric ?? 'cpu_percent';
        $this->loadData();
    }

    public function loadData(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $this->labels = [];
        $this->values = [];
        $this->thresholdLine = [];

        $samples = ResourceAlertSample::query()
            ->where('metric', $this->metric)
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', now()->subHours(6))
            ->orderBy('sampled_at')
            ->get(['value', 'sampled_at']);

        $this->lastValue = $samples->last()?->value ? number_format((float) $samples->last()->value, 1) : null;

        foreach ($samples as $sample) {
            $this->labels[] = $sample->sampled_at->format('H:i');
            $this->values[] = (float) $sample->value;
        }

        if ($this->threshold > 0) {
            $this->thresholdLine = array_fill(0, count($this->labels), $this->threshold);
        }
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
        $this->loadData();
    }
}