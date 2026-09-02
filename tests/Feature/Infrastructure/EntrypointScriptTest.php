<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

afterEach(function (): void {
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
});

it('entrypoint prod ve staging ortamlarında APP_DEBUG=false ile çalışır', function (string $env): void {
    $script = base_path('docker/app/entrypoint.prod.sh');

    $envVars = [
        'APP_ENV' => $env,
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'https://pilot.bizlife.invalid',
        'DB_PASSWORD' => 'secret_db_pass_123456',
        'REDIS_PASSWORD' => 'secret_redis_pass_123456',
        'AWS_ACCESS_KEY_ID' => 'test_key',
        'AWS_SECRET_ACCESS_KEY' => 'test_secret',
        'AWS_BUCKET' => 'test_bucket',
        'MAIL_HOST' => 'mail.invalid',
        'MAIL_FROM_ADDRESS' => 'info@bizlife.invalid',
        'ENTRYPOINT_SKIP_CACHE' => '1',
    ];

    $process = new Process(['sh', $script, 'echo', 'entrypoint_success'], base_path(), $envVars);
    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('entrypoint_success');
})->with(['staging', 'production']);

it('entrypoint local testing veya geçersiz ortamlarda reddeder', function (string $invalidEnv): void {
    $script = base_path('docker/app/entrypoint.prod.sh');

    $envVars = [
        'APP_ENV' => $invalidEnv,
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'https://pilot.bizlife.invalid',
        'DB_PASSWORD' => 'secret_db_pass_123456',
        'REDIS_PASSWORD' => 'secret_redis_pass_123456',
        'AWS_ACCESS_KEY_ID' => 'test_key',
        'AWS_SECRET_ACCESS_KEY' => 'test_secret',
        'AWS_BUCKET' => 'test_bucket',
        'MAIL_HOST' => 'mail.invalid',
        'MAIL_FROM_ADDRESS' => 'info@bizlife.invalid',
        'ENTRYPOINT_SKIP_CACHE' => '1',
    ];

    $process = new Process(['sh', $script, 'echo', 'entrypoint_success'], base_path(), $envVars);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('APP_ENV yalnızca \'production\' veya \'staging\' olabilir');
})->with(['local', 'testing', 'dev', 'development', '']);

it('entrypoint APP_DEBUG=true durumunda derhal reddeder', function (): void {
    $script = base_path('docker/app/entrypoint.prod.sh');

    $envVars = [
        'APP_ENV' => 'staging',
        'APP_DEBUG' => 'true',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'https://pilot.bizlife.invalid',
        'DB_PASSWORD' => 'secret_db_pass_123456',
        'REDIS_PASSWORD' => 'secret_redis_pass_123456',
        'AWS_ACCESS_KEY_ID' => 'test_key',
        'AWS_SECRET_ACCESS_KEY' => 'test_secret',
        'AWS_BUCKET' => 'test_bucket',
        'MAIL_HOST' => 'mail.invalid',
        'MAIL_FROM_ADDRESS' => 'info@bizlife.invalid',
        'ENTRYPOINT_SKIP_CACHE' => '1',
    ];

    $process = new Process(['sh', $script, 'echo', 'entrypoint_success'], base_path(), $envVars);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('APP_DEBUG=false zorunludur');
});

it('entrypoint zorunlu sırlar boş olduğunda fail-fast reddeder', function (string $missingVar): void {
    $script = base_path('docker/app/entrypoint.prod.sh');

    $envVars = [
        'APP_ENV' => 'staging',
        'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => 'https://pilot.bizlife.invalid',
        'DB_PASSWORD' => 'secret_db_pass_123456',
        'REDIS_PASSWORD' => 'secret_redis_pass_123456',
        'AWS_ACCESS_KEY_ID' => 'test_key',
        'AWS_SECRET_ACCESS_KEY' => 'test_secret',
        'AWS_BUCKET' => 'test_bucket',
        'MAIL_HOST' => 'mail.invalid',
        'MAIL_FROM_ADDRESS' => 'info@bizlife.invalid',
        'ENTRYPOINT_SKIP_CACHE' => '1',
    ];

    $envVars[$missingVar] = '';

    $process = new Process(['sh', $script, 'echo', 'entrypoint_success'], base_path(), $envVars);
    $process->run();

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain("Zorunlu çevre değişkeni boş olamaz: {$missingVar}");
})->with([
    'APP_KEY',
    'DB_PASSWORD',
    'REDIS_PASSWORD',
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_BUCKET',
    'MAIL_HOST',
    'MAIL_FROM_ADDRESS',
]);
