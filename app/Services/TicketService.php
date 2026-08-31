<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketReply;
use App\Models\TicketSatisfaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 工单/支持系统服务
 */
class TicketService
{
    /**
     * SLA 配置（按优先级，分钟）
     */
    private const SLA_CONFIG = [
        'urgent' => ['response' => 30, 'resolve' => 240],
        'high' => ['response' => 60, 'resolve' => 480],
        'medium' => ['response' => 240, 'resolve' => 1440],
        'low' => ['response' => 480, 'resolve' => 2880],
    ];

    /**
     * 创建工单
     */
    public function create(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $priority = $data['priority'] ?? 'medium';
            $slaConfig = self::SLA_CONFIG[$priority] ?? self::SLA_CONFIG['medium'];

            $ticket = Ticket::create(array_merge($data, [
                'sla_minutes' => $slaConfig['response'],
                'sla_due_at' => now()->addMinutes($slaConfig['response']),
            ]));

            // 自动分配
            $this->autoAssign($ticket);

            Log::info('Ticket: created', [
                'ticket_id' => $ticket->id,
                'priority' => $priority,
                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            ]);

            return $ticket;
        });
    }

    /**
     * 回复工单
     */
    public function reply(Ticket $ticket, string $content, ?User $user = null, bool $isInternal = false): TicketReply
    {
        $reply = DB::transaction(function () use ($ticket, $content, $user, $isInternal) {
            $reply = $ticket->replies()->create([
                'user_id' => $user?->id,
                'content' => $content,
                'is_internal' => $isInternal,
            ]);

            // 如果不是内部备注，更新工单状态
            if (!$isInternal) {
                $ticket->recordFirstResponse();
                if ($ticket->isOpen()) {
                    $ticket->update(['status' => 'replied']);
                }
            }

            return $reply;
        });

        Log::info('Ticket: replied', [
            'ticket_id' => $ticket->id,
            'reply_id' => $reply->id,
            'is_internal' => $isInternal,
        ]);

        return $reply;
    }

    /**
     * 关闭工单
     */
    public function close(Ticket $ticket): Ticket
    {
        $ticket->close();

        Log::info('Ticket: closed', ['ticket_id' => $ticket->id]);
        return $ticket->fresh();
    }

    /**
     * 解决工单
     */
    public function resolve(Ticket $ticket): Ticket
    {
        $ticket->resolve();

        Log::info('Ticket: resolved', ['ticket_id' => $ticket->id]);
        return $ticket->fresh();
    }

    /**
     * 重新打开工单
     */
    public function reopen(Ticket $ticket): Ticket
    {
        if ($ticket->isClosed()) {
            $ticket->reopen();
            Log::info('Ticket: reopened', ['ticket_id' => $ticket->id]);
        }
        return $ticket->fresh();
    }

    /**
     * 分配工单
     */
    public function assign(Ticket $ticket, int $userId): Ticket
    {
        $user = User::findOrFail($userId);
        $ticket->assignTo($user);

        Log::info('Ticket: assigned', [
            'ticket_id' => $ticket->id,
            'assigned_to' => $userId,
        ]);

        return $ticket->fresh();
    }

    /**
     * 提交满意度评价
     */
    public function submitSatisfaction(Ticket $ticket, int $score, ?string $comment = null): TicketSatisfaction
    {
        if ($ticket->satisfaction) {
            throw new \RuntimeException(__("app.ticket.ticket_already_rated"));
        }

        $satisfaction = TicketSatisfaction::create([
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'score' => $score,
            'comment' => $comment,
        ]);

        Log::info('Ticket: satisfaction submitted', [
            'ticket_id' => $ticket->id,
            'score' => $score,
        ]);

        return $satisfaction;
    }

    /**
     * 处理 SLA 检查
     */
    public function checkSla(): array
    {
        $stats = ['warnings' => 0, 'breaches' => 0];

        // 即将超时的工单（15分钟内）
        $warningTickets = Ticket::whereIn('status', ['open', 'pending', 'replied'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addMinutes(15))
            ->where('sla_due_at', '>', now())
            ->get();

        foreach ($warningTickets as $ticket) {
            $ticket->slaEvents()->create([
                'event_type' => 'sla_warning',
                'triggered_at' => now(),
            ]);
            $stats['warnings']++;

            Log::warning('Ticket: SLA warning', [
                'ticket_id' => $ticket->id,
                'sla_due_at' => $ticket->sla_due_at->toIso8601String(),
            ]);
        }

        // 已超时的工单
        $breachedTickets = Ticket::whereIn('status', ['open', 'pending', 'replied'])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->whereDoesntHave('slaEvents', fn($q) => $q->where('event_type', 'sla_breach'))
            ->get();

        foreach ($breachedTickets as $ticket) {
            $ticket->slaEvents()->create([
                'event_type' => 'sla_breach',
                'triggered_at' => now(),
            ]);
            $stats['breaches']++;

            Log::error('Ticket: SLA breached', [
                'ticket_id' => $ticket->id,
                'sla_due_at' => $ticket->sla_due_at->toIso8601String(),
            ]);
        }

        return $stats;
    }

    /**
     * 获取统计
     */
    public function getStats(): array
    {
        return [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'replied' => Ticket::where('status', 'replied')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'urgent' => Ticket::where('priority', 'urgent')->whereIn('status', ['open', 'replied'])->count(),
            'sla_breached' => Ticket::whereIn('status', ['open', 'replied'])->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            'avg_response_minutes' => (float) (Ticket::query()
                ->whereNotNull('first_response_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (first_response_at - created_at)) / 60) as avg_minutes')
                ->value('avg_minutes') ?? 0),
            'satisfaction_score' => round(TicketSatisfaction::avg('score') ?? 0, 1),
        ];
    }

    /**
     * 自动分配
     */
    protected function autoAssign(Ticket $ticket): void
    {
        // 找负载最轻的支持人员
        $supportUser = User::whereHas('roles', fn($q) => $q->where('name', '客服'))
            ->withCount(['assignedTickets' => fn($q) => $q->whereIn('status', ['open', 'replied'])])
            ->orderBy('assigned_tickets_count')
            ->first();

        if ($supportUser) {
            $ticket->assignTo($supportUser);
        }
    }
}
