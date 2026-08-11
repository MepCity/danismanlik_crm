<?php

declare(strict_types=1);

namespace App\Domain\Deal\Exceptions;

use App\Domain\Deal\DTO\OrphanedStatus;
use App\Domain\Deal\DTO\OrphanImpact;
use App\Support\Exceptions\DomainException;
use App\Support\Workflow\SubjectType;

final class WorkflowDeactivationBlocked extends DomainException
{
    public function __construct(public readonly OrphanImpact $impact)
    {
        parent::__construct((string) trans($this->translationKey(), $this->translationParameters()));
    }

    public function translationKey(): string
    {
        return 'domain.errors.workflow.orphaned';
    }

    public function translationParameters(): array
    {
        return [
            'details' => implode('; ', array_map(
                static fn (OrphanedStatus $status): string => trans_choice(
                    $status->subjectType === SubjectType::Deal
                        ? 'domain.errors.workflow.deal_count'
                        : 'domain.errors.workflow.lead_count',
                    $status->subjectCount,
                    ['count' => $status->subjectCount, 'status' => $status->statusLabel],
                ),
                $this->impact->statuses,
            )),
        ];
    }
}
