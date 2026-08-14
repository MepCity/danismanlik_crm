<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class DueTaskReminder
{
    public function __construct(private EmailNotificationService $emails) {}

    public function run(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $count = 0;

        Task::query()
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($now, &$count): void {
                foreach ($tasks as $task) {
                    $created = DB::transaction(function () use ($task, $now): bool {
                        $locked = Task::query()->with('assignee')->lockForUpdate()->findOrFail($task->id);
                        if ($locked->completed_at !== null || $locked->reminder_sent_at !== null || $locked->remind_at?->isAfter($now)) {
                            return false;
                        }

                        $subject = $this->referenceFor($locked);
                        $title = trans('collaboration.notifications.task_reminder_title');
                        $body = $locked->due_at === null
                            ? trans('collaboration.notifications.task_reminder_body_without_due', ['task' => $locked->title])
                            : trans('collaboration.notifications.task_reminder_body', [
                                'task' => $locked->title,
                                'due_at' => $locked->due_at->format('d.m.Y H:i'),
                            ]);
                        Notification::query()->create([
                            'user_id' => $locked->assigned_to,
                            'type' => 'task.reminder',
                            ...$subject->columns(),
                            'title' => $title,
                            'body' => $body,
                            'channel' => 'in_app',
                        ]);
                        $this->emails->queue($locked->assignee, 'task.reminder', $title, $body, $subject);
                        $locked->update(['reminder_sent_at' => $now]);

                        return true;
                    });

                    $count += (int) $created;
                }
            });

        return $count;
    }

    private function referenceFor(Task $task): SubjectReference
    {
        foreach (CollaborationSubjectType::cases() as $type) {
            $id = $task->getAttribute($type->column());
            if ($id !== null) {
                return new SubjectReference($type, (int) $id);
            }
        }

        throw new \LogicException('Task subject constraint is broken.');
    }
}
