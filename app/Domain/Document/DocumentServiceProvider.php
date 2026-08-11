<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Illuminate\Support\ServiceProvider;

final class DocumentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Belge servis bağları ilgili iş paketlerinde tanımlanacak.
    }
}
