<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>😀 自定义 Emoji / 企业表情包</h2>
                <p class="text-gray-500 text-sm">管理企业自定义表情，用户在聊天中可使用 <code>:shortcode:</code> 发送表情</p>
            </div>
            <div class="flex gap-2">
                <el-button @click="showImport = true">
                    <el-icon><Upload /></el-icon> 批量导入
                </el-button>
                <el-button type="primary" @click="openAdd">
                    <el-icon><Plus /></el-icon> 添加表情
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">总计</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.active }}</div><div class="stat-label">已启用</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.total_usage || 0 }}</div><div class="stat-label">总使用次数</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ categoryCount }}</div><div class="stat-label">分类数</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 热门排行 -->
        <el-card shadow="never" class="mb-4" v-if="stats.top_used?.length">
            <template #header><span>🔥 热门表情 Top 10</span></template>
            <div class="top-emoji-list">
                <div v-for="(e, i) in stats.top_used" :key="e.shortcode" class="top-emoji-item">
                    <span class="top-rank">{{ i + 1 }}</span>
                    <img :src="e.image_url" class="top-emoji-img" />
                    <span class="top-shortcode">:{{ e.shortcode }}:</span>
                    <span class="top-usage">{{ e.usage_count }} 次</span>
                </div>
            </div>
        </el-card>

        <!-- 筛选 + Emoji 网格 -->
        <el-card shadow="never">
            <template #header>
                <div class="flex justify-between items-center">
                    <div class="flex gap-2">
                        <el-select v-model="filterCategory" placeholder="全部分类" clearable size="small" style="width:140px" @change="loadList">
                            <el-option label="通用" value="general" />
                            <el-option label="搞笑" value="funny" />
                            <el-option label="反应" value="reaction" />
                            <el-option label="品牌" value="logo" />
                            <el-option v-for="(_, cat) in stats.categories" :key="cat" :label="cat" :value="cat" />
                        </el-select>
                        <el-input v-model="searchQ" placeholder="搜索 shortcode" size="small" style="width:200px" clearable @clear="loadList" @keyup.enter="loadList" />
                    </div>
                    <el-button text size="small" @click="switchView">
                        {{ gridView ? '📋 列表视图' : '🔲 网格视图' }}
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
                            <el-tag size="small" v-if="e.category">{{ e.category }}</el-tag>
                            <span class="emoji-usage">{{ e.usage_count || 0 }}次</span>
                        </div>
                    </div>
                    <div class="emoji-card-actions">
                        <el-switch :model-value="e.is_active" size="small" @change="toggleActive(e)" />
                        <el-button text size="small" @click="openEdit(e)">编辑</el-button>
                        <el-button text size="small" type="danger" @click="removeEmoji(e)">删除</el-button>
                    </div>
                </div>
                <div v-if="!list.length" class="empty-state">暂无自定义表情</div>
            </div>

            <!-- 表格视图 -->
            <el-table v-else :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column label="表情" width="60">
                    <template #default="{ row }">
                        <img :src="row.image_url" class="table-emoji-img" @error="$event.target.src = 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>❓</text></svg>'" />
                    </template>
                </el-table-column>
                <el-table-column prop="shortcode" label="短代码" width="160">
                    <template #default="{ row }"><code>:{{ row.shortcode }}:</code></template>
                </el-table-column>
                <el-table-column prop="category" label="分类" width="100" />
                <el-table-column prop="aliases" label="别名" min-width="160">
                    <template #default="{ row }">
                        <span v-if="!row.aliases">-</span>
                        <el-tag v-for="a in (row.aliases||'').split(',').map(s=>s.trim()).filter(Boolean)" :key="a" size="small" class="mr-1">:{{ a }}:</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="usage_count" label="使用次数" width="90" />
                <el-table-column prop="sort_order" label="排序" width="70" />
                <el-table-column prop="is_active" label="启用" width="70">
                    <template #default="{ row }"><el-switch :model-value="row.is_active" size="small" @change="toggleActive(row)" /></template>
                </el-table-column>
                <el-table-column label="操作" width="130">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">编辑</el-button>
                        <el-button text size="small" type="danger" @click="removeEmoji(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="50" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="editing ? '编辑表情' : '添加表情'" width="500px">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small">
                <el-form-item label="短代码" prop="shortcode">
                    <el-input v-model="form.shortcode" placeholder="英文字母/数字/下划线，如 hwt_love" maxlength="50">
                        <template #prepend>:</template>
                        <template #append>:</template>
                    </el-input>
                </el-form-item>
                <el-form-item label="表情图片" prop="image_url">
                    <div class="flex gap-2 items-center">
                        <el-input v-model="form.image_url" placeholder="图片 URL" />
                        <el-upload :show-file-list="false" :before-upload="uploadEmojiImage" accept="image/*">
                            <el-button size="small">上传</el-button>
                        </el-upload>
                    </div>
                    <img v-if="form.image_url" :src="form.image_url" class="preview-emoji" @error="$event.target.style.display='none'" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="form.category" style="width:100%">
                        <el-option label="通用" value="general" />
                        <el-option label="搞笑" value="funny" />
                        <el-option label="反应" value="reaction" />
                        <el-option label="品牌" value="logo" />
                        <el-option label="其他" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="别名">
                    <el-input v-model="form.aliases" placeholder="逗号分隔，如 hwt_hello,hi" maxlength="500" />
                    <div class="form-hint">多个别名用逗号分隔，用户也可用别名触发此表情</div>
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveEmoji">保存</el-button>
            </template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="showImport" title="批量导入自定义表情" width="600px">
            <el-alert type="info" :closable="false" class="mb-4">
                <template #title>
                    每行一个表情，格式: <code>shortcode,image_url,分类</code>
                </template>
            </el-alert>
            <el-input v-model="importText" type="textarea" :rows="12" placeholder="示例：&#10;hwt_love,https://example.com/love.png,reaction&#10;hwt_ok,https://example.com/ok.png,general" />
            <div class="mt-2 text-sm text-gray-400">已存在的 shortcode 会自动跳过</div>
            <template #footer>
                <el-button @click="showImport = false">取消</el-button>
                <el-button type="primary" :loading="importing" @click="doImport">开始导入</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/utils/request'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const gridView = ref(true)
