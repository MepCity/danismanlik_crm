<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;

return [
    'company' => Company::class,
    'interaction' => Interaction::class,
    'lead' => Lead::class,
    'deal' => Deal::class,
    'deal_document' => DealDocument::class,
    'file' => File::class,
    'doc_template' => DocTemplate::class,
    'program' => Program::class,
    'program_version' => ProgramVersion::class,
];
