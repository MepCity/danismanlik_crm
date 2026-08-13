<?php

declare(strict_types=1);

use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->setLocale('tr');
    config()->set('documents.disk', 's3');
    Storage::fake('s3');
    Queue::fake();
    app(DemoDataSeeder::class)->run();
});

it('onay metni olmadan demo temizliğini reddeder', function (): void {
    /** @var TestCase $this */
    $this->artisan('demo:purge')
        ->expectsOutputToContain('onayı olmadan çalışmaz')
        ->assertExitCode(2);

    expect(Deal::query()->where('reference_no', 'like', 'DEMO-%')->count())->toBe(4);
});

it('demo iş grafiğini silerken referans veriyi ve gerçek işaretli kaydı korur', function (): void {
    /** @var TestCase $this */
    $referenceCounts = [
        'statuses' => DB::table('statuses')->count(),
        'transitions' => DB::table('transitions')->count(),
        'roles' => Role::query()->count(),
        'permissions' => Permission::query()->count(),
        'programs' => Program::query()->count(),
        'versions' => ProgramVersion::query()->count(),
        'templates' => DocTemplate::query()->count(),
    ];
    $realUser = User::factory()->create(['email' => 'gercek-isaretli-olmayan@firma.invalid']);
    User::factory()->create(['email' => 'pazarlama@demo.invalid']);
    $storageKeys = File::query()->pluck('storage_key')->all();
    expect($storageKeys)->not->toBeEmpty();

    $this->artisan('demo:purge', ['--confirm' => 'DEMO VERİYİ TEMİZLE'])
        ->expectsOutputToContain('Demo temizlik tamamlandı')
        ->assertSuccessful();

    expect(DB::table('companies')->where('source', 'demo')->count())->toBe(0)
        ->and(Deal::query()->where('reference_no', 'like', 'DEMO-%')->count())->toBe(0)
        ->and(User::query()->whereIn('email', ['pazarlama@bizlife', 'proje@bizlife', 'admin@bizlife'])->count())->toBe(0)
        ->and(User::query()->where('email', 'like', '%@demo.invalid')->count())->toBe(0)
        ->and(User::query()->whereKey($realUser->id)->exists())->toBeTrue()
        ->and(DB::table('statuses')->count())->toBe($referenceCounts['statuses'])
        ->and(DB::table('transitions')->count())->toBe($referenceCounts['transitions'])
        ->and(Role::query()->count())->toBe($referenceCounts['roles'])
        ->and(Permission::query()->count())->toBe($referenceCounts['permissions'])
        ->and(Program::query()->count())->toBe($referenceCounts['programs'])
        ->and(ProgramVersion::query()->count())->toBe($referenceCounts['versions'])
        ->and(DocTemplate::query()->count())->toBe($referenceCounts['templates']);

    foreach ($storageKeys as $key) {
        Storage::disk('s3')->assertMissing($key);
    }
});

it('demo hesabı demo olmayan dosyaya bağlıysa temizliği kırmızıya düşürür', function (): void {
    /** @var TestCase $this */
    $demoUser = User::query()->where('email', 'pazarlama@bizlife')->sole();
    $realCompany = DB::table('companies')->insertGetId([
        'legal_name' => 'Kurgusal Korunan İşletme', 'city' => '06', 'source' => 'manual',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('deals')->insert([
        'company_id' => $realCompany,
        'program_version_id' => ProgramVersion::query()->firstOrFail()->id,
        'reference_no' => 'KORUNAN-001',
        'status_id' => DB::table('statuses')->where('type', 'deal')->where('is_initial', true)->value('id'),
        'status_changed_at' => now(),
        'opened_by_user_id' => $demoUser->id,
        'priority' => 'normal',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('demo:purge', ['--confirm' => 'DEMO VERİYİ TEMİZLE'])
        ->expectsOutputToContain('demo olmayan iş verisine bağlı')
        ->assertFailed();

    expect(User::query()->whereKey($demoUser->id)->exists())->toBeTrue();
});
