<?php

namespace PavanSaini\IdempotentWebhooks;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class Idempotency
{
    public function __construct(protected array $config = [])
    {
    }

    /**
     * Run the work at most once for this key. Subsequent calls within TTL
     * get the stored response without re-executing work().
     *
     * @param  string        $key    e.g. "razorpay:evt_abc123"
     * @param  \Carbon\Carbon|int $ttl  Carbon expiry OR seconds
     * @param  \Closure      $work   the actual processing
     * @return mixed
     */
    public function remember(string $key, $ttl, Closure $work)
    {
        [$namespace, $identifier] = $this->splitKey($key);
        $expiresAt = $this->resolveExpiry($ttl);

        // Atomic claim — rely on unique index (namespace, key)
        $claimed = $this->claim($namespace, $identifier, $expiresAt);

        if (! $claimed) {
            // Someone else processed this already — return stored response
            $stored = $this->fetch($namespace, $identifier);
            if ($stored && $stored->response_body !== null) {
                return unserialize($stored->response_body);
            }
            // Claimed but no response yet — retry lost. Returning null is acceptable
            // because the original request will eventually succeed.
            return null;
        }

        try {
            $result = $work();
            $this->storeResponse($namespace, $identifier, $result);
            return $result;
        } catch (Throwable $e) {
            // Don't poison the cache with a failure — remove the claim so a retry
            // can actually retry.
            $this->release($namespace, $identifier);
            throw $e;
        }
    }

    protected function claim(string $namespace, string $key, Carbon $expiresAt): bool
    {
        try {
            DB::table($this->table())->insert([
                'namespace'  => $namespace,
                'key'        => $key,
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]);
            return true;
        } catch (Throwable $e) {
            return false; // duplicate key, already claimed
        }
    }

    protected function fetch(string $namespace, string $key)
    {
        return DB::table($this->table())
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();
    }

    protected function storeResponse(string $namespace, string $key, $response): void
    {
        DB::table($this->table())
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->update([
                'response_body' => serialize($response),
                'response_code' => $this->extractStatusCode($response),
            ]);
    }

    protected function release(string $namespace, string $key): void
    {
        DB::table($this->table())
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->delete();
    }

    protected function splitKey(string $key): array
    {
        if (str_contains($key, ':')) {
            return explode(':', $key, 2);
        }
        return ['default', $key];
    }

    protected function resolveExpiry($ttl): Carbon
    {
        if ($ttl instanceof Carbon) {
            return $ttl;
        }
        if (is_int($ttl)) {
            return now()->addSeconds($ttl);
        }
        return now()->add($ttl);
    }

    protected function extractStatusCode($response): ?int
    {
        if (is_object($response) && method_exists($response, 'status')) {
            return $response->status();
        }
        return null;
    }

    protected function table(): string
    {
        return $this->config['table'] ?? 'idempotent_webhooks';
    }
}
