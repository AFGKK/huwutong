<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BugBountyReport;
use App\Services\BugBountyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BugBountyController extends Controller
{
    public function __construct(
        protected BugBountyService $bugBountyService
    ) {}

    /**
     * 公开: 提交漏洞报告
     */
    public function submitReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'steps_to_reproduce' => 'nullable|string',
            'impact' => 'nullable|string',
            'vulnerability_type' => 'nullable|string|max:100',
            'affected_endpoint' => 'nullable|string|max:255',
            'affected_version' => 'nullable|string|max:50',
            'reporter_email' => 'nullable|email|max:255',
            'reporter_name' => 'nullable|string|max:100',
            'reporter_handle' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $report = $this->bugBountyService->submitReport($request->all());

        return response()->json([
            'message' => 'Thank you for your submission. We will review it within 48 hours.',
            'report_id' => $report->id,
        ], 201);
    }

    /**
     * 公开: 获取安全政策内容
     */
    public function getPolicy(): JsonResponse
    {
        return response()->json(BugBountyService::getPolicyContent());
    }

    /**
     * 公开: 获取致谢墙
     */
    public function getHallOfFame(): JsonResponse
    {
        $hallOfFame = $this->bugBountyService->getHallOfFame();
        return response()->json($hallOfFame);
    }

    // ─── 管理员端点 ───

    /**
     * 管理员: 分页列表
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'severity', 'search', 'per_page']);
        $reports = $this->bugBountyService->listReports($filters);
        return response()->json($reports);
    }

    /**
     * 管理员: 获取单个报告详情
     */
    public function show(int $id): JsonResponse
    {
        $report = BugBountyReport::findOrFail($id);
        return response()->json($report);
    }

    /**
     * 管理员: 审核中
     */
    public function review(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'assigned_to' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $report = $this->bugBountyService->review($id, $request->input('assigned_to'));
        return response()->json(['message' => 'Report is now under review', 'report' => $report]);
    }

    /**
     * 管理员: 确认漏洞
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'nullable|string|in:critical,high,medium,low,informational',
            'bounty_amount' => 'nullable|numeric|min:0',
            'bounty_currency' => 'nullable|string|max:3',
            'is_public' => 'nullable|boolean',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $report = $this->bugBountyService->confirm($id, $request->all());
        return response()->json(['message' => 'Vulnerability confirmed', 'report' => $report]);
    }

    /**
     * 管理员: 标记已修复
     */
    public function markFixed(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $report = $this->bugBountyService->markFixed($id, $request->input('notes'));
        return response()->json(['message' => 'Report marked as fixed', 'report' => $report]);
    }

    /**
     * 管理员: 拒绝报告
     */
    public function decline(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $report = $this->bugBountyService->decline($id, $request->input('reason'));
        return response()->json(['message' => 'Report declined', 'report' => $report]);
    }

    /**
     * 管理员: 标记已打款
     */
    public function markPaid(int $id): JsonResponse
    {
        $report = $this->bugBountyService->markPaid($id);
        return response()->json(['message' => 'Payment completed', 'report' => $report]);
    }

    /**
     * 管理员: 统计数据
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->bugBountyService->getStats());
    }

    /**
     * 管理员: 删除报告
     */
    public function destroy(int $id): JsonResponse
    {
        $report = BugBountyReport::findOrFail($id);
        $report->delete();
        return response()->json(['message' => 'Report deleted']);
    }

    /**
     * 管理员: 管理致谢墙
     */
    public function updateHallOfFame(Request $request, int $id): JsonResponse
    {
        $fame = \App\Models\BugBountyHallOfFame::findOrFail($id);
        $fame->update($request->only([
            'hacker_name', 'avatar_url', 'bio', 'is_featured', 'sort_order', 'rank'
        ]));
        return response()->json(['message' => 'Hall of Fame entry updated', 'entry' => $fame]);
    }

    // ─── 公开页面 ───

    /**
     * security.txt (RFC 9116)
     */
    public function getSecurityTxt(): \Illuminate\Http\Response
    {
        $content = BugBountyService::getSecurityTxt();
        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    /**
     * 安全政策页面（Blade 视图）
     */
    public function getPolicyPage(): \Illuminate\View\View
    {
        $policy = BugBountyService::getPolicyContent();
        return view('public.security-policy', compact('policy'));
    }

    /**
     * 致谢墙页面（Blade 视图）
     */
    public function getHallOfFamePage(): \Illuminate\View\View
    {
        $hallOfFame = $this->bugBountyService->getHallOfFame();
        return view('public.hall-of-fame', compact('hallOfFame'));
    }
}
