<template>
    <div class="prompt-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">总模板</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-green-600">{{ stats.active || 0 }}</div><div class="stat-label">活跃</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-yellow-600">{{ stats.drafts || 0 }}</div><div class="stat-label">草稿</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-blue-600">{{ categoryCount }}</div><div class="stat-label">场景分类</div></div>
            </el-card></el-col>
        </el-row>

        <!-- 工具栏 -->
        <el-card shadow="never">
            <template #header>
                <div class="flex-between">
                    <span>Prompt 模板管理</span>
                    <div>
                        <el-button type="primary" size="small" @click="showCreate = true">
                            <el-icon><Plus /></el-icon>新建模板
                        </el-button>
                    </div>
                </div>
            </template>

            <!-- 筛选 -->
            <div class="flex gap-3 mb-4">
                <el-select v-model="filters.category" placeholder="场景分类" clearable style="width:150px" @change="loadList">
                    <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                </el-select>
                <el-select v-model="filters.status" placeholder="状态" clearable style="width:120px" @change="loadList">
                    <el-option label="活跃" value="active" />
                    <el-option label="草稿" value="draft" />
                    <el-option label="已归档" value="archived" />
                </el-select>
                <el-input v-model="filters.search" placeholder="搜索名称/说明" clearable style="width:250px" @clear="loadList" @keyup.enter="loadList" />
                <el-button @click="loadList">搜索</el-button>
            </div>

            <!-- 表格 -->
            <el-table :data="list" v-loading="loading" stripe @row-click="showDetail">
                <el-table-column prop="name" label="模板名称" min-width="180" />
                <el-table-column label="场景" width="120">
                    <template #default="{ row }">
                        <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="版本" width="80">
                    <template #default="{ row }">v{{ row.version }}</template>
                </el-table-column>
                <el-table-column label="当前生效" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_current" type="success" size="small">生效中</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="engine" label="模型" width="100" />
                <el-table-column label="温度" width="70" align="center">
                    <template #default="{ row }">{{ row.temperature }}</template>
                </el-table-column>
                <el-table-column label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'draft' ? 'warning' : 'info'" size="small">
                            {{ { active: '活跃', draft: '草稿', archived: '归档' }[row.status] }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click.stop="showEdit(row)">编辑</el-button>
                        <el-button v-if="!row.is_current" size="small" type="primary" plain @click.stop="handleSetActive(row)">设为生效</el-button>
                        <el-popconfirm title="确定删除?" @confirm.stop="handleDelete(row.id)">
                            <template #reference><el-button size="small" type="danger" plain @click.stop>删除</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-3 flex-center" v-if="total > perPage">
                <el-pagination background layout="prev,next" :total="total" :page-size="perPage" v-model:current-page="page" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="editingId ? '编辑 Prompt 模板' : '新建 Prompt 模板'" width="750px">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="模板名称" prop="name">
                            <el-input v-model="form.name" placeholder="如: 客服对话 System Prompt" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="场景分类" prop="category">
                            <el-select v-model="form.category" style="width:100%">
                                <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item label="推荐模型">
                            <el-select v-model="form.engine" style="width:100%">
                                <el-option label="DeepSeek" value="deepseek" />
                                <el-option label="OpenAI GPT" value="openai" />
                                <el-option label="Claude" value="claude" />
                                <el-option label="通义千问" value="qwen" />
                                <el-option label="文心一言" value="ernie" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="说明">
                    <el-input v-model="form.description" placeholder="模板用途说明" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item label="变量定义" prop="variables">
                    <el-select v-model="form.variables" multiple filterable allow-create default-first-option style="width:100%" placeholder="输入变量名后回车添加，如: topic">
                        <el-option v-for="v in form.variables" :key="v" :label="v" :value="v" />
                    </el-select>
                    <div class="text-gray-400 text-xs mt-1">在模板内容中使用 {变量名} 引用</div>
                </el-form-item>
                <el-form-item label="Prompt 内容" prop="content">
                    <el-input v-model="form.content" type="textarea" :rows="12" placeholder="输入 Prompt 模板内容，使用 {变量名} 作为占位符" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="温度">
                            <el-slider v-model="form.temperature" :min="0" :max="2" :step="0.05" show-input :debounce="500" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最大 Token">
                            <el-input-number v-model="form.max_tokens" :min="100" :max="32000" :step="100" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="状态">
                            <el-radio-group v-model="form.status">
                                <el-radio value="active">活跃</el-radio>
                                <el-radio value="draft">草稿</el-radio>
                            </el-radio-group>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailDialog" title="Prompt 模板详情" width="800px">
            <template v-if="detail">
                <div class="mb-3">
                    <el-tag :type="categoryTag(detail.category)" size="small">{{ categoryLabel(detail.category) }}</el-tag>
                    <el-tag class="ml-2">v{{ detail.version }}</el-tag>
                    <el-tag v-if="detail.is_current" class="ml-2" type="success">生效中</el-tag>
                    <el-tag v-else class="ml-2" type="info">历史版本</el-tag>
                </div>
                <div class="text-sm text-gray-500 mb-3">{{ detail.description }}</div>
                <div class="mb-2 text-sm"><b>变量:</b> {{ detail.variables?.join(', ') || '无' }}</div>
                <div class="mb-2 text-sm"><b>模型:</b> {{ detail.engine }} | <b>温度:</b> {{ detail.temperature }} | <b>最大Token:</b> {{ detail.max_tokens }}</div>
                <div class="mb-2 text-sm"><b>创建者:</b> {{ detail.creator?.name || '系统' }} | <b>创建时间:</b> {{ detail.created_at }}</div>

                <el-divider />
                <div class="font-mono text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded" style="max-height:400px;overflow:auto">{{ detail.content }}</div>

                <!-- 渲染测试 -->
                <el-divider>渲染测试</el-divider>
                <div class="flex gap-2 mb-2">
                    <el-input v-model="testVars" placeholder='输入测试变量 JSON，如 {"topic":"激活问题"}' :rows="2" type="textarea" />
                    <el-button @click="handleRenderTest" :loading="rendering" style="flex-shrink:0">测试渲染</el-button>
                </div>
                <div v-if="renderedContent" class="font-mono text-sm whitespace-pre-wrap bg-blue-50 p-3 rounded">{{ renderedContent }}</div>

                <!-- 创建新版本 -->
                <el-divider>创建新版本</el-divider>
                <el-input v-model="newVersionContent" type="textarea" :rows="6" placeholder="输入新版本的 Prompt 内容..." />
                <el-input v-model="newVersionNote" class="mt-2" placeholder="版本说明（可选）" />
                <el-button class="mt-2" type="primary" @click="handleCreateVersion" :loading="creatingVersion">创建新版本</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getPromptDashboard, getPromptList, getPromptDetail,
    createPrompt, updatePrompt, createPromptVersion,
    setActivePrompt, renderTestPrompt, deletePrompt,
} from '@/api/promptTemplate'

