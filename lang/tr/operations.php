<?php

declare(strict_types=1);

return [
    'board' => [
        'navigation' => 'Dosya panosu',
        'title' => 'Dosya panosu',
        'empty_column' => 'Bu statüde dosya yok.',
        'unassigned' => 'Atanmadı',
        'days' => ':count gün',
        'today' => 'Bugün',
        'delayed' => 'Uzun süredir bu statüde',
        'counter' => ':received/:total geldi · :missing eksik · :review incelemede · :expired süresi doldu',
        'pending_suggestion' => ':count bekleyen gereklilik önerisi',
    ],
    'detail' => [
        'title' => ':reference dosyası',
        'tabs' => [
            'general' => 'Genel', 'process' => 'Süreç', 'documents' => 'Belge listesi',
            'tasks' => 'Görevler', 'comments' => 'Yorumlar', 'interactions' => 'Görüşmeler',
            'team' => 'Ekip', 'history' => 'İşlem geçmişi',
        ],
        'fields' => [
            'company' => 'Firma', 'reference' => 'Dosya no', 'program' => 'Program',
            'manager' => 'Proje yöneticisi', 'opened_by' => 'Dosyayı açan', 'priority' => 'Öncelik',
            'status' => 'Mevcut statü', 'status_since' => 'Statü başlangıcı',
        ],
        'allowed_transitions' => 'İzinli geçişler',
        'no_transitions' => 'Bu statüden kullanabileceğiniz etkin bir geçiş yok.',
        'transition_error' => 'Geçiş reddedildi',
        'placeholder' => 'Bu bölüm sonraki iş paketinde etkinleştirilecek.',
    ],
    'documents' => [
        'title' => 'Evrak checklist',
        'empty' => 'Evrak listesi program şablonundan üretilir — programı seçin.',
        'name' => 'Evrak', 'required' => 'Zorunluluk', 'required_yes' => 'Zorunlu',
        'required_no' => 'Koşullu / ek', 'status' => 'Durum', 'due' => 'Son tarih',
        'validity' => 'Geçerlilik bitişi', 'versions' => 'Sürüm', 'actions' => 'İşlemler',
        'no_date' => '—', 'expired' => 'Süresi doldu',
        'statuses' => [
            'to_request' => 'Talep edilecek', 'requested' => 'Talep edildi', 'uploaded' => 'Yüklendi',
            'under_review' => 'İnceleniyor', 'accepted' => 'Kabul edildi', 'rejected' => 'Eksik/hatalı',
            'new_version_expected' => 'Yeni sürüm bekleniyor', 'not_required' => 'Bu dosya için gerekli değil',
            'expired' => 'Süresi doldu',
        ],
        'suggestion' => 'Sistem bu belgenin artık gerekli olmadığını öneriyor.',
        'version_line' => ':document — Sürüm :version · :status',
        'history' => 'Sürüm geçmişi',
        'upload' => 'Yükle', 'start_review' => 'İncelemeye al', 'accept' => 'Kabul et',
        'reject' => 'Reddet', 'new_version' => 'Yeni sürüm iste', 'download' => 'İndir',
        'approve_suggestion' => 'Öneriyi onayla', 'reject_suggestion' => 'Öneriyi reddet',
        'reason' => 'Gerekçe', 'reason_help' => 'Ret ve yeni sürüm isteğinde gerekçe zorunludur.',
        'save_decision' => 'Kararı kaydet', 'cancel' => 'Vazgeç',
        'add_ad_hoc' => 'Dosyaya özel evrak ekle', 'ad_hoc_name' => 'Evrak adı',
        'ad_hoc_description' => 'Açıklama', 'ad_hoc_required' => 'Zorunlu', 'add' => 'Ekle',
        'send_missing' => 'Eksik evrak listesini firmaya gönder',
    ],
    'messages' => [
        'transitioned' => 'Dosya statüsü güncellendi.', 'uploaded' => 'Belge Sürüm :version olarak yüklendi.',
        'review_started' => 'Belge incelemeye alındı.', 'decision_saved' => 'Belge kararı kaydedildi.',
        'suggestion_decided' => 'Gereklilik önerisi karara bağlandı.', 'ad_hoc_added' => 'Dosyaya özel evrak eklendi.',
        'no_email_contact' => 'E-posta izni olan aktif birincil firma yetkilisi bulunamadı.',
        'no_missing_documents' => 'Gönderilecek eksik zorunlu evrak yok.',
        'email_queued' => ':count eksik evrak içeren e-posta kuyruğa alındı.',
    ],
    'email' => [
        'subject' => ':reference dosyası eksik evrak listesi',
        'greeting' => 'Sayın :name,',
        'intro' => ':reference numaralı dosya için beklediğimiz zorunlu evraklar:',
        'closing' => 'Belgeleri proje yöneticinizle güvenli kanal üzerinden paylaşmanızı rica ederiz.',
    ],
];
