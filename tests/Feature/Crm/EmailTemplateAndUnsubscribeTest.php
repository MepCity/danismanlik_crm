<?php

declare(strict_types=1);

use App\Domain\Collaboration\Jobs\SendNotificationEmail;
use App\Domain\Collaboration\Models\Notification as CollaborationNotification;
use App\Domain\Crm\Actions\SaveCompanyDirectoryEntry;
use App\Domain\Crm\Actions\SaveEmailTemplate;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Services\BulkCompanyEmailService;
use App\Domain\Crm\Services\EmailTemplateRenderer;
use App\Domain\Crm\Services\MarketingUnsubscribeUrl;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

/** @return array{User, Company, Contact, App\Domain\Crm\Models\EmailTemplate} */
function emailTemplateFixture(): array
{
    $actor = User::factory()->create(['email' => 'sablon-pazarlama@example.invalid']);
    $actor->assignRole('Pazarlama');
    $admin = User::factory()->create(['email' => 'sablon-yonetici@example.invalid']);
    $admin->assignRole('Sistem Yöneticisi');
    $company = app(SaveCompanyDirectoryEntry::class)->execute(null, [
        'legal_name' => 'Kurgusal İç Anadolu Teknoloji AŞ',
        'industry' => 'technology',
        'city' => 'Ankara',
        'is_active' => true,
    ], $actor);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İzinli Yetkili',
        'email' => 'izinli-yetkili@example.invalid',
        'data_source' => 'other',
        'consent_email' => true,
        'is_active' => true,
    ]);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'email',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'explicit_consent',
        'source' => 'form',
        'effective_from' => now()->subMinute(),
        'recorded_by' => $actor->id,
    ]);
    $template = app(SaveEmailTemplate::class)->execute(null, [
        'name' => 'Kurgusal teknoloji tanıtımı',
        'subject' => '{{firma_unvani}} için tanıtım',
        'body' => 'Merhaba {{yetkili_adi}}, {{il}} ilindeki {{sektor}} çalışmalarınız için bilgi.',
        'is_active' => true,
    ], $admin);

    return [$actor, $company, $contact, $template];
}

it('şablon değişkenlerini gerçek firma ve kişiyle doldurur', function (): void {
    [, , $contact, $template] = emailTemplateFixture();

    $rendered = app(EmailTemplateRenderer::class)->render($template, $contact);

    expect($rendered['subject'])->toBe('Kurgusal İç Anadolu Teknoloji AŞ için tanıtım')
        ->and($rendered['body'])->toContain('Kurgusal İzinli Yetkili')
        ->and($rendered['body'])->toContain('Ankara')
        ->and($rendered['body'])->toContain('Teknoloji');
});

it('desteklenmeyen şablon değişkenini bilerek reddeder', function (): void {
    $renderer = app(EmailTemplateRenderer::class);

    expect(fn () => $renderer->validate('Kurgusal konu', '{{gizli_alan}}'))
        ->toThrow(ValidationException::class);
});

it('kişisel süreli çıkış bağlantısını postaya ekler ve izni append-only geri çeker', function (): void {
    /** @var TestCase $this */
    Queue::fake();
    [$actor, $company, $contact, $template] = emailTemplateFixture();

    $result = app(BulkCompanyEmailService::class)->send(
        Company::query()->whereKey($company->id)->get(),
        $template->id,
        ['city' => ['value' => 'Ankara']],
        $actor,
    );
    $notification = CollaborationNotification::query()->where('type', 'company.bulk_email')->sole();
    preg_match('/https?:\/\/\S+/', $notification->body, $matches);

    expect($result->queuedCount)->toBe(1)
        ->and($matches)->not->toBeEmpty();
    Queue::assertPushed(SendNotificationEmail::class, 1);

    $this->get($matches[0])->assertOk()->assertSee('E-posta tercihiniz güncellendi');

    $consents = CommunicationConsent::query()->where('contact_id', $contact->id)->orderBy('id')->get();
    expect($consents)->toHaveCount(2)
        ->and($consents->firstOrFail()->status)->toBe('granted')
        ->and($consents->last()->status)->toBe('withdrawn')
        ->and($contact->refresh()->consent_email)->toBeFalse();

    Queue::fake();
    $second = app(BulkCompanyEmailService::class)->send(
        Company::query()->whereKey($company->id)->get(),
        $template->id,
        [],
        $actor,
    );
    expect($second->queuedCount)->toBe(0)
        ->and($second->consentRejectedCount)->toBe(1);
    Queue::assertNothingPushed();
});

it('imzasız abonelikten çıkma isteğini reddeder', function (): void {
    /** @var TestCase $this */
    [, , $contact] = emailTemplateFixture();

    $this->get(route('marketing.unsubscribe', ['contact' => $contact]))->assertForbidden();
    expect(app(MarketingUnsubscribeUrl::class)->for($contact))->toContain('signature=');
});
