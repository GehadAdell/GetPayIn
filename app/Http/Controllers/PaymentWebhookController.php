<?php

namespace App\Http\Controllers;

use App\Models\Hold;
use App\Models\Order;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');
        $paymentStatus = $request->input('status');
        $holdId = $request->input('hold_id');

        if (!$idempotencyKey) {
            return response()->json(['message' => 'Missing Idempotency Key'], 400);
        }

        $lock = Cache::lock("webhook:{$idempotencyKey}", 60);

        if (!$lock->get()) {
            return response()->json(['message' => 'Webhook processing in progress. Try again later.'], 409);
        }

        try {
            if (WebhookEvent::find($idempotencyKey)) {
                return response()->json(['message' => 'Webhook already processed.'], 200);
            }

            $order = Order::where('hold_id', $holdId)->first();
            if (!$order) {
                return response()->json(['message' => 'Order not found yet.'], 404);
            }

            DB::beginTransaction();

            $order->lockForUpdate()->find($order->id);

            $oldStatus = $order->status;

            if ($paymentStatus === 'success' && $oldStatus !== 'paid') {
                $order->status = 'paid';
                $order->save();

            } elseif ($paymentStatus === 'failed' && $oldStatus !== 'cancelled') {
                $order->status = 'cancelled';
                $order->save();

                $hold = Hold::lockForUpdate()->find($holdId);
                if ($hold && !$hold->is_used) {
                    $hold->is_expired = true;
                    $hold->is_used = false;
                    $hold->save();
                }
            }
            
            if ($oldStatus !== $order->status || $oldStatus === 'paid') {
                WebhookEvent::create([
                    'idempotency_key' => $idempotencyKey,
                    'order_reference' => $order->id,
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Order status updated successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'An error occurred during webhook processing.'], 500);
        } finally {
            $lock->release();
        }
    }
}
