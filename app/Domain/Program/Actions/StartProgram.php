<?php

declare(strict_types=1);

namespace App\Domain\Program\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class StartProgram
{
    public function __construct(
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
    ) {}

    public function execute(Program $program, User $actor): Program
    {
        Gate::forUser($actor)->authorize('update', $program);
        $version = $program->versions()
            ->with(['serviceWorkflow.steps', 'docTemplates'])
            ->latest('id')
            ->first();

        $errors = [];
        if ($version?->service_workflow_id === null || $version->serviceWorkflow === null || ! $version->serviceWorkflow->is_active) {
            $errors['service_workflow_id'][] = trans('management.program_start.validation.workflow');
        }
        if ($version === null || blank($version->call_period)) {
            $errors['call_period'][] = trans('management.program_start.validation.period');
        }
        if ($version === null || $version->docTemplates->where('is_active', true)->isEmpty()) {
            $errors['documents'][] = trans('management.program_start.validation.documents');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        assert($version instanceof ProgramVersion);
        $workflow = $version->serviceWorkflow;

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($program, $version, $workflow, $actor): Program {
            $program->update(['is_active' => true]);
            $version->update(['is_active' => true]);
            $this->activities->record(
                action: 'program.started',
                payload: [
                    'program' => ['id' => $program->id, 'name' => $program->name],
                    'call_period' => $version->call_period,
                    'workflow' => ['id' => $workflow->id, 'name' => $workflow->name],
                ],
                actorId: $actor->id,
                defaultSource: 'user',
                programId: $program->id,
            );

            return $program->refresh()->load('latestVersion');
        });
    }
}
