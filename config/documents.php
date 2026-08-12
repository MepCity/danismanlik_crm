<?php

declare(strict_types=1);

return [
    'disk' => env('DOCUMENT_DISK', 's3'),
    'max_size_kb' => (int) env('DOCUMENT_MAX_SIZE_KB', 10 * 1024),
    'download_link_minutes' => (int) env('DOCUMENT_DOWNLOAD_LINK_MINUTES', 10),
    'scanner' => env('DOCUMENT_SCANNER', 'stub'),
    'clamav' => [
        'host' => env('CLAMAV_HOST', 'clamav'),
        'port' => (int) env('CLAMAV_PORT', 3310),
        'timeout_seconds' => (int) env('CLAMAV_TIMEOUT_SECONDS', 30),
    ],
];
