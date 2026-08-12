<?php

declare(strict_types=1);

return [
    'navigation' => 'Raporlar',
    'title' => 'Raporlar',
    'report_selector' => 'Sabit rapor görünümleri',
    'export' => 'Excel indir',
    'export_permission_required' => 'Excel indirme izni gerekli',
    'row_summary' => ':total satırın :shown satırı gösteriliyor.',
    'not_available' => '—',
    'no_program' => 'Program seçilmedi',
    'availability' => [
        'idle' => 'Boşta',
        'busy' => 'Aktif',
    ],
    'dashboard' => [
        'navigation' => 'Ana panel',
        'title' => 'Ana panel',
        'recent_activities' => 'Son aktiviteler',
        'no_activities' => 'Kapsamınızda henüz aktivite yok.',
        'no_business_data' => 'Bu rolün iş verisi kapsamı yok. Sistem ayarlarına menüden erişebilirsiniz.',
        'cards' => [
            'today_calls' => 'Bugün aranacaklar',
            'overdue_followups' => 'Geciken takipler',
            'new_assignments' => 'Bana atanan yeni işler',
            'missing_documents' => 'Belgesi eksik dosyalar',
            'upcoming_deadlines' => 'Son tarihi yaklaşan başvurular',
            'customer_response' => 'Müşteri dönüşü beklenen işler',
            'unassigned_deals' => 'PM atanmamış işler',
        ],
    ],
    'reports' => [
        'deal_board' => [
            'title' => 'Dosya panosu',
            'empty' => 'Kapsamınızda dosya yok.',
        ],
        'pending_assignments' => [
            'title' => 'Bekleyen atamalar',
            'empty' => 'PM ataması bekleyen dosya yok.',
        ],
        'pm_workload' => [
            'title' => 'PM iş yükü',
            'empty' => 'Kapsamınızda proje yöneticisi yok.',
        ],
        'missing_documents' => [
            'title' => 'Eksik evrak listesi',
            'empty' => 'Eksik zorunlu evrak yok.',
        ],
        'upcoming_deadlines' => [
            'title' => 'Yaklaşan son tarihler',
            'empty' => 'Önümüzdeki 30 günde kapanan çağrı yok.',
        ],
        'conversion_funnel' => [
            'title' => 'Dönüşüm hunisi',
            'empty' => 'Kapsamınızda dönüşüm verisi yok.',
        ],
    ],
    'columns' => [
        'reference_no' => 'Dosya no',
        'company_name' => 'Firma',
        'program_name' => 'Program',
        'status_label' => 'Statü',
        'project_manager' => 'Proje yöneticisi',
        'status_days' => 'Statüde toplam gün',
        'document_collection_days' => 'Evrak toplama günü',
        'waiting_days' => 'Bekleme günü',
        'active_deals' => 'Aktif dosya',
        'missing_documents' => 'Eksik evrak',
        'average_collection_days' => 'Ort. evrak toplama günü',
        'availability' => 'Durum',
        'document_name' => 'Evrak',
        'document_status' => 'Evrak durumu',
        'due_at' => 'Evrak son tarihi',
        'call_period' => 'Çağrı dönemi',
        'application_closes_at' => 'Başvuru kapanışı',
        'remaining_days' => 'Kalan gün',
        'lead_count' => 'Fırsat',
        'call_count' => 'Arama',
        'conversation_count' => 'Görüşme',
        'converted_count' => 'İş alındı',
        'approved_count' => 'Onay',
        'call_to_conversation_rate' => 'Arama → görüşme',
        'approval_rate' => 'İş → onay',
    ],
];
