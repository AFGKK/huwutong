<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>🔇 敏感词管理</h2>
                <p class="text-gray-500 text-sm">管理聊天中的敏感词过滤规则，支持 AC 自动机多模式匹配</p>
            </div>
            <div class="flex gap-2">
                <el-button @click="showImport = true">
                    <el-icon><Upload /></el-icon> 批量导入
                </el-button>
                <el-button @click="exportWords">
                    <el-icon><Download /></el-icon> 导出
                </el-button>
                <el-button type="primary" @click="openAdd">
                    <el-icon><Plus /></el-icon> 添加敏感词
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
                    <div class="stat-item"><div class="stat-value">{{ stats.inactive }}</div><div class="stat-label">已禁用</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-blue-500">{{ stats.categories }}</div><div class="stat-label">分类数</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="关键词">
                    <el-input v-model="filters.q" placeholder="搜索敏感词" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="filters.category" placeholder="全部" clearable @change="loadList" style="width:140px">
                        <el-option label="通用" value="general" />
                        <el-option label="政治" value="politics" />
                        <el-option label="广告" value="ad" />
                        <el-option label="辱骂" value="abuse" />
                        <el-option label="色情" value="porn" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 数据表格 -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="word" label="敏感词" min-width="160" />
                <el-table-column prop="replacement" label="替换为" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.replacement || '***' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="category" label="分类" width="100">
                    <template #default="{ row }">
                        <el-tag :type="categoryType(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="severity" label="严重级别" width="100">
                    <template #default="{ row }">
                        <el-tag :type="severityType(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" label="启用" width="80">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="toggleActive(row)" />
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">编辑</el-button>
                        <el-button text size="small" type="danger" @click="removeWord(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="50" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="editing ? '编辑敏感词' : '添加敏感词'" width="500px" :close-on-click-modal="false">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small">
                <el-form-item label="敏感词" prop="word">
                    <el-input v-model="form.word" placeholder="输入敏感词" maxlength="100" />
                </el-form-item>
                <el-form-item label="替换为" prop="replacement">
                    <el-input v-model="form.replacement" placeholder="默认 ***" maxlength="100" />
                </el-form-item>
                <el-form-item label="分类" prop="category">
                    <el-select v-model="form.category" style="width:100%">
                        <el-option label="通用" value="general" />
                        <el-option label="政治" value="politics" />
                        <el-option label="广告" value="ad" />
                        <el-option label="辱骂" value="abuse" />
                        <el-option label="色情" value="porn" />
                    </el-select>
                </el-form-item>
                <el-form-item label="严重级别" prop="severity">
                    <el-select v-model="form.severity" style="width:100%">
                        <el-option label="低" value="low" />
                        <el-option label="中" value="medium" />
                        <el-option label="高" value="high" />
                        <el-option label="严重" value="critical" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveWord">保存</el-button>
            </template>
        </el-dialog>

        <!-- 批量导入对话框 -->
        <el-dialog v-model="showImport" title="批量导入敏感词" width="600px" :close-on-click-modal="false">
            <el-alert type="info" :closable="false" class="mb-4">
                <template #title>
                    每行一个敏感词，格式: <code>敏感词</code> 或 <code>敏感词,替换文本,分类,严重级别</code>
                </template>
            </el-alert>
            <el-input v-model="importText" type="textarea" :rows="12" placeholder="示例：&#10;傻逼,**,abuse,high&#10;发票,***,ad,medium&#10;代开发票" />
            <div class="mt-2 text-sm text-gray-400">支持最多 5000 条，已存在的敏感词会自动跳过</div>
            <template #footer>
                <el-button @click="showImport = false">取消</el-button>
                <el-button type="primary" :loading="importing" @click="doImport">开始导入</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/utils/request'

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
const rules = { word: [{ required: true, message: '请输入敏感词', trigger: 'blur' }] }

const showImport = ref(false)
const importText = ref('')
const importing = ref(false)

function categoryLabel(cat) {
    const map = { general: '通用', politics: '政治', ad: '广告', abuse: '辱骂', porn: '色情' }
    return map[cat] || cat
}
function categoryType(cat) {
    const map = { general: 'info', politics: 'danger', ad: 'warning', abuse: '', porn: 'danger' }
    return map[cat] || 'info'
}
function severityLabel(sev) {
    const map = { low: '低', medium: '中', high: '高', critical: '严重' }
    return map[sev] || sev
}
function severityType(sev) {
    const map = { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }
    return map[sev] || 'info'
}

async function loadList() {
    loading.value = true
    try {
        const res = await apiClient.get('/im/sensitive-words', { params: { page: page.value, ...filters } })
        const d = res.data?.data || res.data || {}
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
        const res = await apiClient.get('/im/sensitive-words', { params: { per_page: 1 } })
        const d = res.data?.data || res.data || {}
        stats.total = d.total || 0
        // 统计活跃和非活跃
        try {
            const allRes = await apiClient.get('/im/sensitive-words', { params: { per_page: 10000 } })
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
            await apiClient.put(`/im/sensitive-words/${form._id}`, {
                word: form.word, replacement: form.replacement,
                category: form.category, severity: form.severity,
            })
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/im/sensitive-words', {
                word: form.word, replacement: form.replacement,
                category: form.category, severity: form.severity,
            })
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
        await apiClient.put(`/im/sensitive-words/${row.id}`, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? '已启用' : '已禁用')
        loadStats()
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

async function removeWord(row) {
    try {
        await ElMessageBox.confirm(`确定删除敏感词「${row.word}」？`, '确认')
        await apiClient.delete(`/im/sensitive-words/${row.id}`)
        ElMessage.success('已删除')
        loadList()
        loadStats()
    } catch {}
}

async function doImport() {
    if (!importText.value.trim()) { ElMessage.warning('请输入要导入的敏感词'); return }
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
            severity: ['low', 'medium', 'high', 'critical'].includes(parts[3]) ? parts[3] : 'medium',
        })
    }
    if (!words.length) { ElMessage.warning('没有有效的敏感词'); importing.value = false; return }
    try {
        const res = await apiClient.post('/im/sensitive-words/import', { words })
        const data = res.data?.data || {}
        ElMessage.success(`导入成功：${data.imported} 条${data.skipped ? `，跳过 ${data.skipped} 条` : ''}`)
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

async function exportWords() {
    try {
        const res = await apiClient.get('/im/sensitive-words/export')
        const data = res.data?.data || []
        const csv = ['敏感词,替换为,分类,严重级别,启用']
        data.forEach(w => {
            csv.push(`${w.word},${w.replacement},${w.category},${w.severity},${w.is_active ? '是' : '否'}`)
        })
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `敏感词_${new Date().toISOString().slice(0, 10)}.csv`
        a.click()
        URL.revokeObjectURL(url)
        ElMessage.success(`已导出 ${data.length} 条`)
    } catch (e) {
        ElMessage.error('导出失败')
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
.text-blue-500 { color: #409eff; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.justify-center { justify-content: center; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
