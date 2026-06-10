<?php

use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\ProductImports\ProductImportResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\FilamentAccess;
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

it('allows product viewers to access the Filament panel', function () {
    $panel = filament()->getPanel('admin');

    $user = User::factory()->create();
    $user->assignRole('User');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('SuperAdmin');

    expect($user->canAccessPanel($panel))->toBeTrue()
        ->and($admin->canAccessPanel($panel))->toBeTrue()
        ->and($superAdmin->canAccessPanel($panel))->toBeTrue();
});

it('keeps user role read only inside Filament', function () {
    actingAsFilamentRole('User');

    expect(ProductResource::canViewAny())->toBeTrue()
        ->and(ProductResource::canCreate())->toBeFalse()
        ->and(UserResource::canViewAny())->toBeFalse()
        ->and(RoleResource::canViewAny())->toBeFalse()
        ->and(PermissionResource::canViewAny())->toBeFalse()
        ->and(ProductImportResource::canViewAny())->toBeFalse();
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

it('shows product import controls only to super admins in Filament', function () {
    actingAsFilamentRole('SuperAdmin');

    expect(FilamentAccess::hasPermission('imports.upload'))->toBeTrue()
        ->and(FilamentAccess::hasPermission('imports.start'))->toBeTrue()
        ->and(FilamentAccess::hasPermission('imports.stop'))->toBeTrue()
        ->and(ProductImportResource::canViewAny())->toBeTrue();

    actingAsFilamentRole('Admin');

    expect(FilamentAccess::hasPermission('imports.viewAny'))->toBeTrue()
        ->and(FilamentAccess::hasPermission('imports.upload'))->toBeFalse()
        ->and(FilamentAccess::hasPermission('imports.start'))->toBeFalse()
        ->and(FilamentAccess::hasPermission('imports.stop'))->toBeFalse()
        ->and(ProductImportResource::canViewAny())->toBeTrue();

    actingAsFilamentRole('User');

    expect(FilamentAccess::hasPermission('imports.viewAny'))->toBeFalse()
        ->and(FilamentAccess::hasPermission('imports.upload'))->toBeFalse()
        ->and(ProductImportResource::canViewAny())->toBeFalse();
});
