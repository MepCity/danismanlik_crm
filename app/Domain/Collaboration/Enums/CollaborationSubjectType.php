<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Enums;

enum CollaborationSubjectType: string
{
    case Company = 'company';
    case Lead = 'lead';
    case Deal = 'deal';
    case DealDocument = 'deal_document';

    public function column(): string
    {
        return $this->value.'_id';
    }
}
