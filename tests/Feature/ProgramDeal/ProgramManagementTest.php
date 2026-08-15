<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Program\Actions\CopyProgramVersion;
use App\Domain\Program\Actions\SaveProgramConfiguration;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('önceki sürümden şablonları bağımsız kopyalar ve açık dosyaları etkilemez', function (): void {
    $source = ProgramVersion::query()->with('docTemplates')->firstOrFail();
    $sourceTemplate = $source->docTemplates->firstOrFail();
    $actor = User::factory()->create(['email' => 'surum-kopya-aktor@example.invalid']);
    $company = Company::query()->create(['legal_name' => 'Kurgusal Sürüm İşletmesi', 'city' => 'İzmir']);
    $status = Status::query()->where('type', 'deal')->firstOrFail();
    $deal = Deal::query()->create([
        'company_id' => $company->id,
        'program_version_id' => $source->id,
        'reference_no' => 'WP15-SURUM-001',
        'status_id' => $status->id,
        'status_changed_at' => now(),
        'opened_by_user_id' => $actor->id,
    ]);
    $document = DealDocument::query()->create([
        'deal_id' => $deal->id,
        'source_doc_template_id' => $sourceTemplate->id,
        'source_program_version_id' => $source->id,
        'name_snapshot' => $sourceTemplate->name,
        'required_snapshot' => $sourceTemplate->is_required,
        'condition_snapshot' => $sourceTemplate->condition,
        'status' => 'to_request',
    ]);

    $target = app(CopyProgramVersion::class)->execute($source, [
        'call_period' => '2027 kurgusal çağrısı',
        'application_opens_at' => now()->addYear(),
        'application_closes_at' => now()->addYear()->addMonth(),
        'description' => 'Kurgusal kopyalama kanıtı.',
        'is_active' => true,
    ]);

    $copiedTemplate = $target->docTemplates->firstOrFail();
    $copiedTemplate->update(['name' => 'Kurgusal Değiştirilen Yeni Evrak']);

    expect($target->id)->not->toBe($source->id)
        ->and($target->docTemplates)->toHaveCount($source->docTemplates->count())
        ->and($copiedTemplate->id)->not->toBe($sourceTemplate->id)
        ->and($sourceTemplate->refresh()->name)->not->toBe('Kurgusal Değiştirilen Yeni Evrak')
        ->and($deal->refresh()->program_version_id)->toBe($source->id)
        ->and($document->refresh()->source_program_version_id)->toBe($source->id)
        ->and($document->name_snapshot)->toBe($sourceTemplate->name);
});

it('programı dönemi ve belge listesiyle tek işlemde oluşturur', function (): void {
    $actor = User::factory()->create(['email' => 'program-yonetici@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');

    $program = app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Kurgusal Verimlilik Programı',
        'institution' => 'kosgeb',
        'is_active' => true,
        'call_period' => '2028 başvuru dönemi',
        'application_opens_at' => '2028-01-15',
        'application_closes_at' => '2028-03-31',
        'description' => 'Kurgusal program açıklaması.',
        'documents' => [
            [
                'name' => 'Kurgusal Başvuru Formu',
                'description' => 'İmzalı form.',
                'is_required' => true,
                'accepted_formats' => ['pdf'],
                'validity_days' => null,
            ],
            [
                'name' => 'Kurgusal Bütçe Tablosu',
                'description' => null,
                'is_required' => false,
                'accepted_formats' => ['xlsx'],
                'validity_days' => 30,
            ],
        ],
    ], $actor);

    expect($program->code)->toBe('KURGUSAL-VERIMLILIK-PROGRAMI')
        ->and($program->versions)->toHaveCount(1)
        ->and($program->versions->firstOrFail()->call_period)->toBe('2028 başvuru dönemi')
        ->and($program->versions->firstOrFail()->docTemplates)->toHaveCount(2)
        ->and($program->versions->firstOrFail()->docTemplates->pluck('sort_order')->all())->toBe([0, 1]);
});

it('program düzenlenirken kaldırılan belgeyi silmeden pasifleştirir', function (): void {
    $actor = User::factory()->create(['email' => 'program-duzenleyen@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $program = Program::query()->with('latestVersion.docTemplates')->firstOrFail();
    $version = $program->latestVersion;
    $kept = $version->docTemplates->firstOrFail();
    $removed = $version->docTemplates->skip(1)->firstOrFail();
    $condition = $kept->condition;

    app(SaveProgramConfiguration::class)->execute($program, [
        'name' => $program->name,
        'institution' => $program->institution,
        'is_active' => true,
        'call_period' => $version->call_period,
        'application_opens_at' => $version->application_opens_at?->toDateString(),
        'application_closes_at' => $version->application_closes_at?->toDateString(),
        'description' => $version->description,
        'documents' => [[
            'id' => $kept->id,
            'name' => $kept->name.' güncel',
            'description' => $kept->description,
            'is_required' => $kept->is_required,
            'accepted_formats' => $kept->accepted_formats,
            'validity_days' => $kept->validity_days,
        ]],
    ], $actor);

    expect($kept->refresh()->is_active)->toBeTrue()
        ->and($kept->condition)->toBe($condition)
        ->and($removed->refresh()->is_active)->toBeFalse()
        ->and(DocTemplate::query()->whereKey($removed->id)->exists())->toBeTrue();
});

it('program yetkisi olmayan kullanıcının birleşik kaydını reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'program-yetkisiz@example.invalid']);
    $actor->assignRole('Pazarlama');
    $programCount = Program::query()->count();

    expect(fn () => app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Yetkisiz Kurgusal Program',
        'institution' => 'kosgeb',
        'is_active' => true,
        'call_period' => '2029 başvuru dönemi',
        'documents' => [[
            'name' => 'Yetkisiz Kurgusal Belge',
            'is_required' => true,
            'accepted_formats' => ['pdf'],
        ]],
    ], $actor))->toThrow(AuthorizationException::class);

    expect(Program::query()->count())->toBe($programCount);
});

it('belgesiz program tanımını açık doğrulama hatasıyla reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'program-belgesiz@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $programCount = Program::query()->count();

    try {
        app(SaveProgramConfiguration::class)->execute(null, [
            'name' => 'Belgesiz Kurgusal Program',
            'institution' => 'kosgeb',
            'is_active' => true,
            'call_period' => '2031 başvuru dönemi',
            'documents' => [],
        ], $actor);

        throw new RuntimeException('Belgesiz program doğrulaması çalışmadı.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['documents'])->toContain('En az bir gerekli belge ekleyin.');
    }

    expect(Program::query()->count())->toBe($programCount);
});
