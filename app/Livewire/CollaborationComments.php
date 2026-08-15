<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Services\CommentService;
use App\Models\User;
use App\Support\Collaboration\SubjectModelResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class CollaborationComments extends Component
{
    public string $subjectType;

    public int $subjectId;

    public string $body = '';

    public ?int $parentId = null;

    public ?int $editingId = null;

    public ?int $mentionUserId = null;

    public function mount(string $subjectType, int $subjectId): void
    {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->authorizeSubject();
    }

    public function addMention(): void
    {
        $candidate = $this->mentionCandidates()->firstWhere('id', $this->mentionUserId);
        abort_unless($candidate instanceof User, 404);
        $this->body = rtrim($this->body).' @['.$candidate->name.'](user:'.$candidate->id.') ';
        $this->mentionUserId = null;
    }

    public function startReply(int $commentId): void
    {
        $this->comment($commentId);
        $this->resetComposer();
        $this->parentId = $commentId;
    }

    public function startEdit(int $commentId): void
    {
        $comment = $this->comment($commentId);
        Gate::authorize('update', $comment);
        $this->body = $comment->body;
        $this->parentId = null;
        $this->editingId = $comment->id;
    }

    public function cancelComposer(): void
    {
        $this->resetComposer();
    }

    public function save(CommentService $comments): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);
        $actor = Auth::user();
        abort_unless($actor !== null, 403);

        if ($this->editingId !== null) {
            $comments->edit($actor, $this->comment($this->editingId), $this->body);
        } else {
            $comments->create($actor, $this->subject(), $this->body, $this->parentId);
        }

        $this->resetComposer();
        $this->dispatch('comments-updated');
    }

    public function render(): View
    {
        $this->authorizeSubject();
        $commentRows = Comment::query()
            ->where($this->subject()->type->column(), $this->subjectId)
            ->whereNull('parent_id')
            ->with(['replies' => fn ($query) => $query->with('user')->oldest(), 'user'])
            ->oldest()
            ->get();

        return view('livewire.collaboration-comments', [
            'comments' => $commentRows,
            'mentionCandidates' => $this->mentionCandidates(),
        ]);
    }

    /** @return Collection<int, User> */
    private function mentionCandidates(): Collection
    {
        $this->authorizeSubject();

        return User::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function authorizeSubject(): Model
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $subject = app(SubjectModelResolver::class)->resolve($this->subject());
        Gate::forUser($user)->authorize('view', $subject);

        return $subject;
    }

    private function comment(int $commentId): Comment
    {
        $comment = Comment::query()->findOrFail($commentId);
        abort_unless((int) $comment->getAttribute($this->subject()->type->column()) === $this->subjectId, 404);

        return $comment;
    }

    private function subject(): SubjectReference
    {
        return new SubjectReference(CollaborationSubjectType::from($this->subjectType), $this->subjectId);
    }

    private function resetComposer(): void
    {
        $this->reset('body', 'parentId', 'editingId', 'mentionUserId');
        $this->resetValidation();
    }
}
