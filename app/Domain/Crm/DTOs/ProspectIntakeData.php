<?php

declare(strict_types=1);

namespace App\Domain\Crm\DTOs;

use Illuminate\Support\Carbon;

final readonly class ProspectIntakeData
{
    public function __construct(
        public ?int $companyId,
        public ?string $companyName,
        public ?string $taxNumber,
        public ?string $city,
        public string $source,
        public ?int $contactId,
        public ?string $contactName,
        public ?string $contactTitle,
        public ?string $phone,
        public ?string $email,
        public ?string $disclosureDate,
        public int $programVersionId,
        public int $targetStatusId,
        public Carbon $calledAt,
        public string $callDirection,
        public ?string $outcome,
        public string $callNote,
        public ?Carbon $nextCallAt = null,
        public ?string $companyComment = null,
        public ?string $taskTitle = null,
        public ?Carbon $taskDueAt = null,
        public ?Carbon $taskRemindAt = null,
        public ?string $companyIndustry = null,
    ) {}
}
