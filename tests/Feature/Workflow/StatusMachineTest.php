<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\StatusHistory;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Models\WorkflowRevision;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Events\DomainEvent;
use App\Support\Outbox\DatabaseOutboxWriter;
use App\Support\Outbox\Models\OutboxMessage;
use App\Support\Outbox\OutboxWriter;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $transitionAttributes
 * @return array{actor: User, deal: Deal, from: Status, to: Status, transition: Transition, history: StatusHistory, revision: WorkflowRevision}
 */
function statusMachineDealFixture(array $transitionAttributes = []): array
{
    $actor = User::factory()->create([
        'email' => fake()->unique()->safeEmail().'.invalid',
        'data_scope' => 'own',
    ]);
    $permission = Permission::findOrCreate('deal.transition', 'web');
    $actor->givePermissionTo($permission);
    $company = Company::query()->create([
        'legal_name' => 'Kurgusal Statü Makinesi İşletmesi '.fake()->unique()->numerify('####'),
        'city' => 'Hatay',
    ]);
    $program = Program::query()->create([
        'name' => 'Kurgusal Statü Makinesi Programı '.fake()->unique()->numerify('####'),
        'institution' => 'other',
        'code' => fake()->unique()->bothify('SM-####-????'),
    ]);
    $version = $program->versions()->create(['call_period' => fake()->unique()->bothify('2099-????')]);
    $from = Status::query()->create([
        'code' => fake()->unique()->bothify('from_????'),
        'label' => 'Belgeler toplanıyor',
        'type' => 'deal',
        'color' => 'waiting',
    ]);
    $to = Status::query()->create([
        'code' => fake()->unique()->bothify('to_????'),
        'label' => 'Başvuru hazırlanıyor',
        'type' => 'deal',
        'color' => 'info',
    ]);
    $transition = Transition::query()->create([
        'from_status_id' => $from->id,
        'to_status_id' => $to->id,
        'required_permission' => 'deal.transition',
        ...$transitionAttributes,
    ]);
    $revision = WorkflowRevision::query()->create([
        'snapshot' => ['statuses' => [], 'transitions' => []],
        'effective_from' => now()->subMinute(),
        'changed_by' => $actor->id,
        'reason' => 'kurgusal statü makinesi testi',
    ]);
    $changedAt = now()->subHour();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => fake()->unique()->bothify('SM-D-########'),
        'status_id' => $from->id,
        'status_changed_at' => $changedAt,
        'opened_by_user_id' => $actor->id,
        'requested_amount' => '6000000.00',
    ]);
    $history = StatusHistory::query()->create([
        'deal_id' => $deal->id,
        'status_id' => $from->id,
        'status_label_snapshot' => $from->label,
        'workflow_revision_id' => $revision->id,
        'entered_at' => $changedAt,
        'changed_by' => $actor->id,
    ]);

    return compact('actor', 'deal', 'from', 'to', 'transition', 'history', 'revision');
}

/** @param array{actor: User, deal: Deal, to: Status} $fixture */
function runDealTransition(array $fixture): void
{
    app(StatusMachineContract::class)->transition(new StatusTransition(
        SubjectType::Deal,
        $fixture['deal']->id,
        $fixture['to']->id,
        $fixture['actor']->id,
        'kurgusal ilerleme gerekçesi',
    ));
}

