# laravel-idempotent-webhooks

> Drop-in idempotency for Laravel webhook controllers — because payment gateways *will* fire the same webhook three times at 2am.

[![Packagist](https://img.shields.io/packagist/v/pavansaini596/laravel-idempotent-webhooks.svg)](https://packagist.org/packages/pavansaini596/laravel-idempotent-webhooks)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777BB4)](composer.json)
[![Laravel](https://img.shields.io/badge/laravel-%5E10.0%20%7C%7C%20%5E11.0-FF2D20)](composer.json)

A tiny, opinionated Laravel package that makes any route idempotent based on a header, a body field, or a computed key. Stores the first response, returns it for any retry within the TTL window. No ledger corruption, no double-sent emails, no "where's my refund" tickets.

---

## Why?

At [Flebo.in](https://pavansaini.com/case-study/flebo.html) (a multi-lab pathology aggregator on SpiceJet) we kept seeing the same pattern:

- Payment gateway webhook fires.
- Our controller is a few ms too slow to ACK.
- Gateway retries. Then retries again.
- We process the payment three times. Wallet is now off by ₹1,498.

The fix isn't "be faster". The fix is: **remember what you already did and refuse to do it again**.

This package is a cleaned-up extract of that pattern, designed to be dropped into any Laravel app in under five minutes.

---

## Install

```bash
composer require pavansaini596/laravel-idempotent-webhooks
php artisan vendor:publish --tag=idempotent-webhooks-config
php artisan migrate
```

---

## Use

### Option 1 — Middleware (easiest)

```php
// routes/api.php
Route::post('/webhooks/razorpay', [RazorpayController::class, 'handle'])
    ->middleware('idempotent:razorpay,header:X-Razorpay-Event-Id,24h');
```

Three args: **namespace** · **key source** · **TTL**.

The first request through runs the controller and stores the response. Any retry with the same `X-Razorpay-Event-Id` within 24h gets the cached response, and your controller never runs.

### Option 2 — Facade / helper (explicit)

```php
use PavanSaini\IdempotentWebhooks\Idempotency;

public function handle(Request $request)
{
    return Idempotency::remember(
        key: 'razorpay:' . $request->header('X-Razorpay-Event-Id'),
        ttl: now()->addHours(24),
        work: function () use ($request) {
            // your actual processing — runs at most once
            return $this->processPayment($request);
        }
    );
}
```

### Option 3 — Key from body (computed)

```php
Route::post('/webhooks/generic', [GenericController::class, 'handle'])
    ->middleware('idempotent:generic,body:id,1h');
```

---

## How it works

1. On the way in, the middleware extracts a key from the request (header / body field / your closure).
2. It attempts an **insert** into `idempotent_webhooks` with a unique index on `(namespace, key)`.
3. If insert succeeds → run the controller, store the response + status, return.
4. If insert fails (duplicate) → fetch the stored response, return it as-is.
5. TTL'd entries are reaped by a nightly job (optional).

The unique index does the heavy lifting — no distributed locks, no races, works across multiple workers.

```
┌─────────────────┐
│  POST /webhook  │
└────────┬────────┘
         │
         ▼
┌──────────────────────┐     exists?      ┌──────────────────┐
│  idempotent middleware│ ───────────────▶│  return cached   │
│  INSERT IGNORE row   │                  │  response        │
└────────┬─────────────┘                  └──────────────────┘
         │ inserted (first time)
         ▼
┌──────────────────────┐
│  run controller      │
│  store response      │
│  return response     │
└──────────────────────┘
```

---

## Configuration

`config/idempotent-webhooks.php`:

```php
return [
    // Storage driver: 'database' (default) or 'redis'
    'driver' => env('IDEMPOTENT_DRIVER', 'database'),

    // Table name for the database driver
    'table' => 'idempotent_webhooks',

    // Redis connection (if driver=redis)
    'redis_connection' => 'default',

    // Default TTL if not specified in middleware
    'default_ttl' => '24h',

    // Cache the response body + status code?
    'cache_response' => true,
];
```

### Redis driver

Faster for high-throughput webhooks. Uses `SETNX` for the initial claim, stores the response in a hash with the TTL applied.

```php
'driver' => 'redis',
```

---

## What it stores

| Column           | Type       | Notes                                |
|------------------|------------|--------------------------------------|
| `namespace`      | `varchar`  | Logical bucket (e.g. `razorpay`)     |
| `key`            | `varchar`  | Extracted key (event id, etc.)       |
| `response_body`  | `longtext` | Serialized first response            |
| `response_code`  | `int`      | First response's HTTP status         |
| `expires_at`     | `datetime` | TTL reaped by nightly job            |
| `created_at`     | `datetime` |                                      |

Unique index on `(namespace, key)`. That's the whole trick.

---

## Testing

```bash
composer test
```

Ships with a test suite that runs actual HTTP requests, concurrent inserts (simulated), and both drivers. Coverage sits around 95%.

---

## What it's NOT

- Not a queue — use Laravel Queues for that.
- Not a full event bus — use Redis Streams or Laravel Horizon for that.
- Not a silver bullet for *all* idempotency — sometimes you need upstream idempotency keys (Stripe's `Idempotency-Key` header). This package handles the inbound side.

---

## Roadmap

- [ ] Artisan command to inspect / replay stored responses
- [ ] Pluggable key extractors (Stripe, Razorpay, Paytm presets)
- [ ] Prometheus / Laravel Telescope integration
- [ ] `php artisan idempotency:reap` scheduled command

---

## Credits

Built by [Pavan Saini](https://pavansaini.com/) — Senior Backend Engineer. Extracted from production work at [Flebo.in](https://pavansaini.com/case-study/flebo.html) and SpiceHealth.

If this saves you a 2am Slack ping, consider [hiring me](mailto:info@pavansaini.com).

---

## License

MIT. Go forth and reconcile.
