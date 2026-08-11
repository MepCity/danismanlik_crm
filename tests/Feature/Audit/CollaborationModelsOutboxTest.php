<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Notification as CollaborationNotification;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Events\LeadConverted;
use App\Support\Outbox\DatabaseOutboxWriter;
use App\Support\Outbox\Models\OutboxMessage;
use App\Support\Outbox\OutboxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('connects collaboration models and casts structured values', function (): void {
    ['actor' => $actor, 'deal' => $deal] = wp07bDealFixture();
    $comment = Comment::query()->create([
        'deal_id' => $deal->id,
        'user_id' => $actor->id,
        'body' => 'Kurgusal model yorumu',
        'mentions' => [$actor->id],
        'visibility' => 'internal',
    ]);
    $task = Task::query()->create([
        'deal_id' => $deal->id,
        'assigned_to' => $actor->id,
        'created_by' => $actor->id,
        'title' => 'Kurgusal görev',
        'due_at' => now()->addDay(),
    ]);
    $notification = CollaborationNotification::query()->create([
        'user_id' => $actor->id,
        'type' => 'task.assigned',
        'deal_id' => $deal->id,
        'title' => 'Kurgusal bildirim',
        'body' => 'Kurgusal görev atandı.',
        'channel' => 'in_app',
    ]);

    expect($comment->mentions)->toBe([$actor->id])
        ->and($comment->user->is($actor))->toBeTrue()
        ->and($comment->deal->is($deal))->toBeTrue()
        ->and($task->assignee->is($actor))->toBeTrue()
        ->and($task->creator->is($actor))->toBeTrue()
        ->and($task->due_at)->not->toBeNull()
        ->and($notification->user->is($actor))->toBeTrue()
        ->and($notification->deal->is($deal))->toBeTrue();
});

it('persists domain events through the real outbox writer in the same transaction', function (): void {
    ['actor' => $actor, 'deal' => $deal] = wp07bDealFixture();

    expect(app(OutboxWriter::class))->toBeInstanceOf(DatabaseOutboxWriter::class);

    DB::transaction(fn () => Event::dispatch(new LeadConverted(
        leadId: '101',
        dealId: (string) $deal->id,
        actorId: (string) $actor->id,
    )));

    $message = OutboxMessage::query()->sole();

    expect($message->event_name)->toBe('lead.converted')
        ->and($message->payload)->toMatchArray(['lead_id' => '101', 'deal_id' => (string) $deal->id])
        ->and($message->actor->is($actor))->toBeTrue()
        ->and($message->processed_at)->toBeNull()
        ->and($message->attempts)->toBe(0);

    expect(fn () => DB::transaction(function () use ($actor, $deal): void {
        Event::dispatch(new LeadConverted(
            leadId: 'rollback',
            dealId: (string) $deal->id,
            actorId: (string) $actor->id,
        ));

        throw new RuntimeException('Kurgusal transaction geri alma kanıtı');
    }))->toThrow(RuntimeException::class);

    expect(OutboxMessage::query()->count())->toBe(1);
});
