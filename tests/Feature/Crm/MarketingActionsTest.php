<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Crm\Actions\ConvertLead;
use App\Domain\Crm\Actions\RecordInteraction;
use App\Domain\Crm\Actions\SaveContact;
use App\Domain\Crm\Actions\TransitionLead;
use App\Domain\Crm\Actions\WithdrawCallConsent;
use App\Domain\Crm\Models\CommunicationConsent;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Outbox\Models\OutboxMessage;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

/** @return array{actor: User, officer: User, company: Company, contact: Contact, lead: Lead, version: ProgramVersion} */
function marketingActionFixture(string $statusCode = 'proposal_sent', string $suffix = 'ana'): array
{
    $actor = User::factory()->create(['email' => "pazarlama-aksiyon-{$suffix}@example.invalid"]);
    $actor->assignRole('Pazarlama');
    $officer = User::factory()->create(['email' => "yetkili-aksiyon-{$suffix}@example.invalid"]);
    $officer->assignRole('Şirket Yetkilisi');
    $company = Company::query()->create(['legal_name' => "Kurgusal Pazarlama {$suffix}", 'city' => 'Ankara', 'source' => 'test']);
    $contact = Contact::query()->create([
        'company_id' => $company->id,
        'full_name' => 'Kurgusal İrtibat',
        'data_source' => 'form',
        'phone' => '+90 000 000 00 00',
        'consent_call' => true,
        'do_not_call' => false,
        'is_primary' => true,
    ]);
    CommunicationConsent::query()->create([
        'contact_id' => $contact->id,
        'channel' => 'call',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'explicit_consent',
        'source' => 'form',
        'effective_from' => now()->subMonths(2),
        'recorded_by' => $actor->id,
    ]);
    $status = Status::query()->where('type', 'lead')->where('code', $statusCode)->sole();
    $version = ProgramVersion::query()->firstOrFail();
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $actor->id,
        'interested_program_version_id' => $version->id,
        'status_id' => $status->id,
        'next_call_at' => $statusCode === 'callback' ? now()->addDay() : null,
    ]);
    StatusHistory::query()->create([
        'lead_id' => $lead->id,
        'status_id' => $status->id,
        'status_label_snapshot' => $status->label,
        'workflow_revision_id' => WorkflowRevision::query()->firstOrFail()->id,
        'entered_at' => now()->subDay(),
        'changed_by' => $actor->id,
    ]);

    return compact('actor', 'officer', 'company', 'contact', 'lead', 'version');
}

it('ret kaydını izin defterine ekler ve önceki satırı değiştirmez', function (): void {
    $fixture = marketingActionFixture(suffix: 'ret');
    $original = CommunicationConsent::query()->create([
        'contact_id' => $fixture['contact']->id,
        'channel' => 'call',
        'purpose' => 'marketing',
        'status' => 'granted',
        'legal_basis' => 'explicit_consent',
        'source' => 'form',
        'effective_from' => now()->subMonth(),
        'recorded_by' => $fixture['actor']->id,
    ]);
    $originalId = $original->id;
    $originalCreatedAt = $original->created_at->toDateTimeString();

    app(WithdrawCallConsent::class)->handle($fixture['contact']->id, $fixture['actor']->id);

    expect(CommunicationConsent::query()->where('contact_id', $fixture['contact']->id)->count())->toBe(3)
        ->and($original->fresh()?->id)->toBe($originalId)
        ->and($original->fresh()?->status)->toBe('granted')
        ->and($original->fresh()?->created_at?->toDateTimeString())->toBe($originalCreatedAt)
        ->and($fixture['contact']->refresh()->do_not_call)->toBeTrue()
        ->and($fixture['contact']->consent_call)->toBeFalse();
});

