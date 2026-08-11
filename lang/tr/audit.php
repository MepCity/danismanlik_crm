<?php

declare(strict_types=1);

return [
    'commands' => [
        'ensure_partitions' => [
            'description' => 'Denetim kaydı için aylık partition tablolarını hazırlar.',
            'missing_table' => 'audit_log tablosu bulunamadı.',
            'invalid_months' => 'Ay sayısı 0 ile 24 arasında bir tam sayı olmalıdır.',
            'complete' => ':count aylık denetim partition tablosu hazır.',
        ],
    ],
];
