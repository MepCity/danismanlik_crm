<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class CompanyDuplicateFinder
{
    /**
     * @param  Collection<int, Company>  $visibleCompanies
     * @return Collection<int, Company>
     */
    public function find(Collection $visibleCompanies, string $name, ?string $taxNumber = null): Collection
    {
        $needle = $this->normalize($name);
        $taxNumber = filled($taxNumber) ? trim((string) $taxNumber) : null;

        if (mb_strlen($needle) < 3 && $taxNumber === null) {
            return new Collection;
        }

        return $visibleCompanies->filter(function (Company $company) use ($needle, $taxNumber): bool {
            if ($taxNumber !== null && $company->tax_number === $taxNumber) {
                return true;
            }

            $candidate = $this->normalize($company->legal_name);

            return $needle !== '' && (str_contains($candidate, $needle) || str_contains($needle, $candidate));
        })->take(5)->values();
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
    }
}
