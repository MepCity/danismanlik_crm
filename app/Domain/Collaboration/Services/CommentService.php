<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Notification;
use App\Models\User;
use App\Support\Audit\ActorSource;
use App\Support\Collaboration\SubjectModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CommentService
{
    public function __construct(
        private MentionParser $mentions,
        private SubjectModelResolver $subjects,
        private CollaborationTransaction $transactions,
    ) {}

    public function create(
        User $actor,
        SubjectReference $subject,
        string $body,
        ?int $parentId = null,
    ): Comment {
        Gate::forUser($actor)->authorize('create', Comment::class);
        $subjectModel = $this->subjects->resolve($subject);
        Gate::forUser($actor)->authorize('view', $subjectModel);
        $this->validate($body);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $subject, $subjectModel, $body, $parentId): Comment {
            if ($parentId !== null) {
                $parent = Comment::query()->findOrFail($parentId);
                Gate::forUser($actor)->authorize('view', $parent);

                if (array_filter($parent->only(['company_id', 'lead_id', 'deal_id', 'deal_document_id'])) !== array_filter($subject->columns())) {
                    throw ValidationException::withMessages(['parent_id' => trans('collaboration.validation.parent_subject')]);
                }
            }

            $mentionIds = $this->visibleMentionIds($body, $subjectModel);
            $comment = Comment::query()->create([
                ...$subject->columns(),
                'user_id' => $actor->id,
                'body' => trim($body),
                'mentions' => $mentionIds,
                'visibility' => 'internal',
                'parent_id' => $parentId,
            ]);

            $this->notifyMentions($mentionIds, $actor, $subject);

            return $comment;
        });
    }

    public function edit(User $actor, Comment $comment, string $body): Comment
    {
        Gate::forUser($actor)->authorize('update', $comment);
        $this->validate($body);
        $subject = $this->referenceFor($comment);
        $subjectModel = $this->subjects->resolve($subject);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($actor, $comment, $body, $subject, $subjectModel): Comment {
            $previousMentions = $comment->mentions;
            $mentionIds = $this->visibleMentionIds($body, $subjectModel);
            $comment->update([
                'body' => trim($body),
                'mentions' => $mentionIds,
                'edited_at' => now(),
            ]);
            $this->notifyMentions(array_values(array_diff($mentionIds, $previousMentions)), $actor, $subject);

            return $comment->refresh();
        });
    }

    /** @return list<int> */
    private function visibleMentionIds(string $body, Model $subject): array
    {
        return User::query()
            ->whereKey($this->mentions->userIds($body))
            ->where('is_active', true)
            ->get()
            ->filter(static fn (User $user): bool => Gate::forUser($user)->allows('view', $subject))
            ->modelKeys();
    }

    /** @param list<int> $userIds */
    private function notifyMentions(array $userIds, User $actor, SubjectReference $subject): void
    {
        foreach ($userIds as $userId) {
            Notification::query()->create([
                'user_id' => $userId,
                'type' => 'comment.mentioned',
                ...$subject->columns(),
                'title' => trans('collaboration.notifications.mention_title'),
                'body' => trans('collaboration.notifications.mention_body', ['user' => $actor->name]),
                'channel' => 'in_app',
            ]);
        }
    }

    private function referenceFor(Comment $comment): SubjectReference
    {
        foreach (CollaborationSubjectType::cases() as $type) {
            $id = $comment->getAttribute($type->column());
            if ($id !== null) {
                return new SubjectReference($type, (int) $id);
            }
        }

        throw new AuthorizationException;
    }

    private function validate(string $body): void
    {
        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => trans('collaboration.validation.body_required')]);
        }
    }
}
