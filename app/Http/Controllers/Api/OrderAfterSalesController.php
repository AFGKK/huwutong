<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\OrderAfterSalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 🛒 订单售后工单控制器 (M2-157)
 */
class OrderAfterSalesController extends Controller
{
    public function __construct(
        protected OrderAfterSalesService $afterSales,
    ) {}

    /**
     * 分页获取所有售后工单
     * GET /api/admin/order-after-sales/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $filters = $request->only(['status', 'priority', 'reason', 'order_id', 'keyword', 'date_from', 'date_to', 'per_page', 'sort_by', 'sort_order']);
        return ApiResponse::success($this->afterSales->listTickets($tenantId, $filters));
    }

    /**
     * 查看工单详情
     * GET /api/admin/order-after-sales/tickets/{ticket}
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $detail = $this->afterSales->getTicketDetail($ticket->id, $tenantId);
        if (!$detail) {
            return ApiResponse::notFound('工单不存在');
        }
        return ApiResponse::success($detail);
    }

    /**
     * 创建售后工单
     * POST /api/admin/order-after-sales/tickets
     */
    public function createTicket(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'customer_id' => 'required|exists:customers,id',
            'reason' => 'required|string|max:50',
            'description' => 'required|string|max:5000',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $ticket = $this->afterSales->createTicket(
            $order,
            $validated['customer_id'],
            $tenantId,
            $validated['reason'],
            $validated['description'],
            auth()->id(),
        );

        return ApiResponse::created($ticket->toArray(), '售后工单已创建');
    }

    /**
     * 获取订单关联工单
     * GET /api/admin/order-after-sales/orders/{order}/tickets
     */
    public function orderTickets(Order $order): JsonResponse
    {
        return ApiResponse::success($this->afterSales->getOrderTickets($order));
    }

    /**
     * 回复工单
     * POST /api/admin/order-after-sales/tickets/{ticket}/reply
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($ticket->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权操作此工单');
        }

        $validated = $request->validate([
            'content' => 'required|string|max:10000',
            'is_internal' => 'boolean',
        ]);

        $reply = $this->afterSales->replyTicket(
            $ticket,
            $validated['content'],
            auth()->user(),
            $validated['is_internal'] ?? false,
        );

        return ApiResponse::created($reply->toArray(), '回复成功');
    }

    /**
     * 解决工单
     * POST /api/admin/order-after-sales/tickets/{ticket}/resolve
     */
    public function resolve(Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($ticket->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权操作此工单');
        }
        $ticket = $this->afterSales->resolveTicket($ticket);
        return ApiResponse::success($ticket->toArray(), '工单已标记为解决');
    }

    /**
     * 关闭工单
     * POST /api/admin/order-after-sales/tickets/{ticket}/close
     */
    public function close(Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($ticket->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权操作此工单');
        }
        $ticket = $this->afterSales->closeTicket($ticket);
        return ApiResponse::success($ticket->toArray(), '工单已关闭');
    }

    /**
     * 分配工单
     * POST /api/admin/order-after-sales/tickets/{ticket}/assign
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($ticket->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权操作此工单');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $ticket = $this->afterSales->assignTicket($ticket, $validated['user_id']);
        return ApiResponse::success($ticket->toArray(), '工单已分配');
    }

    /**
     * 提交满意度评价
     * POST /api/admin/order-after-sales/tickets/{ticket}/satisfaction
     */
    public function satisfaction(Request $request, Ticket $ticket): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        if ($ticket->tenant_id !== $tenantId) {
            return ApiResponse::forbidden('无权操作此工单');
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $this->afterSales->submitSatisfaction($ticket, $validated['score'], $validated['comment']);
            return ApiResponse::created($result->toArray(), '评价已提交');
        } catch (\RuntimeException $e) {
            return ApiResponse::validationError($e->getMessage());
        }
    }

    /**
     * 获取售后原因列表
     * GET /api/admin/order-after-sales/reasons
     */
    public function reasons(): JsonResponse
    {
        return ApiResponse::success($this->afterSales->getReasons());
    }

    /**
     * 售后统计
     * GET /api/admin/order-after-sales/stats
     */
    public function stats(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        return ApiResponse::success($this->afterSales->getStats($tenantId));
    }
}
