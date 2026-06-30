<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LicenseNoteController extends Controller
{
    /**
     * 获取 License 的所有备注
     */
    public function index(License $license): JsonResponse
    {
        $this->authorize('view', $license);

        $notes = $license->notes()
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(function ($note) {
                $note->setRelation('mentioned_users', $this->resolveMentions($note->mentions));
                return $note;
            });

        return ApiResponse::success($notes);
    }

    /**
     * 添加备注
     */
    public function store(Request $request, License $license): JsonResponse
    {
        $this->authorize('view', $license);

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'mentions' => 'nullable|array',
            'mentions.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $note = $license->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $validator->validated()['content'],
            'mentions' => $validator->validated()['mentions'] ?? [],
        ]);

        $note->load('user:id,name,email');

        return ApiResponse::created($note, '备注已添加');
    }

    /**
     * 删除备注
     */
    public function destroy(Request $request, License $license, LicenseNote $note): JsonResponse
    {
        $this->authorize('view', $license);

        if ($note->user_id !== $request->user()->id && ! $request->user()->hasPermissionTo('super-admin')) {
            return ApiResponse::error('FORBIDDEN', '只能删除自己的备注', 403);
        }

        $note->delete();

        return ApiResponse::success(null, '备注已删除');
    }

    /**
     * @mention 用户搜索
     * GET /api/users/search?q=
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $query = User::select('id', 'name', 'email')
            ->where('status', 'active');

        if (strlen($q) > 0) {
            $query->where(function ($qry) use ($q) {
                $qry->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $users = $query->limit(20)->get();

        return ApiResponse::success($users);
    }

    /**
     * 解析 @提及 的用户信息
     */
    private function resolveMentions(?array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        return User::whereIn('id', $userIds)
            ->select('id', 'name', 'email')
            ->get()
            ->toArray();
    }
}
