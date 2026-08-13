<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\DTOs\TimelineItem;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Models\User;
use App\Support\Collaboration\SubjectModelResolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class TimelineQuery
{
    public function __construct(
        private ActivityTranslator $translator,
        private SubjectModelResolver $subjects,
    ) {}

    /** @return LengthAwarePaginator<int, TimelineItem> */
    public function paginate(
        User $viewer,
        SubjectReference $subject,
        ?string $filter = null,
        int $perPage = 25,
        int $page = 1,
    ): LengthAwarePaginator {
        Gate::forUser($viewer)->authorize('view', $this->subjects->resolve($subject));

        if (! in_array($filter, [null, 'status', 'document', 'comment'], true)) {
            throw ValidationException::withMessages(['filter' => trans('collaboration.validation.timeline_filter')]);
        }

        $union = $filter === 'comment'
            ? $this->comments($subject)
            : $this->activities($subject, $filter)->unionAll($this->comments($subject, $filter));

        $paginator = DB::query()
            ->fromSub($union, 'timeline')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection($paginator->getCollection()->map(function (object $row): TimelineItem {
            $payload = is_string($row->content) ? json_decode($row->content, true) : $row->content;
            $payload = is_array($payload) ? $payload : [];
            $isComment = $row->type === 'comment';

            return new TimelineItem(
                id: (int) $row->id,
                type: (string) $row->type,
                occurredAt: Carbon::parse($row->occurred_at),
                actor: $isComment
                    ? ((string) ($row->actor_name ?? trans('collaboration.activity.system')))
                    : $this->translator->actor($row->actor_name, (string) $row->source),
                sentence: $isComment
                    ? trans('collaboration.activity.comment', ['body' => (string) $row->content])
                    : $this->translator->sentence((string) $row->action, $payload, $row->subject_label),
            );
        }));

        return $paginator;
    }

    private function activities(SubjectReference $subject, ?string $filter): Builder
    {
        $query = DB::table('activities as event')
            ->leftJoin('users as actor', 'actor.id', '=', 'event.actor_id')
            ->leftJoin('deal_documents as document', 'document.id', '=', 'event.deal_document_id')
            ->select([
                'event.id',
                DB::raw("'activity'::text as type"),
                'event.action',
                DB::raw('event.payload::text as content'),
                'event.source',
                'event.created_at as occurred_at',
                'actor.name as actor_name',
                'document.name_snapshot as subject_label',
            ]);
        $this->subjectWhere($query, $subject, 'event', 'document');

        if ($filter === 'status') {
            $query->where('event.action', 'like', '%.status_changed');
        } elseif ($filter === 'document') {
            $query->whereNotNull('event.deal_document_id');
        }

        return $query;
    }

    private function comments(SubjectReference $subject, ?string $filter = null): Builder
    {
        $query = DB::table('comments as comment')
            ->join('users as actor', 'actor.id', '=', 'comment.user_id')
            ->leftJoin('deal_documents as document', 'document.id', '=', 'comment.deal_document_id')
            ->select([
                'comment.id',
                DB::raw("'comment'::text as type"),
                DB::raw('NULL::text as action'),
                'comment.body as content',
                DB::raw("'user'::text as source"),
                'comment.created_at as occurred_at',
                'actor.name as actor_name',
                'document.name_snapshot as subject_label',
            ]);
        $this->subjectWhere($query, $subject, 'comment', 'document');

        if ($filter !== null && $filter !== 'comment') {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function subjectWhere(Builder $query, SubjectReference $subject, string $table, string $document): void
    {
        if ($subject->type === CollaborationSubjectType::Company) {
            $query->where(function (Builder $query) use ($subject, $table, $document): void {
                $query->where($table.'.company_id', $subject->id)
                    ->orWhereIn($table.'.lead_id', function (Builder $query) use ($subject): void {
                        $query->from('leads')->select('id')->where('company_id', $subject->id);
                    })
                    ->orWhereIn($table.'.deal_id', function (Builder $query) use ($subject): void {
                        $query->from('deals')->select('id')->where('company_id', $subject->id);
                    })
                    ->orWhereIn($document.'.deal_id', function (Builder $query) use ($subject): void {
                        $query->from('deals')->select('id')->where('company_id', $subject->id);
                    });
            });

            return;
        }

        if ($subject->type === CollaborationSubjectType::Deal) {
            $query->where(function (Builder $query) use ($subject, $table, $document): void {
                $query->where($table.'.deal_id', $subject->id)
                    ->orWhere($document.'.deal_id', $subject->id);
            });

            return;
        }

        $query->where($table.'.'.$subject->type->column(), $subject->id);
    }
}
