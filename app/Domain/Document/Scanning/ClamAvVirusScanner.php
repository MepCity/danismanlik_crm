<?php

declare(strict_types=1);

namespace App\Domain\Document\Scanning;

final readonly class ClamAvVirusScanner implements VirusScanner
{
    private const int CHUNK_SIZE = 8192;

    public function __construct(
        private string $host,
        private int $port,
        private int $timeoutSeconds,
    ) {}

    public function scan(string $path): ScanResult
    {
        $file = @fopen($path, 'rb');

        if (! is_resource($file)) {
            return ScanResult::Failed;
        }

        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->host, $this->port),
            $errorCode,
            $errorMessage,
            $this->timeoutSeconds,
        );

        if (! is_resource($socket)) {
            fclose($file);

            return ScanResult::Failed;
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            if (! $this->writeAll($socket, "zINSTREAM\0")) {
                return ScanResult::Failed;
            }

            while (! feof($file)) {
                $chunk = fread($file, self::CHUNK_SIZE);

                if ($chunk === false) {
                    return ScanResult::Failed;
                }

                if ($chunk !== '' && ! $this->writeAll($socket, pack('N', strlen($chunk)).$chunk)) {
                    return ScanResult::Failed;
                }
            }

            if (! $this->writeAll($socket, pack('N', 0))) {
                return ScanResult::Failed;
            }

            $response = stream_get_contents($socket);

            if (! is_string($response)) {
                return ScanResult::Failed;
            }

            if (str_contains($response, ' FOUND')) {
                return ScanResult::Infected;
            }

            return str_contains($response, ' OK') ? ScanResult::Clean : ScanResult::Failed;
        } finally {
            fclose($file);
            fclose($socket);
        }
    }

    /** @param resource $stream */
    private function writeAll($stream, string $contents): bool
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = fwrite($stream, substr($contents, $offset));

            if ($written === false || $written === 0) {
                return false;
            }

            $offset += $written;
        }

        return true;
    }
}
