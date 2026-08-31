<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>{{ t(`${P}.title`) }}</h2>
                <p class="text-gray-500 text-sm">{{ t(`${P}.subtitle`) }}</p>
            </div>
            <div class="flex gap-2">
                <el-button @click="showImport = true">
                    <el-icon><Upload /></el-icon> {{ t(`${P}.bulk_import`) }}
                </el-button>
                <el-button @click="exportWords">
                    <el-icon><Download /></el-icon> {{ t('actions.export') }}
                </el-button>
                <el-button type="primary" @click="openAdd">
                    <el-icon><Plus /></el-icon> {{ t(`${P}.add_btn`) }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">{{ t(`${P}.stats.total`) }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.active }}</div><div class="stat-label">{{ t(`${P}.stats.active`) }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.inactive }}</div><div class="stat-label">{{ t(`${P}.stats.inactive`) }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-blue-500">{{ stats.categories }}</div><div class="stat-label">{{ t(`${P}.stats.categories`) }}</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t(`${P}.filters.keyword`)">
                    <el-input v-model="filters.q" :placeholder="t(`${P}.filters.search_ph`)" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.category`)">
                    <el-select v-model="filters.category" :placeholder="t(`${P}.filters.all`)" clearable @change="loadList" style="width:140px">
                        <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 数据表格 -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="word" :label="t(`${P}.columns.word`)" min-width="160" />
                <el-table-column prop="replacement" :label="t(`${P}.columns.replacement`)" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.replacement || '***' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="category" :label="t(`${P}.columns.category`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="categoryType(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="severity" :label="t(`${P}.columns.severity`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="severityType(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="t(`${P}.columns.active`)" width="80">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="toggleActive(row)" />
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t(`${P}.columns.created_at`)" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.actions`)" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text size="small" type="danger" @click="removeWord(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="50" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="dialogTitle" width="500px" :close-on-click-modal="false">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small">
                <el-form-item :label="t(`${P}.fields.word`)" prop="word">
                    <el-input v-model="form.word" :placeholder="t(`${P}.word_ph`)" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t(`${P}.fields.replacement`)" prop="replacement">
                    <el-input v-model="form.replacement" :placeholder="t(`${P}.replacement_ph`)" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t(`${P}.fields.category`)" prop="category">
                    <el-select v-model="form.category" style="width:100%">
                        <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.fields.severity`)" prop="severity">
                    <el-select v-model="form.severity" style="width:100%">
                        <el-option v-for="(label, key) in severityOptions" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveWord">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="showImport" :title="t(`${P}.import_title`)" width="600px" :close-on-click-modal="false">
            <el-alert type="info" :closable="false" class="mb-4">
                <template #title>
                    {{ t(`${P}.import_format_hint`) }}
                </template>
            </el-alert>
            <el-input v-model="importText" type="textarea" :rows="12" :placeholder="t(`${P}.import_placeholder`)" />
            <div class="mt-2 text-sm text-gray-400">{{ t(`${P}.import_limit_hint`) }}</div>
            <template #footer>
                <el-button @click="showImport = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="importing" @click="doImport">{{ t(`${P}.import_start`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getSensitiveWords,
    createSensitiveWord,
    updateSensitiveWord,
    deleteSensitiveWord,
    importSensitiveWords,
    exportSensitiveWords,
} from '@/api/sensitiveWords'

const { t } = useI18n()
const P = 'sensitive_words_page'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)

const stats = reactive({ total: 0, active: 0, inactive: 0, categories: 0 })

const filters = reactive({ q: '', category: '' })

const showDialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({ word: '', replacement: '***', category: 'general', severity: 'medium' })

const categoryKeys = ['general', 'politics', 'ad', 'abuse', 'porn']
const severityKeys = ['low', 'medium', 'high', 'critical']

const categoryOptions = computed(() =>
    Object.fromEntries(categoryKeys.map((key) => [key, t(`${P}.categories.${key}`)])),
)
const severityOptions = computed(() =>
    Object.fromEntries(severityKeys.map((key) => [key, t(`${P}.severities.${key}`)])),
)
const dialogTitle = computed(() =>
    editing.value ? t(`${P}.dialog_edit_title`) : t(`${P}.dialog_add_title`),
)
const rules = computed(() => ({
    word: [{ required: true, message: t(`${P}.rules.word_required`), trigger: 'blur' }],
}))

const showImport = ref(false)
const importText = ref('')
const importing = ref(false)

function categoryLabel(cat) {
    return categoryOptions.value[cat] || cat
}
function categoryType(cat) {
    const map = { general: 'info', politics: 'danger', ad: 'warning', abuse: '', porn: 'danger' }
    return map[cat] || 'info'
}
function severityLabel(sev) {
    return severityOptions.value[sev] || sev
}
function severityType(sev) {
    const map = { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }
    return map[sev] || 'info'
}

async function loadList() {
    loading.value = true
    try {
        const res = await getSensitiveWords({ params: { page: page.value, ...filters } })
        const d = res.data?.data || res.data || {}
        list.value = d.data || []
        total.value = d.total || 0
    } catch (e) {
        ElMessage.error(t('messages.load_failed'))
    } finally {
        loading.value = false
    }
}

async function loadStats() {
    try {
        const res = await getSensitiveWords({ params: { per_page: 1 } })
        const d = res.data?.data || res.data || {}
        stats.total = d.total || 0
        // 统计活跃和非活跃
        try {
            const allRes = await getSensitiveWords({ params: { per_page: 10000 } })
            const all = allRes.data?.data?.data || []
            stats.active = all.filter(w => w.is_active).length
            stats.inactive = all.filter(w => !w.is_active).length
            const cats = new Set(all.map(w => w.category))
            stats.categories = cats.size
        } catch {}
    } catch {}
}

function openAdd() {
    editing.value = false
    form.word = ''
    form.replacement = '***'
    form.category = 'general'
    form.severity = 'medium'
    showDialog.value = true
}
function openEdit(row) {
    editing.value = true
    form.word = row.word
    form.replacement = row.replacement
    form.category = row.category
    form.severity = row.severity
    form._id = row.id
    showDialog.value = true
}

async function saveWord() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        if (editing.value) {
            await updateSensitiveWord(form._id, {
                word: form.word, replacement: form.replacement,
                category: form.category, severity: form.severity,
            })
            ElMessage.success(t(`${P}.messages.updated`))
        } else {
            await createSensitiveWord({
                word: form.word, replacement: form.replacement,
                category: form.category, severity: form.severity,
            })
            ElMessage.success(t(`${P}.messages.added`))
        }
        showDialog.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.save_failed`))
    } finally {
        saving.value = false
    }
}

