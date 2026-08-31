<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OfficialAccountFollower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerFollowController extends Controller
{
    public function follow(int $id, Request $request): JsonResponse
    {
        $seller = User::findOrFail($id);
        $userId = $request->user()->id;

        if ($userId === $id) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.cannot_follow_self')], 400);
        }

        // Check if already following
        $existing = DB::table('seller_followers')
            ->where('seller_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return response()->json(['success' => true, 'message' => __('app.controller_compat.already_followed')]);
        }

        DB::table('seller_followers')->insert([
            'seller_id' => $id,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => __('app.controller_compat.follow_success')]);
    }

    public function unfollow(int $id, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        DB::table('seller_followers')
            ->where('seller_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['success' => true, 'message' => __('app.controller_compat.unfollowed')]);
    }

    public function status(int $id, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $following = DB::table('seller_followers')
            ->where('seller_id', $id)
            ->where('user_id', $userId)
            ->exists();

        $followerCount = DB::table('seller_followers')
            ->where('seller_id', $id)
            ->count();

        $productCount = \App\Models\Product::where('user_id', $id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'is_following' => $following,
                'follower_count' => $followerCount,
                'product_count' => $productCount,
            ],
        ]);
    }
}
