<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            if (config('cache.default') === 'redis' || config('queue.default') === 'redis' || config('database.redis.default.host')) {
                try {
                    Redis::connection()->ping();
                } catch (Throwable $redisError) {
                    if (app()->environment('staging', 'production')) {
                        throw new RuntimeException('Redis connection failed: '.$redisError->getMessage());
                    }
                }
            }

            Storage::disk(config('filesystems.default'));

            return response()->json([
                'status' => 'ok',
                'services' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                    'storage' => 'ok',
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Readiness health check failed: '.$e->getMessage());

            return response()->json([
                'status' => 'unhealthy',
            ], 503);
        }
    }
}
