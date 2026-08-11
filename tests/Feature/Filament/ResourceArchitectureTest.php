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
