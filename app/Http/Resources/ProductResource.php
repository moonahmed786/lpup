<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * JSON:API resource for products, built on Laravel 13's first-party
 * Illuminate\Http\Resources\JsonApi\JsonApiResource.
 *
 * Emits the JSON:API document shape:
 *   { "data": { "type": "products", "id": "1", "attributes": { ... } } }
 *
 * `toType()`/`toId()` default to the model's table name and key; the
 * declared $attributes drive the `attributes` member and honour
 * sparse fieldsets automatically.
 */
class ProductResource extends JsonApiResource
{
    /**
     * Attributes exposed in the JSON:API `attributes` member.
     *
     * @var array<int, string>
     */
    public $attributes = [
        'name',
        'sku',
        'quantity',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * No relationships on the products schema yet.
     *
     * @var array<int|string, mixed>
     */
    public $relationships = [];
}
