<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\BlockchainLicense;
use App\Models\McpServer;
use App\Models\AiAgent;
use App\Models\ServerlessFunction;
use App\Models\EdgeNode;
use App\Services\BlockchainLicenseService;
use App\Services\McpAuthService;
use App\Services\ServerlessAuthService;
use App\Services\EdgeAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 创新授权管理 (M3-14~17)
 */
class InnovationAuthController extends Controller
{
    public function __construct(
        protected BlockchainLicenseService $blockchain,
        protected McpAuthService $mcp,
        protected ServerlessAuthService $serverless,
        protected EdgeAuthService $edge,
    ) {}

    // ═══════════ M3-14 区块链 License ═══════════

    public function blockchainDashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->blockchain->getDashboard($request->user()->tenant_id));
    }

    public function blockchainList(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            BlockchainLicense::with('license')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function createChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate(['wallet_address' => 'required|string|max:100']);
        return ApiResponse::success($this->blockchain->createChallenge($validated['wallet_address']));
    }

    public function verifyWallet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string',
            'signature' => 'required|string',
            'nonce' => 'required|string',
        ]);

        $verified = $this->blockchain->verifyWalletSignature(
            $validated['wallet_address'], $validated['signature'], $validated['nonce']
        );

        return ApiResponse::success(['verified' => $verified]);
    }

    // ═══════════ M3-15 MCP / AI Agent ═══════════

    public function mcpDashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->mcp->getDashboard($request->user()->tenant_id));
    }

    public function mcpServers(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            McpServer::where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function registerMcp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'protocol' => 'nullable|in:stdio,sse,websocket',
            'endpoint' => 'nullable|string|max:500',
            'capabilities' => 'nullable|array',
        ]);

        $server = $this->mcp->registerServer($request->user()->tenant_id, $validated);
        return ApiResponse::created($server, __("app.innovation_auth.msg_fe12b5a5"));
    }

    public function aiAgents(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            AiAgent::where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function registerAgent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'framework' => 'nullable|string|max:50',
            'capabilities' => 'nullable|array',
            'monthly_token_quota' => 'nullable|integer|min:0',
        ]);

        $agent = $this->mcp->registerAgent($request->user()->tenant_id, $validated);
        return ApiResponse::created($agent, __("app.innovation_auth.msg_2bd93bbe"));
    }

    public function checkAgentQuota(Request $request, AiAgent $agent): JsonResponse
    {
        return ApiResponse::success($this->mcp->checkTokenQuota($agent->id));
    }

    // ═══════════ M3-16 Serverless ═══════════

    public function serverlessDashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->serverless->getDashboard($request->user()->tenant_id));
    }

    public function serverlessFunctions(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            ServerlessFunction::where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function registerFunction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'runtime' => 'nullable|string|max:30',
            'qps_limit' => 'nullable|integer|min:1',
            'monthly_invocation_limit' => 'nullable|integer|min:0',
            'timeout_seconds' => 'nullable|integer|min:1|max:300',
        ]);

        $func = $this->serverless->registerFunction($request->user()->tenant_id, $validated);
        return ApiResponse::created($func, __("app.innovation_auth.msg_c2578ea1"));
    }

    public function generateServerlessToken(Request $request, ServerlessFunction $function): JsonResponse
    {
        return ApiResponse::success($this->serverless->generateToken($function));
    }

    // ═══════════ M3-17 Edge ═══════════

    public function edgeDashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->edge->getDashboard($request->user()->tenant_id));
    }

    public function edgeNodes(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            EdgeNode::where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function registerEdgeNode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'node_type' => 'nullable|in:cloudflare,akamai,fastly,custom',
            'region' => 'nullable|string|max:100',
            'geo_allowed' => 'nullable|array',
        ]);

        $node = $this->edge->registerNode($request->user()->tenant_id, $validated);
        return ApiResponse::created($node, __("app.innovation_auth.msg_b172441c"));
    }

    // ═══════════ 批量更新状态 ═══════════

    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:blockchain,mcp,agent,serverless,edge',
            'id' => 'required|integer',
            'status' => 'required|string|in:active,paused,suspended,decommissioned',
        ]);

        $model = match ($validated['type']) {
            'blockchain' => BlockchainLicense::class,
            'mcp' => McpServer::class,
            'agent' => AiAgent::class,
            'serverless' => ServerlessFunction::class,
            'edge' => EdgeNode::class,
        };

        $record = $model::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($validated['id']);
        $record->update(['status' => $validated['status']]);

        return ApiResponse::success($record, __("app.innovation_auth.msg_7d6e0e1a"));
    }
}
