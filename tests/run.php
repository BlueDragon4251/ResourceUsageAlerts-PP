<?php

declare(strict_types=1);

use App\Enums\ContainerStatus;
use App\Models\Server;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertOperator;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertSeverity;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertStatus;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertChannel;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertPushSubscription;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertRule;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertSample;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertMessageFormatter;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertRuleEvaluator;
use PelicanPlugins\ResourceUsageAlerts\Services\OutboundEndpointGuard;
use PelicanPlugins\ResourceUsageAlerts\Services\ResourceSampleService;
use PelicanPlugins\ResourceUsageAlerts\Services\WebPushNotificationService;

if (! function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split('/'.str_replace('/', '\/', $pattern).'/u', $string, $limit);
    }
}

$root = dirname(__DIR__, 2);
$loader = require $root.'/pelican/vendor/autoload.php';
$loader->addPsr4('PelicanPlugins\\ResourceUsageAlerts\\', dirname(__DIR__).'/src/');

$container = new Container;
Container::setInstance($container);
$container->instance('config', new ConfigRepository([
    'resourceusagealerts' => ['poll_interval_minutes' => 5],
]));
$translationLoader = new ArrayLoader;
$translationLoader->addMessages('en', 'strings', require dirname(__DIR__).'/lang/en/strings.php', 'resourceusagealerts');
$translationLoader->addMessages('de', 'strings', require dirname(__DIR__).'/lang/de/strings.php', 'resourceusagealerts');
$container->instance('translator', new Translator($translationLoader, 'en'));

$tests = [];

$tests['numeric operators'] = function (): void {
    $evaluator = new AlertRuleEvaluator;
    $cases = [
        [AlertOperator::GT, 91, 90, true],
        [AlertOperator::GTE, 90, 90, true],
        [AlertOperator::LT, 89, 90, true],
        [AlertOperator::LTE, 90, 90, true],
        [AlertOperator::EQ, 90, 90, true],
        [AlertOperator::NEQ, 91, 90, true],
    ];

    foreach ($cases as [$operator, $value, $threshold, $expected]) {
        $rule = new ResourceAlertRule;
        $rule->forceFill([
            'metric' => AlertMetric::RAM_PERCENT,
            'operator' => $operator,
            'threshold' => $threshold,
        ]);
        assertSame($expected, $evaluator->isConditionMet($rule, $value), "Operator {$operator->value}");
    }
};

$tests['duration persistence'] = function (): void {
    $evaluator = new AlertRuleEvaluator;
    $now = Carbon::parse('2026-06-08 12:00:00');
    $rule = new ResourceAlertRule;
    $rule->forceFill([
        'metric' => AlertMetric::CPU_PERCENT,
        'operator' => AlertOperator::GTE,
        'threshold' => 90,
        'duration_minutes' => 10,
    ]);
    $samples = collect([
        sample(95, $now->copy()->subMinutes(10)),
        sample(96, $now->copy()->subMinutes(5)),
        sample(97, $now),
    ]);

    assertSame(true, $evaluator->samplesMeetDuration($rule, $samples, $now));
    $samples[1]->value = 50;
    assertSame(false, $evaluator->samplesMeetDuration($rule, $samples, $now));
};

$tests['stale samples are ignored'] = function (): void {
    $evaluator = new AlertRuleEvaluator;
    $fresh = sample(90, now()->subMinutes(6));
    $stale = sample(90, now()->subMinutes(8));
    assertSame(true, $evaluator->isSampleFresh($fresh));
    assertSame(false, $evaluator->isSampleFresh($stale));
};

$tests['cooldown'] = function (): void {
    $evaluator = new AlertRuleEvaluator;
    $rule = new ResourceAlertRule;
    $rule->forceFill(['cooldown_minutes' => 30]);
    $event = new ResourceAlertEvent;
    $event->setDateFormat('Y-m-d H:i:s');
    $event->setRelation('rule', $rule);
    $event->forceFill(['last_notified_at' => now()->subMinutes(31)]);
    assertSame(true, $evaluator->shouldNotify($event));
    $event->forceFill(['last_notified_at' => now()->subMinutes(10)]);
    assertSame(false, $evaluator->shouldNotify($event));
};

$tests['crash transitions'] = function (): void {
    $service = new ResourceSampleService;
    assertSame(true, $service->isCrashTransition('running', ContainerStatus::Exited));
    assertSame(true, $service->isCrashTransition('running', ContainerStatus::Dead));
    assertSame(false, $service->isCrashTransition('stopping', ContainerStatus::Offline));
    assertSame(false, $service->isCrashTransition('running', ContainerStatus::Missing));
};

