<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Create a user with the given role and authenticate on the api guard. */
function actingAsRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Passport::actingAs($user, [], 'api');

    return $user;
}

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Widget',
        'sku' => 'SKU-TEST-001',
        'quantity' => 10,
        'price' => 19.99,
        'description' => 'Test product',
        'status' => 'active',
    ], $overrides);
}

it('blocks unauthenticated access to products', function () {
    $this->getJson('/api/products')->assertUnauthorized();
});

describe('User role (read only)', function () {
    beforeEach(fn () => actingAsRole('User'));

    it('can list products', function () {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'products');
    });

    it('can view a single product', function () {
        $product = Product::factory()->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $product->id)
            ->assertJsonPath('data.attributes.sku', $product->sku);
    });

    it('cannot create a product', function () {
        $this->postJson('/api/products', productPayload())->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    });

    it('cannot update a product', function () {
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['name' => 'Changed'])
            ->assertForbidden();
    });

    it('cannot delete a product', function () {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    });
});

describe('Admin role (manage products)', function () {
    beforeEach(fn () => actingAsRole('Admin'));

    it('can create a product', function () {
        $this->postJson('/api/products', productPayload())
            ->assertCreated()
            ->assertJsonPath('data.attributes.sku', 'SKU-TEST-001');

        $this->assertDatabaseHas('products', ['sku' => 'SKU-TEST-001']);
    });

    it('validates product creation', function () {
        $this->postJson('/api/products', productPayload(['quantity' => -5, 'price' => -1, 'status' => 'nope']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'price', 'status']);
    });

    it('validates product list query parameters', function () {
        $this->getJson('/api/products?per_page=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    });

    it('can update a product', function () {
        $product = Product::factory()->create();

        $this->patchJson("/api/products/{$product->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.attributes.name', 'Renamed');
    });

    it('can soft delete a product', function () {
        $product = Product::factory()->create();

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    });
});

describe('SuperAdmin role (full access via policy before())', function () {
    beforeEach(fn () => actingAsRole('SuperAdmin'));

    it('can create and delete products', function () {
        $create = $this->postJson('/api/products', productPayload(['sku' => 'SA-001']))
            ->assertCreated();

        $id = $create->json('data.id');

        $this->deleteJson("/api/products/{$id}")->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $id]);
    });
});
