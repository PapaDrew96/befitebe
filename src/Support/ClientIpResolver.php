<?php

declare(strict_types=1);

namespace Befit\Support;

use Psr\Http\Message\ServerRequestInterface;

final class ClientIpResolver
{
    /** @param string[] $trustedProxies */
    public function __construct(private readonly array $trustedProxies = [])
    {
    }

    public function resolve(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $remote = $this->normalize((string) ($server['REMOTE_ADDR'] ?? ''));

        if ($remote === null) {
            return 'unknown';
        }

        if (!$this->isTrustedProxy($remote)) {
            return $remote;
        }

        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded === '') {
            return $remote;
        }

        $chain = [];
        foreach (explode(',', $forwarded) as $part) {
            $ip = $this->normalize(trim($part));
            if ($ip !== null) {
                $chain[] = $ip;
            }
        }

        $chain[] = $remote;

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            if (!$this->isTrustedProxy($chain[$i])) {
                return $chain[$i];
            }
        }

        return $chain[0] ?? $remote;
    }

    public function isTrustedProxy(string $ip): bool
    {
        return in_array($ip, $this->trustedProxies, true);
    }

    private function normalize(string $ip): ?string
    {
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }
}
