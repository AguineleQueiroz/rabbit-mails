<?php

namespace App\Services;

use Redis;

class HeartbeatService
{
    private Redis $redis;
    private string $workerId;

    public function __construct(string $workerId)
    {
        $this->workerId = $workerId;
        $this->redis    = new Redis();
        $this->redis->connect($_ENV['REDIS_HOST'], (int)$_ENV['REDIS_PORT']);
    }

    public function beat(string $status = 'active', int $processed = 0, int $failed = 0): void
    {
        $key = "consumer:{$this->workerId}";
        /* 15s TTL: if the worker dies, the key expires and the dashboard no longer displays the worker. */
        $this->redis->setex($key, 15, json_encode([
            'worker_id'        => $this->workerId,
            'status'           => $status,
            'last_heartbeat'   => date('c'),
            'emails_processed' => $processed,
            'emails_failed'    => $failed,
        ]));
    }

    public function getAll(): array
    {
        $keys = $this->redis->keys('consumer:*');

        if (empty($keys)) {
            return [];
        }

        return array_filter(
            array_map(fn ($key) => json_decode($this->redis->get($key), true), $keys)
        );
    }
}
