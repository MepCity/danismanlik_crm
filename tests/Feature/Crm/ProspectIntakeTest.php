<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Actions\CreateProspectIntake;
use App\Domain\Crm\Actions\TransitionLead;
use App\Domain\Crm\DTOs\ProspectIntakeData;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    app(ReferenceDataSeeder::class)->run();
});

function intakeData(string $suffix, int $programVersionId, int $targetStatusId, ?int $companyId = null, bool $withTaskDueAt = true): ProspectIntakeData
{
    return new ProspectIntakeData(
        companyId: $companyId,
        companyName: $companyId === null ? "Kurgusal Akış {$suffix} AŞ" : null,
        taxNumber: null,
        city: $companyId === null ? 'İstanbul' : null,
        source: 'phone',
        contactId: null,
        contactName: "Kurgusal Yetkili {$suffix}",
        contactTitle: 'Genel Müdür',
        phone: '+90 000 000 00 00',
        email: "yetkili-{$suffix}@firma.invalid",
        callConsent: true,
        disclosureDate: now()->toDateString(),
        programVersionId: $programVersionId,
        targetStatusId: $targetStatusId,
        calledAt: Carbon::now()->subMinute(),
        callDirection: 'outbound',
        outcome: 'interested',
        callNote: 'Program kapsamı, ihtiyaç ve sonraki adım ayrıntılı biçimde görüşüldü.',
        companyComment: 'Firmanın satın alma kararını genel müdür veriyor.',
        taskTitle: 'Tekrar iletişim kur',
        taskDueAt: $withTaskDueAt ? Carbon::now()->addDay() : null,
        taskRemindAt: $withTaskDueAt ? Carbon::now()->addHours(20) : null,
    );
}

it('tek işlemde firma kişi fırsat görüşme yorum görev ve aktör izini oluşturur', function (): void {
    $actor = User::factory()->create(['name' => 'Kurgusal Pazarlamacı', 'email' => 'akis-pazarlama@example.invalid']);
    $actor->assignRole('Pazarlama');
    $version = ProgramVersion::query()->firstOrFail();
    $interested = Status::query()->where('type', 'lead')->where('code', 'interested')->sole();

    $result = app(CreateProspectIntake::class)->handle($actor, intakeData('tek', $version->id, $interested->id));

    expect($result->company->legal_name)->toBe('Kurgusal Akış tek AŞ')
        ->and($result->contact->title)->toBe('Genel Müdür')
        ->and($result->lead->primary_contact_id)->toBe($result->contact->id)
        ->and($result->lead->status_id)->toBe($interested->id)
        ->and($result->interaction->contact_id)->toBe($result->contact->id)
        ->and($result->interaction->note)->toContain('sonraki adım')
        ->and(Comment::query()->where('company_id', $result->company->id)->count())->toBe(1)
        ->and(Task::query()->where('lead_id', $result->lead->id)->count())->toBe(1)
        ->and(Activity::query()->where('company_id', $result->company->id)->where('action', 'company.created')->exists())->toBeTrue()
        ->and(Activity::query()->where('lead_id', $result->lead->id)->where('action', 'interaction.recorded')->exists())->toBeTrue();

    $companyAudit = DB::table('audit_log')->where('table_name', 'companies')->where('row_id', $result->company->id)->where('operation', 'INSERT')->sole();
    expect((int) $companyAudit->actor_id)->toBe($actor->id)->and($companyAudit->source)->toBe('user');
});

it('son tarih vermeden takip görevi oluşturur', function (): void {
    $actor = User::factory()->create(['email' => 'tarihsiz-gorev@example.invalid']);
    $actor->assignRole('Pazarlama');
    $version = ProgramVersion::query()->firstOrFail();
    $interested = Status::query()->where('type', 'lead')->where('code', 'interested')->sole();

    $result = app(CreateProspectIntake::class)->handle(
        $actor,
        intakeData('tarihsiz', $version->id, $interested->id, withTaskDueAt: false),
    );

    $task = Task::query()->where('lead_id', $result->lead->id)->sole();

    expect($task->title)->toBe('Tekrar iletişim kur')
        ->and($task->due_at)->toBeNull()
        ->and($task->remind_at)->toBeNull();
});

