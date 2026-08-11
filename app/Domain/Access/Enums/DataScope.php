<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums;

enum DataScope: string
{
    case None = 'none';
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Own => 1,
            self::Team => 2,
            self::All => 3,
        };
    }
}
