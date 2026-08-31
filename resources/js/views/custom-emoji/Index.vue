<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>{{ t('custom_emoji_page.title') }}</h2>
                <p class="text-gray-500 text-sm">{{ t('custom_emoji_page.subtitle') }}</p>
            </div>
            <div class="flex gap-2">
                <el-button @click="showImport = true">
                    <el-icon><Upload /></el-icon> {{ t('custom_emoji_page.bulk_import') }}
                </el-button>
                <el-button type="primary" @click="openAdd">
                    <el-icon><Plus /></el-icon> {{ t('custom_emoji_page.add_emoji') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">{{ t('custom_emoji_page.stat_total') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.active }}</div><div class="stat-label">{{ t('custom_emoji_page.stat_active') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.total_usage || 0 }}</div><div class="stat-label">{{ t('custom_emoji_page.stat_total_usage') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ categoryCount }}</div><div class="stat-label">{{ t('custom_emoji_page.stat_categories') }}</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 热门排行 -->
        <el-card shadow="never" class="mb-4" v-if="stats.top_used?.length">
            <template #header><span>{{ t('custom_emoji_page.top_used_title') }}</span></template>
            <div class="top-emoji-list">
                <div v-for="(e, i) in stats.top_used" :key="e.shortcode" class="top-emoji-item">
                    <span class="top-rank">{{ i + 1 }}</span>
                    <img :src="e.image_url" class="top-emoji-img" />
                    <span class="top-shortcode">:{{ e.shortcode }}:</span>
                    <span class="top-usage">{{ t('custom_emoji_page.usage_count', { count: e.usage_count }) }}</span>
                </div>
            </div>
        </el-card>

        <!-- 筛选 + Emoji 网格 -->
        <el-card shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <div class="flex gap-2">
                        <el-select v-model="filterCategory" :placeholder="t('custom_emoji_page.filter_all_categories')" clearable size="small" style="width:140px" @change="loadList">
                            <el-option v-for="(label, key) in filterCategoryOptions" :key="key" :label="label" :value="key" />
                        </el-select>
                        <el-input v-model="searchQ" :placeholder="t('custom_emoji_page.search_shortcode_ph')" size="small" style="width:200px" clearable @clear="loadList" @keyup.enter="loadList" />
                    </div>
                    <el-button text size="small" @click="switchView">
                        {{ viewSwitchLabel }}
                    </el-button>
                </div>
            </template>

            <!-- 网格视图 -->
            <div v-if="gridView" class="emoji-grid">
                <div v-for="e in list" :key="e.id" class="emoji-card" :class="{ 'emoji-disabled': !e.is_active }">
                    <div class="emoji-card-img-wrap">
                        <img :src="e.image_url" class="emoji-card-img" @error="$event.target.src = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>❓</text></svg>'" />
                    </div>
                    <div class="emoji-card-info">
                        <div class="emoji-card-code">:{{ e.shortcode }}:</div>
                        <div class="emoji-card-meta">
                            <el-tag size="small" v-if="e.category">{{ categoryLabel(e.category) }}</el-tag>
                            <span class="emoji-usage">{{ t('custom_emoji_page.usage_count', { count: e.usage_count || 0 }) }}</span>
                        </div>
                    </div>
                    <div class="emoji-card-actions">
                        <el-switch :model-value="e.is_active" size="small" @change="toggleActive(e)" />
                        <el-button text size="small" @click="openEdit(e)">{{ t('actions.edit') }}</el-button>
                        <el-button text size="small" type="danger" @click="removeEmoji(e)">{{ t('actions.delete') }}</el-button>
                    </div>
                </div>
                <div v-if="!list.length" class="empty-state">{{ t('custom_emoji_page.empty_state') }}</div>
            </div>

            <!-- 表格视图 -->
            <el-table v-else :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column :label="t('custom_emoji_page.col_emoji')" width="60">
                    <template #default="{ row }">
                        <img :src="row.image_url" class="table-emoji-img" @error="$event.target.src = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>❓</text></svg>'" />
                    </template>
                </el-table-column>
                <el-table-column prop="shortcode" :label="t('custom_emoji_page.col_shortcode')" width="160">
                    <template #default="{ row }"><code>:{{ row.shortcode }}:</code></template>
                </el-table-column>
                <el-table-column prop="category" :label="t('custom_emoji_page.col_category')" width="100">
                    <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column prop="aliases" :label="t('custom_emoji_page.col_aliases')" min-width="160">
                    <template #default="{ row }">
                        <span v-if="!row.aliases">-</span>
                        <el-tag v-for="a in (row.aliases||'').split(',').map(s=>s.trim()).filter(Boolean)" :key="a" size="small" class="mr-1">:{{ a }}:</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="usage_count" :label="t('custom_emoji_page.col_usage_count')" width="90" />
                <el-table-column prop="sort_order" :label="t('custom_emoji_page.col_sort_order')" width="70" />
                <el-table-column prop="is_active" :label="t('custom_emoji_page.col_active')" width="70">
                    <template #default="{ row }"><el-switch :model-value="row.is_active" size="small" @change="toggleActive(row)" /></template>
                </el-table-column>
                <el-table-column :label="t('custom_emoji_page.col_actions')" width="130">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text size="small" type="danger" @click="removeEmoji(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="50" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="dialogTitle" width="500px">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small">
                <el-form-item :label="t('custom_emoji_page.fields.shortcode')" prop="shortcode">
                    <el-input v-model="form.shortcode" :placeholder="t('custom_emoji_page.shortcode_ph')" maxlength="50">
                        <template #prepend>:</template>
                        <template #append>:</template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="t('custom_emoji_page.fields.image_url')" prop="image_url">
                    <div class="flex gap-2 items-center">
                        <el-input v-model="form.image_url" :placeholder="t('custom_emoji_page.image_url_ph')" />
                        <el-upload :show-file-list="false" :before-upload="uploadEmojiImage" accept="image/*">
                            <el-button size="small">{{ t('actions.upload') }}</el-button>
                        </el-upload>
                    </div>
                    <img v-if="form.image_url" :src="form.image_url" class="preview-emoji" @error="$event.target.style.display='none'" />
                </el-form-item>
                <el-form-item :label="t('custom_emoji_page.fields.category')">
                    <el-select v-model="form.category" style="width:100%">
                        <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('custom_emoji_page.fields.aliases')">
                    <el-input v-model="form.aliases" :placeholder="t('custom_emoji_page.aliases_ph')" maxlength="500" />
                    <div class="form-hint">{{ t('custom_emoji_page.aliases_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('custom_emoji_page.fields.sort_order')">
                    <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveEmoji">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="showImport" :title="t('custom_emoji_page.import_title')" width="600px">
            <el-alert type="info" :closable="false" class="mb-4">
                <template #title>
                    {{ t('custom_emoji_page.import_format_hint') }}
                </template>
            </el-alert>
            <el-input v-model="importText" type="textarea" :rows="12" :placeholder="t('custom_emoji_page.import_placeholder')" />
            <div class="mt-2 text-sm text-gray-400">{{ t('custom_emoji_page.import_skip_hint') }}</div>
            <template #footer>
                <el-button @click="showImport = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="importing" @click="doImport">{{ t('custom_emoji_page.import_start') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getCustomEmojis,
    getCustomEmojiStats,
    createCustomEmoji,
    updateCustomEmoji,
    deleteCustomEmoji,
    importCustomEmojis,
} from '@/api/customEmoji'
import apiClient from '@/utils/request'

const { t } = useI18n()
const P = 'custom_emoji_page'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const gridView = ref(true)
const filterCategory = ref('')
const searchQ = ref('')

const stats = reactive({ total: 0, active: 0, total_usage: 0, top_used: [], categories: {} })
const categoryCount = computed(() => Object.keys(stats.categories || {}).length)

const categoryKeys = ['general', 'funny', 'reaction', 'logo', 'other']
const categoryOptions = computed(() =>
    Object.fromEntries(categoryKeys.map((key) => [key, t(`${P}.categories.${key}`)])),
)
const filterCategoryOptions = computed(() => {
    const opts = { ...categoryOptions.value }
    for (const cat of Object.keys(stats.categories || {})) {
        if (!opts[cat]) opts[cat] = cat
    }
    return opts
})
const viewSwitchLabel = computed(() =>
    gridView.value ? t(`${P}.list_view`) : t(`${P}.grid_view`),
)
const dialogTitle = computed(() =>
    editing.value ? t(`${P}.dialog_edit_title`) : t(`${P}.dialog_add_title`),
)

function categoryLabel(cat) {
    return categoryOptions.value[cat] || cat
}

const showDialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({ shortcode: '', image_url: '', category: 'general', aliases: '', sort_order: 0 })
const rules = computed(() => ({
    shortcode: [
        { required: true, message: t(`${P}.rules.shortcode_required`), trigger: 'blur' },
        { pattern: /^[a-zA-Z0-9_\u4e00-\u9fa5]+$/, message: t(`${P}.rules.shortcode_pattern`), trigger: 'blur' },
    ],
    image_url: [{ required: true, message: t(`${P}.rules.image_url_required`), trigger: 'blur' }],
}))

const showImport = ref(false)
const importText = ref('')
const importing = ref(false)

async function loadList() {
    loading.value = true
    try {
        const params = { page: page.value }
        if (filterCategory.value) params.category = filterCategory.value
        if (searchQ.value) params.q = searchQ.value
        const res = await getCustomEmojis(params)
        const d = res.data?.data || {}
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
        const res = await getCustomEmojiStats()
        const d = res.data?.data || {}
        Object.assign(stats, d)
    } catch {}
}

function switchView() { gridView.value = !gridView.value }

function openAdd() {
    editing.value = false
    form.shortcode = ''
    form.image_url = ''
    form.category = 'general'
    form.aliases = ''
    form.sort_order = 0
    showDialog.value = true
}

function openEdit(row) {
    editing.value = true
    form.shortcode = row.shortcode
    form.image_url = row.image_url
    form.category = row.category
    form.aliases = row.aliases || ''
    form.sort_order = row.sort_order ?? 0
    form._id = row.id
    showDialog.value = true
}

async function uploadEmojiImage(file) {
    const fd = new FormData()
    fd.append('file', file)
    try {
        const res = await apiClient.post('/im/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        const url = res.data?.data?.url
        if (url) form.image_url = url
        ElMessage.success(t(`${P}.messages.upload_success`))
    } catch {
        ElMessage.error(t(`${P}.messages.upload_failed`))
    }
    return false
}

async function saveEmoji() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        const payload = {
            shortcode: form.shortcode,
            image_url: form.image_url,
            category: form.category,
            aliases: form.aliases || undefined,
            sort_order: form.sort_order,
        }
        if (editing.value) {
            await updateCustomEmoji(form._id, payload)
            ElMessage.success(t(`${P}.messages.updated`))
        } else {
            await createCustomEmoji(payload)
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
        await updateCustomEmoji(row.id, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? t(`${P}.messages.enabled`) : t(`${P}.messages.disabled`))
        loadStats()
    } catch {
        ElMessage.error(t('messages.failed'))
    }
}

async function removeEmoji(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_delete`, { shortcode: row.shortcode }),
            t('actions.confirm'),
        )
        await deleteCustomEmoji(row.id)
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
    const emojis = lines.map(line => {
        const parts = line.split(',').map(p => p.trim())
        return { shortcode: parts[0], image_url: parts[1] || '', category: parts[2] || 'general' }
    }).filter(e => e.shortcode && e.image_url)
    if (!emojis.length) {
        ElMessage.warning(t(`${P}.messages.import_invalid`))
        importing.value = false
        return
    }
    try {
        const res = await importCustomEmojis({ emojis })
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

onMounted(() => { loadList(); loadStats() })
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.text-gray-500 { color: #909399; }
.text-sm { font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
.justify-between { justify-content: space-between; }
.mr-1 { margin-right: 4px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }

.top-emoji-list { display: flex; flex-wrap: wrap; gap: 12px; }
.top-emoji-item { display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: #f5f7fa; border-radius: 8px; }
.top-rank { font-weight: 700; color: #e6a23c; font-size: 14px; min-width: 18px; }
.top-emoji-img { width: 28px; height: 28px; object-fit: contain; }
.top-shortcode { font-size: 12px; color: #606266; font-family: monospace; }
.top-usage { font-size: 11px; color: #909399; }

.emoji-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.emoji-card { border: 1px solid #e4e7ed; border-radius: 8px; padding: 12px; display: flex; flex-direction: column; align-items: center; gap: 8px; transition: box-shadow .2s; }
.emoji-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.1); }
.emoji-card.emoji-disabled { opacity: 0.5; }
.emoji-card-img-wrap { width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; }
.emoji-card-img { max-width: 64px; max-height: 64px; object-fit: contain; }
.emoji-card-info { text-align: center; }
.emoji-card-code { font-size: 13px; font-weight: 500; font-family: monospace; color: #0f172a; }
.emoji-card-meta { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 4px; }
.emoji-usage { font-size: 11px; color: #909399; }
.emoji-card-actions { display: flex; align-items: center; gap: 4px; margin-top: 4px; }
.empty-state { grid-column: 1 / -1; text-align: center; padding: 60px; color: #909399; }

.table-emoji-img { width: 32px; height: 32px; object-fit: contain; }
.preview-emoji { width: 48px; height: 48px; object-fit: contain; margin-top: 8px; border: 1px solid #e4e7ed; border-radius: 4px; padding: 4px; }
.form-hint { font-size: 12px; color: #909399; margin-top: 4px; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
