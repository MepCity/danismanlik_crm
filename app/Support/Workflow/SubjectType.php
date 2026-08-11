<?php

declare(strict_types=1);

namespace App\Support\Workflow;

enum SubjectType: string
{
    case Lead = 'lead';
    case Deal = 'deal';
}
