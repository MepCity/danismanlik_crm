<?php

declare(strict_types=1);

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Crm\Models\Company;
use App\Domain\Program\Actions\SaveProgramConfiguration;
use App\Domain\Program\Actions\StartProgram;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ServiceWorkflow;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('akışsız ve evraksız hizmeti başlatmayı eksik listesiyle reddeder', function (): void {
    $actor = User::factory()->create(['email' => 'hizmet-baslatma-engeli@example.invalid']);
    $actor->assignRole('Şirket Yetkilisi');
    $program = app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Kurgusal Eksik Hizmet',
        'institution' => 'kosgeb',
        'is_active' => false,
        'service_workflow_id' => null,
        'call_period' => null,
        'documents' => [],
    ], $actor);

    try {
        app(StartProgram::class)->execute($program, $actor);
        throw new RuntimeException('Eksik hizmet başlatma koruması çalışmadı.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['service_workflow_id', 'call_period', 'documents']);
    }

    expect($program->refresh()->is_active)->toBeFalse()
        ->and($program->latestVersion->is_active)->toBeFalse()
        ->and(DB::table('activities')->where('program_id', $program->id)->where('action', 'program.started')->exists())->toBeFalse();
});

it('hazır hizmeti başlatır ve aktivite ile denetim izi üretir', function (): void {
    $actor = User::factory()->create(['email' => 'hizmet-baslatan@example.invalid']);
    $actor->assignRole('Sistem Yöneticisi');
    $workflow = ServiceWorkflow::query()->firstOrFail();
    $program = app(SaveProgramConfiguration::class)->execute(null, [
        'name' => 'Kurgusal Yayınlanabilir Hizmet',
        'institution' => 'kosgeb',
        'is_active' => false,
        'service_workflow_id' => $workflow->id,
        'call_period' => '2034 kurgusal çağrısı',
        'documents' => [['name' => 'Kurgusal Başvuru Formu', 'is_required' => true, 'accepted_formats' => ['pdf']]],
    ], $actor);

    app(StartProgram::class)->execute($program, $actor);

    expect($program->refresh()->is_active)->toBeTrue()
        ->and($program->latestVersion->is_active)->toBeTrue()
        ->and(DB::table('activities')->where('program_id', $program->id)->where('action', 'program.started')->exists())->toBeTrue()
        ->and(DB::table('audit_log')->where('table_name', 'programs')->where('row_id', $program->id)->where('operation', 'UPDATE')->exists())->toBeTrue();
});

it('hizmete mevcut yorum bileşeninin domain servisiyle yorum yazar', function (): void {
    $actor = User::factory()->create(['email' => 'hizmet-yorumcusu@example.invalid']);
    $actor->assignRole('Şirket Yetkilisi');
    $program = Program::query()->firstOrFail();
    $company = Company::query()->create(['legal_name' => 'Kurgusal Çift Özne İşletmesi', 'city' => 'Ankara']);

    $comment = app(CommentService::class)->create(
        $actor,
        new SubjectReference(CollaborationSubjectType::Program, $program->id),
        'Kurgusal hizmet notu.',
    );

    expect($comment->program_id)->toBe($program->id)
        ->and($comment->deal_id)->toBeNull();

    expect(fn () => DB::transaction(fn () => DB::table('comments')->insert([
        'program_id' => $program->id,
        'company_id' => $company->id,
        'user_id' => $actor->id,
        'body' => 'İki özne yasak',
        'mentions' => json_encode([], JSON_THROW_ON_ERROR),
        'visibility' => 'internal',
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);
});
