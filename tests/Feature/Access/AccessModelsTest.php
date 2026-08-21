<?php

declare(strict_types=1);

use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Models\Team;
use App\Domain\Access\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('keeps operation permissions separate from row scope defaults', function (): void {
    $user = User::factory()->create(['email' => 'scope@example.invalid']);
    $role = Role::query()->create([
        'name' => 'test-role',
        'guard_name' => 'web',
    ]);

    $user->assignRole($role);
    $role->refresh();

    expect($user->data_scope)->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($role->getAttribute('default_scope'))->toBe('none')
        ->and($user->hasRole('test-role'))->toBeTrue();
});

it('connects teams and users through typed memberships', function (): void {
    $manager = User::factory()->create(['email' => 'manager@example.invalid']);
    $member = User::factory()->create(['email' => 'member@example.invalid']);
    $team = Team::query()->create([
        'name' => 'Pilot Ekibi',
        'manager_id' => $manager->id,
    ]);

    TeamMember::query()->create([
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    expect($team->manager->is($manager))->toBeTrue()
        ->and($team->members->modelKeys())->toBe([$member->id])
        ->and($member->teams->modelKeys())->toBe([$team->id])
        ->and($member->teamMemberships)->toHaveCount(1)
        ->and($manager->managedTeams->modelKeys())->toBe([$team->id]);
});

it('casts append only access history values', function (): void {
    $administrator = User::factory()->create(['email' => 'admin@example.invalid']);
    $authorizer = User::factory()->create(['email' => 'authorizer@example.invalid']);

    $history = RolePermissionHistory::query()->create([
        'subject_type' => 'user',
        'subject_id' => $administrator->id,
        'change_type' => 'scope_changed',
        'old_value' => ['scope' => 'none'],
        'new_value' => ['scope' => 'all'],
        'changed_by' => $authorizer->id,
        'reason' => 'Onaylı kapsam değişikliği',
    ]);

    expect($history->old_value)->toBe(['scope' => 'none'])
        ->and($history->new_value)->toBe(['scope' => 'all'])
        ->and($history->changedBy->is($authorizer))->toBeTrue();
});
