<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Sticker;
use App\Models\StickerPack;
use App\Models\UserConversation;
use App\Services\UserChatPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
            'cover_url' => 'nullable|url|max:2048',
        ]);

        $pack = StickerPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'cover_url' => $validated['cover_url'] ?? '',
            'user_id' => auth()->id(),
            'is_system' => false,
        ]);

        return ApiResponse::success($pack, __('app.api.sticker.pack_created'), 201);
    }

    public function addSticker(int $packId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url' => 'required|url|max:2048',
            'emoji' => 'nullable|string|max:20',
        ]);

        $sticker = Sticker::create([
            'sticker_pack_id' => $packId,
            'image_url' => $validated['image_url'],
            'emoji' => $validated['emoji'] ?? '',
        ]);

        return ApiResponse::success($sticker, __('app.api.sticker.added'), 201);
    }

    public function deletePack(int $id): JsonResponse
    {
        $pack = StickerPack::findOrFail($id);
        $pack->stickers()->delete();
        $pack->delete();
        return ApiResponse::success(null, __('app.api.sticker.deleted'));
    }

    // ════════════════════════════════════════════
    // 发送贴纸消息
    // ════════════════════════════════════════════

    public function sendSticker(int $convId, Request $request): JsonResponse
    {
        $request->validate([
            'sticker_id' => 'nullable|integer|exists:stickers,id',
            'image_url' => 'required_without:sticker_id|url|max:2048',
            'emoji' => 'nullable|string|max:20',
        ]);

        $conv = UserConversation::findOrFail($convId);
        $myId = auth()->id();

        // 1. 参与者校验
        $isParticipant = ConversationParticipant::where('conversation_id', $convId)
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (! $isParticipant) {
            return ApiResponse::error(__('app.api.chat.not_participant'));
        }

        // 2. 私聊 DM 策略检查
        if ($conv->type === 'private') {
            $policy = app(UserChatPolicyService::class);
            $otherParticipant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', '!=', $myId)
                ->whereNull('deleted_at')
                ->first();

            if ($otherParticipant) {
                $privateEval = $policy->evaluatePrivateMessage($myId, $otherParticipant->user_id);
                if (! $privateEval['allowed']) {
                    return ApiResponse::error($privateEval['reason'] ?? __('app.api.chat.cannot_send_dm'));
                }

                $myParticipant = ConversationParticipant::where('conversation_id', $convId)
                    ->where('user_id', $myId)
                    ->whereNull('deleted_at')
                    ->first();
                if ($myParticipant?->request_status === 'pending') {
                    $policy->acceptMessageRequest($convId, $myId);
                }
                if ($otherParticipant->request_status === 'rejected') {
                    return ApiResponse::error(__('app.api.chat.dm_rejected'));
                }
            }
        }

        // 3. 慢速模式检查
        if ($conv->slow_mode_interval > 0) {
            $participant = ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $myId)->first();
            if ($participant && $participant->slow_mode_until && $participant->slow_mode_until->isFuture()) {
                $waitSeconds = now()->diffInSeconds($participant->slow_mode_until);
                return ApiResponse::error(__('app.api.chat.slow_mode', ['n' => $waitSeconds]));
            }
        }

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
            'sender_id' => $myId,
            'content' => $emoji ? __('app.api.sticker.sticker_with_emoji', ['emoji' => $emoji]) : __('app.api.sticker.sticker_content'),
            'message_type' => 'sticker',
            'metadata' => [
                'type' => 'sticker',
                'image_url' => $imageUrl,
                'emoji' => $emoji,
                'sticker_id' => $stickerId,
            ],
            'client_msg_id' => 'sticker-' . uniqid(),
        ]);

        // 更新慢速模式时间戳
        if ($conv->slow_mode_interval > 0) {
            ConversationParticipant::where('conversation_id', $convId)
                ->where('user_id', $myId)
                ->update(['slow_mode_until' => now()->addSeconds($conv->slow_mode_interval)]);
        }

        UserConversation::where('id', $convId)->update(['last_message_at' => now()]);

        return ApiResponse::success($msg->load('sender:id,name'), __('app.api.sticker.sent'), 201);
    }

    // ════════════════════════════════════════════
    // 上传贴纸文件
    // ════════════════════════════════════════════

    public function uploadSticker(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:png,gif,webp,jpg,jpeg|max:2048',
            'pack_id' => 'nullable|integer|exists:sticker_packs,id',
            'emoji' => 'nullable|string|max:20',
        ]);

        $file = $request->file('file');
        $path = $file->store('stickers', 'public');
        $url = Storage::disk('public')->url($path);

        if ($packId = $request->input('pack_id')) {
            $sticker = Sticker::create([
                'sticker_pack_id' => $packId,
                'image_url' => $url,
                'emoji' => $request->input('emoji', ''),
            ]);
            return ApiResponse::success(['sticker' => $sticker, 'url' => $url], __('app.api.sticker.uploaded'), 201);
        }

        return ApiResponse::success(['url' => $url], __('app.api.sticker.uploaded'), 201);
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
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=👍', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=👍', 'title' => __('app.api.sticker.gif_like'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/4ECDC4/ffffff?text=😂', 'preview' => 'https://via.placeholder.com/100/4ECDC4/ffffff?text=😂', 'title' => __('app.api.sticker.gif_laugh'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FFE66D/000000?text=🎉', 'preview' => 'https://via.placeholder.com/100/FFE66D/000000?text=🎉', 'title' => __('app.api.sticker.gif_party'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=❤️', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=❤️', 'title' => __('app.api.sticker.gif_heart'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/95E1D3/000000?text=😢', 'preview' => 'https://via.placeholder.com/100/95E1D3/000000?text=😢', 'title' => __('app.api.sticker.gif_cry'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/FF6B6B/ffffff?text=🔥', 'preview' => 'https://via.placeholder.com/100/FF6B6B/ffffff?text=🔥', 'title' => __('app.api.sticker.gif_fire'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/96CEB4/000000?text=🙏', 'preview' => 'https://via.placeholder.com/100/96CEB4/000000?text=🙏', 'title' => __('app.api.sticker.gif_pray'), 'width' => 200, 'height' => 200],
            ['url' => 'https://via.placeholder.com/200/6C5B7B/ffffff?text=💪', 'preview' => 'https://via.placeholder.com/100/6C5B7B/ffffff?text=💪', 'title' => __('app.api.sticker.gif_strong'), 'width' => 200, 'height' => 200],
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
                __('app.api.sticker.cat_emoji') => ['😀','😂','🤣','😊','😍','🥰','😎','🤔','😢','😤','😡','🥳'],
                __('app.api.sticker.cat_gesture') => ['👍','👎','👏','🙏','🤝','✌️','🤞','💪'],
                __('app.api.sticker.cat_object') => ['🎉','🎊','✨','🔥','💯','⭐','🚀','💡','📌','❤️'],
            ],
        ]);
    }
}
