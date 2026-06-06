<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
    ) {}

    /**
     * 工单列表（管理）
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        $query = Ticket::with([
            'customer.user:id,name',
            'user:id,name',
            'category:id,name',
            'assignee:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    /**
     * 创建工单（公开）
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:300',
            'description' => 'required|string|min:20',
            'category_id' => 'sometimes|exists:ticket_categories,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ticket = $this->ticketService->create([
            'tenant_id' => $request->user()->tenant_id,
            'customer_id' => $request->user()->customer_id,
            'user_id' => $request->user()->id,
            'category_id' => $request->input('category_id'),
            'subject' => $request->input('subject'),
            'description' => $request->input('description'),
            'priority' => $request->input('priority', 'medium'),
            'source' => 'portal',
            'tags' => $request->input('tags'),
        ]);

        return response()->json([
            'success' => true,
            'message' => '工单已提交',
            'data' => $ticket->load('category:id,name'),
        ], 201);
    }

    /**
     * 工单详情
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'customer.user:id,name,email',
            'user:id,name',
            'category:id,name',
            'assignee:id,name',
            'publicReplies.user:id,name',
            'satisfaction',
        ]);

        return response()->json(['success' => true, 'data' => $ticket]);
    }

    /**
     * 回复工单
     */
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|min:1',
            'is_internal' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $reply = $this->ticketService->reply(
            $ticket,
            $request->input('content'),
            $request->user(),
            $request->input('is_internal', false)
        );

        return response()->json([
            'success' => true,
            'message' => '回复成功',
            'data' => $reply,
        ]);
    }

    /**
     * 解决工单
     */
    public function resolve(Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $this->ticketService->resolve($ticket);

        return response()->json([
            'success' => true,
            'message' => '工单已标记为已解决',
        ]);
    }

    /**
     * 关闭工单
     */
    public function close(Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $this->ticketService->close($ticket);

        return response()->json([
            'success' => true,
            'message' => '工单已关闭',
        ]);
    }

    /**
     * 重新打开工单
     */
    public function reopen(Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $this->ticketService->reopen($ticket);

        return response()->json([
            'success' => true,
            'message' => '工单已重新打开',
        ]);
    }

    /**
     * 分配工单
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->ticketService->assign($ticket, $request->input('user_id'));

        return response()->json([
            'success' => true,
            'message' => '已分配',
        ]);
    }

    /**
     * 提交满意度评价
     */
    public function satisfaction(Request $request, Ticket $ticket): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'score' => 'required|integer|min:1|max:5',
            'comment' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $this->ticketService->submitSatisfaction(
                $ticket,
                $request->input('score'),
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => '感谢您的评价']);
    }

    /**
     * 工单分类列表
     */
    public function categories(): JsonResponse
    {
        $categories = TicketCategory::active()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'description']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * 管理工单分类
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('create', TicketCategory::class);

        TicketCategory::create($request->validate([
            'name' => 'required|string|max:100',
            'description' => 'sometimes|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
        ]));

        return response()->json(['success' => true, 'message' => '分类已创建'], 201);
    }

    /**
     * 删除分类
     */
    public function destroyCategory(TicketCategory $category): JsonResponse
    {
        $this->authorize('delete', TicketCategory::class);

        if ($category->tickets()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '该分类下还有工单，无法删除',
            ], 422);
        }

        $category->delete();
        return response()->json(['success' => true, 'message' => '分类已删除']);
    }

    /**
     * 统计
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);

        return response()->json([
            'success' => true,
            'data' => $this->ticketService->getStats(),
        ]);
    }

    /**
     * SLA 检查（定时任务调用）
     */
    public function checkSla(): JsonResponse
    {
        $result = $this->ticketService->checkSla();

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 客户自己的工单列表
     */
    public function myTickets(Request $request): JsonResponse
    {
        $tickets = Ticket::where('user_id', $request->user()->id)
            ->with('category:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $tickets]);
    }
}
