<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Services\TransitionPathResolverContract;
use App\Domain\Program\Models\Program;
use App\Models\User;
use App\Support\Workflow\SubjectType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
});

test('transition path resolver deterministically selects lower sort order and id among equal length paths', function (): void {
    $actor = User::factory()->create(['data_scope' => 'all']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme A']);
    $program = Program::query()->create(['name' => 'P1', 'institution' => 'other', 'code' => 'P1-01']);
    $version = $program->versions()->create(['call_period' => '2099-01']);

    $start = Status::query()->create(['code' => 's_start', 'label' => 'Başlangıç', 'type' => 'lead', 'color' => 'neutral', 'sort_order' => 10]);
    $branchA = Status::query()->create(['code' => 's_branch_a', 'label' => 'Dal A', 'type' => 'lead', 'color' => 'info', 'sort_order' => 20]);
    $branchB = Status::query()->create(['code' => 's_branch_b', 'label' => 'Dal B', 'type' => 'lead', 'color' => 'info', 'sort_order' => 30]);
    $target = Status::query()->create(['code' => 's_target', 'label' => 'Hedef', 'type' => 'lead', 'color' => 'success', 'sort_order' => 40]);

    Transition::query()->create(['from_status_id' => $start->id, 'to_status_id' => $branchB->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $start->id, 'to_status_id' => $branchA->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $branchA->id, 'to_status_id' => $target->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $branchB->id, 'to_status_id' => $target->id, 'is_active' => true]);

    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $start->id,
        'owner_user_id' => $actor->id,
    ]);

    $resolver = app(TransitionPathResolverContract::class);
    $path = $resolver->findShortestPath(SubjectType::Lead, $lead->id, $target->id, $actor->id);

    // Dal A has sort_order 20 < Dal B (sort_order 30), so path must go via Branch A
    expect($path)->toBe([$start->id, $branchA->id, $target->id]);
});

test('transition path resolver bypasses shorter path if actor lacks required permission', function (): void {
    $actor = User::factory()->create(['data_scope' => 'own']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme B']);
    $program = Program::query()->create(['name' => 'P2', 'institution' => 'other', 'code' => 'P2-01']);
    $version = $program->versions()->create(['call_period' => '2099-02']);

    $perm = Permission::findOrCreate('special.lead.transition', 'web');

    $start = Status::query()->create(['code' => 's2_start', 'label' => 'Başlangıç 2', 'type' => 'lead', 'color' => 'neutral', 'sort_order' => 10]);
    $directTarget = Status::query()->create(['code' => 's2_target', 'label' => 'Hedef 2', 'type' => 'lead', 'color' => 'success', 'sort_order' => 50]);
    $intermediate = Status::query()->create(['code' => 's2_inter', 'label' => 'Ara Statü', 'type' => 'lead', 'color' => 'info', 'sort_order' => 20]);

    // Short path (start -> target) requires special permission actor does NOT have
    Transition::query()->create([
        'from_status_id' => $start->id,
        'to_status_id' => $directTarget->id,
        'required_permission' => 'special.lead.transition',
        'is_active' => true,
    ]);

    // Longer path (start -> intermediate -> target) has no permission requirement
    Transition::query()->create([
        'from_status_id' => $start->id,
        'to_status_id' => $intermediate->id,
        'is_active' => true,
    ]);
    Transition::query()->create([
        'from_status_id' => $intermediate->id,
        'to_status_id' => $directTarget->id,
        'is_active' => true,
    ]);

    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $start->id,
        'owner_user_id' => $actor->id,
    ]);

    $resolver = app(TransitionPathResolverContract::class);
    $path = $resolver->findShortestPath(SubjectType::Lead, $lead->id, $directTarget->id, $actor->id);

    expect($path)->toBe([$start->id, $intermediate->id, $directTarget->id]);
});

test('transition path resolver bypasses shorter path if condition is not satisfied', function (): void {
    $actor = User::factory()->create(['data_scope' => 'all']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme C']);
    $program = Program::query()->create(['name' => 'P3', 'institution' => 'other', 'code' => 'P3-01']);
    $version = $program->versions()->create(['call_period' => '2099-03']);

    $start = Status::query()->create(['code' => 's3_start', 'label' => 'Başlangıç 3', 'type' => 'deal', 'color' => 'neutral', 'sort_order' => 10]);
    $target = Status::query()->create(['code' => 's3_target', 'label' => 'Hedef 3', 'type' => 'deal', 'color' => 'success', 'sort_order' => 50]);
    $alt = Status::query()->create(['code' => 's3_alt', 'label' => 'Alternatif', 'type' => 'deal', 'color' => 'info', 'sort_order' => 20]);

    // Direct transition requires requested_amount > 10,000,000
    Transition::query()->create([
        'from_status_id' => $start->id,
        'to_status_id' => $target->id,
        'condition' => [
            'all' => [
                [
                    'field' => 'deal.requested_amount',
                    'op' => 'gt',
                    'value' => '10000000.00',
                ],
            ],
        ],
        'is_active' => true,
    ]);

    // Alt path (start -> alt -> target) has no condition
    Transition::query()->create(['from_status_id' => $start->id, 'to_status_id' => $alt->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $alt->id, 'to_status_id' => $target->id, 'is_active' => true]);

    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-TEST-3001',
        'status_id' => $start->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
        'requested_amount' => '5000000.00', // Only 5M, so condition fails
    ]);

    $resolver = app(TransitionPathResolverContract::class);
    $path = $resolver->findShortestPath(SubjectType::Deal, $deal->id, $target->id, $actor->id);

    expect($path)->toBe([$start->id, $alt->id, $target->id]);
});

