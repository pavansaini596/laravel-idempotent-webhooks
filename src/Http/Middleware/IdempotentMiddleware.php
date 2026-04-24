<?php

namespace PavanSaini\IdempotentWebhooks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PavanSaini\IdempotentWebhooks\Idempotency;

class IdempotentMiddleware
{
    public function __construct(protected Idempotency $idempotency)
    {
    }

    /**
     * Usage:
     *   ->middleware('idempotent:razorpay,header:X-Razorpay-Event-Id,24h')
     *
     * Args:
     *   0 - namespace (e.g. "razorpay")
     *   1 - source:   "header:<name>" | "body:<dot.path>"
     *   2 - ttl       e.g. "24h", "1h", "3600" (seconds), or omit for default
     */
    public function handle(Request $request, Closure $next, string $namespace, string $source, ?string $ttl = null)
    {
        $identifier = $this->extractKey($request, $source);

        if ($identifier === null || $identifier === '') {
            // No idempotency key — just pass through. Fail open, don't break prod.
            return $next($request);
        }

        $key = $namespace . ':' . $identifier;
        $expiry = $this->parseTtl($ttl ?? config('idempotent-webhooks.default_ttl', '24h'));

        return $this->idempotency->remember($key, $expiry, fn () => $next($request));
    }

    protected function extractKey(Request $request, string $source): ?string
    {
        [$type, $name] = explode(':', $source, 2);

        return match ($type) {
            'header' => $request->header($name),
            'body'   => data_get($request->all(), $name),
            default  => null,
        };
    }

    protected function parseTtl(string $ttl)
    {
        if (is_numeric($ttl)) {
            return now()->addSeconds((int) $ttl);
        }
        // "24h", "1h", "30m", "15s"
        if (preg_match('/^(\d+)([smhd])$/', $ttl, $m)) {
            $n = (int) $m[1];
            return match ($m[2]) {
                's' => now()->addSeconds($n),
                'm' => now()->addMinutes($n),
                'h' => now()->addHours($n),
                'd' => now()->addDays($n),
            };
        }
        return now()->addHours(24);
    }
}
