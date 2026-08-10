<?php

declare(strict_types=1);

namespace App\Support\Audit;

enum ActorSource: string
{
    case User = 'user';
    case Automation = 'automation';
    case Integration = 'integration';
}
