<template>
    <div class="prompt-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.total`) }}</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-green-600">{{ stats.active || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.active`) }}</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-yellow-600">{{ stats.drafts || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.drafts`) }}</div></div>
            </el-card></el-col>
            <el-col :span="6"><el-card shadow="never">
                <div class="stat-item"><div class="stat-value text-blue-600">{{ categoryCount }}</div><div class="stat-label">{{ t(`${P}.stats.categories`) }}</div></div>
            </el-card></el-col>
        </el-row>

        <!-- 工具栏 -->
        <el-card shadow="never">
            <template #header>
                <div class="flex-between">
                    <span>{{ t(`${P}.title`) }}</span>
                    <div>
                        <el-button type="primary" size="small" @click="showDialog = true">
                            <el-icon><Plus /></el-icon>{{ t(`${P}.create_btn`) }}
                        </el-button>
                    </div>
                </div>
            </template>

            <!-- 筛选 -->
            <div class="flex gap-3 mb-4">
                <el-select v-model="filters.category" :placeholder="t(`${P}.filters.category_ph`)" clearable style="width:150px" @change="loadList">
                    <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                </el-select>
                <el-select v-model="filters.status" :placeholder="t(`${P}.filters.status_ph`)" clearable style="width:120px" @change="loadList">
                    <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-input v-model="filters.search" :placeholder="t(`${P}.filters.search_ph`)" clearable style="width:250px" @clear="loadList" @keyup.enter="loadList" />
                <el-button @click="loadList">{{ t('actions.search') }}</el-button>
            </div>

            <!-- 表格 -->
            <el-table :data="list" v-loading="loading" stripe @row-click="showDetail">
                <el-table-column prop="name" :label="t(`${P}.cols.name`)" min-width="180" />
                <el-table-column :label="t(`${P}.cols.category`)" width="120">
                    <template #default="{ row }">
                        <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.version`)" width="80">
                    <template #default="{ row }">v{{ row.version }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.is_current`)" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_current" type="success" size="small">{{ t(`${P}.badge.current`) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="engine" :label="t(`${P}.cols.engine`)" width="100" />
                <el-table-column :label="t(`${P}.cols.temperature`)" width="70" align="center">
                    <template #default="{ row }">{{ row.temperature }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.status`)" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'draft' ? 'warning' : 'info'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.actions`)" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click.stop="showEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button v-if="!row.is_current" size="small" type="primary" plain @click.stop="handleSetActive(row)">{{ t(`${P}.set_active_btn`) }}</el-button>
                        <el-popconfirm :title="t('messages.confirm_delete')" @confirm.stop="handleDelete(row.id)">
                            <template #reference><el-button size="small" type="danger" plain @click.stop>{{ t('actions.delete') }}</el-button></template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-3 flex-center" v-if="total > perPage">
                <el-pagination background layout="prev,next" :total="total" :page-size="perPage" v-model:current-page="page" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="editingId ? t(`${P}.dialog.edit_title`) : t(`${P}.dialog.create_title`)" width="750px">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.name`)" prop="name">
                            <el-input v-model="form.name" :placeholder="t(`${P}.form.name_ph`)" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t(`${P}.form.category`)" prop="category">
                            <el-select v-model="form.category" style="width:100%">
                                <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t(`${P}.form.engine`)">
                            <el-select v-model="form.engine" style="width:100%">
                                <el-option v-for="e in engineOptions" :key="e.value" :label="e.label" :value="e.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.description`)">
                    <el-input v-model="form.description" :placeholder="t(`${P}.form.description_ph`)" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.variables`)" prop="variables">
                    <el-select v-model="form.variables" multiple filterable allow-create default-first-option style="width:100%" :placeholder="t(`${P}.form.variables_ph`)">
                        <el-option v-for="v in form.variables" :key="v" :label="v" :value="v" />
                    </el-select>
                    <div class="text-gray-400 text-xs mt-1">{{ t(`${P}.form.variables_hint`) }}</div>
                </el-form-item>
                <el-form-item :label="t(`${P}.form.content`)" prop="content">
                    <el-input v-model="form.content" type="textarea" :rows="12" :placeholder="t(`${P}.form.content_ph`)" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.temperature`)">
                            <el-slider v-model="form.temperature" :min="0" :max="2" :step="0.05" show-input :debounce="500" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.max_tokens`)">
                            <el-input-number v-model="form.max_tokens" :min="100" :max="32000" :step="100" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${P}.form.status`)">
                            <el-radio-group v-model="form.status">
                                <el-radio value="active">{{ t(`${P}.status.active`) }}</el-radio>
                                <el-radio value="draft">{{ t(`${P}.status.draft`) }}</el-radio>
                            </el-radio-group>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailDialog" :title="t(`${P}.detail.title`)" width="800px">
            <template v-if="detail">
                <div class="mb-3">
                    <el-tag :type="categoryTag(detail.category)" size="small">{{ categoryLabel(detail.category) }}</el-tag>
                    <el-tag class="ml-2">v{{ detail.version }}</el-tag>
                    <el-tag v-if="detail.is_current" class="ml-2" type="success">{{ t(`${P}.badge.current`) }}</el-tag>
                    <el-tag v-else class="ml-2" type="info">{{ t(`${P}.badge.historical`) }}</el-tag>
                </div>
                <div class="text-sm text-gray-500 mb-3">{{ detail.description }}</div>
                <div class="mb-2 text-sm"><b>{{ t(`${P}.detail.variables`) }}:</b> {{ detail.variables?.join(', ') || t(`${P}.detail.none`) }}</div>
                <div class="mb-2 text-sm"><b>{{ t(`${P}.detail.engine`) }}:</b> {{ detail.engine }} | <b>{{ t(`${P}.detail.temperature`) }}:</b> {{ detail.temperature }} | <b>{{ t(`${P}.detail.max_tokens`) }}:</b> {{ detail.max_tokens }}</div>
                <div class="mb-2 text-sm"><b>{{ t(`${P}.detail.creator`) }}:</b> {{ detail.creator?.name || t(`${P}.detail.system`) }} | <b>{{ t(`${P}.detail.created_at`) }}:</b> {{ detail.created_at }}</div>

                <el-divider />
                <div class="font-mono text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded" style="max-height:400px;overflow:auto">{{ detail.content }}</div>

                <!-- 渲染测试 -->
                <el-divider>{{ t(`${P}.detail.render_test`) }}</el-divider>
                <div class="flex gap-2 mb-2">
                    <el-input v-model="testVars" :placeholder="t(`${P}.detail.test_vars_ph`)" :rows="2" type="textarea" />
                    <el-button @click="handleRenderTest" :loading="rendering" style="flex-shrink:0">{{ t(`${P}.detail.render_btn`) }}</el-button>
                </div>
                <div v-if="renderedContent" class="font-mono text-sm whitespace-pre-wrap bg-blue-50 p-3 rounded">{{ renderedContent }}</div>

                <!-- 创建新版本 -->
                <el-divider>{{ t(`${P}.detail.new_version`) }}</el-divider>
                <el-input v-model="newVersionContent" type="textarea" :rows="6" :placeholder="t(`${P}.detail.new_version_ph`)" />
                <el-input v-model="newVersionNote" class="mt-2" :placeholder="t(`${P}.detail.version_note_ph`)" />
                <el-button class="mt-2" type="primary" @click="handleCreateVersion" :loading="creatingVersion">{{ t(`${P}.detail.create_version_btn`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getPromptDashboard, getPromptList, getPromptDetail,
    createPrompt, updatePrompt, createPromptVersion,
    setActivePrompt, renderTestPrompt, deletePrompt,
} from '@/api/promptTemplate'

const P = 'prompt_templates_page'
const { t } = useI18n()

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

const categoryKeys = ['chat', 'summary', 'sentiment', 'translation', 'quality', 'todo', 'agent']

const categories = computed(() =>
    categoryKeys.map((value) => ({ value, label: t(`${P}.categories.${value}`) })),
)

const statusFilterOptions = computed(() => [
    { value: 'active', label: t(`${P}.status.active`) },
    { value: 'draft', label: t(`${P}.status.draft`) },
    { value: 'archived', label: t(`${P}.status.archived`) },
])

const engineOptions = computed(() => [
    { value: 'deepseek', label: t(`${P}.engines.deepseek`) },
    { value: 'openai', label: t(`${P}.engines.openai`) },
    { value: 'claude', label: t(`${P}.engines.claude`) },
    { value: 'qwen', label: t(`${P}.engines.qwen`) },
    { value: 'ernie', label: t(`${P}.engines.ernie`) },
])

const categoryTag = (v) => ({ chat: 'primary', summary: 'success', sentiment: 'warning', translation: 'info', quality: 'danger', todo: '', agent: 'primary' }[v] || '')

function categoryLabel(v) {
    return categoryKeys.includes(v) ? t(`${P}.categories.${v}`) : v
}

function statusLabel(status) {
    const map = {
        active: t(`${P}.status.active`),
        draft: t(`${P}.status.draft`),
        archived: t(`${P}.status.archived_short`),
    }
    return map[status] || status
}

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

const rules = computed(() => ({
    name: [{ required: true, message: t(`${P}.rules.name_required`) }],
    category: [{ required: true, message: t(`${P}.rules.category_required`) }],
    content: [{ required: true, message: t(`${P}.rules.content_required`) }],
}))

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
            ElMessage.success(t(`${P}.messages.updated`))
        } else {
            await createPrompt(data)
            ElMessage.success(t(`${P}.messages.created`))
        }
        showDialog.value = false
        loadList()
        loadDashboard()
    } catch {} finally { saving.value = false }
}

async function handleSetActive(row) {
    try {
        await setActivePrompt(row.id)
        ElMessage.success(t(`${P}.messages.set_active`))
        loadList()
    } catch {}
}

async function handleDelete(id) {
    try {
        await deletePrompt(id)
        ElMessage.success(t(`${P}.messages.deleted`))
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
        ElMessage.success(t(`${P}.messages.version_created`))
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