it('fırsat ve görüşmeye başka firmanın kişisinin bağlanmasını veritabanında reddeder', function (): void {
    $actor = User::factory()->create();
    $first = Company::query()->create(['legal_name' => 'Kurgusal Birinci Firma', 'city' => 'Ankara']);
    $second = Company::query()->create(['legal_name' => 'Kurgusal İkinci Firma', 'city' => 'İzmir']);
    $foreignContact = Contact::query()->create(['company_id' => $second->id, 'full_name' => 'Kurgusal Yabancı Kişi', 'data_source' => 'other']);
    $status = Status::query()->where('type', 'lead')->where('is_initial', true)->sole();

    expect(fn () => DB::transaction(fn () => Lead::query()->create([
        'company_id' => $first->id,
        'primary_contact_id' => $foreignContact->id,
        'owner_user_id' => $actor->id,
        'status_id' => $status->id,
    ])))->toThrow(QueryException::class, 'contact must belong to the subject company');

    $lead = Lead::query()->create([
        'company_id' => $first->id,
        'owner_user_id' => $actor->id,
        'status_id' => $status->id,
    ]);
    expect(fn () => DB::transaction(fn () => Interaction::query()->create([
        'lead_id' => $lead->id,
        'contact_id' => $foreignContact->id,
        'user_id' => $actor->id,
        'type' => 'call',
        'direction' => 'outbound',
        'purpose' => 'marketing',
        'occurred_at' => now(),
    ])))->toThrow(QueryException::class, 'contact must belong to the subject company');
});

it('aynı firmada aynı program için bağımsız iki proje ve iki evrak listesi oluşturur', function (): void {
    $actor = User::factory()->create(['email' => 'coklu-proje@example.invalid']);
    $actor->assignRole('Pazarlama');
    $officer = User::factory()->create(['email' => 'coklu-yetkili@example.invalid']);
    $officer->assignRole('Şirket Yetkilisi');
    $version = ProgramVersion::query()->firstOrFail();
    $interested = Status::query()->where('type', 'lead')->where('code', 'interested')->sole();
    $first = app(CreateProspectIntake::class)->handle($actor, intakeData('bir', $version->id, $interested->id));
    $second = app(CreateProspectIntake::class)->handle($actor, intakeData('iki', $version->id, $interested->id, $first->company->id));
    $proposal = Status::query()->where('type', 'lead')->where('code', 'proposal_sent')->sole();
    $won = Status::query()->where('type', 'lead')->where('converts_to_deal', true)->sole();
    $transitions = app(TransitionLead::class);

    foreach ([$first->lead, $second->lead] as $lead) {
        $transitions->handle($lead->id, $proposal->id, $actor->id);
        $transitions->handle($lead->id, $won->id, $actor->id, programVersionId: $version->id);
    }

    $deals = Deal::query()->where('company_id', $first->company->id)->orderBy('id')->get();
    expect($deals)->toHaveCount(2)
        ->and($deals[0]->reference_no)->not->toBe($deals[1]->reference_no)
        ->and(DealDocument::query()->where('deal_id', $deals[0]->id)->count())->toBeGreaterThan(0)
        ->and(DealDocument::query()->where('deal_id', $deals[1]->id)->count())->toBeGreaterThan(0);

    $pmOne = User::factory()->create(['email' => 'pm-bir@example.invalid']);
    $pmOne->assignRole('Proje Yöneticisi');
    $pmTwo = User::factory()->create(['email' => 'pm-iki@example.invalid']);
    $pmTwo->assignRole('Proje Yöneticisi');
    foreach ([[$deals[0], $pmOne], [$deals[1], $pmTwo]] as [$deal, $pm]) {
        $target = Transition::query()->where('from_status_id', $deal->status_id)->where('required_permission', 'deal.assign')->sole();
        app(AssignDeal::class)->handle($deal->id, $pm->id, $target->to_status_id, $officer->id);
    }

    expect(app(ScopedQuery::class)->contains($pmOne, $deals[0], 'view'))->toBeTrue()
        ->and(app(ScopedQuery::class)->contains($pmOne, $deals[1], 'view'))->toBeFalse()
        ->and(app(ScopedQuery::class)->contains($pmTwo, $deals[1], 'view'))->toBeTrue();
});
