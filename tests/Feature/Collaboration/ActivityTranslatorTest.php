<?php

declare(strict_types=1);

use App\Domain\Collaboration\Services\ActivityTranslator;

it('tüm bilinen olaylar için Türkçe anlaşılır etkinlik cümlesi üretir', function (string $action, array $payload, string $expectedSnippet): void {
    $translator = new ActivityTranslator;
    $sentence = $translator->sentence($action, $payload, 'Kurgusal Belge');

    expect($sentence)->not->toContain(':action')
        ->and($sentence)->not->toContain('işlemini gerçekleştirdi')
        ->and($sentence)->toContain($expectedSnippet);
})->with([
    ['deal.assigned', ['from_assignee' => ['name' => 'Eski PM'], 'to_assignee' => ['name' => 'Yeni PM']], 'Yeni PM'],
    ['lead.converted', ['deal_reference' => 'BLF-1001'], 'BLF-1001'],
    ['workflow.bulk_transition', ['subject_count' => 5, 'target_status' => ['label' => 'Başvuru hazırlanıyor'], 'source_statuses' => ['Eski Statü']], 'Başvuru hazırlanıyor'],
    ['deal.status_changed', ['from_status' => ['label' => 'Atama bekliyor'], 'to_status' => ['label' => 'PM atandı']], 'PM atandı'],
    ['lead.status_changed', ['from_status' => ['label' => 'Yeni'], 'to_status' => ['label' => 'Arandı']], 'Arandı'],
    ['document.status_changed', ['document_name' => 'İmza Sirküleri', 'from_status' => 'uploaded', 'to_status' => 'accepted'], 'Kabul edildi'],
    ['document.uploaded', ['document_name' => 'İmza Sirküleri', 'original_name' => 'sirkuler.pdf', 'version_no' => 1], 'sirkuler.pdf'],
    ['document.ad_hoc_created', ['document' => ['name' => 'Ek Fizibilite']], 'Ek Fizibilite'],
    ['document.requirement_suggested', ['document' => ['name' => 'Ek Belge']], 'Ek Belge'],
    ['document.requirement_decided', ['document' => ['name' => 'Ek Belge']], 'Ek Belge'],
    ['deal.documents_requested', ['document_count' => 3], '3'],
    ['deal.documents_archive_requested', ['document_count' => 4], '4'],
    ['deal.documents_archive_downloaded', ['document_count' => 4], '4'],
    ['deal.checklist_generated', ['document_count' => 5], '5'],
    ['deal.condition_documents_added', ['document_names' => ['Fizibilite Raporu']], 'Fizibilite Raporu'],
    ['document.access_requested', ['version_no' => 2], '2'],
    ['document.downloaded', ['version_no' => 2], '2'],
    ['document.scan_completed', [], 'güvenlik taramasını tamamladı'],
    ['task.created', ['task' => ['title' => 'Evrakları incele']], 'Evrakları incele'],
    ['task.assigned', ['task' => ['title' => 'Evrakları incele'], 'to_assignee' => ['name' => 'Ahmet']], 'Ahmet'],
    ['task.completed', ['task' => ['title' => 'Evrakları incele']], 'tamamladı'],
    ['task.reopened', ['task' => ['title' => 'Evrakları incele']], 'yeniden açtı'],
    ['company.customer_flow_started', ['deal_reference' => 'BLF-2002'], 'BLF-2002'],
    ['company.bulk_email_requested', [], 'toplu e-posta gönderimini başlattı'],
    ['company.created', ['company' => ['name' => 'Örnek Firma']], 'firma kaydını oluşturdu'],
    ['company.updated', ['company' => ['name' => 'Örnek Firma']], 'firma bilgilerini güncelledi'],
    ['program.started', ['program' => ['name' => 'Yeşil Sanayi']], 'hizmeti yayına aldı'],
    ['deal.created', ['company' => ['name' => 'Örnek Firma']], 'müşteri operasyon dosyasını oluşturdu'],
    ['lead.created', ['company' => ['name' => 'Örnek Firma']], 'fırsatı oluşturdu'],
    ['interaction.recorded', [], 'görüşmeyi kaydetti'],
]);

it('gerçekten bilinmeyen olay için güvenli fallback metnini korur', function (): void {
    $translator = new ActivityTranslator;
    $sentence = $translator->sentence('unknown.legacy_event', []);

    expect($sentence)->toBe('unknown legacy event işlemini gerçekleştirdi');
});
