<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SlashCommandService;
use Illuminate\Http\Request;

class SlashCommandController extends Controller
{
    public function __construct(protected SlashCommandService $commandService) {}

    /**
     * 执行快捷指令
     */
    public function execute(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:user_conversations,id',
        ]);

        $result = $this->commandService->execute(
            $validated['text'],
            $request->user()->id,
            $validated['conversation_id'] ?? null,
        );

        return ApiResponse::success($result);
    }

    /**
     * 获取所有可用命令
     */
    public function commands(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'commands' => $this->commandService->getCommands(),
        ]);
    }
}
