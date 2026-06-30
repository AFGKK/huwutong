<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ 管理 - 互物通</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased">
    <div class="max-w-4xl mx-auto px-4 py-8">
        @php
            $faqsJson = $faqs->map(fn($f) => ['id' => $f->id, 'question' => $f->question, 'answer' => $f->answer, 'icon' => $f->icon, 'is_active' => $f->is_active])->values();
        @endphp
        <script>const FAQS = @json($faqsJson);</script>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">聊天 FAQ 管理</h1>
                <p class="text-sm text-gray-500 mt-1">管理 IM 客服中的常见问题列表</p>
            </div>
            <a href="/admin" class="text-sm text-blue-600 hover:text-blue-700">← 返回后台</a>
        </div>

        @if (session('success'))
            <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                <span class="text-sm font-medium text-gray-700">共 <span id="faq-count">{{ count($faqs) }}</span> 条</span>
                <button onclick="openModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">+ 新增</button>
            </div>
            <div id="faq-list" class="divide-y divide-gray-100">
                @forelse($faqs as $faq)
                <div class="p-4 flex items-center gap-2 hover:bg-gray-50 transition" data-id="{{ $faq->id }}">
                    <div class="flex flex-col gap-0.5">
                        <form method="POST" action="{{ route('admin.chat-faqs.move-up', $faq) }}" class="leading-none">
                            @csrf
                            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600" title="上移">▲</button>
                        </form>
                        <form method="POST" action="{{ route('admin.chat-faqs.move-down', $faq) }}" class="leading-none">
                            @csrf
                            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600" title="下移">▼</button>
                        </form>
                    </div>
                    <span class="text-lg w-8 text-center flex-shrink-0">{{ $faq->icon ?: '💬' }}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-900 truncate">{{ $faq->question }}</div>
                        <div class="text-xs text-gray-400 truncate mt-0.5">{{ $faq->answer ?: '' }}</div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <form method="POST" action="{{ route('admin.chat-faqs.toggle-active', $faq) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs px-2 py-1 rounded {{ $faq->is_active ? 'bg-green-50 text-green-600 hover:bg-green-100' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}" title="切换启用状态">
                                {{ $faq->is_active ? '✅ 已启用' : '⛔ 已停用' }}
                            </button>
                        </form>
                        <button onclick="editFaq({{ $faq->id }})" class="text-xs px-2 py-1 rounded bg-blue-50 text-blue-600 hover:bg-blue-100">编辑</button>
                        <form method="POST" action="{{ route('admin.chat-faqs.destroy', $faq) }}" class="inline" onsubmit="return confirm('确定删除此 FAQ？')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs px-2 py-1 rounded bg-red-50 text-red-500 hover:bg-red-100">删除</button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-gray-400 text-sm">暂无 FAQ 数据，点击右上角「+ 新增」添加</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="faq-modal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
        <form method="POST" action="{{ route('admin.chat-faqs.store') }}" class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">
            <input type="hidden" name="id" id="faq-id" value="">
            <h3 class="text-lg font-semibold text-gray-900 mb-4" id="modal-title">新增 FAQ</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">问题</label>
                    <input type="text" name="question" id="faq-question" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm" placeholder="请输入常见问题" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">答案</label>
                    <textarea name="answer" id="faq-answer" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm" placeholder="请输入答案"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">图标（Emoji）</label>
                    <input type="text" name="icon" id="faq-icon" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm" placeholder="💬" value="💬">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" id="faq-active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-400" value="1" checked>
                    <label for="faq-active" class="text-sm text-gray-700">启用</label>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">取消</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">保存</button>
            </div>
        </form>
    </div>

    <script>
    function openModal(data) {
        const modal = document.getElementById('faq-modal');
        const form = modal.querySelector('form');
        const title = document.getElementById('modal-title');
        const method = document.getElementById('form-method');
        const idInput = document.getElementById('faq-id');
        const question = document.getElementById('faq-question');
        const answer = document.getElementById('faq-answer');
        const icon = document.getElementById('faq-icon');
        const active = document.getElementById('faq-active');

        if (data) {
            title.textContent = '编辑 FAQ';
            method.value = 'PUT';
            form.action = '{{ route("admin.chat-faqs.index") }}/' + data.id;
            idInput.value = data.id;
            question.value = data.question || '';
            answer.value = data.answer || '';
            icon.value = data.icon || '💬';
            active.checked = data.is_active !== false;
        } else {
            title.textContent = '新增 FAQ';
            method.value = 'POST';
            form.action = '{{ route("admin.chat-faqs.store") }}';
            idInput.value = '';
            question.value = '';
            answer.value = '';
            icon.value = '💬';
            active.checked = true;
        }
        modal.classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('faq-modal').classList.add('hidden');
    }

    function editFaq(id) {
        const data = FAQS.find(f => f.id === id);
        if (data) openModal(data);
        else alert('获取 FAQ 数据失败');
    }
    </script>
</body>
</html>