$tests['message formatter'] = function (): void {
    $rule = new ResourceAlertRule;
    $rule->forceFill([
        'metric' => AlertMetric::RAM_PERCENT,
        'operator' => AlertOperator::GTE,
        'threshold' => 90,
        'duration_minutes' => 10,
    ]);
    $server = new Server;
    $server->forceFill(['name' => 'Survival-01']);
    $event = new ResourceAlertEvent;
    $event->forceFill([
        'metric' => AlertMetric::RAM_PERCENT,
        'severity' => AlertSeverity::CRITICAL,
        'status' => AlertStatus::OPEN,
        'value' => 93.2,
        'threshold' => 90,
    ]);
    $event->setRelation('rule', $rule);
    $event->setRelation('server', $server);

    $formatter = new AlertMessageFormatter;
    assertContains('Critical', $formatter->triggeredTitle($event));
    assertContains('Survival-01', $formatter->triggeredTitle($event));
    assertContains('93.2%', $formatter->triggeredBody($event));
    assertContains('Threshold: >= 90.0%', $formatter->triggeredBody($event));
};

$tests['notification payload redaction'] = function (): void {
    $rule = new ResourceAlertRule;
    $rule->forceFill([
        'metric' => AlertMetric::CPU_PERCENT,
        'operator' => AlertOperator::GTE,
        'threshold' => 80,
        'duration_minutes' => 5,
        'config' => ['secret' => 'super-secret-token', 'webhook_url' => 'https://secret.invalid/hook'],
    ]);
    $event = new ResourceAlertEvent;
    $event->setDateFormat('Y-m-d H:i:s');
    $event->forceFill(['id' => 7, 'metric' => AlertMetric::CPU_PERCENT, 'severity' => AlertSeverity::WARNING, 'status' => AlertStatus::OPEN, 'value' => 91, 'threshold' => 80, 'triggered_at' => now()]);
    $event->setRelation('rule', $rule);
    $event->setRelation('server', null);
    $event->setRelation('node', null);
    $event->setRelation('user', null);
    $payloads = [
        json_encode((new AlertMessageFormatter)->discordPayload($event), JSON_THROW_ON_ERROR),
        json_encode((new AlertMessageFormatter)->slackPayload($event), JSON_THROW_ON_ERROR),
        json_encode((new AlertMessageFormatter)->pushPayload($event), JSON_THROW_ON_ERROR),
    ];
    foreach ($payloads as $payload) {
        assertNotContains('super-secret-token', $payload);
        assertNotContains('secret.invalid', $payload);
    }
};

$tests['encrypted channel cast'] = function (): void {
    $method = new ReflectionMethod(ResourceAlertChannel::class, 'casts');
    $casts = $method->invoke(new ResourceAlertChannel);
    assertSame('encrypted:array', $casts['config']);
};

$tests['encrypted push subscription cast'] = function (): void {
    $method = new ReflectionMethod(ResourceAlertPushSubscription::class, 'casts');
    $casts = $method->invoke(new ResourceAlertPushSubscription);
    assertSame('encrypted:array', $casts['subscription']);
};

$tests['browser push subscription normalization'] = function (): void {
    $service = new WebPushNotificationService;
    $method = new ReflectionMethod($service, 'normalizeSubscription');
    $normalized = $method->invoke($service, [
        'endpoint' => 'https://push.example.test/subscription',
        'keys' => [
            'p256dh' => 'browser-public-key',
            'auth' => 'browser-auth-token',
        ],
        'contentEncoding' => 'aes128gcm',
    ]);

    assertSame('browser-public-key', $normalized['publicKey']);
    assertSame('browser-auth-token', $normalized['authToken']);
    assertSame('aes128gcm', $normalized['contentEncoding']);
};

$tests['outbound endpoint security'] = function (): void {
    $guard = new OutboundEndpointGuard;
    assertThrows(fn () => $guard->assertAllowed('http://example.com/hook'));
    assertThrows(fn () => $guard->assertAllowed('https://127.0.0.1/hook'));

    config()->set('resourceusagealerts.block_private_webhook_ips', false);
    assertThrows(fn () => $guard->assertAllowed('https://example.com/hook', ['hooks.slack.com']));
    $guard->assertAllowed('https://hooks.slack.com/services/test', ['hooks.slack.com']);
    config()->set('resourceusagealerts.block_private_webhook_ips', true);
};

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[PASS] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] {$name}: {$exception->getMessage()}\n";
    }
}

echo sprintf("\n%d passed, %d failed.\n", count($tests) - $failed, $failed);
exit($failed === 0 ? 0 : 1);

function sample(float $value, Carbon $sampledAt): ResourceAlertSample
{
    $sample = new ResourceAlertSample;
    $sample->setDateFormat('Y-m-d H:i:s');
    $sample->forceFill(['value' => $value, 'sampled_at' => $sampledAt]);

    return $sample;
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : 'Values are not identical.');
    }
}

function assertContains(string $needle, string $haystack): void
{
    if (! str_contains($haystack, $needle)) {
        throw new RuntimeException("Expected string to contain: {$needle}");
    }
}

function assertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException("Expected string not to contain: {$needle}");
    }
}

function assertThrows(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException('Expected callback to throw.');
}
