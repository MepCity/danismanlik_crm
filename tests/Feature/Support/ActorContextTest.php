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

it('applies HTTP actor settings only inside the request transaction', function (): void {
    $user = new User;
    $user->forceFill([
        'id' => 42,
        'name' => 'Test Kullanıcısı',
        'email' => 'test@example.invalid',
    ]);

    Route::middleware('web')->get('/_test/actor-context', function (): array {
        return readActorSettings();
    });

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
