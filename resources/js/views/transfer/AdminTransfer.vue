<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/transfer.js'

const loading = ref(false)
const stats = ref(null)
const transfers = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const transferDialog = ref(false)
const transferForm = ref({ type: 'device_transfer', license_id: null, target_customer_id: null, target_user_id: null, target_device_fingerprint: null, target_device_name: null, reason: '' })
const detailVisible = ref(false)
const detailData = ref(null)

const typeOptions = [
    { value: 'device_transfer', label: '设备转移' },
    { value: 'customer_transfer', label: '客户转移' },
    { value: 'user_transfer', label: '用户转移' },
]

async function loadStats() { try { const r = await api.stats(); stats.value = r.data.data } catch (e) {} }

async function loadTransfers(page = 1) {
    loading.value = true
    try {
        const res = await api.list({ page, per_page: 15 })
        const d = res.data.data
        transfers.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function showDetail(t) {
    try { const res = await api.show(t.id); detailData.value = res.data.data; detailVisible.value = true } catch (e) {}
}

async function approveTransfer(t) {
    try { await api.approve(t.id); ElMessage.success('已批准并执行转移'); loadTransfers(pagination.value.current_page); loadStats() } catch (e) { ElMessage.error('操作失败') }
}

async function rejectTransfer(t) {
    try {
        const { value } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝转移')
        if (!value) return
        await api.reject(t.id, { reason: value })
        ElMessage.success('已拒绝'); loadTransfers(pagination.value.current_page); loadStats()
    } catch (e) { if (e !== 'cancel') ElMessage.error('操作失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

onMounted(() => { loadStats(); loadTransfers() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>授权管理</el-breadcrumb-item>
            <el-breadcrumb-item>License 转移</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">总请求</div><div class="stat-value">{{ stats.total }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">待处理</div><div class="stat-value text-warning">{{ stats.pending }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">已完成</div><div class="stat-value text-success">{{ stats.completed }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">已拒绝</div><div class="stat-value">{{ stats.rejected }}</div></el-card></el-col>
            <el-col :span="8"><el-card shadow="never"><div class="stat-label">设备/客户/用户转移</div><div class="stat-value text-sm">{{ stats.by_type?.device_transfer || 0 }} / {{ stats.by_type?.customer_transfer || 0 }} / {{ stats.by_type?.user_transfer || 0 }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <el-table :data="transfers" v-loading="loading" stripe>
                <el-table-column prop="reference" label="编号" width="140" />
                <el-table-column label="类型" width="100"><template #default="{ row }">{{ {device_transfer:'设备转移',customer_transfer:'客户转移',user_transfer:'用户转移'}[row.type] }}</template></el-table-column>
                <el-table-column label="License" width="200">
                    <template #default="{ row }">
                        <div>{{ row.license?.license_key || '-' }}</div>
                        <div class="text-xs text-gray-400">{{ row.license?.product_name || '' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="发起人" width="120"><template #default="{ row }">{{ row.requester?.name || '-' }}</template></el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }"><el-tag :type="row.status === 'completed' ? 'success' : row.status === 'approved' ? '' : row.status === 'rejected' ? 'danger' : row.status === 'pending' ? 'warning' : 'info'" size="small">{{ {pending:'待审批',approved:'已批准',completed:'已完成',rejected:'已拒绝',cancelled:'已取消',expired:'已过期'}[row.status] }}</el-tag></template>
                </el-table-column>
                <el-table-column label="目标" min-width="150"><template #default="{ row }">{{ row.target_customer?.name || row.target_device?.name || '-' }}</template></el-table-column>
                <el-table-column label="原因" width="150" show-overflow-tooltip><template #default="{ row }">{{ row.reason || '-' }}</template></el-table-column>
                <el-table-column label="时间" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approveTransfer(row)">批准</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="rejectTransfer(row)">拒绝</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadTransfers" /></div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="转移请求详情" width="650px">
            <div v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="编号">{{ detailData.reference }}</el-descriptions-item>
                    <el-descriptions-item label="类型">{{ typeOptions.find(t => t.value === detailData.type)?.label }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ detailData.status }}</el-descriptions-item>
                    <el-descriptions-item label="License">{{ detailData.license?.license_key }}</el-descriptions-item>
                    <el-descriptions-item label="发起人">{{ detailData.requester?.name }}</el-descriptions-item>
                    <el-descriptions-item label="审批人">{{ detailData.approver?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="审批时间">{{ fmtDate(detailData.approved_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="目标客户">{{ detailData.target_customer?.name || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-3"><el-divider>转移原因</el-divider><p class="text-sm">{{ detailData.reason || '无' }}</p></div>
                <div v-if="detailData.admin_notes"><el-divider>管理员备注</el-divider><p class="text-sm">{{ detailData.admin_notes }}</p></div>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
</style>
