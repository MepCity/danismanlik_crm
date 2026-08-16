<?php

declare(strict_types=1);

use App\Filament\Resources\ScopedResource;

it('her somut filament resource kapsam zorlayan temel sınıftan türer', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament/Resources')));
    $violations = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents === false || ! preg_match('/final class (\w+Resource) extends (\w+)/', $contents, $match)) {
            continue;
        }

        $className = $match[1];
        $namespace = preg_match('/namespace ([^;]+);/', $contents, $namespaceMatch) ? $namespaceMatch[1] : null;
        $fullyQualifiedClass = $namespace === null ? $className : $namespace.'\\'.$className;

        if (! is_subclass_of($fullyQualifiedClass, ScopedResource::class)) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBe([], 'Kapsamsız Filament Resource bulundu: '.implode(', ', $violations));
});

it('kapsam sorgusu alt resource tarafından geçersiz kılınamaz', function (): void {
    $method = new ReflectionMethod(ScopedResource::class, 'getEloquentQuery');

    expect($method->isFinal())->toBeTrue()
        ->and($method->getDeclaringClass()->getName())->toBe(ScopedResource::class);
});

it('token dosyası dışında çiğ hex renk bırakmaz', function (): void {
    $directory = resource_path('css/filament/operations');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $violations = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getFilename() === 'tokens.css') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents !== false && preg_match('/#[0-9a-fA-F]{3,8}\b/', $contents)) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBe([], 'Token dosyası dışında çiğ hex bulundu: '.implode(', ', $violations));
});

it('tablo yoğunluğunu gerçek hücre sarmalayıcılarında uygular', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->not->toBeFalse()
        ->and($theme)->not->toBeFalse()
        ->and($tokens)->toContain('--crm-row-height: 38px;')
        ->and($tokens)->toContain('--crm-table-divider-width: 1px;')
        ->and($tokens)->toContain('--crm-table-header-height: 34px;')
        ->and($tokens)->toContain('--crm-pagination-height: 40px;')
        ->and($theme)->toContain('.fi-ta-cell > .fi-ta-col')
        ->and($theme)->toContain('min-height: calc(var(--crm-row-height) - var(--crm-table-divider-width));')
        ->and($theme)->toContain('.fi-ta-header-cell')
        ->and($theme)->toContain('height: var(--crm-table-header-height);')
        ->and($theme)->toContain('.fi-ta-ctn .fi-pagination')
        ->and($theme)->toContain('height: var(--crm-pagination-height);');
});

it('tasarım paketi ortak hareket ve erişilebilirlik sözleşmesini uygular', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($tokens)->not->toBeFalse()
        ->and($theme)->not->toBeFalse()
        ->and($tokens)->toContain('--crm-duration-base: 170ms;')
        ->and($tokens)->toContain('--crm-ease: cubic-bezier(0.16, 1, 0.3, 1);')
        ->and($tokens)->toContain('--crm-radius-md: 0.625rem;')
        ->and($tokens)->toContain('--crm-elevation-overlay:')
        ->and($tokens)->toContain('--crm-surface-pressed:')
        ->and($tokens)->toContain('--crm-border-focus:')
        ->and($theme)->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($theme)->toContain('@view-transition')
        ->and($theme)->toContain(':where(a, button, input, select, textarea, [tabindex]):focus-visible')
        ->and($theme)->toContain('outline: 2px solid var(--crm-focus-ring);')
        ->and($theme)->toContain('.operations-button:active')
        ->and($theme)->toContain('transform: translateY(0) scale(0.985);');
});

it('varlıkları host node kurulumu olmadan container içinde derler', function (): void {
    $makefile = file_get_contents(base_path('Makefile'));
    $compose = file_get_contents(base_path('compose.yaml'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($makefile)->not->toBeFalse()
        ->and($compose)->not->toBeFalse()
        ->and($provider)->not->toBeFalse()
        ->and($makefile)->toContain('up: frontend-assets')
        ->and($makefile)->toContain('php artisan filament:assets --no-interaction')
        ->and($compose)->toContain('image: node:24-bookworm-slim')
        ->and($compose)->toContain('npm ci --ignore-scripts --no-audit --no-fund && npm run build')
        ->and($provider)->toContain('->spa()');
});

it('tasarım pilotunu pano ve dosya detayında görünür kılar', function (): void {
    $board = file_get_contents(resource_path('views/filament/pages/deal-board.blade.php'));
    $detail = file_get_contents(resource_path('views/filament/pages/deal-detail.blade.php'));

    expect($board)->not->toBeFalse()
        ->and($detail)->not->toBeFalse()
        ->and($board)->toContain('pipeline-scroll-cue')
        ->and($board)->toContain('pipeline-card__context')
        ->and($detail)->toContain('deal-workspace__grid')
        ->and($detail)->toContain('deal-activity__filters')
        ->and($detail)->not->toContain('deal-tabs');
});

it('panel markasını ve ürün alanını özel kabukta gösterir', function (): void {
    $logo = file_get_contents(resource_path('views/filament/components/brand-logo.blade.php'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($logo)->not->toBeFalse()
        ->and($provider)->not->toBeFalse()
        ->and($logo)->toContain("__('panel.brand')")
        ->and($logo)->toContain("__('panel.shell.product_area')")
        ->and($provider)->toContain("->brandLogo(fn () => view('filament.components.brand-logo'))")
        ->and($provider)->toContain("->darkModeBrandLogo(fn () => view('filament.components.brand-logo'))");
});
