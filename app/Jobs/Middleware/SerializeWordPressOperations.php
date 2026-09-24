<?php

namespace App\Jobs\Middleware;

use App\Models\CustomerBlog;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SerializeWordPressOperations
{
    // Covers even an operation launched near the end of the SSH deadline.
    public const LOCK_SECONDS = 660;

    public static function key(string $ip): string
    {
        $packed = inet_pton($ip);
        // Treat IPv4-mapped IPv6 and IPv4 addresses as the same host.
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $packed = substr($packed, 12);
        }

        return 'blogshed-server:'.bin2hex($packed);
    }

    public function handle(object $job, callable $next): void
    {
        $blog = CustomerBlog::with('server')->find($job->blogId);
        $ip = $blog?->server?->ip_address;
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            $next($job); // Let the job report the invalid assignment normally.

            return;
        }

        $lock = Cache::store(config('blogshed.lock_store'))->lock(self::key($ip), self::LOCK_SECONDS);
        if (! $lock->get()) {
            $connection = $job->connection ?? config('queue.default');
            if (config("queue.connections.$connection.driver") === 'sync') {
                throw new RuntimeException('WordPress operations require a persistent queue to wait for a busy server.');
            }

            // No remote operation has started. A fresh queue envelope preserves
            // tries=1 for actual execution while allowing unlimited contention waits.
            $deferred = method_exists($job, 'freshForDeferral') ? $job->freshForDeferral() : new ($job::class)($job->blogId);
            $deferred->onConnection($job->connection)->onQueue($job->queue)->delay(15);
            Bus::dispatch($deferred);

            return;
        }

        try {
            $next($job);
        } finally {
            // An SSH failure can leave a bounded operation running remotely.
            // Keep its lease until that remote lifetime has safely elapsed.
            if (! $job->remoteOperationUncertain) {
                $lock->release();
            }
        }
    }
}
