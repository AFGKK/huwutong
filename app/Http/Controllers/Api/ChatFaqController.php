<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatFaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = ChatFaq::active()->get();
        return response()->json(['success' => true, 'data' => $faqs]);
    }

    public function adminIndex(): JsonResponse
    {
        $faqs = ChatFaq::orderBy('sort_order')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $faqs]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'question' => 'required|string|max:200',
            'answer' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $faq = ChatFaq::create($request->only(['question', 'answer', 'icon', 'sort_order', 'is_active']));
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function show(ChatFaq $faq): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function update(Request $request, ChatFaq $faq): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'question' => 'required|string|max:200',
            'answer' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $faq->update($request->only(['question', 'answer', 'icon', 'sort_order', 'is_active']));
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function destroy(ChatFaq $faq): JsonResponse
    {
        $faq->delete();
        return response()->json(['success' => true]);
    }

    /**
     * 批量排序
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array|min:1',
            'orders.*.id' => 'required|integer|exists:chat_faqs,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $item) {
            ChatFaq::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true, 'message' => __('app.controller_compat.chat_faq_msg_81')]);
    }
}
