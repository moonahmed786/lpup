<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Product API endpoints.
 */
#[Middleware('auth:api')]
class ProductController extends Controller
{
    #[Authorize('viewAny', Product::class)]
    public function index(Request $request): JsonResource
    {
        $products = Product::query()
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    #[Authorize('create', Product::class)]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Authorize('view', 'product')]
    public function show(Product $product): JsonResource
    {
        return new ProductResource($product);
    }

    #[Authorize('update', 'product')]
    public function update(UpdateProductRequest $request, Product $product): JsonResource
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    #[Authorize('delete', 'product')]
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