const loading = ref(false)
const saving = ref(false)
const rendering = ref(false)
const creatingVersion = ref(false)
const stats = ref({})
const list = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const detail = ref(null)
const renderedContent = ref('')
const testVars = ref('')
const newVersionContent = ref('')
const newVersionNote = ref('')

const categories = [
    { value: 'chat', label: '💬 客服对话' },
    { value: 'summary', label: '📝 会话摘要' },
    { value: 'sentiment', label: '😊 情感分析' },
    { value: 'translation', label: '🌐 翻译' },
    { value: 'quality', label: '✅ 会话质检' },
    { value: 'todo', label: '📋 待办提取' },
    { value: 'agent', label: '🤖 Agent 工具' },
]

const categoryTag = (v) => ({ chat: 'primary', summary: 'success', sentiment: 'warning', translation: 'info', quality: 'danger', todo: '', agent: 'primary' }[v] || '')
const categoryLabel = (v) => categories.find(c => c.value === v)?.label || v
const categoryCount = computed(() => Object.keys(stats.value?.byCategory || {}).length)

const filters = reactive({ category: '', status: '', search: '' })
const showDialog = ref(false)
const showDetailDialog = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const form = reactive({
    name: '', category: 'chat', content: '', description: '',
    variables: [], engine: 'deepseek', temperature: 0.7,
    max_tokens: 2000, status: 'active',
})
const rules = {
    name: [{ required: true, message: '请输入模板名称' }],
    category: [{ required: true, message: '请选择场景' }],
    content: [{ required: true, message: '请输入 Prompt 内容' }],
}

