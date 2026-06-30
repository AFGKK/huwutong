<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\Sticker;
use App\Models\StickerPack;
use App\Models\UserConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StickerController extends Controller
{
    // ════════════════════════════════════════════
    // 贴纸包管理
    // ════════════════════════════════════════════

    public function packs(): JsonResponse
    {
        $packs = StickerPack::with('stickers')->orderBy('sort_order')->get();
        return ApiResponse::success($packs);
    }

    public function systemPacks(): JsonResponse
    {
        $packs = StickerPack::with('stickers')->where('is_system', true)->orderBy('sort_order')->get();
        return ApiResponse::success($packs);
    }

    public function createPack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'cover_url' => 'nullable|string|max:500',
        ]);

        $pack = StickerPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'cover_url' => $validated['cover_url'] ?? '',
            'user_id' => auth()->id(),
            'is_system' => false,
        ]);

        return ApiResponse::success($pack, '贴纸包已创建', 201);
    }

    public function addSticker(int $packId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => 'required|string|max:500',
            'emoji' => 'nullable|string|max:20',
        ]);

        $sticker = Sticker::create([
            'sticker_pack_id' => $packId,
            'image_url' => $validated['image_url'],
            'emoji' => $validated['emoji'] ?? '',
        ]);

        return ApiResponse::success($sticker, '贴纸已添加', 201);
    }

    public function deletePack(int $id): JsonResponse
    {
        $pack = StickerPack::findOrFail($id);
        $pack->stickers()->delete();
        $pack->delete();
        return ApiResponse::success(null, '已删除');
    }

    // ════════════════════════════════════════════
    // 发送贴纸消息
    // ════════════════════════════════════════════

    public function sendSticker(int $convId, Request $request): JsonResponse
    {
        $request->validate([
            'sticker_id' => 'nullable|integer|exists:stickers,id',
            'image_url' => 'required_without:sticker_id|string|max:500',
            'emoji' => 'nullable|string|max:20',
        ]);

        $imageUrl = $request->input('image_url');
        $emoji = $request->input('emoji', '');
        $stickerId = $request->input('sticker_id');

        if ($stickerId) {
            $sticker = Sticker::find($stickerId);
            if ($sticker) {
                $imageUrl = $sticker->image_url;
                $emoji = $sticker->emoji ?? $emoji;
            }
        }

        $msg = ConversationMessage::create([
            'conversation_id' => $convId,
            'sender_id' => auth()->id(),
            'content' => $emoji ? "{$emoji} [贴纸]" : '[贴纸]',
            'message_type' => 'sticker',
            'metadata' => [
                'type' => 'sticker',
                'image_url' => $imageUrl,
                'emoji' => $emoji,
                'sticker_id' => $stickerId,
            ],
            'client_msg_id' => 'sticker-' . uniqid(),
        ]);

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);

        return ApiResponse::success($msg->load('sender:id,name'), '已发送', 201);
    }

    // ════════════════════════════════════════════
    // GIF 搜索（代理 Tenor/Giphy）
    // ════════════════════════════════════════════

    public function searchGif(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|max:100']);
        $q = $request->input('q');
        $limit = min((int) $request->input('limit', 20), 50);

        // 尝试调用 Tenor API（有配置时）
        $tenorKey = config('services.tenor.key', '');
        if ($tenorKey) {
            try {
                $resp = Http::get('https://tenor.googleapis.com/v2/search', [
                    'q' => $q,
                    'key' => $tenorKey,
                    'limit' => $limit,
                    'media_filter' => 'gif,tinygif',
                ]);
                if ($resp->successful()) {
                    $results = $resp->json('results', []);
                    $gifs = array_map(fn($r) => [
                        'url' => $r['media_formats']['gif']['url'] ?? '',
                        'preview' => $r['media_formats']['tinygif']['url'] ?? '',
                        'title' => $r['title'] ?? '',
                        'width' => $r['media_formats']['gif']['dims'][0] ?? 200,
                        'height' => $r['media_formats']['gif']['dims'][1] ?? 200,
                    ], $results);
                    return ApiResponse::success(array_slice($gifs, 0, $limit));
                }
            } catch (\Exception $e) {
                // 失败时降级到 Giphy
            }
        }

        // 降级: 使用 Giphy 公开 API（无需 key，但有速率限制）
        try {
            $resp = Http::get('https://api.giphy.com/v1/gifs/search', [
                'q' => $q,
                'api_key' => 'GIPHY_PUBLIC_API_KEY', // 可替换为真实 key
                'limit' => $limit,
                'rating' => 'g',
            ]);
            if ($resp->successful()) {
                $results = $resp->json('data', []);
                $gifs = array_map(fn($r) => [
                    'url' => $r['images']['original']['url'] ?? '',
                    'preview' => $r['images']['fixed_height_small']['url'] ?? '',
                    'title' => $r['title'] ?? '',
                    'width' => $r['images']['original']['width'] ?? 200,
                    'height' => $r['images']['original']['height'] ?? 200,
                ], $results);
                return ApiResponse::success(array_slice($gifs, 0, $limit));
            }
        } catch (\Exception $e) {
            // 失败时降级到内置占位图
        }

        // 最终降级: 使用占位图服务（真实可访问的图片）
        $placeholders = [
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=👍', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=👍', 'title' => '👍 赞', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/4ECDC4/ffffff?text=😂', 'preview' => 'https://via.placeholder.com/100/4ECDC4/ffffff?text=😂', 'title' => '😂 笑', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FFE66D/000000?text=🎉', 'preview' => 'https://via.placeholder.com/100/FFE66D/000000?text=🎉', 'title' => '🎉 庆祝', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=❤️', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=❤️', 'title' => '❤️ 爱心', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/95E1D3/000000?text=😢', 'preview' => 'https://via.placeholder.com/100/95E1D3/000000?text=😢', 'title' => '😢 哭', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=🔥', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=🔥', 'title' => '🔥 火', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/96CEB4/000000?text=🙏', 'preview' => 'https://via.placeholder.com/100/96CEB4/000000?text=🙏', 'title' => '🙏 拜托', 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/6C5B7B/ffffff?text=💪', 'preview' => 'https://via.placeholder.com/100/6C5B7B/ffffff?text=💪', 'title' => '💪 加油', 'width' => 200, 'height' => 200],
        ];

        // 简单关键词匹配过滤
        $filtered = array_filter($placeholders, fn($g) => mb_strpos($g['title'], $q) !== false || mb_strpos($g['title'], $q) !== false);
        $results = count($filtered) > 0 ? array_values($filtered) : $placeholders;

        return ApiResponse::success(array_slice($results, 0, $limit));
    }

    // ════════════════════════════════════════════
    // 常用 Emoji
    // ════════════════════════════════════════════

    public function frequentEmojis(): JsonResponse
    {
        return ApiResponse::success([
            'emojis' => ['👍','❤️','😂','😮','😢','😡','🎉','🔥','💯','❓','👏','🙏','💪','✨','🎊','🤝','💡','📌','🚀','⭐'],
            'categories' => [
                '表情' => ['😀','😂','🤣','😊','😍','🥰','😎','🤔','😢','😤','😡','🥳'],
                '手势' => ['👍','👎','👏','🙏','🤝','✌️','🤞','💪'],
                '物品' => ['🎉','🎊','✨','🔥','💯','⭐','🚀','💡','📌','❤️'],
            ],
        ]);
    }
}
