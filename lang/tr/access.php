<?php

declare(strict_types=1);

return [
    'break_glass' => [
        'forbidden' => 'Acil erişim verme veya iptal etme yetkiniz yok.',
        'reason_required' => 'Acil erişim gerekçesi zorunludur.',
        'expiry_required' => 'Acil erişim için gelecekte bir bitiş zamanı zorunludur.',
        'duration_exceeded' => 'Acil erişim süresi en fazla :minutes dakika olabilir.',
        'invalid_target' => 'Acil erişim yalnızca iş verisi kapsamı olmayan kullanıcıya verilebilir.',
    ],
    'notifications' => [
        'break_glass_title' => 'Acil iş verisi erişimi verildi',
        'break_glass_body' => ':user kullanıcısına :expires_at tarihine kadar acil erişim verildi. Gerekçe: :reason',
    ],
];
