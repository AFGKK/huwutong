<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatFaq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatFaqAdminController extends Controller
{
    public function index(): View
    {
        $faqs = ChatFaq::orderBy('sort_order')->orderBy('id')->get();
        return view('admin.chat-faqs', compact('faqs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:200',
            'answer' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        ChatFaq::create($validated);

        return redirect()->route('admin.chat-faqs.index')->with('success', 'FAQ 已添加');
    }

    public function update(Request $request, ChatFaq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question' => 'required|string|max:200',
            'answer' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $faq->update($validated);

        return redirect()->route('admin.chat-faqs.index')->with('success', 'FAQ 已更新');
    }

    public function toggleActive(ChatFaq $faq): RedirectResponse
    {
        $faq->update(['is_active' => !$faq->is_active]);
        $status = $faq->is_active ? '已启用' : '已停用';
        return redirect()->route('admin.chat-faqs.index')->with('success', "FAQ「{$faq->question}」{$status}");
    }

    public function moveUp(ChatFaq $faq): RedirectResponse
    {
        $prev = ChatFaq::where('sort_order', '<', $faq->sort_order ?? 0)
            ->orderBy('sort_order', 'desc')->first();
        if ($prev) {
            $temp = $faq->sort_order ?? 0;
            $faq->update(['sort_order' => $prev->sort_order ?? 0]);
            $prev->update(['sort_order' => $temp]);
        }
        return redirect()->route('admin.chat-faqs.index')->with('success', '排序已更新');
    }

    public function moveDown(ChatFaq $faq): RedirectResponse
    {
        $next = ChatFaq::where('sort_order', '>', $faq->sort_order ?? 0)
            ->orderBy('sort_order', 'asc')->first();
        if ($next) {
            $temp = $faq->sort_order ?? 0;
            $faq->update(['sort_order' => $next->sort_order ?? 0]);
            $next->update(['sort_order' => $temp]);
        }
        return redirect()->route('admin.chat-faqs.index')->with('success', '排序已更新');
    }

    public function destroy(ChatFaq $faq): RedirectResponse
    {
        $faq->delete();
        return redirect()->route('admin.chat-faqs.index')->with('success', 'FAQ 已删除');
    }
}
