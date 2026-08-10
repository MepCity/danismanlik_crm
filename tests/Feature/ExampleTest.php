<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('returns a successful response from the welcome page', function (): void {
    $response = get('/');

    $response->assertStatus(200);
});
