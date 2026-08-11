<?php

declare(strict_types=1);

namespace App\Domain\Crm\Observers;

use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Services\ChecklistReevaluatorContract;

final readonly class CompanyChecklistObserver
{
    public function __construct(
        private ChecklistDealGateway $deals,
        private ChecklistReevaluatorContract $reevaluator,
    ) {}

    public function updated(Company $company): void
    {
        if (! $company->wasChanged('city')) {
            return;
        }

        foreach ($this->deals->idsForCompany($company->id) as $dealId) {
            $this->reevaluator->reevaluate($dealId);
        }
    }
}
