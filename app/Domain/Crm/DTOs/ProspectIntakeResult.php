<?php

declare(strict_types=1);

namespace App\Domain\Crm\DTOs;

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;

final readonly class ProspectIntakeResult
{
    public function __construct(
        public Company $company,
        public Contact $contact,
        public Lead $lead,
        public Interaction $interaction,
    ) {}
}
