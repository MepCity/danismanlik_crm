<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Filament\Pages\DealDetail;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('production ortamında yazmadan önce demo seederı reddeder', function (): void {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $seeder = (new DemoDataSeeder)->setContainer(app());

        expect(fn () => $seeder->run())
            ->toThrow(RuntimeException::class, 'Demo verileri production ortamında yüklenemez.');
        expect(User::query()->count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }
});

it('tamamen kurgusal demo grafiğini ve sabit hesapları kurar', function (): void {
    /** @var TestCase $this */
    Storage::fake('s3');
    Queue::fake();
    (new DemoDataSeeder)->setContainer(app())->run();

    $marketing = User::query()->where('email', 'pazarlama@bizlife')->firstOrFail();
    $admin = User::query()->where('email', 'admin@bizlife')->firstOrFail();
    $deals = Deal::query()->withCount('documents')->orderBy('reference_no')->get();
    $documentCounts = $deals->pluck('documents_count', 'reference_no')->all();
    $snapshots = $deals->pluck('workflow_snapshot', 'reference_no');
    /** @var array{name: string, description: string, steps: list<array{type: string, attention_note?: string|null, is_completed: bool}>} $firstSnapshot */
    $firstSnapshot = $snapshots['DEMO-2026-001'];
    /** @var array{name: string, description: string, steps: list<array{type: string, attention_note?: string|null, is_completed: bool}>} $newSnapshot */
    $newSnapshot = $snapshots['DEMO-2026-003'];

    expect(User::query()->whereIn('email', ['pazarlama@bizlife', 'proje@bizlife', 'admin@bizlife'])->count())->toBe(3)
        ->and($marketing->hasRole('Pazarlama'))->toBeTrue()
        ->and($admin->hasAllRoles(['Şirket Yetkilisi', 'Sistem Yöneticisi']))->toBeTrue()
        ->and($admin->data_scope)->toBe('all')
        ->and($admin->can('system.users'))->toBeTrue()
        ->and(Hash::check(DemoDataSeeder::PASSWORD, $marketing->password))->toBeTrue()
        ->and(Team::query()->count())->toBe(2)
        ->and(Company::query()->count())->toBe(30)
        ->and(Deal::query()->count())->toBe(4)
        ->and(Deal::query()->whereHas('company', fn ($query) => $query->where('legal_name', 'Kurgusal Ufuk Teknoloji Ltd. Şti.'))->count())->toBe(2)
        ->and($documentCounts)->toBe([
            'DEMO-2026-001' => 7,
            'DEMO-2026-002' => 6,
            'DEMO-2026-003' => 5,
            'DEMO-2026-004' => 6,
        ])
        ->and($snapshots->filter()->count())->toBe(4)
        ->and($firstSnapshot['name'])->not->toBeEmpty()
        ->and($firstSnapshot['description'])->not->toBeEmpty()
        ->and($firstSnapshot['steps'])->toHaveCount(4)
        ->and(collect($firstSnapshot['steps'])->pluck('type')->unique()->sort()->values()->all())
        ->toBe(['action', 'decision', 'waiting'])
        ->and(collect($firstSnapshot['steps'])->contains(fn (array $step): bool => filled($step['attention_note'] ?? null)))
        ->toBeTrue()
        ->and(collect($firstSnapshot['steps'])->pluck('is_completed')->all())->toBe([true, false, false, false])
        ->and(collect($newSnapshot['steps'])->pluck('is_completed')->all())->toBe([false, false, false, false]);

    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs($admin)->get(UserResource::getUrl())->assertOk();
});

it('demo evraklarını gerçek yükleme akışıyla sürümlendirir ve panelde gösterir', function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    Storage::fake('s3');
    Queue::fake();
    config()->set('documents.disk', 's3');
    (new DemoDataSeeder)->setContainer(app())->run();

    $documentsWithFiles = DealDocument::query()
        ->whereIn('status', ['uploaded', 'accepted'])
        ->withCount('files')
        ->get();
    $versioned = DealDocument::query()
        ->where('status', 'accepted')
        ->whereHas('deal', fn ($query) => $query->where('reference_no', 'DEMO-2026-001'))
        ->whereHas('files', fn ($query) => $query->where('version_no', 2))
        ->with(['deal', 'files'])
        ->sole();
    $rejected = DealDocument::query()
        ->where('status', 'rejected')
        ->whereHas('deal', fn ($query) => $query->where('reference_no', 'DEMO-2026-001'))
        ->sole();

    expect($documentsWithFiles)->not->toBeEmpty()
        ->and($documentsWithFiles->pluck('files_count')->min())->toBeGreaterThan(0)
        ->and($versioned->files->pluck('version_no')->sort()->values()->all())->toBe([1, 2])
        ->and($rejected->notes)->toBe('Kurgusal örnekte imza sayfası eksik bırakıldı.')
        ->and(File::query()->count())->toBeGreaterThan($documentsWithFiles->count());

    Filament::setCurrentPanel(Filament::getPanel('operations'));
    Auth::login(User::query()->where('email', 'admin@bizlife')->sole());

    Livewire::test(DealDetail::class, ['deal' => $versioned->deal_id])
        ->assertSee('KOSGEB başvuru rehberi')
        ->assertSee('Tamamlandı')
        ->assertSee('Şimdiki adım')
        ->set('activeTab', 'documents')
        ->assertSee("{$versioned->name_snapshot} — Sürüm 2 · Kabul edildi")
        ->assertSee("{$versioned->name_snapshot} — Sürüm 1 · Kabul edildi");
});
