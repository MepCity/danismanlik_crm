<?php

declare(strict_types=1);

use App\Domain\Deal\Models\Transition;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\ProgramVersion;
use App\Support\Conditions\Exceptions\InvalidConditionDefinition;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new ReferenceDataSeeder)->setContainer(app())->run();
});

it('model kaydında geçersiz koşul tanımını reddeder', function (string $model, array $condition): void {
    $attributes = match ($model) {
        DocTemplate::class => [
            'program_version_id' => ProgramVersion::query()->valueOrFail('id'),
            'name' => 'Kurgusal geçersiz koşul şablonu',
            'is_required' => true,
            'condition' => $condition,
            'accepted_formats' => ['pdf'],
            'sort_order' => 99,
            'is_active' => true,
        ],
        Transition::class => [
            ...Transition::query()->firstOrFail()->only(['from_status_id', 'to_status_id', 'required_permission']),
            'condition' => $condition,
            'is_active' => true,
        ],
        default => throw new InvalidArgumentException('Desteklenmeyen koşul modeli.'),
    };

    expect(fn (): Model => $model::query()->create($attributes))
        ->toThrow(InvalidConditionDefinition::class);
})->with([
    'evrak şablonunda bilinmeyen operatör' => [DocTemplate::class, [
        'all' => [['field' => 'company.city', 'op' => 'kesinlikle_yok', 'value' => ['Ankara']]],
    ]],
    'evrak şablonunda çözülemeyen alan' => [DocTemplate::class, [
        'all' => [['field' => 'company.kurgusal_alan', 'op' => 'in', 'value' => ['Ankara']]],
    ]],
    'geçişte bilinmeyen operatör' => [Transition::class, [
        'all' => [['field' => 'company.city', 'op' => 'kesinlikle_yok', 'value' => ['Ankara']]],
    ]],
    'geçişte çözülemeyen alan' => [Transition::class, [
        'all' => [['field' => 'deal.kurgusal_alan', 'op' => 'gt', 'value' => 1]],
    ]],
]);

it('wp-08 geçerli koşul seedlerini model doğrulamasından geçirir', function (): void {
    expect(DocTemplate::query()->whereNotNull('condition')->count())->toBeGreaterThan(0)
        ->and(Transition::query()->whereNotNull('condition')->count())->toBeGreaterThan(0);
});
