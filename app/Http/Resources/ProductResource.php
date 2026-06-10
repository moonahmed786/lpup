<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * JSON:API representation for products.
 */
class ProductResource extends JsonApiResource
{
    /**
     * Product fields exposed through the API.
     *
     * @var array<int, string>
     */
    public $attributes = [
        'name',
        'sku',
        'quantity',
        'price',
        'description',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * Product resources do not expose relationships at this point.
     *
     * @var array<int|string, mixed>
     */
    public $relationships = [];
}
