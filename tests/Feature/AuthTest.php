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

it('returns login instructions for browser GET requests', function () {
    $this->getJson('/api/login')
        ->assertStatus(405)
        ->assertJsonPath('method', 'POST')
        ->assertJsonPath('body.email', 'user@example.com')
        ->assertJsonPath('body.password', 'password');
});

it('issues an http-only access cookie for valid credentials', function () {
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
        ->assertCookie(config('api.auth_cookie.name'))
        ->assertJsonMissingPath('access_token')
        ->assertJsonMissingPath('token_type')
        ->assertJsonStructure(['message', 'user' => ['id', 'email', 'roles', 'permissions']])
        ->assertJsonPath('user.roles', ['User']);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('api.auth_cookie.name'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue();

    $this->withCredentials()
        ->withUnencryptedCookie(config('api.auth_cookie.name'), $cookie->getValue())
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('email', 'jane@example.com');
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

it('throttles repeated login attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
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

    $this->postJson('/api/logout')
        ->assertNoContent()
        ->assertCookieExpired(config('api.auth_cookie.name'));
});