it('geri çekilmiş izinde giden pazarlama aramasını doğrudan action seviyesinde reddeder', function (): void {
    $fixture = marketingActionFixture(suffix: 'servis-ret');
    app(WithdrawCallConsent::class)->handle($fixture['contact']->id, $fixture['actor']->id);

    expect(fn () => app(RecordInteraction::class)->forLead(
        $fixture['lead']->id,
        $fixture['actor']->id,
        'call',
        Carbon::now(),
        'contacted',
        'Kaydedilmemesi gereken kurgusal arama',
    ))->toThrow(ValidationException::class, 'Giden pazarlama araması reddedildi')
        ->and(Interaction::query()->where('lead_id', $fixture['lead']->id)->exists())->toBeFalse();
});

it('izinli giden aramayı ve ret sonrasındaki gelen aramayı ayrı bağlamla kaydeder', function (): void {
    $fixture = marketingActionFixture(suffix: 'arama-baglam');
    $action = app(RecordInteraction::class);

    $outbound = $action->forLead($fixture['lead']->id, $fixture['actor']->id, 'call', Carbon::now(), 'contacted', null);
    app(WithdrawCallConsent::class)->handle($fixture['contact']->id, $fixture['actor']->id);
    $inbound = $action->forInboundLeadCall($fixture['lead']->id, $fixture['actor']->id, Carbon::now(), 'contacted', 'Kişi kendisi aradı.');

    expect($outbound->direction)->toBe('outbound')
        ->and($outbound->purpose)->toBe('marketing')
        ->and($inbound->direction)->toBe('inbound')
        ->and($inbound->purpose)->toBe('marketing')
        ->and(Interaction::query()->where('lead_id', $fixture['lead']->id)->count())->toBe(2);
});

it('kişiyi sistem kaynağıyla oluşturur ve boş iç kaydı veritabanında reddeder', function (): void {
    $fixture = marketingActionFixture(suffix: 'kaynak');

    $contact = app(SaveContact::class)->create(
        $fixture['company']->id,
        $fixture['actor']->id,
        'Kurgusal Yeni Kişi',
        email: 'yeni-kisi@example.invalid',
        emailConsent: true,
    );

    expect($contact->data_source)->toBe('other')
        ->and($contact->consent_email)->toBeTrue()
        ->and(CommunicationConsent::query()
            ->where('contact_id', $contact->id)
            ->where('channel', 'email')
            ->where('purpose', 'marketing')
            ->where('status', 'granted')
            ->exists())->toBeTrue()
        ->and(fn () => Contact::query()->create([
            'company_id' => $fixture['company']->id,
            'full_name' => 'Kurgusal Kaynaksız Kişi',
            'data_source' => '',
        ]))->toThrow(QueryException::class, 'contacts_data_source_not_blank');
});

it('callback ve lost hedeflerinin zorunlu alanlarını veri tabanından okuyarak doğrular', function (): void {
    $callbackFixture = marketingActionFixture('called', 'callback');
    $callback = Status::query()->where('type', 'lead')->where('code', 'callback')->sole();
    $lostFixture = marketingActionFixture('interested', 'lost');
    $lost = Status::query()->where('type', 'lead')->where('code', 'lost')->sole();

    expect(fn () => app(TransitionLead::class)->handle($callbackFixture['lead']->id, $callback->id, $callbackFixture['actor']->id))
        ->toThrow(ValidationException::class, 'tekrar arama tarihi zorunludur')
        ->and(fn () => app(TransitionLead::class)->handle($lostFixture['lead']->id, $lost->id, $lostFixture['actor']->id))
        ->toThrow(ValidationException::class, 'kayıp nedeni zorunludur');

    expect(fn () => DB::transaction(fn () => $callbackFixture['lead']->update(['status_id' => $callback->id])))
        ->toThrow(QueryException::class, 'next_call_at is required')
        ->and(fn () => DB::transaction(fn () => $lostFixture['lead']->update(['status_id' => $lost->id])))
        ->toThrow(QueryException::class, 'lost_reason is required');
});

it('beş görüşmeyi fırsattan ayrı satırlar olarak kaydeder ve statüyü değiştirmez', function (): void {
    $fixture = marketingActionFixture('interested', 'gorusme');
    $statusId = $fixture['lead']->status_id;
    $action = app(RecordInteraction::class);

    foreach (range(1, 5) as $index) {
        $action->forLead($fixture['lead']->id, $fixture['actor']->id, 'call', Carbon::now()->addMinutes($index), 'contacted', "Kurgusal not {$index}");
    }

    expect(Interaction::query()->where('lead_id', $fixture['lead']->id)->count())->toBe(5)
        ->and($fixture['lead']->refresh()->status_id)->toBe($statusId);
});

