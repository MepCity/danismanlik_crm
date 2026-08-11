<?php

declare(strict_types=1);

return [
    'errors' => [
        'condition' => [
            'invalid_definition' => 'Koşul tanımı geçersiz.',
            'unknown_operator' => 'Bilinmeyen koşul operatörü: :operator.',
            'unresolvable_field' => 'Koşul alanı çözümlenemedi: :field.',
        ],
        'status' => [
            'undefined_transition' => '":from" statüsünden ":to" statüsüne tanımlı bir geçiş yok.',
            'inactive_transition' => '":from" → ":to" geçişi kullanım dışı.',
            'terminal' => '":status" terminal statüsünden çıkış yapılamaz.',
            'permission' => 'Bu geçiş için :permission izni gerekiyor.',
            'missing_documents' => ':count zorunlu evrak eksik: :documents.',
            'condition' => 'Geçiş koşulu sağlanmadı. Kontrol edilmesi gereken alanlar: :fields.',
            'target_unavailable' => 'Hedef statü kullanım dışı veya özne türüyle uyumsuz.',
            'history_missing' => 'Öznenin açık statü geçmişi bulunamadı.',
            'revision_missing' => 'Yürürlükte bir iş akışı revizyonu bulunamadı.',
        ],
    ],
];
