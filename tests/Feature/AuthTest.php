<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('issues an access token for valid credentials', function () {
    // A personal access client must exist for createToken() to work.
    app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client', 'users');

    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret-password'),
    ]);
    $user->assignRole('User');

    $response = $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'secret-password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'user' => ['id', 'email', 'roles', 'permissions']])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.roles', ['User']);
});

it('rejects invalid credentials with a 422', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
        'password' => bcrypt('secret-password'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('validates the login payload', function () {
    $this->postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('blocks /api/me without authentication', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('returns the authenticated user from /api/me', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Passport::actingAs($user, [], 'api');

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('roles', ['Admin']);
});

it('allows an authenticated user to log out', function () {
    $user = User::factory()->create();
    Passport::actingAs($user, [], 'api');

    $this->postJson('/api/logout')->assertNoContent();
});
