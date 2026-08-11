<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{actor_id: string, session_id: string, client_ip: string, source: string}
 */
function readActorSettings(): array
{
    $settings = DB::selectOne(<<<'SQL'
        select
            current_setting('app.actor_id', true) as actor_id,
            current_setting('app.session_id', true) as session_id,
            current_setting('app.client_ip', true) as client_ip,
            current_setting('app.source', true) as source
        SQL);

    return [
        'actor_id' => (string) $settings->actor_id,
        'session_id' => (string) $settings->session_id,
        'client_ip' => (string) $settings->client_ip,
        'source' => (string) $settings->source,
    ];
}

it('applies HTTP actor settings only inside an explicit transaction', function (): void {
    $user = new User;
    $user->forceFill([
        'id' => 42,
        'name' => 'Test Kullanıcısı',
        'email' => 'test@example.invalid',
    ]);

    Route::middleware('web')->get(
        '/_test/actor-context',
        fn (): array => DB::transaction(fn (): array => readActorSettings()),
    );

    actingAs($user);
    $response = get('/_test/actor-context');

    $response->assertOk()
        ->assertJsonPath('actor_id', (string) $user->getKey())
        ->assertJsonPath('source', 'user');

    $outside = readActorSettings();

    expect($outside['actor_id'])->toBeEmpty();
    expect($outside['session_id'])->toBeEmpty();
    expect($outside['client_ip'])->toBeEmpty();
    expect($outside['source'])->toBeEmpty();
});

it('does not open a transaction for a GET request', function (): void {
    Route::middleware('web')->get('/_test/transaction-level', fn (): array => [
        'transaction_level' => DB::transactionLevel(),
    ]);

    get('/_test/transaction-level')
        ->assertOk()
        ->assertJsonPath('transaction_level', 0);
});

it('applies actor settings to explicit writes and leaves outside writes unknown', function (): void {
    $user = new User;
    $user->forceFill([
        'id' => 42,
        'name' => 'Test Kullanıcısı',
        'email' => 'test@example.invalid',
    ]);

    Route::middleware('web')->get('/_test/actor-write-context', function (): array {
        DB::statement(<<<'SQL'
            create temporary table actor_context_probe (
                phase text not null,
                actor_id text,
                source text
            ) on commit preserve rows
            SQL);

        try {
            DB::transaction(function (): void {
                DB::insert(<<<'SQL'
                    insert into actor_context_probe (phase, actor_id, source)
                    values (
                        'inside',
                        current_setting('app.actor_id', true),
                        current_setting('app.source', true)
                    )
                    SQL);
            });

            DB::insert(<<<'SQL'
                insert into actor_context_probe (phase, actor_id, source)
                values (
                    'outside',
                    current_setting('app.actor_id', true),
                    current_setting('app.source', true)
                )
                SQL);

            return DB::table('actor_context_probe')
                ->orderBy('phase')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
        } finally {
            DB::statement('drop table if exists actor_context_probe');
        }
    });

    actingAs($user);

    get('/_test/actor-write-context')
        ->assertOk()
        ->assertJsonFragment([
            'phase' => 'inside',
            'actor_id' => '42',
            'source' => 'user',
        ])
        ->assertJsonFragment([
            'phase' => 'outside',
            'actor_id' => '',
            'source' => '',
        ]);
});