it('applies every deal transition effect in one successful call', function (): void {
    Carbon::setTestNow('2099-01-02 10:00:00');
    $fixture = statusMachineDealFixture();
    $oldChangedAt = $fixture['deal']->status_changed_at;

    runDealTransition($fixture);

    $newHistory = StatusHistory::query()
        ->where('deal_id', $fixture['deal']->id)
        ->whereNull('exited_at')
        ->sole();
    $activity = Activity::query()->where('deal_id', $fixture['deal']->id)->sole();
    $outbox = OutboxMessage::query()->where('event_name', 'deal.status_changed')->sole();

    expect($fixture['history']->refresh()->exited_at)->not->toBeNull()
        ->and($newHistory->status_id)->toBe($fixture['to']->id)
        ->and($newHistory->status_label_snapshot)->toBe('Başvuru hazırlanıyor')
        ->and($newHistory->workflow_revision_id)->toBe($fixture['revision']->id)
        ->and($newHistory->transition_id)->toBe($fixture['transition']->id)
        ->and($newHistory->changed_by)->toBe($fixture['actor']->id)
        ->and($newHistory->reason)->toBe('kurgusal ilerleme gerekçesi')
        ->and($fixture['deal']->refresh()->status_id)->toBe($fixture['to']->id)
        ->and($fixture['deal']->status_changed_at->greaterThan($oldChangedAt))->toBeTrue()
        ->and($activity->action)->toBe('deal.status_changed')
        ->and($activity->source)->toBe('user')
        ->and($activity->payload)->toMatchArray([
            'from_status' => ['id' => $fixture['from']->id, 'label' => 'Belgeler toplanıyor'],
            'to_status' => ['id' => $fixture['to']->id, 'label' => 'Başvuru hazırlanıyor'],
        ])
        ->and($outbox->payload)->toMatchArray([
            'deal_id' => (string) $fixture['deal']->id,
            'from_status_id' => (string) $fixture['from']->id,
            'to_status_id' => (string) $fixture['to']->id,
        ]);
});

it('rejects a missing transition row without changing the subject', function (): void {
    $fixture = statusMachineDealFixture();
    $other = Status::query()->create([
        'code' => 'tanimsiz_hedef',
        'label' => 'Tanımsız hedef',
        'type' => 'deal',
        'color' => 'neutral',
    ]);

    $call = fn () => app(StatusMachineContract::class)->transition(new StatusTransition(
        SubjectType::Deal,
        $fixture['deal']->id,
        $other->id,
        $fixture['actor']->id,
    ));

    expect($call)->toThrow(StatusTransitionRejected::class, 'tanımlı bir geçiş yok')
        ->and($fixture['deal']->refresh()->status_id)->toBe($fixture['from']->id);
});

it('rejects an inactive transition', function (): void {
    $fixture = statusMachineDealFixture(['is_active' => false]);

    expect(fn () => runDealTransition($fixture))
        ->toThrow(StatusTransitionRejected::class, 'kullanım dışı');
});

it('rejects an actor without the configured permission', function (): void {
    $fixture = statusMachineDealFixture();
    $unauthorized = User::factory()->create(['email' => 'yetkisiz@example.invalid']);
    $fixture['actor'] = $unauthorized;

    expect(fn () => runDealTransition($fixture))
        ->toThrow(StatusTransitionRejected::class, 'deal.transition');
});

it('names every missing required document when the condition fails', function (): void {
    $condition = ['all' => [[
        'op' => 'all_in',
        'field' => 'deal.required_documents.status',
        'value' => ['accepted', 'not_required'],
    ]]];
    $fixture = statusMachineDealFixture(['condition' => $condition]);

    foreach ([
        ['Findeks Raporu', 'requested'],
        ['YMM Bildirim Formu', 'rejected'],
        ['Fizibilite Raporu', 'uploaded'],
    ] as [$name, $status]) {
        DealDocument::query()->create([
            'deal_id' => $fixture['deal']->id,
            'source_program_version_id' => $fixture['deal']->program_version_id,
            'name_snapshot' => $name,
            'required_snapshot' => true,
            'status' => $status,
        ]);
    }

    expect(fn () => runDealTransition($fixture))->toThrow(
        StatusTransitionRejected::class,
        '3 zorunlu evrak eksik: Findeks Raporu, YMM Bildirim Formu, Fizibilite Raporu',
    );
});

it('passes the condition when every required document is accepted or not required', function (): void {
    $condition = ['all' => [[
        'op' => 'all_in',
        'field' => 'deal.required_documents.status',
        'value' => ['accepted', 'not_required'],
    ]]];
    $fixture = statusMachineDealFixture(['condition' => $condition]);

    foreach ([['Kurgusal Kabul Belgesi', 'accepted'], ['Kurgusal Muaf Belge', 'not_required']] as [$name, $status]) {
        DealDocument::query()->create([
            'deal_id' => $fixture['deal']->id,
            'source_program_version_id' => $fixture['deal']->program_version_id,
            'name_snapshot' => $name,
            'required_snapshot' => true,
            'status' => $status,
        ]);
    }

    runDealTransition($fixture);

    expect($fixture['deal']->refresh()->status_id)->toBe($fixture['to']->id);
});

