<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('recreates missing forward audit partitions with the scheduled command', function (): void {
    $target = now('UTC')->startOfMonth()->addMonths(3);
    $partition = 'audit_log_'.$target->format('Ym');

    DB::statement("DROP TABLE {$partition}");

    expect(Artisan::call('audit:ensure-partitions', ['--months' => 3]))->toBe(0)
        ->and(DB::scalar('SELECT to_regclass(?)', [$partition]))->toBe($partition);
});

it('uses the partial outbox index for unprocessed messages', function (): void {
    DB::table('outbox')->insert([
        'event_name' => 'test.pending',
        'payload' => json_encode(['kurgusal' => true], JSON_THROW_ON_ERROR),
        'available_at' => now(),
        'created_at' => now(),
    ]);

    $definition = DB::table('pg_indexes')
        ->where('schemaname', DB::raw('current_schema()'))
        ->where('tablename', 'outbox')
        ->where('indexname', 'outbox_unprocessed_available')
        ->value('indexdef');

    DB::statement('SET LOCAL enable_seqscan = off');
    $plan = collect(DB::select(<<<'SQL'
        EXPLAIN (COSTS OFF)
        SELECT id
        FROM outbox
        WHERE processed_at IS NULL
        ORDER BY available_at, id
        LIMIT 10
        SQL))->pluck('QUERY PLAN')->implode("\n");

    expect($definition)->toContain('WHERE (processed_at IS NULL)')
        ->and($plan)->toContain('outbox_unprocessed_available');
});

it('registers daily audit partition maintenance', function (): void {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('audit:ensure-partitions');
});
