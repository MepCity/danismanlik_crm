<?php

declare(strict_types=1);

namespace App\Domain\Deal\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Audit\ActorSource;

final readonly class UpdateDealAmount
{
    public function __construct(
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
    ) {}

    public function execute(int $dealId, string $requestedAmount, User $actor): Deal
    {
        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($dealId, $requestedAmount, $actor): Deal {
            $deal = Deal::query()->findOrFail($dealId);
            $oldAmount = $deal->requested_amount;

            if ($oldAmount === $requestedAmount) {
                return $deal;
            }

            $deal->requested_amount = $requestedAmount;
            $deal->save();

            $this->activities->record(
                action: 'deal.updated',
                payload: [
                    'field' => 'requested_amount',
                    'old_value' => $oldAmount,
                    'new_value' => $requestedAmount,
                ],
                actorId: $actor->id,
                dealId: $deal->id,
                defaultSource: 'user',
            );

            return $deal->refresh();
        });
    }
}