async function toggleActive(row) {
    try {
        await updateSensitiveWord(row.id, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? t(`${P}.messages.enabled`) : t(`${P}.messages.disabled`))
        loadStats()
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    }
}

async function removeWord(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_delete`, { word: row.word }),
            t('actions.confirm'),
        )
        await deleteSensitiveWord(row.id)
        ElMessage.success(t(`${P}.messages.deleted`))
        loadList()
        loadStats()
    } catch {}
}

async function doImport() {
    if (!importText.value.trim()) {
        ElMessage.warning(t(`${P}.messages.import_empty`))
        return
    }
    importing.value = true
    const lines = importText.value.split('\n').map(l => l.trim()).filter(Boolean)
    const words = []
    for (const line of lines) {
        const parts = line.split(',').map(p => p.trim())
        if (!parts[0]) continue
        words.push({
            word: parts[0],
            replacement: parts[1] || '***',
            category: parts[2] || 'general',
            severity: severityKeys.includes(parts[3]) ? parts[3] : 'medium',
        })
    }
    if (!words.length) {
        ElMessage.warning(t(`${P}.messages.import_invalid`))
        importing.value = false
        return
    }
    try {
        const res = await importSensitiveWords({ words })
        const data = res.data?.data || {}
        ElMessage.success(
            data.skipped
                ? t(`${P}.messages.import_success_with_skipped`, { imported: data.imported, skipped: data.skipped })
                : t(`${P}.messages.import_success`, { imported: data.imported }),
        )
        showImport.value = false
        importText.value = ''
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.messages.import_failed`))
    } finally {
        importing.value = false
    }
}

async function exportWords() {
    try {
        const res = await exportSensitiveWords()
        const data = res.data?.data || []
        const csv = [t(`${P}.export_csv_header`)]
        data.forEach(w => {
            csv.push(`${w.word},${w.replacement},${w.category},${w.severity},${w.is_active ? t(`${P}.export_yes`) : t(`${P}.export_no`)}`)
        })
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = t(`${P}.export_filename`, { date: new Date().toISOString().slice(0, 10) })
        a.click()
        URL.revokeObjectURL(url)
        ElMessage.success(t(`${P}.messages.export_success`, { count: data.length }))
    } catch (e) {
        ElMessage.error(t(`${P}.messages.export_failed`))
    }
}

onMounted(() => { loadList(); loadStats() })
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-gray-500 { color: #909399; }
.text-sm { font-size: 13px; }
.text-blue-500 { color: #3b82f6; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.justify-center { justify-content: center; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
