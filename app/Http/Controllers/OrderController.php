<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|uuid',
        ]);

        $holdId = $request->input('hold_id');

        try {
            DB::beginTransaction();

            $hold = Hold::lockForUpdate()->find($holdId);

            if (!$hold) {
                DB::rollBack();
                return response()->json(['message' => 'Hold not found.'], 404);
            }

            if ($hold->is_used) {
                DB::rollBack();
                return response()->json(['message' => 'Hold has already been used to create an order.'], 409);
            }

            if ($hold->expires_at->isPast() || $hold->is_expired) {
                DB::rollBack();
                return response()->json(['message' => 'Hold has expired.'], 409);
            }

            $product = $hold->product;
            $order = Order::create([
                'hold_id' => $hold->id,
                'qty' => $hold->qty,
                'total_price' => $hold->qty * $product->price,
                'status' => 'PENDING_PAYMENT',
            ]);

            $hold->is_used = true;
            $hold->is_expired = false;
            $hold->save();

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully. Pending payment.',
                'order_id' => $order->id,
                'status' => $order->status,
                'total_price' => $order->total_price,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Could not create order due to a system error.'], 500);
        }
    }
}
