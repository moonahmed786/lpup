<?php

use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function actingAsFilamentRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}

it('allows only super admins to manage roles and permissions', function () {
    actingAsFilamentRole('Admin');

    expect(RoleResource::canViewAny())->toBeFalse()
        ->and(RoleResource::canCreate())->toBeFalse()
        ->and(PermissionResource::canViewAny())->toBeFalse()
        ->and(PermissionResource::canCreate())->toBeFalse();

    actingAsFilamentRole('SuperAdmin');

    expect(RoleResource::canViewAny())->toBeTrue()
        ->and(RoleResource::canCreate())->toBeTrue()
        ->and(PermissionResource::canViewAny())->toBeTrue()
        ->and(PermissionResource::canCreate())->toBeTrue();
});

it('keeps users read only for products and admins away from product uploads', function () {
    $admin = Role::findByName('Admin', 'api');
    $user = Role::findByName('User', 'api');

    expect($admin->hasPermissionTo('products.create', 'api'))->toBeTrue()
        ->and($admin->hasPermissionTo('products.update', 'api'))->toBeTrue()
        ->and($admin->hasPermissionTo('products.delete', 'api'))->toBeTrue()
        ->and($admin->hasPermissionTo('imports.upload', 'api'))->toBeFalse()
        ->and($user->hasPermissionTo('products.viewAny', 'api'))->toBeTrue()
        ->and($user->hasPermissionTo('products.view', 'api'))->toBeTrue()
        ->and($user->hasPermissionTo('products.create', 'api'))->toBeFalse()
        ->and($user->hasPermissionTo('products.update', 'api'))->toBeFalse()
        ->and($user->hasPermissionTo('products.delete', 'api'))->toBeFalse();
});
