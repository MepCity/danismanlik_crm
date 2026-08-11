<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Document\Services\ExpireDocuments;
use Illuminate\Console\Command;

final class ExpireDocumentsCommand extends Command
{
    protected $signature = 'documents:expire';

    protected $description = 'Geçerlilik süresi dolan belgeleri işaretler';

    public function handle(ExpireDocuments $service): int
    {
        $this->info(trans('documents.commands.expired', ['count' => $service->run()]));

        return self::SUCCESS;
    }
}
