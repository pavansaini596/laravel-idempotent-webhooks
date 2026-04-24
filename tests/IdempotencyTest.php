<?php

namespace PavanSaini\IdempotentWebhooks\Tests;

use Orchestra\Testbench\TestCase;
use PavanSaini\IdempotentWebhooks\Facades\Idempotency;
use PavanSaini\IdempotentWebhooks\IdempotentWebhooksServiceProvider;

class IdempotencyTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [IdempotentWebhooksServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Idempotency' => Idempotency::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_runs_work_once_and_caches_result(): void
    {
        $counter = 0;
        $work = function () use (&$counter) {
            $counter++;
            return ['ok' => true, 'run' => $counter];
        };

        $first  = Idempotency::remember('razorpay:evt_1', 3600, $work);
        $second = Idempotency::remember('razorpay:evt_1', 3600, $work);
        $third  = Idempotency::remember('razorpay:evt_1', 3600, $work);

        $this->assertEquals(1, $counter, 'work() must run exactly once');
        $this->assertEquals($first, $second);
        $this->assertEquals($first, $third);
    }

    public function test_different_keys_run_independently(): void
    {
        $counter = 0;
        $work = function () use (&$counter) {
            return ++$counter;
        };

        Idempotency::remember('razorpay:evt_a', 3600, $work);
        Idempotency::remember('razorpay:evt_b', 3600, $work);

        $this->assertEquals(2, $counter);
    }

    public function test_releases_claim_on_exception_so_retry_can_work(): void
    {
        try {
            Idempotency::remember('razorpay:evt_fail', 3600, fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException $e) {
            // expected
        }

        $runs = 0;
        $result = Idempotency::remember('razorpay:evt_fail', 3600, function () use (&$runs) {
            $runs++;
            return 'recovered';
        });

        $this->assertEquals(1, $runs, 'after a failure, retry should actually re-run work()');
        $this->assertEquals('recovered', $result);
    }
}
