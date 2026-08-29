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
        ->and($tokens)->toContain('--crm-radius-md: 0.375rem;')
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

it('ortak operasyon kabuğunu tokenlarla uygular', function (): void {
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($tokens)->not->toBeFalse()
        ->and($theme)->not->toBeFalse()
        ->and($provider)->not->toBeFalse()
        ->and($tokens)->toContain('--crm-nav-bg:')
        ->and($tokens)->toContain('--crm-topbar-bg:')
        ->and($tokens)->toContain('--crm-control-height: 36px;')
        ->and($tokens)->toContain('--crm-shell-rail-width: 100px;')
        ->and($tokens)->toContain('--crm-shell-topbar-height: 60px;')
        ->and($theme)->toContain('.crm-rail__control')
        ->and($theme)->toContain('background: var(--crm-nav-bg);')
        ->and($provider)->toContain('->defaultThemeMode(ThemeMode::Light)')
        ->and($provider)->toContain("->sidebarWidth('100px')");
});

it('giriş kontrollerini kart yüzeyinden belirgin biçimde ayırır', function (): void {
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($theme)->not->toBeFalse()
        ->and($theme)->toContain('.fi-input-wrp,')
        ->and($theme)->toContain('background: var(--crm-surface-muted);')
        ->and($theme)->toContain('border: 1px solid var(--crm-border-strong);')
        ->and($theme)->toContain('.fi-input-wrp:focus-within,')
        ->and($theme)->toContain('box-shadow: 0 0 0 3px var(--crm-focus-ring);')
        ->and($theme)->toContain("input[type='checkbox'].fi-checkbox-input,");
});

it('bütün form kontrollerini panel yüzeyinden belirgin biçimde ayırır', function (): void {
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));

    expect($theme)->not->toBeFalse()
        ->and($theme)->toContain('border: 1px solid var(--crm-border-strong);')
        ->and($theme)->toContain('background: var(--crm-surface-muted);')
        ->and($theme)->toContain("input[type='checkbox'].fi-checkbox-input,")
        ->and($theme)->toContain("input[type='radio'].fi-radio-input {")
        ->and($theme)->toContain('.fi-toggle {')
        ->and($theme)->toContain('box-shadow: 0 0 0 3px var(--crm-focus-ring);');
});

it('bütün kaynak listelerinde referanstan bağımsız ortak liste kabuğunu kullanır', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament/Resources')));
    $violations = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_starts_with($file->getFilename(), 'List') || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if ($contents === false || ! str_contains($contents, 'extends ListRecords')) {
            continue;
        }

        if (! str_contains($contents, 'use HasConsistentListChrome;')) {
            $violations[] = $file->getPathname();
        }
    }

    $chrome = file_get_contents(app_path('Filament/Concerns/HasConsistentListChrome.php'));

    expect($violations)->toBe([], 'Ortak liste kabuğunu kullanmayan kaynak bulundu: '.implode(', ', $violations))
        ->and($chrome)->not->toBeFalse()
        ->and($chrome)->toContain("'all' => Tab::make")
        ->and($chrome)->toContain("Action::make('refresh_list')");
});

it('iş akışları ekranında cubicl referans sözleşmesini uygular', function (): void {
    $page = file_get_contents(resource_path('views/filament/resources/service-workflows/pages/list-service-workflows.blade.php'));
    $tokens = file_get_contents(resource_path('css/filament/operations/tokens.css'));
    $theme = file_get_contents(resource_path('css/filament/operations/theme.css'));
    $listPage = file_get_contents(app_path('Filament/Resources/ServiceWorkflows/Pages/ListServiceWorkflows.php'));

    expect($page)->not->toBeFalse()
        ->and($tokens)->not->toBeFalse()
        ->and($theme)->not->toBeFalse()
        ->and($listPage)->not->toBeFalse()
        ->and($page)->toContain('data-testid="cubicl-workflow-page"')
        ->and($page)->toContain('data-testid="cubicl-workflow-intro"')
        ->and($page)->toContain('data-testid="cubicl-workflow-inline-create"')
        ->and($page)->toContain('data-testid="cubicl-workflow-cards"')
        ->and($tokens)->toContain('--crm-workflow-control-height: 32px;')
        ->and($tokens)->toContain('--crm-workflow-card-radius: 10px;')
        ->and($tokens)->toContain('--crm-workflow-transition: 150ms ease-in-out;')
        ->and($theme)->toContain('.cubicl-workflow__intro')
        ->and($theme)->toContain('.cubicl-workflow__create-btn')
        ->and($theme)->toContain('.cubicl-workflow-form-section')
        ->and($listPage)->toContain("protected string \$view = 'filament.resources.service-workflows.pages.list-service-workflows';");
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
