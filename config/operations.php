<?php

declare(strict_types=1);

return [
    'delayed_status_days' => (int) env('OPERATIONS_DELAYED_STATUS_DAYS', 7),
    'company_industries' => [
        'food',
        'agriculture',
        'manufacturing',
        'machinery',
        'automotive',
        'textile',
        'construction',
        'energy',
        'technology',
        'software',
        'telecommunications',
        'healthcare',
        'pharmaceuticals',
        'education',
        'finance',
        'insurance',
        'logistics',
        'tourism',
        'retail',
        'services',
        'consulting',
        'media',
        'mining',
        'chemicals',
        'packaging',
        'furniture',
        'other',
    ],
];