it('iş alındı dönüşüm zincirinin tüm çıktılarını tek tek üretir', function (): void {
    $fixture = marketingActionFixture(suffix: 'donusum');
    $won = Status::query()->where('type', 'lead')->where('converts_to_deal', true)->sole();
    $initial = Status::query()->where('type', 'deal')->where('is_initial', true)->sole();

    $dealId = app(TransitionLead::class)->handle(
        $fixture['lead']->id,
        $won->id,
        $fixture['actor']->id,
        programVersionId: $fixture['version']->id,
    );
    $deal = Deal::query()->findOrFail($dealId);

    expect($deal->program_version_id)->toBe($fixture['version']->id)
        ->and($deal->workflow_snapshot)->toBe($fixture['version']->workflow_snapshot)
        ->and($deal->status_id)->toBe($initial->id)
        ->and($fixture['lead']->refresh()->status_id)->toBe($won->id)
        ->and($fixture['lead']->converted_deal_id)->toBe($deal->id)
        ->and(DealDocument::query()->where('deal_id', $deal->id)->count())->toBeGreaterThan(0)
        ->and(StatusHistory::query()->where('deal_id', $deal->id)->where('status_id', $initial->id)->exists())->toBeTrue()
        ->and(Notification::query()->where('deal_id', $deal->id)->where('user_id', $fixture['officer']->id)->where('type', 'deal.assignment_pending')->exists())->toBeTrue()
        ->and(Activity::query()->where('lead_id', $fixture['lead']->id)->where('action', 'lead.converted')->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('event_name', 'lead.converted')->exists())->toBeTrue();
});

it('checklist üretimi hata verirse dönüşümün tamamını geri alır', function (): void {
    $fixture = marketingActionFixture(suffix: 'atomik');
    $won = Status::query()->where('type', 'lead')->where('converts_to_deal', true)->sole();
    $before = Deal::query()->count();
    app()->instance(ChecklistGeneratorContract::class, new class implements ChecklistGeneratorContract
    {
        public function generate(int $dealId, int $actorId): never
        {
            DealDocument::query()->create([
                'deal_id' => $dealId,
                'source_program_version_id' => Deal::query()->findOrFail($dealId)->program_version_id,
                'name_snapshot' => 'Kurgusal yarım belge',
                'required_snapshot' => true,
                'status' => 'to_request',
            ]);

            throw new RuntimeException('Kurgusal checklist hatası');
        }
    });

    expect(fn () => app(ConvertLead::class)->handle($fixture['lead']->id, $won->id, $fixture['version']->id, $fixture['actor']->id))
        ->toThrow(RuntimeException::class, 'Kurgusal checklist hatası');

    expect(Deal::query()->count())->toBe($before)
        ->and($fixture['lead']->refresh()->converted_deal_id)->toBeNull()
        ->and($fixture['lead']->status->code)->toBe('proposal_sent')
        ->and(DealDocument::query()->where('name_snapshot', 'Kurgusal yarım belge')->exists())->toBeFalse();
});

it('aynı fırsatın ikinci kez dönüştürülmesini reddeder', function (): void {
    $fixture = marketingActionFixture(suffix: 'tekil');
    $won = Status::query()->where('type', 'lead')->where('converts_to_deal', true)->sole();
    $converter = app(ConvertLead::class);
    $converter->handle($fixture['lead']->id, $won->id, $fixture['version']->id, $fixture['actor']->id);

    expect(fn () => $converter->handle($fixture['lead']->id, $won->id, $fixture['version']->id, $fixture['actor']->id))
        ->toThrow(ValidationException::class, 'daha önce dosyaya dönüştürülmüş')
        ->and(Deal::query()->where('company_id', $fixture['company']->id)->count())->toBe(1);
});
