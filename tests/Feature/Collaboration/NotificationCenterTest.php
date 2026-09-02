<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Services\NotificationService;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Program\Models\Program;
use App\Filament\Pages\DealDetail;
use App\Livewire\NotificationCenter;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

it('kullanıcı yalnız kendi bildirimlerini görebilir ve okunmamış sayacı doğru çalışır', function (): void {
    $user1 = User::factory()->create(['email' => 'bildirim-user1@example.invalid']);
    $user1->assignRole('Proje Yöneticisi');
    $user2 = User::factory()->create(['email' => 'bildirim-user2@example.invalid']);
    $user2->assignRole('Pazarlama');

    $service = app(NotificationService::class);

    expect($service->unreadCount($user1))->toBe(0);

    Notification::query()->create([
        'user_id' => $user1->id,
        'title' => 'Dosya atandı',
        'body' => 'DEMO-001 dosyası size atandı.',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    Notification::query()->create([
        'user_id' => $user1->id,
        'title' => 'Yeni evrak',
        'body' => 'İmza sirküleri yüklendi.',
        'channel' => 'in_app',
        'type' => 'document.uploaded',
    ]);

    Notification::query()->create([
        'user_id' => $user2->id,
        'title' => 'Diğer kullanıcı bildirimi',
        'body' => 'Bu bildirim user2 için.',
        'channel' => 'in_app',
        'type' => 'lead.status_changed',
    ]);

    expect($service->unreadCount($user1))->toBe(2)
        ->and($service->unreadCount($user2))->toBe(1)
        ->and($service->listForUser($user1)->count())->toBe(2)
        ->and($service->listForUser($user2)->count())->toBe(1);
});

it('okundu işaretleme ve tümünü okundu işaretleme sayacı günceller', function (): void {
    $user = User::factory()->create(['email' => 'okundu-user@example.invalid']);
    $user->assignRole('Proje Yöneticisi');
    $service = app(NotificationService::class);

    $n1 = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'Bildirim 1',
        'body' => 'Gövde 1',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);
    $n2 = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'Bildirim 2',
        'body' => 'Gövde 2',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    expect($service->unreadCount($user))->toBe(2);

    $service->markAsRead($user, $n1->id);
    expect($service->unreadCount($user))->toBe(1);

    $service->markAllAsRead($user);
    expect($service->unreadCount($user))->toBe(0);
});

it('bildirim hedef kaydı kullanıcı yetki kapsamındaysa URL üretir aksi halde güvenli null döner', function (): void {
    $pm = User::factory()->create(['email' => 'pm-hedef@example.invalid', 'data_scope' => 'own']);
    $pm->assignRole('Proje Yöneticisi');

    $otherUser = User::factory()->create(['email' => 'diger-pm@example.invalid']);

    $company = Company::query()->create([
        'legal_name' => 'Hedef Firma A.Ş.',
        'industry' => 'manufacturing',
        'owner_user_id' => $otherUser->id,
        'is_active' => true,
    ]);

    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
    $version = $program->versions()->firstOrFail();
    $status = Status::query()->where('type', 'deal')->firstOrFail();

    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $otherUser->id,
        'pm_user_id' => $otherUser->id,
        'reference_no' => 'HDF-2026-001',
    ]);

    $notification = Notification::query()->create([
        'user_id' => $pm->id,
        'deal_id' => $deal->id,
        'title' => 'Kapsam dışı dosya',
        'body' => 'Bu dosya pm kullanıcısının kapsamında değil.',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    $service = app(NotificationService::class);

    // Kapsam dışı olduğu için URL null olmalı
    expect($service->targetUrl($pm, $notification))->toBeNull();

    // PM atandığında ve yetkili olduğunda URL açılabilmeli
    $deal->update(['pm_user_id' => $pm->id]);
    expect($service->targetUrl($pm, $notification))->toBe(DealDetail::getUrl(['deal' => $deal->id]));
});

it('kapsamı geri alınan veya silinen hedefe ait bildirim listeden ve sayaçtan çıkar içerik sızdırmaz', function (): void {
    $pm = User::factory()->create(['email' => 'pm-revocation@example.invalid', 'data_scope' => 'own']);
    $pm->assignRole('Proje Yöneticisi');

    $company = Company::query()->create([
        'legal_name' => 'Gizli Firma A.Ş.',
        'industry' => 'manufacturing',
        'owner_user_id' => $pm->id,
        'is_active' => true,
    ]);

    $program = Program::query()->where('code', 'KOSGEB-YESIL-SANAYI')->firstOrFail();
    $version = $program->versions()->firstOrFail();
    $status = Status::query()->where('type', 'deal')->firstOrFail();

    $otherUser = User::factory()->create();

    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $otherUser->id,
        'pm_user_id' => $pm->id,
        'reference_no' => 'GZL-2026-001',
    ]);

    $notification = Notification::query()->create([
        'user_id' => $pm->id,
        'deal_id' => $deal->id,
        'title' => 'Gizli Proje Bildirimi',
        'body' => 'Gizli Firma A.Ş. için çok özel bilgiler içeren bildirim.',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    $service = app(NotificationService::class);

    // 1. Durum: PM yetkiliyken bildirim görünür, sayılır ve URL üretilir
    expect($service->unreadCount($pm))->toBe(1)
        ->and($service->listForUser($pm)->count())->toBe(1)
        ->and($service->listForUser($pm)->first()?->title)->toBe('Gizli Proje Bildirimi')
        ->and($service->targetUrl($pm, $notification))->not->toBeNull();

    // 2. Durum: Dosya başka birine atanıp PM'in yetki kapsamından çıktığında içerik ve sayaç sızdırılmaz
    $otherUser = User::factory()->create();
    $deal->update(['pm_user_id' => $otherUser->id]);

    expect($service->unreadCount($pm))->toBe(0)
        ->and($service->listForUser($pm)->count())->toBe(0)
        ->and($service->targetUrl($pm, $notification))->toBeNull();

    // 3. Durum: Yetki geri geldiğinde tekrar görünür
    $deal->update(['pm_user_id' => $pm->id]);
    expect($service->unreadCount($pm))->toBe(1)
        ->and($service->listForUser($pm)->count())->toBe(1);
});

it('livewire bildirim merkezi bileşeni etkileşimleri doğru işletir', function (): void {
    $user = User::factory()->create(['email' => 'livewire-user@example.invalid']);
    $user->assignRole('Proje Yöneticisi');

    $n = Notification::query()->create([
        'user_id' => $user->id,
        'title' => 'Canlı Bildirim',
        'body' => 'Açıklama',
        'channel' => 'in_app',
        'type' => 'deal.assigned',
    ]);

    Auth::login($user);

    Livewire::test(NotificationCenter::class)
        ->assertSee('Canlı Bildirim')
        ->assertSee('1 yeni')
        ->call('markAsRead', $n->id)
        ->assertDontSee('1 yeni');
});
