<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Tests\Feature;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertScope;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;
use Tests\TestCase;

class AlertRuleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private AlertRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = app(AlertRuleEvaluator::class);
    }

    public function test_acknowledged_status_enum_value(): void
    {
        $this->assertEquals('acknowledged', AlertStatus::ACKNOWLEDGED->value);
        $this->assertTrue(AlertStatus::ACKNOWLEDGED->isAcknowledged());
        $this->assertFalse(AlertStatus::ACKNOWLEDGED->isOpen());
        $this->assertFalse(AlertStatus::ACKNOWLEDGED->isResolved());
    }

    public function test_acknowledged_alert_is_resolved_when_condition_no_longer_met(): void
    {
        $server = Server::factory()->create();
        $rule = ResourceAlertRule::query()->create([
            'name' => 'High CPU',
            'metric' => AlertMetric::CPU_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'scope' => AlertScope::SERVER,
            'server_id' => $server->id,
            'threshold' => 80,
            'duration_minutes' => 0,
            'enabled' => true,
        ]);

        // Create an acknowledged event
        $event = ResourceAlertEvent::query()->create([
            'rule_id' => $rule->id,
            'server_id' => $server->id,
            'metric' => AlertMetric::CPU_PERCENT,
            'severity' => AlertSeverity::WARNING,
            'status' => AlertStatus::ACKNOWLEDGED,
            'value' => 90,
            'threshold' => 80,
            'triggered_at' => now(),
            'acknowledged_at' => now(),
        ]);

        // Create a sample with value below threshold (recovered)
        ResourceAlertSample::query()->create([
            'metric' => AlertMetric::CPU_PERCENT,
            'server_id' => $server->id,
            'value' => 50,
            'sampled_at' => now(),
        ]);

        $this->evaluator->resolveIfRecovered($rule);

        $event->refresh();
        $this->assertEquals(AlertStatus::RESOLVED, $event->status);
        $this->assertNotNull($event->resolved_at);
    }

    public function test_acknowledged_alert_is_not_resolved_when_condition_still_met(): void
    {
        $server = Server::factory()->create();
        $rule = ResourceAlertRule::query()->create([
            'name' => 'High CPU',
            'metric' => AlertMetric::CPU_PERCENT,
            'operator' => AlertOperator::GTE,
            'severity' => AlertSeverity::WARNING,
            'scope' => AlertScope::SERVER,
            'server_id' => $server->id,
            'threshold' => 80,
            'duration_minutes' => 0,
            'enabled' => true,
        ]);

        $event = ResourceAlertEvent::query()->create([
            'rule_id' => $rule->id,
            'server_id' => $server->id,
            'metric' => AlertMetric::CPU_PERCENT,
            'severity' => AlertSeverity::WARNING,
            'status' => AlertStatus::ACKNOWLEDGED,
            'value' => 90,
            'threshold' => 80,
            'triggered_at' => now(),
            'acknowledged_at' => now(),
        ]);

        // Create a sample with value still above threshold
        ResourceAlertSample::query()->create([
            'metric' => AlertMetric::CPU_PERCENT,
            'server_id' => $server->id,
            'value' => 95,
            'sampled_at' => now(),
        ]);

        $this->evaluator->resolveIfRecovered($rule);

        $event->refresh();
        $this->assertEquals(AlertStatus::ACKNOWLEDGED, $event->status);
    }

    public function test_scope_unresolved_returns_open_and_acknowledged(): void
    {
        $server = Server::factory()->create();

        $openEvent = ResourceAlertEvent::query()->create([
            'server_id' => $server->id,
            'metric' => AlertMetric::CPU_PERCENT,
            'severity' => AlertSeverity::WARNING,
            'status' => AlertStatus::OPEN,
            'value' => 90,
            'triggered_at' => now(),
        ]);

        $ackEvent = ResourceAlertEvent::query()->create([
            'server_id' => $server->id,
            'metric' => AlertMetric::CPU_PERCENT,
            'severity' => AlertSeverity::WARNING,
            'status' => AlertStatus::ACKNOWLEDGED,
            'value' => 85,
            'triggered_at' => now(),
            'acknowledged_at' => now(),
        ]);

        $resolvedEvent = ResourceAlertEvent::query()->create([
            'server_id' => $server->id,
            'metric' => AlertMetric::CPU_PERCENT,
            'severity' => AlertSeverity::WARNING,
            'status' => AlertStatus::RESOLVED,
            'value' => 70,
            'triggered_at' => now(),
            'resolved_at' => now(),
        ]);

        $unresolved = ResourceAlertEvent::query()->unresolved()->pluck('id');
        $this->assertTrue($unresolved->contains($openEvent->id));
        $this->assertTrue($unresolved->contains($ackEvent->id));
        $this->assertFalse($unresolved->contains($resolvedEvent->id));
    }
}