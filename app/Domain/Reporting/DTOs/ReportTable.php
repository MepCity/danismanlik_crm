<?php

declare(strict_types=1);

namespace App\Domain\Reporting\DTOs;

use App\Domain\Reporting\Enums\ReportType;
use Illuminate\Support\Collection;
use stdClass;

final readonly class ReportTable
{
    /**
     * @param  list<ReportColumn>  $columns
     * @param  Collection<int, stdClass>  $rows
     */
    public function __construct(
        public ReportType $type,
        public array $columns,
        public Collection $rows,
        public int $total,
    ) {}
}
