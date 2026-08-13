<?php

declare(strict_types=1);

use App\Domain\Access\Models\Team;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Filament\Pages\DealDetail;
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
    Storage::fake('s3');
    Queue::fake();
    (new DemoDataSeeder)->setContainer(app())->run();

    $marketing = User::query()->where('email', 'pazarlama@bizlife')->firstOrFail();

    expect(User::query()->whereIn('email', ['pazarlama@bizlife', 'proje@bizlife', 'admin@bizlife'])->count())->toBe(3)
        ->and($marketing->hasRole('Pazarlama'))->toBeTrue()
        ->and(Hash::check(DemoDataSeeder::PASSWORD, $marketing->password))->toBeTrue()
        ->and(Team::query()->count())->toBe(2)
        ->and(Deal::query()->count())->toBe(4)
        ->and(Deal::query()->whereHas('company', fn ($query) => $query->where('legal_name', 'Kurgusal Ufuk Teknoloji Ltd. Şti.'))->count())->toBe(2)
        ->and(Deal::query()->withCount('documents')->get()->pluck('documents_count')->all())
        ->each->toBe(7);
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
        ->set('activeTab', 'documents')
        ->assertSee("{$versioned->name_snapshot} — Sürüm 2 · Kabul edildi")
        ->assertSee("{$versioned->name_snapshot} — Sürüm 1 · Kabul edildi");
});
