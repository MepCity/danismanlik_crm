<?php

declare(strict_types=1);

use App\Domain\Access\Exceptions\BreakGlassRejected;
use App\Domain\Access\Models\RolePermissionHistory;
use App\Domain\Access\Services\BreakGlassService;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\File;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-11 12:00:00');
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array{admin: User, officer: User, deal: Deal, file: File} */
function breakGlassFixture(): array
{
    $admin = scopedUser('Sistem Yöneticisi', 'acil-admin');
    $officer = scopedUser('Şirket Yetkilisi', 'acil-yetkili');
    $graph = scopedWorkGraph(scopedUser('Pazarlama', 'acil-veri-sahibi'), 'BREAK');
    $deal = $graph['deal'];
    $file = $graph['file'];

    return compact('admin', 'officer', 'deal', 'file');
}

it('gerekçesiz süresiz geçmiş ve üst sınırı aşan talepleri reddeder', function (): void {
    $fixture = breakGlassFixture();
    $service = app(BreakGlassService::class);

    expect(fn () => $service->grant($fixture['admin'], $fixture['officer'], ' ', now()->addMinutes(15)))
        ->toThrow(BreakGlassRejected::class)
        ->and(fn () => $service->grant($fixture['admin'], $fixture['officer'], 'Acil bakım', null))
        ->toThrow(BreakGlassRejected::class)
        ->and(fn () => $service->grant($fixture['admin'], $fixture['officer'], 'Acil bakım', now()->subMinute()))
        ->toThrow(BreakGlassRejected::class)
        ->and(fn () => $service->grant($fixture['admin'], $fixture['officer'], 'Acil bakım', now()->addMinutes(61)))
        ->toThrow(BreakGlassRejected::class);
});

it('verildi ve kullanıldı olaylarını yazar, şirket yetkilisini bilgilendirir', function (): void {
    $fixture = breakGlassFixture();
    $grant = app(BreakGlassService::class)->grant(
        $fixture['admin'],
        $fixture['officer'],
        'Kurgusal üretim arızasını inceleme',
        now()->addMinutes(30),
    );

    $visible = app(ScopedQuery::class)
        ->apply(Deal::query(), $fixture['admin'], 'deal.view')
        ->whereKey($fixture['deal']->id)
        ->exists();

    expect($visible)->toBeTrue()
        ->and(Gate::forUser($fixture['admin'])->allows('download', $fixture['file']))->toBeTrue()
        ->and(RolePermissionHistory::query()->where('change_type', 'access.break_glass_granted')->exists())->toBeTrue()
        ->and(RolePermissionHistory::query()->where('change_type', 'access.break_glass_used')->exists())->toBeTrue()
        ->and(Notification::query()
            ->where('user_id', $fixture['officer']->id)
            ->where('type', 'access.break_glass_granted')->exists())->toBeTrue()
        ->and($grant->reason)->toBe('Kurgusal üretim arızasını inceleme');
});

it('iptalden sonra erişimi kapatır ve iptal olayını yazar', function (): void {
    $fixture = breakGlassFixture();
    $service = app(BreakGlassService::class);
    $grant = $service->grant($fixture['admin'], $fixture['officer'], 'Kurgusal acil inceleme', now()->addMinutes(30));

    $service->revoke($grant, $fixture['officer']);

    expect(app(ScopedQuery::class)->apply(Deal::query(), $fixture['admin'])->get())->toBeEmpty()
        ->and(Gate::forUser($fixture['admin'])->allows('download', $fixture['file']))->toBeFalse()
        ->and(RolePermissionHistory::query()->where('change_type', 'access.break_glass_revoked')->exists())->toBeTrue();
});

it('süre dolunca erişimi sorgu anında kapatır ve süre doldu olayını bir kez yazar', function (): void {
    $fixture = breakGlassFixture();
    app(BreakGlassService::class)->grant(
        $fixture['admin'],
        $fixture['officer'],
        'Kurgusal süreli inceleme',
        now()->addMinutes(15),
    );
    Carbon::setTestNow(now()->addMinutes(16));

    $scoper = app(ScopedQuery::class);
    expect($scoper->apply(Deal::query(), $fixture['admin'])->get())->toBeEmpty()
        ->and($scoper->apply(Deal::query(), $fixture['admin'])->get())->toBeEmpty()
        ->and(RolePermissionHistory::query()->where('change_type', 'access.break_glass_expired')->count())->toBe(1);
});
