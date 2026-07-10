<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\DbSql;
use App\Services\TicketService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🛒 订单售后工单服务 (M2-157)
 *
 * 基于 TicketService(M2-105) 构建订单级售后工单
 */
class OrderAfterSalesService
{
    public function __construct(
        protected TicketService $ticketService,
    ) {}

    /**
     * 创建售后工单
     */
    public function createTicket(Order $order, int $customerId, int $tenantId, string $reason, string $description, ?int $userId = null): Ticket
    {
        $maxTickets = config('order-ticket.max_tickets_per_order', 3);
        $existingCount = Ticket::where('source', 'order')
            ->where('metadata->order_id', $order->id)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->count();

        if ($existingCount >= $maxTickets) {
            throw new \RuntimeException("该订单已有 {$existingCount} 个未关闭售后工单，请先处理完成后再创建");
        }

        $reasons = config('order-ticket.reasons', []);
        $reasonConfig = $reasons[$reason] ?? $reasons['other'];

        $data = [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'user_id' => $userId,
            'subject' => "[订单 #{$order->id}] {$reasonConfig['label']}",
            'description' => $this->buildDescription($order, $reason, $description),
            'priority' => $reasonConfig['priority'] ?? 'medium',
            'source' => 'order',
            'metadata' => [
                'order_id' => $order->id,
                'order_total' => $order->total_amount,
                'order_status' => $order->status,
                'reason' => $reason,
                'sku_items' => $order->items?->map(fn($i) => [
                    'sku_id' => $i->sku_id,
                    'product_name' => $i->product_name,
                    'quantity' => $i->quantity,
                    'price' => $i->price,
                ]),
            ],
        ];

        $ticket = DB::transaction(function () use ($data, $order) {
            $ticket = $this->ticketService->create($data);

            // 更新订单售后状态
            $order->update(['after_sales_status' => 'pending']);

            return $ticket;
        });

        Log::info('售后工单已创建', [
            'ticket_id' => $ticket->id,
            'order_id' => $order->id,
            'reason' => $reason,
        ]);

        return $ticket;
    }

    /**
     * 获取订单关联工单
     */
    public function getOrderTickets(Order $order): array
    {
        return Ticket::where('tenant_id', $order->tenant_id)
            ->where('source', 'order')
            ->where('metadata->order_id', $order->id)
            ->with('customer', 'assignee', 'replies', 'satisfaction')
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /**
     * 分页获取所有售后工单
     */
    public function listTickets(int $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::where('tenant_id', $tenantId)
            ->where('source', 'order')
            ->with('customer', 'assignee', 'satisfaction');

        // 按状态筛选
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 按优先级筛选
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // 按原因筛选
        if (!empty($filters['reason'])) {
            $query->where('metadata->reason', $filters['reason']);
        }

        // 按订单号搜索
        if (!empty($filters['order_id'])) {
            $query->where('metadata->order_id', $filters['order_id']);
        }

        // 按关键词搜索
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('subject', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // 日期范围
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;
        $sortField = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortField, $sortOrder)->paginate($perPage);
    }

    /**
     * 获取工单详情
     */
    public function getTicketDetail(int $ticketId, int $tenantId): ?Ticket
    {
        return Ticket::where('id', $ticketId)
            ->where('tenant_id', $tenantId)
            ->where('source', 'order')
            ->with('customer', 'assignee', 'replies.user', 'satisfaction')
            ->first();
    }

    /**
     * 回复工单
     */
    public function replyTicket(Ticket $ticket, string $content, ?User $user = null, bool $isInternal = false): TicketReply
    {
        $reply = $this->ticketService->reply($ticket, $content, $user, $isInternal);

        // 更新订单售后状态
        if ($ticket->metadata && isset($ticket->metadata['order_id'])) {
            $order = Order::find($ticket->metadata['order_id']);
            if ($order && $order->after_sales_status === 'pending') {
                $order->update(['after_sales_status' => 'in_progress']);
            }
        }

        return $reply;
    }

    /**
     * 解决工单
     */
    public function resolveTicket(Ticket $ticket): Ticket
    {
        $ticket = $this->ticketService->resolve($ticket);

        // 更新订单售后状态
        if ($ticket->metadata && isset($ticket->metadata['order_id'])) {
            Order::where('id', $ticket->metadata['order_id'])
                ->update(['after_sales_status' => 'resolved']);
        }

        return $ticket;
    }

    /**
     * 关闭工单
     */
    public function closeTicket(Ticket $ticket): Ticket
    {
        $ticket = $this->ticketService->close($ticket);

        // 更新订单售后状态
        if ($ticket->metadata && isset($ticket->metadata['order_id'])) {
            Order::where('id', $ticket->metadata['order_id'])
                ->update(['after_sales_status' => 'closed']);
        }

        return $ticket;
    }

    /**
     * 分配工单
     */
    public function assignTicket(Ticket $ticket, int $userId): Ticket
    {
        return $this->ticketService->assign($ticket, $userId);
    }

    /**
     * 提交满意度评价
     */
    public function submitSatisfaction(Ticket $ticket, int $score, ?string $comment = null): \App\Models\TicketSatisfaction
    {
        return $this->ticketService->submitSatisfaction($ticket, $score, $comment);
    }

    /**
     * 获取售后统计
     */
    public function getStats(int $tenantId): array
    {
        $query = Ticket::where('tenant_id', $tenantId)->where('source', 'order');

        return [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'by_reason' => (clone $query)->selectRaw(DbSql::jsonExtract('metadata', 'reason').' as reason, COUNT(*) as count')
                ->groupBy('reason')->pluck('count', 'reason')->toArray(),
            'by_priority' => (clone $query)->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')->pluck('count', 'priority')->toArray(),
            'avg_response_time' => (clone $query)->whereNotNull('first_response_at')
                ->selectRaw('AVG('.DbSql::timestampDiff('MINUTE', 'created_at', 'first_response_at').') as avg_mins')
                ->value('avg_mins'),
        ];
    }

    /**
     * 获取可用的售后原因列表
     */
    public function getReasons(): array
    {
        return config('order-ticket.reasons', []);
    }

    protected function buildDescription(Order $order, string $reason, string $description): string
    {
        $items = '';
        if ($order->items) {
            foreach ($order->items as $item) {
                $items .= "- {$item->product_name} x{$item->quantity} ¥{$item->price}\n";
            }
        }

        return <<<TEXT
【售后原因】{$reason}
【订单编号】#{$order->id}
【订单金额】¥{$order->total_amount}
【订单状态】{$order->status}
【商品明细】
{$items}
【问题描述】
{$description}
TEXT;
    }
}
