<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }

        return ProductResource::collection($query->paginate(20));
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $this->authorize('manage', Product::class);

        $product->delete();

        return response()->json(status: 204);
    }

    public function lookup(Request $request)
    {
        $request->validate(['barcode' => ['required', 'string']]);

        $product = Product::query()->where('barcode', $request->barcode)->first();

        if (! $product) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        }

        return new ProductResource($product);
    }
}
