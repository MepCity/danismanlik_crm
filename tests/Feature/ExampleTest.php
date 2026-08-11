<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\get;

it('boots in Turkish against PostgreSQL', function (): void {
    $response = get('/');

    $response->assertStatus(200);

    expect(DB::scalar('select current_database()'))->toBe('tesvik_crm_test')
        ->and((int) DB::scalar("select current_setting('server_version_num')"))->toBeGreaterThanOrEqual(170000)
        ->and(DB::scalar("select current_setting('server_encoding')"))->toBe('UTF8')
        ->and(DB::scalar("select current_setting('client_encoding')"))->toBe('UTF8')
        ->and(config('app.locale'))->toBe('tr');

    $validator = Validator::make([], ['email' => ['required']]);

    expect($validator->errors()->first('email'))->toBe('E-posta alanı zorunludur.');
});
