<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PelicanPlugins\ResourceUsageAlerts\Enums\AlertMetric;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertMetricToken;
use PelicanPlugins\ResourceUsageAlerts\Services\ResourceSampleService;

class ExternalMetricController extends Controller
{
    public function __invoke(Request $request, ResourceSampleService $samples): JsonResponse
    {
        $plainToken = (string) $request->bearerToken();
        abort_if(strlen($plainToken) < 32, 401);
        $token = ResourceAlertMetricToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('enabled', true)
            ->first();
        abort_unless($token && (! $token->expires_at || $token->expires_at->isFuture()), 401);

        $data = $request->validate([
            'metric' => ['required', 'string', 'max:100'],
            'value' => ['required', 'numeric'],
            'context' => ['nullable', 'array', 'max:30'],
        ]);
        $allowed = (array) $token->allowed_metrics;
        abort_if($allowed !== [] && ! in_array($data['metric'], $allowed, true), 403);
        $metric = AlertMetric::tryFrom($data['metric']) ?? AlertMetric::CUSTOM;
        $context = array_merge((array) ($data['context'] ?? []), [
            'source' => 'external_metric_api',
            'external_metric' => $data['metric'],
            'token_id' => $token->id,
        ]);
        $sample = $samples->storeSample($token->server_id, $token->node_id, $metric->value, $data['value'], $context);
        $token->forceFill(['last_used_at' => now()])->save();

        return response()->json(['accepted' => true, 'sample_id' => $sample->id], 202);
    }
}
