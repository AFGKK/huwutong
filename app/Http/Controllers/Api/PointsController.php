<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\ApiResponse;
use App\Models\ContentTip;
use App\Models\OaArticle;
use App\Models\ForumPost;
use App\Models\BlogPost;
use App\Models\UserPoint;
use App\Models\ShareReward;
use App\Models\PointTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointsController extends Controller
{
    // ── 允许打赏的内容类型映射 ──
    const TIPPABLE_TYPES = [
        'oa_article' => OaArticle::class,
        'forum_post' => ForumPost::class,
        'blog_post'  => BlogPost::class,
    ];

    // ── 站外分享奖励内容类型映射 ──
    const SHAREABLE_TYPES = [
        'oa_article' => OaArticle::class,
        'forum_post' => ForumPost::class,
        'blog_post'  => BlogPost::class,
        'product'    => null, // 预留
    ];

    // ── 每次分享奖励积分 ──
    const SHARE_REWARD_POINTS = 1;
    // ── 每日分享奖励上限 ──
    const DAILY_SHARE_REWARD_LIMIT = 5;

    // ── 获取我的积分余额 ──
    public function balance(): JsonResponse
    {
        $points = UserPoint::firstOrCreate(['user_id' => auth()->id()]);

        return ApiResponse::success([
            'balance' => (float) $points->balance,
            'total_earned' => (float) $points->total_earned,
            'total_spent' => (float) $points->total_spent,
        ]);
    }

    // ── 积分交易记录 ──
    public function transactions(Request $request): JsonResponse
    {
        $txns = \App\Models\PointTransaction::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($txns);
    }

    // ── 打赏内容 ──
    public function tip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|in:' . implode(',', array_keys(self::TIPPABLE_TYPES)),
            'content_id'   => 'required|integer',
            'points'       => 'required|numeric|min:1|max:99999',
            'message'      => 'nullable|string|max:500',
        ]);

        $modelClass = self::TIPPABLE_TYPES[$validated['content_type']];
        $content = $modelClass::findOrFail($validated['content_id']);

        // 获取内容作者 user_id
        $receiverId = $this->resolveContentAuthor($content, $validated['content_type']);
        if (! $receiverId) {
            return ApiResponse::error('无法确定内容作者', 400);
        }
        if ((int) $receiverId === (int) auth()->id()) {
            return ApiResponse::error('不能打赏自己的内容', 400);
        }

        $points = (float) $validated['points'];

        // 事务：扣积分 + 记录打赏 + 加积分给作者
        DB::beginTransaction();
        try {
            // 扣打赏者的积分
            $tipperPoints = UserPoint::spend(
                auth()->id(),
                $points,
                "打赏 {$validated['content_type']}#{$validated['content_id']}"
            );
            if (! $tipperPoints) {
                DB::rollBack();
                return ApiResponse::error('积分余额不足', 400);
            }

            // 创建打赏记录
            $tip = ContentTip::create([
                'tipper_id'     => auth()->id(),
                'receiver_id'   => $receiverId,
                'tippable_type' => $modelClass,
                'tippable_id'   => $content->getKey(),
                'points'        => $points,
                'message'       => $validated['message'] ?? null,
            ]);

            // 给作者加积分（扣除平台抽成 - 暂定100%给作者）
            UserPoint::earn(
                $receiverId,
                $points,
                "收到打赏 {$validated['content_type']}#{$validated['content_id']}",
                $tip
            );

            DB::commit();

            return ApiResponse::success([
                'tip_id'    => $tip->id,
                'points'    => $points,
                'balance'   => (float) $tipperPoints->balance,
                'message'   => '打赏成功',
            ], '打赏成功');

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('打赏失败：' . $e->getMessage(), 500);
        }
    }

    // ── 某个内容收到的打赏列表 ──
    public function contentTips(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|in:' . implode(',', array_keys(self::TIPPABLE_TYPES)),
            'content_id'   => 'required|integer',
        ]);

        $modelClass = self::TIPPABLE_TYPES[$validated['content_type']];
        $tips = ContentTip::where('tippable_type', $modelClass)
            ->where('tippable_id', $validated['content_id'])
            ->with('tipper:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($tips);
    }

    // ── 统计某个内容收到的总打赏 ──
    public function contentTipStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|in:' . implode(',', array_keys(self::TIPPABLE_TYPES)),
            'content_id'   => 'required|integer',
        ]);

        $modelClass = self::TIPPABLE_TYPES[$validated['content_type']];
        $stats = ContentTip::where('tippable_type', $modelClass)
            ->where('tippable_id', $validated['content_id'])
            ->selectRaw('COUNT(*) as tip_count, COALESCE(SUM(points), 0) as total_points')
            ->first();

        // 我是否打赏过
        $myTip = ContentTip::where('tippable_type', $modelClass)
            ->where('tippable_id', $validated['content_id'])
            ->where('tipper_id', auth()->id())
            ->first();

        return ApiResponse::success([
            'tip_count'    => (int) $stats->tip_count,
            'total_points' => (float) $stats->total_points,
            'my_tip'       => $myTip ? (float) $myTip->points : 0,
        ]);
    }

    // ── 解析内容作者ID ──
    private function resolveContentAuthor($content, string $type): ?int
    {
        return match ($type) {
            'oa_article' => $content->author_id ?? $content->user_id ?? null,
            'forum_post' => $content->user_id,
            'blog_post'  => $content->author_id ?? $content->user_id ?? null,
            default      => null,
        };
    }

    // ── 站外分享得积分 ──
    public function rewardShare(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content_type' => 'required|string|in:' . implode(',', array_keys(self::SHAREABLE_TYPES)),
            'content_id'   => 'required|integer',
            'platform'     => 'required|string|in:wechat,weibo,copy',
        ]);

        $userId = auth()->id();

        // 检查每日上限
        $todayRewards = ShareReward::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->sum('points_awarded');

        if ($todayRewards >= self::DAILY_SHARE_REWARD_LIMIT) {
            return ApiResponse::success([
                'rewarded' => false,
                'reason'   => 'daily_limit',
                'message'  => '今日分享奖励已达上限（' . self::DAILY_SHARE_REWARD_LIMIT . '积分）',
            ]);
        }

        // 检查是否已奖励过（同一内容同一平台只奖励一次）
        $alreadyRewarded = ShareReward::where('user_id', $userId)
            ->where('content_type', $validated['content_type'])
            ->where('content_id', $validated['content_id'])
            ->where('platform', $validated['platform'])
            ->exists();

        if ($alreadyRewarded) {
            return ApiResponse::success([
                'rewarded' => false,
                'reason'   => 'already_rewarded',
                'message'  => '该内容已奖励过',
            ]);
        }

        // 计算实际奖励（不超过每日剩余额度）
        $remaining = self::DAILY_SHARE_REWARD_LIMIT - $todayRewards;
        $points = min(self::SHARE_REWARD_POINTS, $remaining);

        DB::beginTransaction();
        try {
            // 记录奖励
            ShareReward::create([
                'user_id'        => $userId,
                'content_type'   => $validated['content_type'],
                'content_id'     => $validated['content_id'],
                'platform'       => $validated['platform'],
                'points_awarded' => $points,
            ]);

            // 发放积分
            $desc = '分享' . $this->contentTypeLabel($validated['content_type']) . '到' . $this->platformLabel($validated['platform']);
            UserPoint::earn($userId, $points, $desc);

            DB::commit();

            $balance = UserPoint::where('user_id', $userId)->value('balance');

            return ApiResponse::success([
                'rewarded' => true,
                'points'   => $points,
                'balance'  => (float) ($balance ?? 0),
                'message'  => "🎉 分享得 {$points} 积分！",
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return ApiResponse::error('奖励发放失败', 500);
        }
    }

    // ── 今日分享奖励状态 ──
    public function shareRewardStatus(): JsonResponse
    {
        $userId = auth()->id();
        $todayRewards = ShareReward::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->sum('points_awarded');

        return ApiResponse::success([
            'today_earned' => (float) $todayRewards,
            'daily_limit'  => self::DAILY_SHARE_REWARD_LIMIT,
            'remaining'    => max(0, self::DAILY_SHARE_REWARD_LIMIT - $todayRewards),
        ]);
    }

    private function contentTypeLabel(string $type): string
    {
        return match ($type) {
            'oa_article' => '互物号文章',
            'forum_post' => '广场帖子',
            'blog_post'  => '博客文章',
            'product'    => '商品',
            default      => '内容',
        };
    }

    private function platformLabel(string $platform): string
    {
        return match ($platform) {
            'wechat'  => '微信',
            'weibo'   => '微博',
            'copy'    => '复制链接',
            default   => $platform,
        };
    }

    // ── 管理员：给用户加积分 ──
    public function adminGrant(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'amount'      => 'required|numeric|min:1',
            'description' => 'required|string|max:500',
        ]);

        $points = UserPoint::earn(
            $validated['user_id'],
            (float) $validated['amount'],
            $validated['description']
        );

        return ApiResponse::success([
            'balance' => (float) $points->balance,
        ], '积分发放成功');
    }

    // ── 管理员：用户积分列表 ──
    public function adminUserList(Request $request): JsonResponse
    {
        $query = UserPoint::with('user:id,name,email,avatar')
            ->orderBy('balance', 'desc');

        // 搜索用户
        if ($keyword = $request->input('keyword')) {
            $query->whereHas('user', function($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        $users = $query->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($users);
    }

    // ── 管理员：积分交易流水 ──
    public function adminTransactions(Request $request): JsonResponse
    {
        $query = PointTransaction::with('user:id,name,email');

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $txns = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 30));

        return ApiResponse::paginated($txns);
    }

    // ── 管理员：积分统计概览 ──
    public function adminStats(): JsonResponse
    {
        $totalUsers = UserPoint::count();
        $totalBalance = UserPoint::sum('balance');
        $totalEarned = UserPoint::sum('total_earned');
        $totalSpent = UserPoint::sum('total_spent');
        $todayEarned = PointTransaction::where('type', 'earn')
            ->whereDate('created_at', today())->sum('amount');
        $todaySpent = PointTransaction::where('type', 'spend')
            ->whereDate('created_at', today())->sum('amount');

        return ApiResponse::success([
            'total_users'   => $totalUsers,
            'total_balance' => (float) $totalBalance,
            'total_earned'  => (float) $totalEarned,
            'total_spent'   => (float) $totalSpent,
            'today_earned'  => (float) $todayEarned,
            'today_spent'   => (float) $todaySpent,
        ]);
    }
}
