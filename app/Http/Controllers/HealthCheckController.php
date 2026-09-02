<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        try {
            // 1. Veritabanı Kontrolü (Canlı bağlantı)
            DB::connection()->getPdo();

            // 2. Redis Kontrolü (Canlı ping)
            $usesRedis = in_array('redis', [
                config('cache.default'),
                config('queue.default'),
                config('session.driver'),
            ], true);

            if ($usesRedis || (app()->environment('staging', 'production') && config('database.redis.default.host'))) {
                Redis::connection()->ping();
            }

            // 3. Gerçek ama Yan Etkisiz Depolama Kontrolü (S3 / MinIO / Yerel)
            $disk = (string) (config('filesystems.default') ?: 's3');
            Storage::disk($disk)->exists('.healthcheck_probe');

            return response()->json([
                'status' => 'ok',
                'services' => [
                    'database' => 'ok',
                    'redis' => 'ok',
                    'storage' => 'ok',
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Readiness health check failed: '.$e->getMessage());

            return response()->json([
                'status' => 'unhealthy',
            ], 503);
        }
    }
}