test('transition path resolver navigates cyclic graph without infinite loop', function (): void {
    $actor = User::factory()->create(['data_scope' => 'all']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme D']);
    $program = Program::query()->create(['name' => 'P4', 'institution' => 'other', 'code' => 'P4-01']);
    $version = $program->versions()->create(['call_period' => '2099-04']);

    $node1 = Status::query()->create(['code' => 's4_1', 'label' => 'Düğüm 1', 'type' => 'lead', 'color' => 'neutral', 'sort_order' => 10]);
    $node2 = Status::query()->create(['code' => 's4_2', 'label' => 'Düğüm 2', 'type' => 'lead', 'color' => 'info', 'sort_order' => 20]);
    $node3 = Status::query()->create(['code' => 's4_3', 'label' => 'Düğüm 3', 'type' => 'lead', 'color' => 'waiting', 'sort_order' => 30]);
    $target = Status::query()->create(['code' => 's4_target', 'label' => 'Hedef 4', 'type' => 'lead', 'color' => 'success', 'sort_order' => 40]);

    // Cycle between 1, 2, 3
    Transition::query()->create(['from_status_id' => $node1->id, 'to_status_id' => $node2->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $node2->id, 'to_status_id' => $node3->id, 'is_active' => true]);
    Transition::query()->create(['from_status_id' => $node3->id, 'to_status_id' => $node1->id, 'is_active' => true]); // Cycle back
    Transition::query()->create(['from_status_id' => $node3->id, 'to_status_id' => $target->id, 'is_active' => true]);

    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $node1->id,
        'owner_user_id' => $actor->id,
    ]);

    $resolver = app(TransitionPathResolverContract::class);
    $path = $resolver->findShortestPath(SubjectType::Lead, $lead->id, $target->id, $actor->id);

    expect($path)->toBe([$node1->id, $node2->id, $node3->id, $target->id]);
});

test('transition path resolver throws localized StatusTransitionRejected when no path exists', function (): void {
    $actor = User::factory()->create(['data_scope' => 'all']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme E']);
    $program = Program::query()->create(['name' => 'P5', 'institution' => 'other', 'code' => 'P5-01']);
    $version = $program->versions()->create(['call_period' => '2099-05']);

    $isolatedA = Status::query()->create(['code' => 's5_a', 'label' => 'İzole A', 'type' => 'lead', 'color' => 'neutral']);
    $isolatedB = Status::query()->create(['code' => 's5_b', 'label' => 'İzole B', 'type' => 'lead', 'color' => 'success']);

    $lead = Lead::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'status_id' => $isolatedA->id,
        'owner_user_id' => $actor->id,
    ]);

    $resolver = app(TransitionPathResolverContract::class);

    expect(fn () => $resolver->findShortestPath(SubjectType::Lead, $lead->id, $isolatedB->id, $actor->id))
        ->toThrow(StatusTransitionRejected::class, '"İzole A" statüsünden "İzole B" statüsüne uygun bir geçiş yolu bulunamadı.');
});

test('transition path resolver finds deterministic transition for assignment with tie breaking', function (): void {
    $actor = User::factory()->create(['data_scope' => 'all']);
    $perm = Permission::findOrCreate('deal.assign', 'web');
    $actor->givePermissionTo($perm);

    $company = Company::query()->create(['legal_name' => 'Kurgusal İşletme F']);
    $program = Program::query()->create(['name' => 'P6', 'institution' => 'other', 'code' => 'P6-01']);
    $version = $program->versions()->create(['call_period' => '2099-06']);

    $start = Status::query()->create(['code' => 's6_start', 'label' => 'Atama Bekliyor', 'type' => 'deal', 'color' => 'neutral']);
    $pmA = Status::query()->create(['code' => 's6_pma', 'label' => 'PM Ekip A', 'type' => 'deal', 'color' => 'info', 'sort_order' => 15]);
    $pmB = Status::query()->create(['code' => 's6_pmb', 'label' => 'PM Ekip B', 'type' => 'deal', 'color' => 'info', 'sort_order' => 25]);

    Transition::query()->create(['from_status_id' => $start->id, 'to_status_id' => $pmB->id, 'required_permission' => 'deal.assign', 'is_active' => true]);
    $chosenTransition = Transition::query()->create(['from_status_id' => $start->id, 'to_status_id' => $pmA->id, 'required_permission' => 'deal.assign', 'is_active' => true]);

    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $version->id,
        'reference_no' => 'D-TEST-6001',
        'status_id' => $start->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
    ]);

    $resolver = app(TransitionPathResolverContract::class);
    $transition = $resolver->findDeterministicTransition(SubjectType::Deal, $deal->id, $actor->id, 'deal.assign');

    expect($transition->id)->toBe($chosenTransition->id)
        ->and($transition->to_status_id)->toBe($pmA->id);
});
