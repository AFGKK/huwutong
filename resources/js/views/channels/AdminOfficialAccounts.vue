<template>
    <div>
        <el-page-header :content="'互物号管理 (' + total + ')'" @back="$router.push('/')" />
        <div class="mt-4 flex items-center gap-3 mb-4 flex-wrap">
            <el-input v-model="search" placeholder="搜索互物号名称/别名..." size="small" style="width:240px" clearable @input="loadAccounts" />
            <el-select v-model="statusFilter" placeholder="状态" size="small" style="width:120px" clearable @change="loadAccounts">
                <el-option label="待审核" value="pending" />
                <el-option label="已认证" value="verified" />
                <el-option label="待认证" value="verify_request" />
                <el-option label="正常" value="active" />
                <el-option label="已拒绝" value="rejected" />
                <el-option label="禁用" value="suspended" />
            </el-select>
            <el-select v-model="categoryFilter" placeholder="分类" size="small" style="width:130px" clearable @change="loadAccounts">
                <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
            </el-select>
            <!-- 批量操作 -->
            <template v-if="selectedIds.length">
                <el-button size="small" type="success" @click="batchToggle('active')">批量启用</el-button>
                <el-button size="small" type="warning" @click="batchToggle('suspended')">批量禁用</el-button>
                <el-button size="small" type="danger" @click="batchDelete">批量删除</el-button>
                <span class="text-xs text-gray-400">已选 {{ selectedIds.length }} 项</span>
            </template>
        </div>
        <el-table :data="accounts" v-loading="loading" border stripe size="small" @row-click="showDetail" @selection-change="onSelectionChange">
            <el-table-column type="selection" width="40" />
            <el-table-column label="ID" prop="id" width="60" />
            <el-table-column label="名称" width="180">
                <template #default="{ row }">
                    <div class="flex items-center gap-2">
                        <el-avatar :size="24" :src="row.avatar" v-if="row.avatar" />
                        <span v-else>📢</span>
                        <span class="font-medium cursor-pointer hover:text-blue-500" @click.stop="showDetail(row)">{{ row.name }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column label="别名" prop="slug" width="120" />
            <el-table-column label="分类" width="100">
                <template #default="{ row }">{{ row.category?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="所有者" width="120">
                <template #default="{ row }">{{ row.owner?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="关注" prop="followers_count" width="60" align="center" />
            <el-table-column label="文章" prop="articles_count" width="60" align="center" />
            <el-table-column label="状态" width="120" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                        {{ row.status === 'active' ? '正常' : row.status === 'suspended' ? '禁用' : row.status === 'pending' ? '待审核' : row.status === 'rejected' ? '已拒绝' : row.status }}
                    </el-tag>
                    <div v-if="row.is_verified" class="text-[10px] text-green-600 mt-0.5">
                        ✓ {{ row.settings?.verified_info?.type === 'enterprise' ? '企业认证' : '个人认证' }} · {{ row.settings?.verified_info?.name || '' }}
                    </div>
                    <div v-if="row.status === 'suspended' && row.settings?.appeal_reason" class="text-[10px] text-orange-500 mt-0.5">📩 申请解封</div>
                    <div v-if="row.settings?.verify_request && !row.settings?.verify_request?.rejected" class="text-[10px] text-blue-500 mt-0.5">🪪 申请认证</div>
                </template>
            </el-table-column>
            <el-table-column label="创建时间" width="140">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="420" fixed="right">
                <template #default="{ row }">
                    <template v-if="row.status === 'pending'">
                        <el-button size="small" text type="success" @click.stop="approveAccount(row)">✅ 通过</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectAccount(row)">❌ 拒绝</el-button>
                    </template>
                    <template v-else-if="row.status === 'suspended' && row.settings?.appeal_reason">
                        <el-button size="small" text type="success" @click.stop="approveAppeal(row)">✅ 同意解封</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectAppeal(row)">❌ 驳回</el-button>
                    </template>
                    <template v-else-if="row.settings?.verify_request && !row.settings?.verify_request?.rejected">
                        <el-button size="small" text type="success" @click.stop="approveVerify(row)">✅ 通过认证</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectVerify(row)">❌ 驳回</el-button>
                    </template>
                    <el-button size="small" text type="primary" @click.stop="showDetail(row)">详情</el-button>
                    <el-button size="small" text type="primary" @click.stop="editAccount(row)">编辑</el-button>
                    <el-button size="small" text :type="row.is_verified ? 'warning' : 'success'" @click.stop="toggleVerify(row)">
                        {{ row.is_verified ? '取消认证' : '认证' }}
                    </el-button>
                    <el-button v-if="row.status !== 'pending'" size="small" text :type="row.status === 'active' ? 'warning' : 'success'" @click.stop="toggleStatus(row)">
                        {{ row.status === 'active' ? '禁用' : '启用' }}
                    </el-button>
                    <el-button size="small" text type="danger" @click.stop="deleteAccount(row)">删除</el-button>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="hasMore" class="text-center py-4">
            <el-button :loading="loadingMore" size="small" @click="loadMore">加载更多</el-button>
        </div>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" :title="detail?.name" width="680px" top="5vh">
            <template v-if="detailLoading">
                <div class="text-center py-8"><el-icon class="is-loading" :size="32"><Loading /></el-icon></div>
            </template>
            <template v-else-if="detail">
                <div class="flex gap-4 mb-4">
                    <el-avatar :size="64" :src="detail.avatar" />
                    <div class="flex-1">
                        <div class="text-lg font-bold">{{ detail.name }}</div>
                        <div class="text-sm text-gray-400 mt-1">别名: {{ detail.slug }} | 分类: {{ detail.category?.name || '—' }}</div>
                        <div class="text-sm text-gray-400">{{ detail.description || '暂无简介' }}</div>
                    </div>
                </div>
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item label="所有者">{{ detail.owner?.name || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="关注数">{{ detail.followers_count }}</el-descriptions-item>
                    <el-descriptions-item label="文章数">{{ detail.articles_count }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="detail.status === 'active' ? 'success' : detail.status === 'pending' ? 'warning' : detail.status === 'rejected' ? 'info' : 'danger'" size="small">
                            {{ detail.status === 'active' ? '正常' : detail.status === 'pending' ? '待审核' : detail.status === 'rejected' ? '已拒绝' : '禁用' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ formatTime(detail.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="更新时间">{{ formatTime(detail.updated_at) }}</el-descriptions-item>
                    <el-descriptions-item label="认证状态" v-if="detail.is_verified">
                        <el-tag type="success" size="small">✓ 已认证</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="认证类型" v-if="detail.is_verified && detail.settings?.verified_info">
                        {{ detail.settings.verified_info.type === 'enterprise' ? '企业认证' : '个人认证' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="认证名称" v-if="detail.is_verified && detail.settings?.verified_info">
                        {{ detail.settings.verified_info.name }}
                    </el-descriptions-item>
                    <el-descriptions-item label="认证时间" v-if="detail.verified_at">
                        {{ formatTime(detail.verified_at) }}
                    </el-descriptions-item>
                </el-descriptions>

                <!-- 认证申请 -->
                <div v-if="detail.settings?.verify_request && !detail.settings?.verify_request?.rejected" class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-blue-700">🪪 认证申请</span>
                        <span class="text-xs text-blue-500">申请时间：{{ formatTime(detail.settings.verify_request.applied_at) }}</span>
                    </div>
                    <div class="text-sm text-blue-800 space-y-1">
                        <div>认证类型：{{ detail.settings.verify_request.type === 'enterprise' ? '企业认证' : '个人认证' }}</div>
                        <div>认证名称：{{ detail.settings.verify_request.name }}</div>
                        <div class="mt-2 bg-white rounded p-3 border border-blue-100">认证说明：{{ detail.settings.verify_request.reason }}</div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <el-button size="small" type="success" @click="approveVerify(detail)">✅ 通过认证</el-button>
                        <el-button size="small" type="danger" @click="rejectVerify(detail)">❌ 驳回</el-button>
                    </div>
                </div>
                <!-- 认证驳回记录 -->
                <div v-if="detail.settings?.verify_request?.rejected" class="mt-4 bg-red-50 border border-red-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-red-600">🚫 认证申请已被驳回</span>
                        <span v-if="detail.settings.verify_request.rejected_at" class="text-xs text-red-400">驳回时间：{{ formatTime(detail.settings.verify_request.rejected_at) }}</span>
                    </div>
                    <div v-if="detail.settings.verify_request.reject_reason" class="text-sm text-red-700 bg-white rounded p-3 border border-red-100">
                        <span class="font-medium">驳回原因：</span>{{ detail.settings.verify_request.reject_reason }}
                    </div>
                </div>

                <!-- 解封申请 -->
                <div v-if="detail.status === 'suspended' && detail.settings?.appeal_reason" class="mt-4 bg-orange-50 border border-orange-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-orange-700">📩 解封申请</span>
                        <span class="text-xs text-orange-500">申请时间：{{ formatTime(detail.settings.appealed_at) }}</span>
                    </div>
                    <div class="text-sm text-orange-800 bg-white rounded p-3 border border-orange-100">
                        {{ detail.settings.appeal_reason }}
                    </div>
                    <div class="flex gap-2 mt-3">
                        <el-button size="small" type="success" @click="approveAppeal(detail)">✅ 同意解封</el-button>
                        <el-button size="small" type="danger" @click="rejectAppeal(detail)">❌ 驳回</el-button>
                    </div>
                </div>
                <!-- 驳回记录 -->
                <div v-if="detail.settings?.appeal_rejected" class="mt-4 bg-red-50 border border-red-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-red-600">🚫 解封申请已被驳回</span>
                        <span v-if="detail.settings.appeal_rejected_at" class="text-xs text-red-400">驳回时间：{{ formatTime(detail.settings.appeal_rejected_at) }}</span>
                    </div>
                    <div v-if="detail.settings.appeal_reject_reason" class="text-sm text-red-700 bg-white rounded p-3 border border-red-100">
                        <span class="font-medium">驳回原因：</span>{{ detail.settings.appeal_reject_reason }}
                    </div>
                </div>

                <div class="mt-4" v-if="detail.articles?.length">
                    <div class="text-sm font-bold mb-2">最近文章 ({{ detail.articles.length }})</div>
                    <div v-for="a in detail.articles" :key="a.id" class="text-sm py-1 border-b border-gray-100 flex justify-between">
                        <span class="truncate">{{ a.title }}</span>
                        <span class="text-gray-400 text-xs">{{ formatTime(a.created_at) }}</span>
                    </div>
                </div>
                <div class="mt-4 text-right">
                    <el-button type="primary" size="small" @click="editAccount(detail)">✏️ 编辑</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- 编辑弹窗 -->
        <el-dialog v-model="editVisible" :title="'编辑 - ' + (editForm.name || '')" width="480px" top="20vh">
            <el-form :model="editForm" label-width="80px" size="small">
                <el-form-item label="名称">
                    <el-input v-model="editForm.name" maxlength="50" />
                </el-form-item>
                <el-form-item label="别名">
                    <el-input v-model="editForm.slug" maxlength="100" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="editForm.category_id" placeholder="选择分类" clearable style="width:100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="简介">
                    <el-input v-model="editForm.description" type="textarea" :rows="3" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="editVisible = false">取消</el-button>
                <el-button size="small" type="primary" :loading="editLoading" @click="submitEdit">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const accounts = ref([])
const categories = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const total = ref(0)
const search = ref('')
const statusFilter = ref('')
const categoryFilter = ref('')
const selectedIds = ref([])

// 详情
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref(null)

// 编辑
const editVisible = ref(false)
const editLoading = ref(false)
const editForm = ref({ id: null, name: '', slug: '', category_id: null, description: '' })

function formatTime(t) {
    if (!t) return ''
    return new Date(t).toLocaleString('zh-CN')
}

function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id)
}

async function loadAccounts() {
    loading.value = true; page.value = 1
    try {
        const params = { per_page: 20, page: 1 }
        if (search.value) params.q = search.value
        if (statusFilter.value) params.status = statusFilter.value
        if (categoryFilter.value) params.category_id = categoryFilter.value
        const res = await apiClient.get('/admin/official-accounts', { params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        accounts.value = res.data?.data || []
        total.value = res.data?.meta?.total || 0
        hasMore.value = (res.data?.meta?.last_page || 1) > 1
    } catch { accounts.value = []; total.value = 0 }
    finally { loading.value = false }
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value) return
    loadingMore.value = true; page.value++
    try {
        const params = { per_page: 20, page: page.value }
        if (search.value) params.q = search.value
        if (statusFilter.value) params.status = statusFilter.value
        if (categoryFilter.value) params.category_id = categoryFilter.value
        const res = await apiClient.get('/admin/official-accounts', { params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        accounts.value = [...accounts.value, ...(res.data?.data || [])]
        hasMore.value = page.value < (res.data?.meta?.last_page || 1)
    } catch { /* ignore */ }
    finally { loadingMore.value = false }
}

async function showDetail(acc) {
    detailVisible.value = true; detailLoading.value = true; detail.value = null
    try {
        const res = await apiClient.get(`/admin/official-accounts/${acc.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        detail.value = res.data?.data
    } catch { /* ignore */ }
    finally { detailLoading.value = false }
}

function editAccount(acc) {
    editForm.value = {
        id: acc.id,
        name: acc.name || '',
        slug: acc.slug || '',
        category_id: acc.category_id || null,
        description: acc.description || '',
    }
    editVisible.value = true
    detailVisible.value = false
}

async function submitEdit() {
    if (!editForm.value.name?.trim()) { ElMessage.warning('请输入名称'); return }
    editLoading.value = true
    try {
        const res = await apiClient.put(`/admin/official-accounts/${editForm.value.id}`, {
            name: editForm.value.name,
            slug: editForm.value.slug || undefined,
            category_id: editForm.value.category_id || undefined,
            description: editForm.value.description || undefined,
        }, { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } })
        ElMessage.success('已更新')
        editVisible.value = false
        await loadAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '更新失败')
    } finally { editLoading.value = false }
}

async function toggleStatus(acc) {
    try {
        const res = await apiClient.post(`/admin/official-accounts/${acc.id}/toggle-status`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        acc.status = res.data?.data?.status
        ElMessage.success(acc.status === 'active' ? '已启用' : '已禁用')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function approveAccount(acc) {
    try {
        await ElMessageBox.confirm(`确定审核通过「${acc.name}」？`, '确认通过', { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/approve`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已审核通过')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectAccount(acc) {
    try {
        const { value } = await ElMessageBox.prompt(`请输入拒绝「${acc.name}」的原因（可选）`, '拒绝审核', {
            confirmButtonText: '确认拒绝',
            cancelButtonText: '取消',
            inputPlaceholder: '拒绝原因...',
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/reject`, { reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已拒绝')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function approveAppeal(acc) {
    try {
        await ElMessageBox.confirm(`确定同意「${acc.name}」的解封申请？`, '确认解封', { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-appeal`, { action: 'approve' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已解封')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectAppeal(acc) {
    try {
        const { value } = await ElMessageBox.prompt(`请输入驳回「${acc.name}」解封申请的原因（可选）`, '驳回解封', {
            confirmButtonText: '确认驳回',
            cancelButtonText: '取消',
            inputPlaceholder: '驳回原因...',
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-appeal`, { action: 'reject', reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已驳回解封申请')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function approveVerify(acc) {
    try {
        await ElMessageBox.confirm(`确定通过「${acc.name}」的认证申请？`, '确认认证', { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-verify`, { action: 'approve' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('认证申请已通过')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectVerify(acc) {
    try {
        const { value } = await ElMessageBox.prompt(`请输入驳回「${acc.name}」认证申请的原因（可选）`, '驳回认证', {
            confirmButtonText: '确认驳回',
            cancelButtonText: '取消',
            inputPlaceholder: '驳回原因...',
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-verify`, { action: 'reject', reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已驳回认证申请')
        await loadAccounts()
    } catch { /* ignore */ }
}

async function toggleVerify(acc) {
    try {
        const label = acc.is_verified ? '取消认证' : '认证'
        await ElMessageBox.confirm(`确定${label}「${acc.name}」？`, '确认' + label)
        const res = await apiClient.post(`/admin/official-accounts/${acc.id}/verify`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        acc.is_verified = !acc.is_verified
        ElMessage.success(acc.is_verified ? '已认证' : '已取消认证')
    } catch { /* ignore */ }
}

async function deleteAccount(acc) {
    try {
        await ElMessageBox.confirm(`确定删除互物号「${acc.name}」？`, '确认删除', { type: 'warning' })
        await apiClient.delete(`/admin/official-accounts/${acc.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已删除')
        accounts.value = accounts.value.filter(a => a.id !== acc.id)
        total.value--
    } catch { /* ignore */ }
}

async function batchToggle(status) {
    try {
        const label = status === 'active' ? '启用' : '禁用'
        await ElMessageBox.confirm(`确定${label}选中的 ${selectedIds.value.length} 个互物号？`, '确认' + label)
        const res = await apiClient.post('/admin/official-accounts/batch-toggle-status',
            { ids: selectedIds.value, status },
            { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } }
        )
        ElMessage.success(res.data?.message || '操作完成')
        selectedIds.value = []
        await loadAccounts()
    } catch { /* ignore */ }
}

async function batchDelete() {
    try {
        await ElMessageBox.confirm(`确定删除选中的 ${selectedIds.value.length} 个互物号？（有文章的不会被删除）`, '确认删除', { type: 'warning' })
        const res = await apiClient.post('/admin/official-accounts/batch-delete',
            { ids: selectedIds.value },
            { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } }
        )
        ElMessage.success(res.data?.message || '操作完成')
        selectedIds.value = []
        await loadAccounts()
    } catch { /* ignore */ }
}

async function loadCategories() {
    try {
        const res = await apiClient.get('/official-accounts/categories')
        categories.value = res.data?.data || []
    } catch { categories.value = [] }
}

onMounted(() => { loadAccounts(); loadCategories() })
</script>
