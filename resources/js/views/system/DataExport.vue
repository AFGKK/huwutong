<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getAdminExports, getExportStats, adminCreateExport } from '../../api/dataExport.js'

const exports = ref([])
const stats = ref(null)
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const loading = ref(false)

const filters = ref({
    type: '',
    status: '',
    customer_id: '',
})

const createDialogVisible = ref(false)
const createForm = ref({
    customer_id: '',
    type: 'licenses',
    format: 'csv',
})
const submitting = ref(false)

const typeOptions = [
    { value: 'licenses', label: 'License 列表' },
    { value: 'invoices', label: '发票/账单' },
    { value: 'activations', label: '激活记录' },
    { value: 'customers', label: '客户信息' },
]

const statusOptions = [
    { value: 'pending', label: '等待中', type: 'info' },
    { value: 'processing', label: '生成中', type: 'warning' },
    { value: 'completed', label: '已完成', type: 'success' },
    { value: 'failed', label: '失败', type: 'danger' },
]

const statusMap = statusOptions.reduce((m, o) => { m[o.value] = o; return m }, {})

async function loadStats() {
    try {
        const res = await getExportStats()
        stats.value = res.data
    } catch (e) {
        console.error('Failed to load stats:', e)
    }
}

async function loadExports(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page }
        const res = await getAdminExports(params)
        exports.value = res.data.data || []
        pagination.value = {
            total: res.data.total || 0,
            current_page: res.data.current_page || page,
            per_page: res.data.per_page || 15,
        }
    } catch (e) {
        console.error('Failed to load exports:', e)
    } finally {
        loading.value = false
    }
}

function handleSearch() { loadExports(1) }
function resetFilters() {
    filters.value = { type: '', status: '', customer_id: '' }
    loadExports(1)
}

async function handleCreate() {
    submitting.value = true
    try {
        await adminCreateExport(createForm.value)
        ElMessage.success('导出任务已创建')
        createDialogVisible.value = false
        loadExports(1)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
    } finally {
        submitting.value = false
    }
}

function statusType(st) { return statusMap[st]?.type || 'info' }
function statusText(st) { return statusMap[st]?.label || st }

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '-'
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0; let size = bytes
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
    return `${size.toFixed(1)} ${units[i]}`
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleString('zh-CN')
}

function handlePageChange(page) { loadExports(page) }

onMounted(() => {
    loadStats()
    loadExports()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>系统管理</el-breadcrumb-item>
            <el-breadcrumb-item>数据导出管理</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">📦 数据导出管理</span>
                    <el-button type="primary" @click="createDialogVisible = true">创建导出</el-button>
                </div>
            </template>

            <!-- 统计 -->
            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">总导出次数</div>
                        <div class="text-2xl font-bold mt-1">{{ stats.total_exports || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6" v-for="(count, type) in stats.by_type" :key="type">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-gray-500 text-sm">{{ typeOptions.find(t => t.value === type)?.label || type }}</div>
                        <div class="text-2xl font-bold mt-1">{{ count }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 筛选 -->
            <div class="flex gap-3 mb-4 flex-wrap items-center">
                <el-select v-model="filters.type" placeholder="按类型" clearable style="width:150px">
                    <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-select v-model="filters.status" placeholder="按状态" clearable style="width:140px">
                    <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select>
                <el-input v-model="filters.customer_id" placeholder="客户ID" clearable style="width:160px" />
                <el-button type="primary" @click="handleSearch">搜索</el-button>
                <el-button @click="resetFilters">重置</el-button>
            </div>

            <!-- 列表 -->
            <el-table :data="exports" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column label="客户" min-width="160">
                    <template #default="{ row }">
                        <span v-if="row.customer">{{ row.customer.name || row.customer.email || '#' + row.customer_id }}</span>
                        <span v-else class="text-gray-400">#{{ row.customer_id }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="120">
                    <template #default="{ row }">
                        <el-tag type="primary" size="small">{{ typeOptions.find(t => t.value === row.type)?.label || row.type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="格式" width="70">.{{ row.format }}</el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="记录数" width="80">
                    <template #default="{ row }">{{ row.record_count || '-' }}</template>
                </el-table-column>
                <el-table-column label="文件大小" width="100">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column label="创建时间" width="160">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="完成时间" width="160">
                    <template #default="{ row }">{{ formatDate(row.completed_at) }}</template>
                </el-table-column>
                <el-table-column label="过期" width="160">
                    <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="flex justify-center mt-4">
                <el-pagination v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page" :total="pagination.total"
                    layout="prev, pager, next, total" @current-change="handlePageChange" />
            </div>
        </el-card>

        <!-- 创建导出对话框 -->
        <el-dialog v-model="createDialogVisible" title="为客户创建导出" width="450px">
            <el-form :model="createForm" label-position="top">
                <el-form-item label="客户ID" required>
                    <el-input v-model="createForm.customer_id" placeholder="输入客户ID" />
                </el-form-item>
                <el-form-item label="数据类型" required>
                    <el-select v-model="createForm.type" style="width:100%">
                        <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="格式">
                    <el-radio-group v-model="createForm.format">
                        <el-radio value="csv">CSV</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleCreate" :loading="submitting">创建</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
</style>
