<?php

declare(strict_types=1);

use App\Domain\Document\Scanning\ClamAvVirusScanner;
use App\Domain\Document\Scanning\ScanResult;

/** @return array{string, int, int} */
function clamAvResponse(string $response): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if (! is_resource($server)) {
        throw new RuntimeException("Test sunucusu açılamadı: {$errorCode} {$errorMessage}");
    }

    $address = stream_socket_get_name($server, false);
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Test süreci ayrılamadı.');
    }

    if ($pid === 0) {
        $client = stream_socket_accept($server, 5);

        if (is_resource($client)) {
            fread($client, 10);

            while (true) {
                $sizeBytes = fread($client, 4);

                if ($sizeBytes === false || strlen($sizeBytes) !== 4) {
                    break;
                }

                $size = unpack('Nsize', $sizeBytes)['size'];

                if ($size === 0) {
                    break;
                }

                $remaining = $size;

                while ($remaining > 0) {
                    $chunk = fread($client, $remaining);

                    if ($chunk === false || $chunk === '') {
                        break 2;
                    }

                    $remaining -= strlen($chunk);
                }
            }

            fwrite($client, $response);
            fclose($client);
        }

        fclose($server);
        exit(0);
    }

    fclose($server);

    if (! is_string($address)) {
        throw new RuntimeException('Test sunucusu adresi okunamadı.');
    }

    [$host, $port] = explode(':', $address);

    return [$host, (int) $port, $pid];
}

function scanTemporaryFile(string $response): ScanResult
{
    [$host, $port, $pid] = clamAvResponse($response);
    $path = tempnam(sys_get_temp_dir(), 'clamav-test-');

    if ($path === false) {
        throw new RuntimeException('Geçici test dosyası oluşturulamadı.');
    }

    file_put_contents($path, 'tamamen kurgusal test içeriği');

    try {
        return (new ClamAvVirusScanner($host, $port, 2))->scan($path);
    } finally {
        unlink($path);
        pcntl_waitpid($pid, $status);
    }
}

it('maps a ClamAV infected response to infected', function (): void {
    expect(scanTemporaryFile("stream: Eicar-Test-Signature FOUND\0"))->toBe(ScanResult::Infected);
});

it('maps a ClamAV clean response to clean', function (): void {
    expect(scanTemporaryFile("stream: OK\0"))->toBe(ScanResult::Clean);
});

it('fails closed when ClamAV returns no verdict', function (): void {
    expect(scanTemporaryFile(''))->toBe(ScanResult::Failed);
});
