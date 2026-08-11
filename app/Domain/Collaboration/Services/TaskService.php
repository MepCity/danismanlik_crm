<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Task;
use App\Models\User;
use App\Support\Audit\ActorSource;
use App\Support\Collaboration\SubjectModelResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class TaskService
{
    public function __construct(
        private ActivityRecorder $activities,
        private SubjectModelResolver $subjects,
        private CollaborationTransaction $transactions,
    ) {}

    public function create(
        User $actor,
        SubjectReference $subject,
        User $assignee,
        string $title,
        Carbon $dueAt,
        ?Carbon $remindAt = null,
        ?string $description = null,
    ): Task {
        Gate::forUser($actor)->authorize('create', Task::class);
        $subjectModel = $this->subjects->resolve($subject);
        Gate::forUser($actor)->authorize('view', $subjectModel);
        Gate::forUser($assignee)->authorize('view', $subjectModel);
        $this->validate($title, $dueAt, $remindAt);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $subject, $assignee, $title, $dueAt, $remindAt, $description): Task {
            $task = Task::query()->create([
                ...$subject->columns(),
                'assigned_to' => $assignee->id,
                'created_by' => $actor->id,
                'title' => trim($title),
                'description' => $description,
                'due_at' => $dueAt,
                'remind_at' => $remindAt,
            ]);
            $this->record('task.created', $task, $actor, ['assignee' => ['id' => $assignee->id, 'name' => $assignee->name]]);

            return $task;
        });
    }

    public function assign(User $actor, Task $task, User $assignee): Task
    {
        Gate::forUser($actor)->authorize('update', $task);
        Gate::forUser($assignee)->authorize('view', $this->subjects->resolve($this->referenceFor($task)));

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $task, $assignee): Task {
            $from = $task->assignee;
            $task->update(['assigned_to' => $assignee->id]);
            $this->record('task.assigned', $task, $actor, [
                'from_assignee' => ['id' => $from->id, 'name' => $from->name],
                'to_assignee' => ['id' => $assignee->id, 'name' => $assignee->name],
            ]);

            return $task->refresh();
        });
    }

    public function complete(User $actor, Task $task): Task
    {
        Gate::forUser($actor)->authorize('update', $task);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $task): Task {
            $task->update(['completed_at' => now()]);
            $this->record('task.completed', $task, $actor);

            return $task->refresh();
        });
    }

    public function reopen(User $actor, Task $task): Task
    {
        Gate::forUser($actor)->authorize('update', $task);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $task): Task {
            $task->update(['completed_at' => null, 'reminder_sent_at' => null]);
            $this->record('task.reopened', $task, $actor);

            return $task->refresh();
        });
    }

    /** @param array<string, mixed> $payload */
    private function record(string $action, Task $task, User $actor, array $payload = []): void
    {
        $this->activities->record($action, [
            'task' => ['id' => $task->id, 'title' => $task->title],
            ...$payload,
        ], $actor->id, $task->lead_id, $task->deal_id, $task->deal_document_id, defaultSource: 'user');
    }

    private function referenceFor(Task $task): SubjectReference
    {
        foreach (CollaborationSubjectType::cases() as $type) {
            $id = $task->getAttribute($type->column());
            if ($id !== null) {
                return new SubjectReference($type, (int) $id);
            }
        }

        throw ValidationException::withMessages(['subject' => trans('collaboration.validation.subject')]);
    }

    private function validate(string $title, Carbon $dueAt, ?Carbon $remindAt): void
    {
        if (trim($title) === '') {
            throw ValidationException::withMessages(['title' => trans('collaboration.validation.task_title')]);
        }

        if ($remindAt !== null && $remindAt->isAfter($dueAt)) {
            throw ValidationException::withMessages(['remind_at' => trans('collaboration.validation.reminder_after_due')]);
        }
    }
}
