<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function show(int $productId)
    {
        $cacheKey = "product:{$productId}:availability";
        $ttl = 60;

        $data = Cache::remember($cacheKey, $ttl, function () use ($productId) {
            $product = Product::find($productId);

            if (!$product) {
                return null;
            }

            $currentlyHeldQty = Hold::where('product_id', $productId)
                ->where('is_used', false)
                ->where('is_expired', false)
                ->where('expires_at', '>', now())
                ->sum('qty');

            $availableStock = max(0, $product->stock - $currentlyHeldQty);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'total_stock' => $product->stock,
                'currently_held_qty' => $currentlyHeldQty,
                'available_stock' => $availableStock,
            ];
        });

        if (is_null($data)) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json($data);
    }
}
