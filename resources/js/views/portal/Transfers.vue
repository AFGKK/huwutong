<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/transfer.js'

const loading = ref(false)
const requests = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const licenses = ref([])
const showForm = ref(false)
const form = ref({ type: 'device_transfer', license_id: null, target_customer_id: null, target_device_fingerprint: null, target_device_name: null, reason: '' })

const typeOptions = [
    { value: 'device_transfer', label: '设备转移' },
    { value: 'customer_transfer', label: '客户转移' },
    { value: 'user_transfer', label: '用户转移' },
]

async function loadRequests(page = 1) {
    loading.value = true
    try {
        const res = await api.myRequests({ page, per_page: 15 })
        const d = res.data.data
        requests.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function loadLicenses() {
    try { const res = await api.transferableLicenses(); licenses.value = res.data.data || [] } catch (e) {}
}

async function openForm() {
    await loadLicenses()
    form.value = { type: 'device_transfer', license_id: null, target_customer_id: null, target_device_fingerprint: null, target_device_name: null, reason: '' }
    showForm.value = true
}

async function submitTransfer() {
    try {
        await api.create(form.value)
        ElMessage.success('转移请求已提交，请等待管理员审批')
        showForm.value = false
        loadRequests(pagination.value.current_page)
    } catch (e) { ElMessage.error('提交失败: ' + (e.response?.data?.message || e.message)) }
}

async function cancelTransfer(t) {
    try {
        await api.myCancel(t.id)
        ElMessage.success('已取消')
        loadRequests(pagination.value.current_page)
    } catch (e) { ElMessage.error('取消失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

onMounted(() => { loadRequests() })
</script>

<template>
    <div>
        <div class="page-header flex justify-between items-center">
            <div>
                <h2>License 转移</h2>
                <p class="text-sm text-gray-400">申请将 License 转移到其他设备、客户或用户</p>
            </div>
            <el-button type="primary" @click="openForm()">新建转移请求</el-button>
        </div>

        <el-card shadow="never" v-loading="loading">
            <el-table :data="requests" stripe v-if="requests.length">
                <el-table-column prop="reference" label="编号" width="140" />
                <el-table-column label="类型" width="100"><template #default="{ row }">{{ typeOptions.find(t => t.value === row.type)?.label }}</template></el-table-column>
                <el-table-column label="License" width="200"><template #default="{ row }">{{ row.license?.license_key || '-' }}</template></el-table-column>
                <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status === 'completed' ? 'success' : row.status === 'pending' ? 'warning' : row.status === 'rejected' ? 'danger' : 'info'" size="small">{{ {pending:'待审批',approved:'已批准',completed:'已完成',rejected:'已拒绝',cancelled:'已取消',expired:'已过期'}[row.status] }}</el-tag></template></el-table-column>
                <el-table-column label="原因" min-width="150" show-overflow-tooltip><template #default="{ row }">{{ row.reason || '-' }}</template></el-table-column>
                <el-table-column label="申请时间" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'pending'" size="small" type="danger" text @click="cancelTransfer(row)">取消</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-else description="暂无转移请求" />
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadRequests" /></div>
        </el-card>

        <!-- 新建请求弹窗 -->
        <el-dialog v-model="showForm" title="新建 License 转移请求" width="500px">
            <el-form :model="form" label-width="130px">
                <el-form-item label="转移类型"><el-select v-model="form.type" class="w-full"><el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" /></el-select></el-form-item>
                <el-form-item label="选择 License"><el-select v-model="form.license_id" class="w-full" placeholder="选择要转移的 License">
                    <el-option v-for="l in licenses" :key="l.id" :label="l.license_key + (l.product_name ? ' ('+l.product_name+')' : '')" :value="l.id" />
                </el-select></el-form-item>
                <el-form-item v-if="form.type === 'customer_transfer'" label="目标客户 ID"><el-input v-model.number="form.target_customer_id" type="number" /></el-form-item>
                <el-form-item v-if="form.type === 'device_transfer'" label="目标设备指纹"><el-input v-model="form.target_device_fingerprint" /></el-form-item>
                <el-form-item v-if="form.type === 'device_transfer'" label="目标设备名称"><el-input v-model="form.target_device_name" placeholder="可选" /></el-form-item>
                <el-form-item label="转移原因"><el-input v-model="form.reason" type="textarea" :rows="3" placeholder="请说明转移原因" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showForm = false">取消</el-button><el-button type="primary" @click="submitTransfer">提交申请</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
