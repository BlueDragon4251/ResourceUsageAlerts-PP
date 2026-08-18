<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use InvalidArgumentException;

class OutboundEndpointGuard
{
    /** @param array<int, string> $allowedDomains */
    public function assertAllowed(string $url, array $allowedDomains = []): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if ($scheme !== 'https'
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || (int) ($parts['port'] ?? 443) !== 443) {
            throw new InvalidArgumentException('Only HTTPS webhook endpoints on port 443 are allowed.');
        }

        if ($allowedDomains !== [] && ! $this->hostMatches($host, $allowedDomains)) {
            throw new InvalidArgumentException('The webhook endpoint domain is not allowed.');
        }

        if ($this->blocksPrivateAddresses() && $this->resolvesToPrivateAddress($host)) {
            throw new InvalidArgumentException('Private, local, link-local, and reserved webhook endpoints are blocked.');
        }
    }

    /** @param array<int, string> $allowedDomains */
    private function hostMatches(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $domain) {
            $domain = strtolower(ltrim(rtrim(trim($domain), '.'), '*.'));
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function resolvesToPrivateAddress(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPrivateAddress($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (! is_array($records) || $records === []) {
            $records = array_map(
                static fn (string $ip): array => ['ip' => $ip],
                @gethostbynamel($host) ?: []
            );
        }

        if ($records === []) {
            throw new InvalidArgumentException('The webhook endpoint hostname could not be resolved.');
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && $this->isPrivateAddress($ip)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivateAddress(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function blocksPrivateAddresses(): bool
    {
        return filter_var(
            config('resourceusagealerts.block_private_webhook_ips', true),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;
    }
}
