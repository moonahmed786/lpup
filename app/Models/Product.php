<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Policies\ProductPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(ProductPolicy::class)]
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'quantity',
        'status',
        'price',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ProductStatus::class,
            'price' => 'decimal:2',
            'description' => 'string',
        ];
    }
}
