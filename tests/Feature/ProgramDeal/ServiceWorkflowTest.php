<?php

declare(strict_types=1);

use App\Domain\Program\Actions\SaveProgramConfiguration;
use App\Domain\Program\Actions\SaveServiceWorkflow;
use App\Domain\Program\Models\ProgramVersion;
use App\Domain\Program\Models\ServiceWorkflow;
use App\Domain\Program\Models\ServiceWorkflowStep;
use App\Filament\Resources\Programs\ProgramResource;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('yönetici sıralı ve tipli bir hizmet iş akışı oluşturur', function (): void {
    $actor = User::factory()->create(['email' => 'akis-yoneticisi@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    $workflow = app(SaveServiceWorkflow::class)->execute(null, [
        'name' => 'Kurgusal kurul değerlendirme rehberi',
        'description' => 'Kurgusal hizmetler için ortak akış.',
        'is_active' => true,
        'steps' => [
            ['type' => 'action', 'title' => 'Evrakları doğrula', 'guidance' => 'Gelen evrakların güncelliğini kontrol et.', 'attention_note' => 'İmza kontrolünü unutma.'],
            ['type' => 'waiting', 'title' => 'Kurul sonucunu bekle', 'guidance' => 'Kurum bildirimlerini günlük izle.', 'attention_note' => null],
        ],
    ], $actor);

    expect($workflow->steps)->toHaveCount(2)
        ->and($workflow->steps->pluck('type')->all())->toBe(['action', 'waiting'])
        ->and($workflow->steps->pluck('sort_order')->all())->toBe([0, 1])
        ->and(ServiceWorkflowResource::getNavigationLabel())->toBe('İş Akışları')
        ->and(ProgramResource::getNavigationLabel())->toBe('Hizmetler');
});

it('akıştan çıkarılan aşamayı silmeden pasifleştirir', function (): void {
    $actor = User::factory()->create(['email' => 'akis-duzenleyen@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $workflow = ServiceWorkflow::query()->with('steps')->firstOrFail();
    $kept = $workflow->steps->firstOrFail();
    $removed = $workflow->steps->skip(1)->firstOrFail();

    app(SaveServiceWorkflow::class)->execute($workflow, [
        'name' => $workflow->name,
        'description' => $workflow->description,
        'is_active' => true,
        'steps' => [[
            'id' => $kept->id,
            'type' => 'decision',
            'title' => $kept->title,
            'guidance' => $kept->guidance,
            'attention_note' => $kept->attention_note,
        ]],
    ], $actor);

    expect($kept->refresh()->type)->toBe('decision')
        ->and($removed->refresh()->is_active)->toBeFalse()
        ->and(ServiceWorkflowStep::query()->whereKey($removed->id)->exists())->toBeTrue();
});

it('iş akışını veritabanında silinmez ve denetlenebilir tutar', function (): void {
    $workflow = ServiceWorkflow::query()->with('steps')->firstOrFail();
    $step = $workflow->steps->firstOrFail();

    expect(fn () => DB::transaction(fn () => $step->delete()))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => $workflow->delete()))->toThrow(QueryException::class);

    $workflow->update(['description' => 'Denetim tetikleyicisi doğrulaması']);
    $step->update(['attention_note' => 'Kontrol notu']);

    expect(DB::table('audit_log')->where('table_name', 'service_workflows')->where('row_id', $workflow->id)->where('operation', 'UPDATE')->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where('table_name', 'service_workflow_steps')->where('row_id', $step->id)->where('operation', 'UPDATE')->exists())->toBeTrue();
});

it('hizmete bağlanan akışı dönem üzerinde anlık görüntü olarak saklar', function (): void {
    $actor = User::factory()->create(['email' => 'hizmet-akis-aktor@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $workflow = ServiceWorkflow::query()->with('steps')->firstOrFail();

    $program = app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Kurgusal Kapasite Hizmeti',
        'institution' => 'kosgeb',
        'service_workflow_id' => $workflow->id,
        'is_active' => true,
        'call_period' => '2032 dönemi',
        'documents' => [[
            'name' => 'Kurgusal Başvuru Belgesi',
            'is_required' => true,
            'accepted_formats' => ['pdf'],
        ]],
    ], $actor);

    $version = $program->latestVersion;
    $snapshotTitle = $version->workflow_snapshot['steps'][0]['title'];
    $workflow->steps->firstOrFail()->update(['title' => 'Sonradan değiştirilen aşama']);

    expect($version->refresh()->service_workflow_id)->toBe($workflow->id)
        ->and($version->workflow_snapshot['name'])->toBe($workflow->name)
        ->and($version->workflow_snapshot['steps'])->not->toBeEmpty()
        ->and($version->workflow_snapshot['steps'][0]['title'])->toBe($snapshotTitle)
        ->and($version->workflow_snapshot['steps'][0]['title'])->not->toBe('Sonradan değiştirilen aşama');
});

it('aşamasız akışı ve yetkisiz yönetimi sunucu tarafında reddeder', function (): void {
    $admin = User::factory()->create(['email' => 'akis-dogrulama@example.invalid']);
    $admin->assignRole('Sistem Yöneticisi');
    $marketer = User::factory()->create(['email' => 'akis-yetkisiz@example.invalid']);
    $marketer->assignRole('Pazarlama');

    expect(fn () => app(SaveServiceWorkflow::class)->execute(null, [
        'name' => 'Aşamasız akış', 'is_active' => true, 'steps' => [],
    ], $admin))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveServiceWorkflow::class)->execute(null, [
            'name' => 'Yetkisiz akış',
            'is_active' => true,
            'steps' => [['type' => 'action', 'title' => 'Adım', 'guidance' => 'Açıklama']],
        ], $marketer))->toThrow(AuthorizationException::class);
});

it('hizmet iş akışı olmadan yeni hizmet oluşturmayı reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'akissiz-hizmet@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    expect(fn () => app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Akışsız Kurgusal Hizmet',
        'institution' => 'kosgeb',
        'is_active' => true,
        'call_period' => '2033 dönemi',
        'documents' => [['name' => 'Belge', 'is_required' => true, 'accepted_formats' => ['pdf']]],
    ], $actor))->toThrow(ValidationException::class);

    expect(ProgramVersion::query()->where('call_period', '2033 dönemi')->exists())->toBeFalse();
});
