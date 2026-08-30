<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Collaboration\Services\TaskService;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use App\Support\Collaboration\SubjectModelResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class SubjectTasks extends Component
{
    public string $subjectType;

    public int $subjectId;

    public string $title = '';

    public ?int $assigneeId = null;

    public string $dueAt = '';

    public string $description = '';

    public function mount(string $subjectType, int $subjectId): void
    {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->authorizeSubject();
    }

    public function create(TaskService $tasks): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'assigneeId' => ['required', 'integer'],
            'dueAt' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $actor = Auth::user();
        abort_unless($actor !== null, 403);
        $assignee = $this->assignees()->firstWhere('id', $this->assigneeId);
        abort_unless($assignee instanceof User, 404);
        $tasks->create($actor, $this->subject(), $assignee, $this->title, filled($this->dueAt) ? Carbon::parse($this->dueAt) : null, description: $this->description ?: null);
        $this->reset('title', 'assigneeId', 'dueAt', 'description');
    }

    public function toggle(int $taskId, TaskService $tasks): void
    {
        $task = Task::query()->findOrFail($taskId);
        abort_unless((int) $task->getAttribute($this->subject()->type->column()) === $this->subjectId, 404);
        $actor = Auth::user();
        abort_unless($actor !== null, 403);
        $task->completed_at === null ? $tasks->complete($actor, $task) : $tasks->reopen($actor, $task);
    }

    public function render(): View
    {
        $this->authorizeSubject();
        $user = Auth::user();
        abort_unless($user !== null, 403);

        // Seeing the subject is not permission to read its task list: the policy
        // decides whether the viewer may list tasks, ScopedQuery narrows the rows.
        $tasks = Gate::forUser($user)->allows('viewAny', Task::class)
            ? app(ScopedQuery::class)->apply(Task::query(), $user, 'viewAny')
                ->where($this->subject()->type->column(), $this->subjectId)
                ->with(['assignee', 'creator'])
                ->orderByRaw('completed_at NULLS FIRST')
                ->orderBy('due_at')
                ->get()
            : Task::query()->whereRaw('1 = 0')->get();

        return view('livewire.subject-tasks', ['tasks' => $tasks, 'assignees' => $this->assignees()]);
    }

    /** @return Collection<int, User> */
    private function assignees(): Collection
    {
        $subject = $this->authorizeSubject();

        return User::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(static fn (User $user): bool => Gate::forUser($user)->allows('view', $subject));
    }

    private function authorizeSubject(): Model
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $subject = app(SubjectModelResolver::class)->resolve($this->subject());
        Gate::forUser($user)->authorize('view', $subject);

        return $subject;
    }

    private function subject(): SubjectReference
    {
        return new SubjectReference(CollaborationSubjectType::from($this->subjectType), $this->subjectId);
    }
}
