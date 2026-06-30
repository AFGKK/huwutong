<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GraphQLService;
use Illuminate\Http\Request;

class GraphQLController extends Controller
{
    public function __construct(
        protected GraphQLService $graphQL,
    ) {}

    /**
     * POST /api/graphql
     * 执行 GraphQL 查询
     *
     * Body:
     * {
     *   "query": {
     *     "type": "License",
     *     "fields": ["id", "license_key", "status", "product", { "fields": ["id", "name"] }],
     *     "args": {
     *       "filter": { "status": "active" },
     *       "sort": [{ "field": "created_at", "direction": "desc" }],
     *       "page": 1,
     *       "per_page": 20
     *     }
     *   }
     * }
     */
    public function query(Request $request)
    {
        $request->validate([
            'query' => 'required|array',
            'query.type' => 'required|string',
            'query.fields' => 'sometimes|array',
            'query.args' => 'sometimes|array',
            'batch' => 'sometimes|array',
        ]);

        $context = [
            'tenant_id' => $request->user()?->tenant_id,
            'user_id' => $request->user()?->id,
        ];

        // 批量查询
        if ($request->has('batch')) {
            $results = $this->graphQL->executeBatch($request->input('batch'), $context);
            return ApiResponse::success($results);
        }

        $result = $this->graphQL->execute($request->input('query'), $context);
        return ApiResponse::success($result);
    }

    /**
     * GET /api/graphql/schema
     * 获取 schema 信息
     */
    public function schema()
    {
        return ApiResponse::success($this->graphQL->getSchema());
    }

    /**
     * GET /api/graphql/explorer
     * GraphQL Explorer 页面
     */
    public function explorer()
    {
        return response()->json([
            'types' => $this->graphQL->getSchema(),
            'endpoint' => url('/api/graphql'),
        ]);
    }
}
