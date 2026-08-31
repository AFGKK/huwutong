<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    /**
     * 创建投票
     */
    public function create(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:user_conversations,id',
            'question' => 'required|string|max:500',
            'type' => 'required|in:single,multiple,ranked',
            'options' => 'required|array|min:2|max:20',
            'options.*' => 'required|string|max:200',
            'is_anonymous' => 'nullable|boolean',
            'is_hide_results' => 'nullable|boolean',
            'max_choices' => 'nullable|integer|min:1|max:20',
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
        ]);

        $myId = $request->user()->id;

        // 验证参与者
        $isParticipant = ConversationParticipant::where('conversation_id', $validated['conversation_id'])
            ->where('user_id', $myId)->whereNull('deleted_at')->exists();
        if (!$isParticipant) {
            return ApiResponse::error(__('app.api.poll.not_participant'));
        }

        return DB::transaction(function () use ($validated, $myId) {
            $poll = Poll::create([
                'conversation_id' => $validated['conversation_id'],
                'creator_id' => $myId,
                'question' => $validated['question'],
                'type' => $validated['type'],
                'is_anonymous' => $validated['is_anonymous'] ?? false,
                'is_hide_results' => $validated['is_hide_results'] ?? false,
                'max_choices' => $validated['max_choices'] ?? match ($validated['type']) {
                    'multiple' => 3,
                    'ranked' => count($validated['options']),
                    default => 1,
                },
                'expires_at' => !empty($validated['expires_in_hours'])
                    ? now()->addHours($validated['expires_in_hours']) : null,
            ]);

            foreach ($validated['options'] as $i => $label) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'label' => $label,
                    'sort_order' => $i,
                ]);
            }

            // 发送系统消息到会话
            $msg = ConversationMessage::create([
                'conversation_id' => $poll->conversation_id,
                'sender_id' => $myId,
                'message_type' => 'poll',
                'content' => $poll->question,
                'metadata' => [
                    'type' => 'poll_card',
                    'poll_id' => $poll->id,
                    'options' => $poll->options()->pluck('label')->toArray(),
                    'poll_type' => $poll->type,
                ],
            ]);

            return ApiResponse::success(
                $poll->load('options'),
                __('app.api.poll.created'),
                201,
            );
        });
    }

    /**
     * 获取单条投票详情
     */
    public function show(int $pollId, Request $request): \Illuminate\Http\JsonResponse
    {
        $poll = Poll::with('options')->findOrFail($pollId);
        $myId = $request->user()->id;
        $isCreator = $poll->creator_id === $myId;

        $myVotes = PollVote::where('poll_id', $pollId)
            ->where('user_id', $myId)
            ->with('option:id,label')
            ->get();

        $totalVoters = PollVote::where('poll_id', $pollId)->distinct('user_id')->count('user_id');
        $results = $poll->getResults();

        // 如果隐藏结果且未截止且非创建者，不返回具体结果
        if ($poll->is_hide_results && !$poll->is_closed && !$isCreator) {
            $results = [];
        }

        return ApiResponse::success([
            'poll' => $poll->only(['id', 'conversation_id', 'question', 'type', 'is_anonymous', 'is_hide_results', 'is_closed', 'expires_at', 'max_choices', 'created_at']),
            'options' => $poll->options->map(fn($o) => ['id' => $o->id, 'label' => $o->label]),
            'results' => $results,
            'my_votes' => $myVotes->map(fn($v) => ['option_id' => $v->option_id, 'label' => $v->option?->label, 'rank' => $v->rank]),
            'total_voters' => $totalVoters,
            'is_creator' => $isCreator,
        ]);
    }

    /**
     * 投票
     */
    public function vote(int $pollId, Request $request): \Illuminate\Http\JsonResponse
    {
        $poll = Poll::findOrFail($pollId);

        if ($poll->is_closed) {
            return ApiResponse::error('POLL_CLOSED', __('app.api.poll.ended'));
        }
        if ($poll->expires_at && $poll->expires_at->isPast()) {
            $poll->update(['is_closed' => true, 'closed_at' => now()]);
            return ApiResponse::error('POLL_EXPIRED', __('app.api.poll.expired'));
        }

        $validated = $request->validate([
            'votes' => 'required|array|min:1',
            'votes.*.option_id' => 'required|exists:poll_options,id',
            'votes.*.rank' => 'nullable|integer|min:0',
        ]);

        $myId = $request->user()->id;

        // 验证选项属于该投票
        $validOptionIds = $poll->options()->pluck('id')->toArray();
        foreach ($validated['votes'] as $v) {
            if (!in_array($v['option_id'], $validOptionIds)) {
                return ApiResponse::error('INVALID_OPTION', __('app.api.poll.invalid_option'));
            }
        }

        // 检查选项数量限制
        if (count($validated['votes']) > $poll->max_choices) {
            return ApiResponse::error('TOO_MANY_CHOICES', __('app.api.poll.too_many_choices', ['max' => $poll->max_choices]));
        }

        DB::transaction(function () use ($poll, $myId, $validated) {
            // 清除用户之前的投票
            PollVote::where('poll_id', $poll->id)->where('user_id', $myId)->delete();

            // 插入新投票
            foreach ($validated['votes'] as $i => $v) {
                PollVote::create([
                    'poll_id' => $poll->id,
                    'option_id' => $v['option_id'],
                    'user_id' => $myId,
                    'rank' => $v['rank'] ?? ($poll->type === 'ranked' ? $i + 1 : 0),
                ]);
            }
        });

        return ApiResponse::success([
            'poll_id' => $poll->id,
            'total_votes' => $poll->fresh()->total_votes,
        ], __('app.api.poll.voted'));
    }

    /**
     * 获取投票结果
     */
    public function results(int $pollId, Request $request): \Illuminate\Http\JsonResponse
    {
        $poll = Poll::with('options')->findOrFail($pollId);
        $myId = $request->user()->id;

        $results = $poll->getResults();
        $myVotes = PollVote::where('poll_id', $pollId)
            ->where('user_id', $myId)
            ->with('option:id,label')
            ->get();

        $totalUniqueVoters = PollVote::where('poll_id', $pollId)
            ->distinct('user_id')->count('user_id');

        // 匿名投票不显示具体投票人
        $voterDetails = [];
        if (!$poll->is_anonymous) {
            $voterDetails = PollVote::where('poll_id', $pollId)
                ->with('user:id,name')
                ->get()
                ->groupBy('option_id')
                ->map(fn($votes) => $votes->pluck('user.name')->unique()->values())
                ->toArray();
        }

        return ApiResponse::success([
            'poll' => $poll->only(['id', 'question', 'type', 'is_anonymous', 'is_hide_results', 'is_closed', 'expires_at']),
            'results' => $results,
            'my_votes' => $myVotes->map(fn($v) => [
                'option_id' => $v->option_id,
                'label' => $v->option?->label,
                'rank' => $v->rank,
            ]),
            'total_voters' => $totalUniqueVoters,
            'voter_details' => $poll->is_anonymous ? null : $voterDetails,
        ]);
    }

    /**
     * 关闭投票（创建者或管理员）
     */
    public function close(int $pollId, Request $request): \Illuminate\Http\JsonResponse
    {
        $poll = Poll::findOrFail($pollId);
        $myId = $request->user()->id;

        if ($poll->creator_id !== $myId) {
            // 检查是否为群管理员
            $isAdmin = ConversationParticipant::where('conversation_id', $poll->conversation_id)
                ->where('user_id', $myId)
                ->whereIn('role', ['creator', 'admin'])
                ->exists();
            if (!$isAdmin) {
                return ApiResponse::error('FORBIDDEN', __('app.api.poll.close_forbidden'));
            }
        }

        $poll->update(['is_closed' => true, 'closed_at' => now()]);

        return ApiResponse::success(null, __('app.api.poll.closed'));
    }

    /**
     * 获取会话中的活跃投票列表
     */
    public function conversationPolls(int $convId): \Illuminate\Http\JsonResponse
    {
        $polls = Poll::where('conversation_id', $convId)
            ->withCount('votes')
            ->with('options')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::success($polls);
    }
}
