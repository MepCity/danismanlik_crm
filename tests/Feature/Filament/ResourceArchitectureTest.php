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
        ->and($tokens)->toContain('--crm-row-height: 36px;')
        ->and($tokens)->toContain('--crm-table-divider-width: 1px;')
        ->and($tokens)->toContain('--crm-table-header-height: 32px;')
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
        ->and($tokens)->toContain('--crm-duration-base: 180ms;')
        ->and($tokens)->toContain('--crm-ease-out: cubic-bezier(0.16, 1, 0.3, 1);')
        ->and($theme)->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($theme)->toContain(':where(a, button, input, select, textarea, [tabindex]):focus-visible')
        ->and($theme)->toContain('outline: 2px solid var(--crm-focus-ring);')
        ->and($theme)->toContain('.operations-button:active')
        ->and($theme)->toContain('transform: translateY(0) scale(0.985);');
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
