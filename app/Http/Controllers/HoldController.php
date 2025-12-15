<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $productId = $request->input('product_id');
        $qty = $request->input('qty');
        $holdId = (string) Str::uuid();
        $expiryMinutes = 2;

        try {
            DB::beginTransaction();

            $product = Product::lockForUpdate()->find($productId);

            if (!$product) {
                DB::rollBack();
                return response()->json(['message' => 'Product not found.'], 404);
            }

            $currentHolds = Hold::where('product_id', $productId)
                ->where('is_used', false)
                ->where('is_expired', false)
                ->sum('qty');

            $availableStock = $product->stock - $currentHolds;

            if ($availableStock < $qty) {
                DB::rollBack();
                return response()->json(['message' => 'Not enough stock available.'], 409);
            }

            $hold = Hold::create([
                'id' => $holdId,
                'product_id' => $productId,
                'qty' => $qty,
                'expires_at' => now()->addMinutes($expiryMinutes),
            ]);

            DB::commit();

            return response()->json([
                'hold_id' => $hold->id,
                'expires_at' => $hold->expires_at,
                'message' => 'Hold created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Could not create hold due to a system error.'], 500);
        }
    }
}