const filterCategory = ref('')
const searchQ = ref('')

const stats = reactive({ total: 0, active: 0, total_usage: 0, top_used: [], categories: {} })
const categoryCount = computed(() => Object.keys(stats.categories || {}).length)

const showDialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({ shortcode: '', image_url: '', category: 'general', aliases: '', sort_order: 0 })
const rules = {
    shortcode: [
        { required: true, message: '请输入短代码', trigger: 'blur' },
        { pattern: /^[a-zA-Z0-9_\u4e00-\u9fa5]+$/, message: '只能包含字母、数字、下划线和中文', trigger: 'blur' },
    ],
    image_url: [{ required: true, message: '请上传或输入图片 URL', trigger: 'blur' }],
}

const showImport = ref(false)
const importText = ref('')
const importing = ref(false)

async function loadList() {
    loading.value = true
    try {
        const params = { page: page.value }
        if (filterCategory.value) params.category = filterCategory.value
        if (searchQ.value) params.q = searchQ.value
        const res = await apiClient.get('/admin/emoji', { params })
        const d = res.data?.data || {}
        list.value = d.data || []
        total.value = d.total || 0
    } catch (e) {
        ElMessage.error('加载失败')
    } finally {
        loading.value = false
    }
}

async function loadStats() {
    try {
        const res = await apiClient.get('/admin/emoji/stats')
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
        ElMessage.success('上传成功')
    } catch {
        ElMessage.error('上传失败')
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
            await apiClient.put(`/admin/emoji/${form._id}`, payload)
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/admin/emoji', payload)
            ElMessage.success('已添加')
        }
        showDialog.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

async function toggleActive(row) {
    try {
        await apiClient.put(`/admin/emoji/${row.id}`, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? '已启用' : '已禁用')
        loadStats()
    } catch {
        ElMessage.error('操作失败')
    }
}

async function removeEmoji(row) {
    try {
        await ElMessageBox.confirm(`确定删除表情 :${row.shortcode}:？`, '确认')
        await apiClient.delete(`/admin/emoji/${row.id}`)
        ElMessage.success('已删除')
        loadList()
        loadStats()
    } catch {}
}

async function doImport() {
    if (!importText.value.trim()) { ElMessage.warning('请输入要导入的表情'); return }
    importing.value = true
    const lines = importText.value.split('\n').map(l => l.trim()).filter(Boolean)
    const emojis = lines.map(line => {
        const parts = line.split(',').map(p => p.trim())
        return { shortcode: parts[0], image_url: parts[1] || '', category: parts[2] || 'general' }
    }).filter(e => e.shortcode && e.image_url)
    if (!emojis.length) { ElMessage.warning('没有有效的表情数据'); importing.value = false; return }
    try {
        const res = await apiClient.post('/admin/emoji/import', { emojis })
        const data = res.data?.data || {}
        ElMessage.success(`导入成功：${data.imported} 个${data.skipped ? `，跳过 ${data.skipped} 个` : ''}`)
        showImport.value = false
        importText.value = ''
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '导入失败')
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
.emoji-card-code { font-size: 13px; font-weight: 500; font-family: monospace; color: #409eff; }
.emoji-card-meta { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 4px; }
.emoji-usage { font-size: 11px; color: #909399; }
.emoji-card-actions { display: flex; align-items: center; gap: 4px; margin-top: 4px; }
.empty-state { grid-column: 1 / -1; text-align: center; padding: 60px; color: #909399; }

.table-emoji-img { width: 32px; height: 32px; object-fit: contain; }
.preview-emoji { width: 48px; height: 48px; object-fit: contain; margin-top: 8px; border: 1px solid #e4e7ed; border-radius: 4px; padding: 4px; }
.form-hint { font-size: 12px; color: #909399; margin-top: 4px; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
