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
        'workflow' => [
            'orphaned' => 'Değişiklik kaydedilemez. :details ve değişiklikten sonra çıkış geçişi kalmıyor.',
            'deal_count' => '{1} Şu anda :count dosya ":status" statüsünde|[2,*] Şu anda :count dosya ":status" statüsünde',
            'lead_count' => '{1} Şu anda :count fırsat ":status" statüsünde|[2,*] Şu anda :count fırsat ":status" statüsünde',
        ],
        'document' => [
            'suggestion_not_pending' => 'Bu evrak önerisi daha önce karara bağlanmış.',
            'request_invalid_status' => '":document" evrakı talep gönderilmeyi beklemiyor.',
            'request_mixed_deals' => 'Aynı işlemde yalnızca tek dosyanın evrakları talep edilebilir.',
            'request_empty' => 'Talep gönderilecek en az bir evrak seçilmelidir.',
            'request_missing' => 'Seçilen evraklardan biri bulunamadı.',
        ],
    ],
];
