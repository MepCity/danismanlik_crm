<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Activity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @param Closure(): mixed $callback */
function expectWp07bConstraintFailure(Closure $callback): void
{
    expect(fn () => DB::transaction($callback))->toThrow(QueryException::class);
}

it('keeps activities append-only', function (): void {
    ['actor' => $actor, 'deal' => $deal] = wp07bDealFixture();
    $activity = Activity::query()->create([
        'actor_id' => $actor->id,
        'deal_id' => $deal->id,
        'action' => 'deal.assigned',
        'payload' => ['assignee_label' => 'Kurgusal Kullanıcı'],
        'source' => 'user',
    ]);

    expect(fn () => DB::transaction(
        fn () => DB::table('activities')->where('id', $activity->id)->update(['action' => 'changed']),
    ))->toThrow(QueryException::class, 'activities is append-only');

    expect(fn () => DB::transaction(
        fn () => DB::table('activities')->where('id', $activity->id)->delete(),
    ))->toThrow(QueryException::class, 'activities is append-only');
});

it('requires exactly one subject for activities comments and tasks', function (): void {
    ['actor' => $actor, 'deal' => $deal, 'document' => $document] = wp07bDealFixture();
    $now = now();

    expectWp07bConstraintFailure(fn () => DB::table('activities')->insert([
        'actor_id' => $actor->id,
        'action' => 'invalid.zero',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'source' => 'user',
        'created_at' => $now,
    ]));
    expectWp07bConstraintFailure(fn () => DB::table('activities')->insert([
        'actor_id' => $actor->id,
        'deal_id' => $deal->id,
        'deal_document_id' => $document->id,
        'action' => 'invalid.two',
        'payload' => json_encode([], JSON_THROW_ON_ERROR),
        'source' => 'user',
        'created_at' => $now,
    ]));

    expectWp07bConstraintFailure(fn () => DB::table('comments')->insert([
        'user_id' => $actor->id,
        'body' => 'Kurgusal sıfır özne',
        'mentions' => json_encode([], JSON_THROW_ON_ERROR),
        'visibility' => 'internal',
        'created_at' => $now,
        'updated_at' => $now,
    ]));
    expectWp07bConstraintFailure(fn () => DB::table('comments')->insert([
        'deal_id' => $deal->id,
        'deal_document_id' => $document->id,
        'user_id' => $actor->id,
        'body' => 'Kurgusal iki özne',
        'mentions' => json_encode([], JSON_THROW_ON_ERROR),
        'visibility' => 'internal',
        'created_at' => $now,
        'updated_at' => $now,
    ]));

    expectWp07bConstraintFailure(fn () => DB::table('tasks')->insert([
        'assigned_to' => $actor->id,
        'created_by' => $actor->id,
        'title' => 'Kurgusal sıfır özne',
        'due_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]));
    expectWp07bConstraintFailure(fn () => DB::table('tasks')->insert([
        'deal_id' => $deal->id,
        'deal_document_id' => $document->id,
        'assigned_to' => $actor->id,
        'created_by' => $actor->id,
        'title' => 'Kurgusal iki özne',
        'due_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]));
});

it('stores comment edits in the audit log', function (): void {
    ['actor' => $actor, 'deal' => $deal] = wp07bDealFixture();
    $commentId = DB::table('comments')->insertGetId([
        'deal_id' => $deal->id,
        'user_id' => $actor->id,
        'body' => 'Kurgusal ilk yorum',
        'mentions' => json_encode([], JSON_THROW_ON_ERROR),
        'visibility' => 'internal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('comments')->where('id', $commentId)->update([
        'body' => 'Kurgusal düzenlenmiş yorum',
        'edited_at' => now(),
    ]);

    $audit = DB::table('audit_log')
        ->where('table_name', 'comments')
        ->where('row_id', $commentId)
        ->where('operation', 'UPDATE')
        ->latest('id')
        ->first();

    expect(json_decode($audit->old_data, true, flags: JSON_THROW_ON_ERROR))
        ->toHaveKey('body', 'Kurgusal ilk yorum')
        ->and(json_decode($audit->new_data, true, flags: JSON_THROW_ON_ERROR))
        ->toHaveKey('body', 'Kurgusal düzenlenmiş yorum');
});
