<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockNotification;
use App\Models\ProductSku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockNotifyController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sku_id' => 'required|exists:product_skus,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sku = ProductSku::findOrFail($request->sku_id);

        // 如果还有库存，不需要通知
        if ($sku->stock === -1 || $sku->stock > 0) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.stock_notify_msg_32'),
            ], 400);
        }

        $user = $request->user();
        $email = $request->input('email', $user?->email);
        $phone = $request->input('phone');

        if (!$email && !$phone && !$user) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.stock_notify_msg_43'),
            ], 422);
        }

        // 检查是否已订阅
        $existing = StockNotification::where('product_sku_id', $sku->id)
            ->when($email, fn($q) => $q->where('email', $email))
            ->where('notified', false)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.stock_notify_msg_56'),
            ]);
        }

        StockNotification::create([
            'product_sku_id' => $sku->id,
            'user_id' => $user?->id,
            'email' => $email,
            'phone' => $phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.stock_notify_msg_69'),
        ]);
    }
}
