<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
    $this->disableVite();
    (new ReferenceDataSeeder)->setContainer(app())->run();
    Filament::setCurrentPanel(Filament::getPanel('operations'));
});

it('misafir kullanıcıyı kök dizinden operasyon login sayfasına yönlendirir', function (): void {
    /** @var TestCase $this */
    $this->get('/')
        ->assertRedirect('/operasyon/login');
});

it('oturum açmış kullanıcıyı kök dizinden operasyon ana paneline yönlendirir', function (): void {
    /** @var TestCase $this */
    $user = User::factory()->create(['email' => 'oturum-sahibi@example.invalid']);
    $user->assignRole('Pazarlama');

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/operasyon');
});

it('staging ortamında robots.txt tüm yolları engeller', function (): void {
    /** @var TestCase $this */
    config(['app.env' => 'staging']);

    $response = $this->get('/robots.txt');
    $response->assertOk();
    $response->assertSee("User-agent: *\nDisallow: /", false);
});
