<?php

declare(strict_types=1);

return [
    'errors' => [
        'extension' => 'Bu dosya uzantısına izin verilmiyor.',
        'mime' => 'Dosyanın içeriği uzantısıyla eşleşmiyor veya desteklenmiyor.',
        'too_large' => 'Dosya izin verilen boyut sınırını aşıyor.',
        'duplicate' => 'Bu belgenin aynı içeriğe sahip bir sürümü zaten var.',
        'unavailable' => 'Dosya güvenlik taraması tamamlanmadan veya başarısızken indirilemez.',
        'forbidden' => 'Bu belge işlemi için yetkiniz yok.',
        'storage' => 'Dosya nesne deposuna yazılamadı veya depodan okunamadı.',
        'status_transition' => 'Belge bu durum geçişine uygun değil.',
        'reason_required' => 'Eksik/hatalı ve yeni sürüm bekleniyor kararlarında gerekçe zorunludur.',
        'stub_production' => 'Stub virüs tarayıcısı üretim ortamında kullanılamaz.',
        'scan_failed' => 'Virüs taraması için geçici dosya hazırlanamadı.',
        'scanner_driver' => 'Bilinmeyen belge tarayıcı sürücüsü: :driver',
    ],
    'scan' => [
        'infected_reason' => 'Dosyada zararlı içerik tespit edildi.',
    ],
    'commands' => [
        'expired' => ':count belgenin geçerlilik süresi sona erdi.',
    ],
    'notifications' => [
        'condition_added_title' => 'Yeni evrak eklendi',
        'condition_added_body' => 'Bu dosyaya :count yeni zorunlu evrak eklendi: :documents.',
        'expired_title' => 'Belgenin geçerlilik süresi doldu',
        'expired_body' => '":document" belgesi artık evrak tamamlık koşulunu sağlamıyor.',
    ],
    'suggestions' => [
        'condition_no_longer_matches' => '":document" evrakının koşulu artık sağlanmıyor. "Gerekli değil" olarak işaretlensin mi?',
    ],
];
