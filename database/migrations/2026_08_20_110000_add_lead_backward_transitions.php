<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{0: string, 1: string}> */
    private const TRANSITIONS = [
        ['called', 'new'],
        ['interested', 'called'],
        ['interested', 'new'],
        ['proposal_sent', 'interested'],
        ['proposal_sent', 'called'],
        ['proposal_sent', 'new'],
        ['callback', 'interested'],
        ['callback', 'new'],
    ];

    public function up(): void
    {
        $this->setTransitionsActive(true);
        $this->recordRevision('Takip panosunda geri geçişler etkinleştirildi.');
    }

    public function down(): void
    {
        $this->setTransitionsActive(false);
        $this->recordRevision('Takip panosundaki geri geçişler pasifleştirildi.');
    }

    private function setTransitionsActive(bool $active): void
    {
        $statuses = DB::table('statuses')->where('type', 'lead')->pluck('id', 'code');
        $now = now();

        foreach (self::TRANSITIONS as [$from, $to]) {
            $fromId = $statuses->get($from);
            $toId = $statuses->get($to);
            if ($fromId === null || $toId === null) {
                continue;
            }

            DB::table('transitions')->updateOrInsert(
                ['from_status_id' => $fromId, 'to_status_id' => $toId],
                [
                    'required_permission' => 'lead.manage',
                    'condition' => null,
                    'is_active' => $active,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function recordRevision(string $reason): void
    {
        $actorId = DB::table('users')->where('email', 'system-seeder@localhost.invalid')->value('id');
        if ($actorId === null || ! DB::table('statuses')->exists()) {
            return;
        }

        $statuses = DB::table('statuses')->orderBy('type')->orderBy('sort_order')->get()->map(static fn (object $status): array => [
            'type' => $status->type,
            'code' => $status->code,
            'label' => $status->label,
            'color' => $status->color,
            'sort_order' => $status->sort_order,
            'is_terminal' => $status->is_terminal,
            'is_active' => $status->is_active,
        ])->all();
        $transitions = DB::table('transitions as transition')
            ->join('statuses as source', 'source.id', '=', 'transition.from_status_id')
            ->join('statuses as target', 'target.id', '=', 'transition.to_status_id')
            ->orderBy('transition.id')
            ->get([
                'source.type as source_type', 'source.code as source_code',
                'target.type as target_type', 'target.code as target_code',
                'transition.required_permission', 'transition.condition', 'transition.is_active',
            ])->map(static fn (object $transition): array => [
                'from' => $transition->source_type.'.'.$transition->source_code,
                'to' => $transition->target_type.'.'.$transition->target_code,
                'required_permission' => $transition->required_permission,
                'condition' => $transition->condition === null ? null : json_decode($transition->condition, true),
                'is_active' => $transition->is_active,
            ])->all();

        DB::table('workflow_revisions')->insert([
            'snapshot' => json_encode(compact('statuses', 'transitions'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'effective_from' => now(),
            'changed_by' => $actorId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
};
