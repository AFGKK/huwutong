<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getExportTypes, createExport, getMyExports, downloadExport, deleteExport } from '../../api/dataExport.js'

const exportTypes = ref([])
const myExports = ref([])
const loading = ref(false)
const typesLoading = ref(false)

// 导出对话框
const exportDialogVisible = ref(false)
const exportForm = ref({
    type: '',
    format: 'csv',
    filters: {},
})
const submitting = ref(false)

async function loadTypes() {
    typesLoading.value = true
    try {
        const res = await getExportTypes()
        exportTypes.value = res.data || []
    } catch (e) {
        console.error('Failed to load export types:', e)
    } finally {
        typesLoading.value = false
    }
}

async function loadExports() {
    loading.value = true
    try {
        const res = await getMyExports()
        myExports.value = res.data || []
    } catch (e) {
        console.error('Failed to load exports:', e)
    } finally {
        loading.value = false
    }
}

function openExportDialog(type) {
    exportForm.value = { type, format: 'csv', filters: {} }
    exportDialogVisible.value = true
}

async function submitExport() {
    submitting.value = true
    try {
        const res = await createExport(exportForm.value)
        ElMessage.success(res.data?.message || '导出任务已创建')
        exportDialogVisible.value = false
        loadExports()
    } catch (e) {
        if (e.response?.status === 429) {
            ElMessage.warning('导出太频繁，请60秒后再试')
        } else {
            ElMessage.error(e.response?.data?.message || '创建导出失败')
        }
    } finally {
        submitting.value = false
    }
}

async function handleDownload(exp) {
    if (exp.status !== 'completed') {
        ElMessage.info('文件尚未就绪，请稍后再试')
        return
    }
    try {
        const res = await downloadExport(exp.id)
        const url = window.URL.createObjectURL(new Blob([res.data], { type: res.headers?.['content-type'] || 'text/csv' }))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', exp.file_name || `export_${exp.type}.csv`)
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(url)
        ElMessage.success('下载已开始')
    } catch (e) {
        if (e.response?.status === 410) {
            ElMessage.error('文件已过期，请重新导出')
        } else {
            ElMessage.error('下载失败')
        }
    }
}

async function handleDelete(exp) {
    try {
        await ElMessageBox.confirm('删除后不可恢复，确定删除？', '确认')
        await deleteExport(exp.id)
        ElMessage.success('已删除')
        loadExports()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('删除失败')
    }
}

function statusType(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' }
    return map[status] || 'info'
}

function statusText(status) {
    const map = { pending: '等待中', processing: '生成中', completed: '已完成', failed: '失败' }
    return map[status] || status
}

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '-'
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0
    let size = bytes
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
    return `${size.toFixed(1)} ${units[i]}`
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleString('zh-CN')
}

onMounted(() => {
    loadTypes()
    loadExports()
})
</script>

<template>
    <div>
        <div class="mb-4">
            <h1 class="text-xl font-semibold">数据导出</h1>
            <p class="text-gray-500 text-sm mt-1">选择需要导出的数据类型，下载 CSV 文件用于本地分析或存档。</p>
        </div>

        <!-- 可导出的数据类型 -->
        <el-card class="mb-6">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-semibold">选择导出类型</span>
                    <el-button size="small" @click="loadTypes" :icon="'Refresh'" circle />
                </div>
            </template>
            <div v-loading="typesLoading">
                <el-row :gutter="16">
                    <el-col :span="6" v-for="item in exportTypes" :key="item.type">
                        <el-card shadow="hover" class="export-type-card" :class="{ 'cursor-pointer': item.can_export }"
                            @click="item.can_export && openExportDialog(item.type)">
                            <div class="text-center py-3">
                                <div class="text-3xl mb-2">
                                    <template v-if="item.type === 'licenses'">🔑</template>
                                    <template v-else-if="item.type === 'invoices'">🧾</template>
                                    <template v-else-if="item.type === 'activations'">📡</template>
                                    <template v-else>👤</template>
                                </div>
                                <div class="font-semibold">{{ item.label }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ item.record_count }} 条记录</div>
                                <div class="mt-2">
                                    <el-tag v-if="!item.can_export" type="info" size="small">无数据</el-tag>
                                    <el-tag v-else type="primary" size="small" effect="dark">导出 CSV</el-tag>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-empty v-if="!typesLoading && exportTypes.length === 0" description="暂无可用数据类型" />
            </div>
        </el-card>

        <!-- 导出历史 -->
        <el-card>
            <template #header>
                <span class="font-semibold">导出历史</span>
            </template>

            <el-table :data="myExports" v-loading="loading" stripe>
                <el-table-column label="类型" width="120">
                    <template #default="{ row }">
                        <el-tag type="primary" size="small">{{ row.type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="格式" width="80">
                    <template #default="{ row }">.{{ row.format }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusText(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="记录数" width="90">
                    <template #default="{ row }">{{ row.record_count || '-' }}</template>
                </el-table-column>
                <el-table-column label="文件大小" width="100">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="过期时间" width="170">
                    <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'completed'" size="small" type="primary"
                            @click="handleDownload(row)">下载</el-button>
                        <el-button v-else-if="row.status === 'failed'" size="small" type="danger"
                            @click="ElMessage.error(row.error_message || '导出失败')">失败详情</el-button>
                        <el-button v-else size="small" disabled>生成中</el-button>
                        <el-popconfirm title="删除此导出记录？" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!loading && myExports.length === 0" description="暂无导出记录" />
        </el-card>

        <!-- 导出对话框 -->
        <el-dialog v-model="exportDialogVisible" title="确认导出" width="400px">
            <div class="text-center py-4">
                <div class="text-lg font-semibold mb-2">
                    导出 {{ exportTypes.find(t => t.type === exportForm.type)?.label || exportForm.type }}
                </div>
                <div class="text-gray-500 text-sm mb-4">
                    {{ exportTypes.find(t => t.type === exportForm.type)?.record_count || 0 }} 条记录将导出为 CSV 文件
                </div>
                <div class="text-gray-400 text-xs">
                    文件将在7天内可下载，过期自动删除
                </div>
            </div>
            <template #footer>
                <el-button @click="exportDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitExport" :loading="submitting">确认导出</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.export-type-card {
    transition: all .2s;
    border-radius: 10px;
}
.export-type-card.cursor-pointer:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    border-color: #409eff;
}
</style>
