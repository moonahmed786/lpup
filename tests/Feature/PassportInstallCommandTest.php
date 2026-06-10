<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('creates the passport personal access client when missing', function () {
    $this->artisan('lpup:install-passport')
        ->assertSuccessful();

    $client = app(ClientRepository::class)->personalAccessClient('users');

    expect($client->name)->toBe('LPUP Personal Access Client')
        ->and($client->revoked)->toBeFalse();
});

it('does not duplicate an existing passport personal access client', function () {
    $this->artisan('lpup:install-passport')->assertSuccessful();
    $this->artisan('lpup:install-passport')->assertSuccessful();

    expect(Passport::client()::query()
        ->where('provider', 'users')
        ->where('grant_types', 'like', '%personal_access%')
        ->count())->toBe(1);
});