it('rejects every exit from a terminal status even if a transition row exists', function (): void {
    $fixture = statusMachineDealFixture();
    $fixture['from']->update(['is_terminal' => true]);

    expect(fn () => runDealTransition($fixture))
        ->toThrow(StatusTransitionRejected::class, 'terminal statüsünden çıkış yapılamaz');
});

it('rolls back history subject activity and outbox when an effect fails midway', function (): void {
    $fixture = statusMachineDealFixture();
    $baselineHistory = StatusHistory::query()->count();
    $baselineActivities = Activity::query()->count();
    $baselineOutbox = OutboxMessage::query()->count();

    app()->instance(OutboxWriter::class, new class implements OutboxWriter
    {
        public function write(DomainEvent $event): void
        {
            (new DatabaseOutboxWriter)->write($event);

            throw new RuntimeException('Kurgusal effect hatası');
        }
    });

    expect(fn () => runDealTransition($fixture))->toThrow(RuntimeException::class, 'Kurgusal effect hatası')
        ->and($fixture['deal']->refresh()->status_id)->toBe($fixture['from']->id)
        ->and($fixture['history']->refresh()->exited_at)->toBeNull()
        ->and(StatusHistory::query()->count())->toBe($baselineHistory)
        ->and(Activity::query()->count())->toBe($baselineActivities)
        ->and(OutboxMessage::query()->count())->toBe($baselineOutbox);
});

it('runs the same status machine effects for a lead', function (): void {
    $actor = User::factory()->create([
        'email' => 'firsat-sorumlusu@example.invalid',
        'data_scope' => 'own',
    ]);
    $actor->givePermissionTo(Permission::findOrCreate('lead.manage', 'web'));
    $company = Company::query()->create(['legal_name' => 'Kurgusal Fırsat İşletmesi', 'city' => 'Ankara']);
    $from = Status::query()->create([
        'code' => 'firsat_yeni', 'label' => 'Yeni', 'type' => 'lead', 'color' => 'info',
    ]);
    $to = Status::query()->create([
        'code' => 'firsat_arandi', 'label' => 'Arandı', 'type' => 'lead', 'color' => 'neutral',
    ]);
    $transition = Transition::query()->create([
        'from_status_id' => $from->id,
        'to_status_id' => $to->id,
        'required_permission' => 'lead.manage',
    ]);
    $revision = WorkflowRevision::query()->create([
        'snapshot' => [],
        'effective_from' => now()->subMinute(),
        'changed_by' => $actor->id,
        'reason' => 'kurgusal fırsat akışı',
    ]);
    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'owner_user_id' => $actor->id,
        'status_id' => $from->id,
    ]);
    $oldHistory = StatusHistory::query()->create([
        'lead_id' => $lead->id,
        'status_id' => $from->id,
        'status_label_snapshot' => $from->label,
        'workflow_revision_id' => $revision->id,
        'entered_at' => now()->subHour(),
        'changed_by' => $actor->id,
    ]);

    app(StatusMachineContract::class)->transition(new StatusTransition(
        SubjectType::Lead,
        $lead->id,
        $to->id,
        $actor->id,
    ));

    expect($lead->refresh()->status_id)->toBe($to->id)
        ->and($oldHistory->refresh()->exited_at)->not->toBeNull()
        ->and(StatusHistory::query()->where('lead_id', $lead->id)->whereNull('exited_at')->sole()->transition_id)
        ->toBe($transition->id)
        ->and(Activity::query()->where('lead_id', $lead->id)->sole()->action)->toBe('lead.status_changed')
        ->and(OutboxMessage::query()->where('event_name', 'lead.status_changed')->count())->toBe(1);
});
