<?php

namespace PavanSaini\IdempotentWebhooks\Facades;

use Illuminate\Support\Facades\Facade;
use PavanSaini\IdempotentWebhooks\Idempotency as IdempotencyService;

/**
 * @method static mixed remember(string $key, mixed $ttl, \Closure $work)
 *
 * @see \PavanSaini\IdempotentWebhooks\Idempotency
 */
class Idempotency extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IdempotencyService::class;
    }
}
