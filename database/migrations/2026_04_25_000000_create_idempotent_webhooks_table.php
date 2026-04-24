<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('idempotent-webhooks.table', 'idempotent_webhooks'), function (Blueprint $table) {
            $table->id();
            $table->string('namespace', 64);
            $table->string('key', 255);
            $table->longText('response_body')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->useCurrent();

            // THE trick — unique (namespace, key) is the whole game.
            $table->unique(['namespace', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('idempotent-webhooks.table', 'idempotent_webhooks'));
    }
};
