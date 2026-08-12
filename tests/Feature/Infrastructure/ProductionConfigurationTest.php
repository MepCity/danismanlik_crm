<?php

declare(strict_types=1);

it('keeps every production service off host ports', function (): void {
    $compose = file_get_contents(base_path('compose.prod.yaml'));

    expect($compose)->not->toBeFalse();
    expect($compose)->not->toMatch('/^\s+ports:/m');
    expect($compose)->not->toMatch('/network_mode:\s*host/');
});

it('uses managed object storage instead of production MinIO', function (): void {
    $compose = file_get_contents(base_path('compose.prod.yaml'));

    expect($compose)->not->toBeFalse();
    expect($compose)->not->toMatch('/^\s+minio:/m');
    expect($compose)->toContain('DOCUMENT_SCANNER: clamav');
});

it('replicates documents to a separate target without propagating deletes', function (): void {
    $compose = file_get_contents(base_path('compose.prod.yaml'));
    $replicator = file_get_contents(base_path('docker/backup/object-replica-loop.sh'));

    expect($compose)->not->toBeFalse();
    expect($replicator)->not->toBeFalse();
    expect($compose)->toContain('object-replica:');
    expect($replicator)->toContain('mirror --overwrite');
    expect($replicator)->not->toContain('mirror --remove');
});

it('keeps production secrets blank in the committed template', function (): void {
    $template = file_get_contents(base_path('.env.production.example'));

    expect($template)->not->toBeFalse();

    foreach ([
        'APP_KEY',
        'DB_PASSWORD',
        'BACKUP_DB_PASSWORD',
        'REDIS_PASSWORD',
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
        'OBJECT_REPLICA_ACCESS_KEY_ID',
        'OBJECT_REPLICA_SECRET_ACCESS_KEY',
        'CLOUDFLARE_TUNNEL_TOKEN',
        'BACKUP_RESTIC_PASSWORD',
        'SENTRY_LARAVEL_DSN',
    ] as $secret) {
        expect($template)->toMatch('/^'.preg_quote($secret, '/').'=$/m');
    }
});

it('preserves the host port when forwarding local requests to Laravel', function (): void {
    $nginx = file_get_contents(base_path('docker/web/default.conf'));

    expect($nginx)->toBeString();
    assert(is_string($nginx));

    expect($nginx)->toContain('""      $http_host;');
    expect($nginx)->not->toContain('""      $host;');
});
