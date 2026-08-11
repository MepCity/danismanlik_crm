<?php

declare(strict_types=1);

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts deletion of users referenced by teams', function (): void {
    $manager = User::factory()->create(['email' => 'manager-fk@example.invalid']);

    Team::query()->create([
        'name' => 'Kısıt Ekibi',
        'manager_id' => $manager->id,
    ]);

    expect(fn () => $manager->delete())->toThrow(QueryException::class);
});

it('rejects duplicate team memberships', function (): void {
    $manager = User::factory()->create(['email' => 'manager-unique@example.invalid']);
    $member = User::factory()->create(['email' => 'member-unique@example.invalid']);
    $team = Team::query()->create([
        'name' => 'Tekil Üyelik Ekibi',
        'manager_id' => $manager->id,
    ]);

    $membership = [
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => 'member',
    ];

    TeamMember::query()->create($membership);

    expect(fn () => TeamMember::query()->create($membership))->toThrow(QueryException::class);
});

it('rejects updates to role permission history', function (): void {
    $actor = User::factory()->create(['email' => 'actor-history@example.invalid']);
    $history = RolePermissionHistory::query()->create([
        'subject_type' => 'user',
        'subject_id' => $actor->id,
        'change_type' => 'permission_assigned',
        'new_value' => ['permission' => 'system.users.manage'],
        'changed_by' => $actor->id,
        'reason' => 'Yetki atama kaydı',
    ]);

    expect(fn () => $history->update(['reason' => 'Değiştirilemez']))
        ->toThrow(QueryException::class, 'append-only');
});
