<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Bootstrapping
|--------------------------------------------------------------------------
|
| Pest, her testte $this context olarak uses() ile belirtilen sınıfı kullanır.
| Feature testlerinde Illuminate\Foundation\Testing\TestCase gereklidir —
| bu, get(), post(), assertStatus() gibi HTTP test helper'larını sağlar.
|
*/

uses(\Tests\TestCase::class)->in('Feature', 'Unit');
