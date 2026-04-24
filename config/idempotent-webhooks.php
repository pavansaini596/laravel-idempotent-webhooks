<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    | 'database' uses a MySQL/Postgres table with a unique index on (namespace, key).
    | 'redis' uses SETNX — faster for high-throughput webhooks. (Coming soon.)
    */
    'driver' => env('IDEMPOTENT_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    */
    'table' => 'idempotent_webhooks',

    /*
    |--------------------------------------------------------------------------
    | Redis connection (when driver=redis)
    |--------------------------------------------------------------------------
    */
    'redis_connection' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Default TTL if middleware doesn't specify one
    |--------------------------------------------------------------------------
    | Format: "24h", "1h", "30m", "15s", or integer seconds.
    */
    'default_ttl' => '24h',

    /*
    |--------------------------------------------------------------------------
    | Cache the response body + status code?
    |--------------------------------------------------------------------------
    | If false, we only dedupe — we don't replay responses.
    */
    'cache_response' => true,

];
