<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Audit\Actor;
use App\Support\Audit\ActorHolder;
use App\Support\Audit\ActorSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('records application writes with the transaction actor context', function (): void {
    ['actor' => $actor, 'deal' => $deal] = wp07bDealFixture();

    app(ActorHolder::class)->runWith(
        new Actor(ActorSource::User, (string) $actor->id, 'oturum-kurgusal', '192.0.2.10'),
        fn () => DB::transaction(fn () => $deal->update(['priority' => 'high'])),
    );

    $audit = DB::table('audit_log')
        ->where('table_name', 'deals')
        ->where('row_id', $deal->id)
        ->where('operation', 'UPDATE')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and((int) $audit->actor_id)->toBe($actor->id)
        ->and($audit->source)->toBe('user')
        ->and($audit->session_id)->toBe('oturum-kurgusal')
        ->and($audit->client_ip)->toBe('192.0.2.10')
        ->and(json_decode($audit->old_data, true, flags: JSON_THROW_ON_ERROR))->toHaveKey('priority', 'normal')
        ->and(json_decode($audit->new_data, true, flags: JSON_THROW_ON_ERROR))->toHaveKey('priority', 'high');
});

it('records direct SQL writes as an unknown system actor', function (): void {
    ['deal' => $deal] = wp07bDealFixture();

    DB::update('UPDATE deals SET priority = ? WHERE id = ?', ['urgent', $deal->id]);

    $audit = DB::table('audit_log')
        ->where('table_name', 'deals')
        ->where('row_id', $deal->id)
        ->where('operation', 'UPDATE')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->source)->toBe('system');
});

it('never copies user password fields into audit JSON', function (): void {
    $user = User::factory()->create(['email' => 'hassas-alan@example.invalid']);

    $user->update(['password' => 'tamamen-kurgusal-yeni-parola']);

    $audit = DB::table('audit_log')
        ->where('table_name', 'users')
        ->where('row_id', $user->id)
        ->where('operation', 'UPDATE')
        ->latest('id')
        ->first();

    $oldData = json_decode($audit->old_data, true, flags: JSON_THROW_ON_ERROR);
    $newData = json_decode($audit->new_data, true, flags: JSON_THROW_ON_ERROR);

    expect($oldData)->not->toHaveKey('password')
        ->and($newData)->not->toHaveKey('password')
        ->and(json_encode([$oldData, $newData], JSON_THROW_ON_ERROR))
        ->not->toContain('tamamen-kurgusal-yeni-parola');
});

it('rejects audit log updates and deletes at database level', function (): void {
    User::factory()->create(['email' => 'salt-ekleme@example.invalid']);
    $auditId = (int) DB::table('audit_log')->latest('id')->value('id');

    expect(fn () => DB::transaction(
        fn () => DB::table('audit_log')->where('id', $auditId)->update(['source' => 'user']),
    ))->toThrow(QueryException::class, 'audit_log is append-only');

    expect(fn () => DB::transaction(
        fn () => DB::table('audit_log')->where('id', $auditId)->delete(),
    ))->toThrow(QueryException::class, 'audit_log is append-only');
});

it('routes current writes to the current monthly partition', function (): void {
    $user = User::factory()->create(['email' => 'partition@example.invalid']);

    $partition = DB::scalar(
        'SELECT tableoid::regclass::text FROM audit_log WHERE table_name = ? AND row_id = ? ORDER BY id DESC LIMIT 1',
        ['users', $user->id],
    );

    expect($partition)->toBe('audit_log_'.now('UTC')->format('Ym'));
});
