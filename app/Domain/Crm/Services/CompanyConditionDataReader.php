<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Company;

final class CompanyConditionDataReader
{
    /** @return array{city: string} */
    public function read(int $companyId): array
    {
        $company = Company::query()->findOrFail($companyId);

        return ['city' => $company->city];
    }
}
