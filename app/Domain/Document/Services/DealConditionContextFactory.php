<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Domain\Crm\Services\CompanyConditionDataReader;
use App\Domain\Deal\DTO\ChecklistDeal;
use App\Support\Conditions\ArrayConditionContext;

final readonly class DealConditionContextFactory
{
    public function __construct(private CompanyConditionDataReader $companies) {}

    public function make(ChecklistDeal $deal): ArrayConditionContext
    {
        return new ArrayConditionContext([
            'company' => $this->companies->read($deal->companyId),
            'deal' => ['requested_amount' => $deal->requestedAmount],
        ]);
    }
}
