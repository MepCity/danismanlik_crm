<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Collaboration\Services\NotificationWriter;
use App\Domain\Document\Models\DealDocument;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Carbon;

final readonly class ExpireDocuments
{
    public function __construct(
        private DocumentStatusService $statuses,
        private NotificationWriter $notifications,
    ) {}

    public function run(?Carbon $at = null): int
    {
        $at ??= Carbon::now();
        $count = 0;

        DealDocument::query()
            ->whereIn('status', ['uploaded', 'under_review', 'accepted'])
            ->whereNotNull('validity_expires_at')
            ->where('validity_expires_at', '<=', $at)
            ->with('deal')
            ->orderBy('id')
            ->each(function (DealDocument $document) use (&$count): void {
                $expired = $this->statuses->change(
                    $document->id,
                    'expired',
                    null,
                    null,
                    ActorSource::Automation,
                    ['uploaded', 'under_review', 'accepted'],
                );
                $pm = $expired->deal->pm_user_id;

                if ($pm !== null) {
                    $this->notifications->documentExpired(
                        $pm,
                        $expired->deal_id,
                        $expired->id,
                        $expired->name_snapshot,
                    );
                }
                $count++;
            });

        return $count;
    }
}