async function loadDashboard() {
    try {
        const { data: res } = await getPromptDashboard()
        if (res.success) stats.value = res.data
    } catch {}
}

async function loadList() {
    loading.value = true
    try {
        const { data: res } = await getPromptList({ ...filters, page: page.value, per_page: perPage.value })
        if (res.success) {
            list.value = res.data.data || []
            total.value = res.data.total || 0
        }
    } catch {} finally { loading.value = false }
}

async function showDetail(row) {
    try {
        const { data: res } = await getPromptDetail(row.id)
        if (res.success) {
            detail.value = res.data
            renderedContent.value = ''
            testVars.value = ''
            newVersionContent.value = ''
            newVersionNote.value = ''
            showDetailDialog.value = true
        }
    } catch {}
}

function showEdit(row) {
    editingId.value = row.id
    Object.assign(form, {
        name: row.name, category: row.category, content: row.content,
        description: row.description || '', variables: row.variables || [],
        engine: row.engine, temperature: row.temperature,
        max_tokens: row.max_tokens, status: row.status,
    })
    showDialog.value = true
}

async function handleSave() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        const data = { ...form }
        if (editingId.value) {
            await updatePrompt(editingId.value, data)
            ElMessage.success('已更新')
        } else {
            await createPrompt(data)
            ElMessage.success('已创建')
        }
        showDialog.value = false
        loadList()
        loadDashboard()
    } catch {} finally { saving.value = false }
}

async function handleSetActive(row) {
    try {
        await setActivePrompt(row.id)
        ElMessage.success('已设为生效版本')
        loadList()
    } catch {}
}

async function handleDelete(id) {
    try {
        await deletePrompt(id)
        ElMessage.success('已删除')
        loadList()
        loadDashboard()
    } catch {}
}

async function handleRenderTest() {
    if (!detail.value) return
    rendering.value = true
    try {
        let vars = {}
        try { vars = JSON.parse(testVars.value || '{}') } catch {}
        const { data: res } = await renderTestPrompt(detail.value.id, vars)
        if (res.success) renderedContent.value = res.data.rendered
    } catch {} finally { rendering.value = false }
}

async function handleCreateVersion() {
    if (!detail.value || !newVersionContent.value) return
    creatingVersion.value = true
    try {
        await createPromptVersion(detail.value.id, {
            content: newVersionContent.value,
            note: newVersionNote.value,
        })
        ElMessage.success('新版本已创建')
        // 刷新详情
        const { data: res } = await getPromptDetail(detail.value.id)
        if (res.success) detail.value = res.data
        newVersionContent.value = ''
        newVersionNote.value = ''
        loadList()
    } catch {} finally { creatingVersion.value = false }
}

onMounted(() => { loadDashboard(); loadList() })
</script>

<style scoped>
.flex-between { display: flex; align-items: center; justify-content: space-between; }
.flex-center { display: flex; justify-content: center; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.mt-3 { margin-top: 12px; }
.ml-2 { margin-left: 8px; }
.gap-3 { gap: 12px; }
.gap-2 { gap: 8px; }
.flex { display: flex; }
</style>
