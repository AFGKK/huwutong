<template>
    <div>
        <el-page-header :content="t(`${P}.title_with_count`, { n: total })" @back="$router.push('/')" />
        <div class="mt-4 flex items-center gap-3 mb-4 flex-wrap">
            <el-input v-model="search" :placeholder="t(`${P}.search_ph`)" size="small" style="width:240px" clearable @input="loadAccounts" />
            <el-select v-model="statusFilter" :placeholder="t(`${P}.filters.status_ph`)" size="small" style="width:120px" clearable @change="loadAccounts">
                <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
            </el-select>
            <el-select v-model="categoryFilter" :placeholder="t(`${P}.filters.category_ph`)" size="small" style="width:130px" clearable @change="loadAccounts">
                <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
            </el-select>
            <template v-if="selectedIds.length">
                <el-button size="small" type="success" @click="batchToggle('active')">{{ t(`${P}.batch.enable`) }}</el-button>
                <el-button size="small" type="warning" @click="batchToggle('suspended')">{{ t(`${P}.batch.disable`) }}</el-button>
                <el-button size="small" type="danger" @click="batchDelete">{{ t(`${P}.batch.delete`) }}</el-button>
                <span class="text-xs text-gray-400">{{ t(`${P}.batch.selected`, { n: selectedIds.length }) }}</span>
            </template>
        </div>
        <el-table :data="accounts" v-loading="loading" border stripe size="small" @row-click="showDetail" @selection-change="onSelectionChange">
            <el-table-column type="selection" width="40" />
            <el-table-column :label="t(`${P}.cols.id`)" prop="id" width="60" />
            <el-table-column :label="t('channels_page.name')" width="180">
                <template #default="{ row }">
                    <div class="flex items-center gap-2">
                        <el-avatar :size="24" :src="row.avatar" v-if="row.avatar" />
                        <span v-else class="text-xs text-gray-400">{{ t('channels_page.avatar_fallback') }}</span>
                        <span class="font-medium cursor-pointer hover:text-blue-500" @click.stop="showDetail(row)">{{ row.name }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.slug`)" prop="slug" width="120" />
            <el-table-column :label="t('channels_page.category')" width="100">
                <template #default="{ row }">{{ row.category?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.owner`)" width="120">
                <template #default="{ row }">{{ row.owner?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.followers`)" prop="followers_count" width="60" align="center" />
            <el-table-column :label="t(`${P}.cols.articles`)" prop="articles_count" width="60" align="center" />
            <el-table-column :label="t(`${P}.cols.status`)" width="120" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'danger' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                        {{ statusLabel(row.status) }}
                    </el-tag>
                    <div v-if="row.is_verified" class="text-[10px] text-green-600 mt-0.5">
                        {{ t(`${P}.verified_badge`) }} {{ verifyTypeLabel(row.settings?.verified_info?.type) }} · {{ row.settings?.verified_info?.name || '' }}
                    </div>
                    <div v-if="row.status === 'suspended' && row.settings?.appeal_reason" class="text-[10px] text-orange-500 mt-0.5">{{ t('channels_page.apply_unban') }}</div>
                    <div v-if="row.settings?.verify_request && !row.settings?.verify_request?.rejected" class="text-[10px] text-blue-500 mt-0.5">{{ t('channels_page.apply_verify') }}</div>
                </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.created_at`)" width="140">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.actions`)" width="420" fixed="right">
                <template #default="{ row }">
                    <template v-if="row.status === 'pending'">
                        <el-button size="small" text type="success" @click.stop="approveAccount(row)">{{ t('actions.approve') }}</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectAccount(row)">{{ t(`${P}.actions.reject_review`) }}</el-button>
                    </template>
                    <template v-else-if="row.status === 'suspended' && row.settings?.appeal_reason">
                        <el-button size="small" text type="success" @click.stop="approveAppeal(row)">{{ t(`${P}.actions.approve_appeal`) }}</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectAppeal(row)">{{ t('actions.reject') }}</el-button>
                    </template>
                    <template v-else-if="row.settings?.verify_request && !row.settings?.verify_request?.rejected">
                        <el-button size="small" text type="success" @click.stop="approveVerify(row)">{{ t(`${P}.actions.approve_verify`) }}</el-button>
                        <el-button size="small" text type="danger" @click.stop="rejectVerify(row)">{{ t('actions.reject') }}</el-button>
                    </template>
                    <el-button size="small" text type="primary" @click.stop="showDetail(row)">{{ t(`${P}.actions.detail`) }}</el-button>
                    <el-button size="small" text type="primary" @click.stop="editAccount(row)">{{ t('actions.edit') }}</el-button>
                    <el-button size="small" text :type="row.is_verified ? 'warning' : 'success'" @click.stop="toggleVerify(row)">
                        {{ row.is_verified ? t(`${P}.actions.revoke_verify`) : t(`${P}.actions.verify`) }}
                    </el-button>
                    <el-button v-if="row.status !== 'pending'" size="small" text :type="row.status === 'active' ? 'warning' : 'success'" @click.stop="toggleStatus(row)">
                        {{ row.status === 'active' ? t('actions.disable') : t('actions.enable') }}
                    </el-button>
                    <el-button size="small" text type="danger" @click.stop="deleteAccount(row)">{{ t('actions.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="hasMore" class="text-center py-4">
            <el-button :loading="loadingMore" size="small" @click="loadMore">{{ t('channels_page.load_more') }}</el-button>
        </div>

        <el-dialog v-model="detailVisible" :title="detail?.name" width="680px" top="5vh">
            <template v-if="detailLoading">
                <div class="text-center py-8"><el-icon class="is-loading" :size="32"><Loading /></el-icon></div>
            </template>
            <template v-else-if="detail">
                <div class="flex gap-4 mb-4">
                    <el-avatar :size="64" :src="detail.avatar" />
                    <div class="flex-1">
                        <div class="text-lg font-bold">{{ detail.name }}</div>
                        <div class="text-sm text-gray-400 mt-1">{{ t(`${P}.detail.meta_slug_category`, { slug: detail.slug, category: detail.category?.name || '—' }) }}</div>
                        <div class="text-sm text-gray-400">{{ detail.description || t('channels_page.no_description') }}</div>
                    </div>
                </div>
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item :label="t(`${P}.cols.owner`)">{{ detail.owner?.name || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.followers_count`)">{{ detail.followers_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.articles_count`)">{{ detail.articles_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.status`)">
                        <el-tag :type="detail.status === 'active' ? 'success' : detail.status === 'pending' ? 'warning' : detail.status === 'rejected' ? 'info' : 'danger'" size="small">
                            {{ statusLabel(detail.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.cols.created_at`)">{{ formatTime(detail.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.updated_at`)">{{ formatTime(detail.updated_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.verify_status`)" v-if="detail.is_verified">
                        <el-tag type="success" size="small">{{ t(`${P}.detail.verified`) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.verify_type`)" v-if="detail.is_verified && detail.settings?.verified_info">
                        {{ verifyTypeLabel(detail.settings.verified_info.type) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.verify_name`)" v-if="detail.is_verified && detail.settings?.verified_info">
                        {{ detail.settings.verified_info.name }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.verified_at`)" v-if="detail.verified_at">
                        {{ formatTime(detail.verified_at) }}
                    </el-descriptions-item>
                </el-descriptions>

                <div v-if="detail.settings?.verify_request && !detail.settings?.verify_request?.rejected" class="mt-4 bg-blue-50 border border-blue-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-blue-700">{{ t(`${P}.detail.verify_request_title`) }}</span>
                        <span class="text-xs text-blue-500">{{ t(`${P}.detail.applied_at`, { time: formatTime(detail.settings.verify_request.applied_at) }) }}</span>
                    </div>
                    <div class="text-sm text-blue-800 space-y-1">
                        <div>{{ t(`${P}.detail.verify_type_row`, { type: verifyTypeLabel(detail.settings.verify_request.type) }) }}</div>
                        <div>{{ t(`${P}.detail.verify_name_row`, { name: detail.settings.verify_request.name }) }}</div>
                        <div class="mt-2 bg-white rounded p-3 border border-blue-100">{{ t(`${P}.detail.verify_reason_row`, { reason: detail.settings.verify_request.reason }) }}</div>
                    </div>
                    <div class="flex gap-2 mt-3">
                        <el-button size="small" type="success" @click="approveVerify(detail)">{{ t(`${P}.actions.approve_verify`) }}</el-button>
                        <el-button size="small" type="danger" @click="rejectVerify(detail)">{{ t('actions.reject') }}</el-button>
                    </div>
                </div>
                <div v-if="detail.settings?.verify_request?.rejected" class="mt-4 bg-red-50 border border-red-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-red-600">{{ t(`${P}.detail.verify_rejected_title`) }}</span>
                        <span v-if="detail.settings.verify_request.rejected_at" class="text-xs text-red-400">{{ t(`${P}.detail.rejected_at`, { time: formatTime(detail.settings.verify_request.rejected_at) }) }}</span>
                    </div>
                    <div v-if="detail.settings.verify_request.reject_reason" class="text-sm text-red-700 bg-white rounded p-3 border border-red-100">
                        <span class="font-medium">{{ t(`${P}.detail.reject_reason_label`) }}</span>{{ detail.settings.verify_request.reject_reason }}
                    </div>
                </div>

                <div v-if="detail.status === 'suspended' && detail.settings?.appeal_reason" class="mt-4 bg-orange-50 border border-orange-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-orange-700">{{ t(`${P}.detail.appeal_title`) }}</span>
                        <span class="text-xs text-orange-500">{{ t(`${P}.detail.applied_at`, { time: formatTime(detail.settings.appealed_at) }) }}</span>
                    </div>
                    <div class="text-sm text-orange-800 bg-white rounded p-3 border border-orange-100">
                        {{ detail.settings.appeal_reason }}
                    </div>
                    <div class="flex gap-2 mt-3">
                        <el-button size="small" type="success" @click="approveAppeal(detail)">{{ t(`${P}.actions.approve_appeal`) }}</el-button>
                        <el-button size="small" type="danger" @click="rejectAppeal(detail)">{{ t('actions.reject') }}</el-button>
                    </div>
                </div>
                <div v-if="detail.settings?.appeal_rejected" class="mt-4 bg-red-50 border border-red-100 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-sm font-bold text-red-600">{{ t(`${P}.detail.appeal_rejected_title`) }}</span>
                        <span v-if="detail.settings.appeal_rejected_at" class="text-xs text-red-400">{{ t(`${P}.detail.rejected_at`, { time: formatTime(detail.settings.appeal_rejected_at) }) }}</span>
                    </div>
                    <div v-if="detail.settings.appeal_reject_reason" class="text-sm text-red-700 bg-white rounded p-3 border border-red-100">
                        <span class="font-medium">{{ t(`${P}.detail.reject_reason_label`) }}</span>{{ detail.settings.appeal_reject_reason }}
                    </div>
                </div>

                <div class="mt-4" v-if="detail.articles?.length">
                    <div class="text-sm font-bold mb-2">{{ t(`${P}.detail.recent_articles`, { n: detail.articles.length }) }}</div>
                    <div v-for="a in detail.articles" :key="a.id" class="text-sm py-1 border-b border-gray-100 flex justify-between">
                        <span class="truncate">{{ a.title }}</span>
                        <span class="text-gray-400 text-xs">{{ formatTime(a.created_at) }}</span>
                    </div>
                </div>
                <div class="mt-4 text-right">
                    <el-button type="primary" size="small" @click="editAccount(detail)">{{ t('actions.edit') }}</el-button>
                </div>
            </template>
        </el-dialog>

        <el-dialog v-model="editVisible" :title="t(`${P}.edit_title`, { name: editForm.name || '' })" width="480px" top="20vh">
            <el-form :model="editForm" label-width="80px" size="small">
                <el-form-item :label="t('channels_page.name')">
                    <el-input v-model="editForm.name" maxlength="50" />
                </el-form-item>
                <el-form-item :label="t(`${P}.cols.slug`)">
                    <el-input v-model="editForm.slug" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('channels_page.category')">
                    <el-select v-model="editForm.category_id" :placeholder="t('channels_page.select_category_ph')" clearable style="width:100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('channels_page.description')">
                    <el-input v-model="editForm.description" type="textarea" :rows="3" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="editVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" :loading="editLoading" @click="submitEdit">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const P = 'admin_official_accounts_page'
const { t, locale } = useI18n()

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

const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref(null)

const editVisible = ref(false)
const editLoading = ref(false)
const editForm = ref({ id: null, name: '', slug: '', category_id: null, description: '' })

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'))

const statusOptions = computed(() => [
    { value: 'pending', label: t('channels_page.status_pending') },
    { value: 'verified', label: t(`${P}.status.verified`) },
    { value: 'verify_request', label: t(`${P}.status.verify_request`) },
    { value: 'active', label: t(`${P}.status.active`) },
    { value: 'rejected', label: t('channels_page.status_rejected') },
    { value: 'suspended', label: t('channels_page.status_suspended') },
])

const statusLabels = computed(() => ({
    active: t(`${P}.status.active`),
    suspended: t('channels_page.status_suspended'),
    pending: t('channels_page.status_pending'),
    rejected: t('channels_page.status_rejected'),
}))

function statusLabel(status) {
    return statusLabels.value[status] || status
}

function verifyTypeLabel(type) {
    return type === 'enterprise'
        ? t('channels_page.verified.enterprise')
        : t('channels_page.verified.personal')
}

function formatTime(ts) {
    if (!ts) return ''
    return new Date(ts).toLocaleString(dateLocale.value)
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
    if (!editForm.value.name?.trim()) { ElMessage.warning(t(`${P}.msgs.name_required`)); return }
    editLoading.value = true
    try {
        await apiClient.put(`/admin/official-accounts/${editForm.value.id}`, {
            name: editForm.value.name,
            slug: editForm.value.slug || undefined,
            category_id: editForm.value.category_id || undefined,
            description: editForm.value.description || undefined,
        }, { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } })
        ElMessage.success(t(`${P}.msgs.updated`))
        editVisible.value = false
        await loadAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.msgs.update_failed`))
    } finally { editLoading.value = false }
}

async function toggleStatus(acc) {
    try {
        const res = await apiClient.post(`/admin/official-accounts/${acc.id}/toggle-status`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        acc.status = res.data?.data?.status
        ElMessage.success(acc.status === 'active' ? t(`${P}.msgs.enabled`) : t(`${P}.msgs.disabled`))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

async function approveAccount(acc) {
    try {
        await ElMessageBox.confirm(t(`${P}.dialogs.confirm_approve`, { name: acc.name }), t(`${P}.dialogs.confirm_approve_title`), { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/approve`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.approved`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectAccount(acc) {
    try {
        const { value } = await ElMessageBox.prompt(t(`${P}.dialogs.reject_review_prompt`, { name: acc.name }), t(`${P}.dialogs.reject_review_title`), {
            confirmButtonText: t(`${P}.dialogs.confirm_reject_btn`),
            cancelButtonText: t('actions.cancel'),
            inputPlaceholder: t(`${P}.dialogs.reject_reason_ph`),
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/reject`, { reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.rejected`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function approveAppeal(acc) {
    try {
        await ElMessageBox.confirm(t(`${P}.dialogs.confirm_appeal`, { name: acc.name }), t(`${P}.dialogs.confirm_unban_title`), { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-appeal`, { action: 'approve' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.unbanned`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectAppeal(acc) {
    try {
        const { value } = await ElMessageBox.prompt(t(`${P}.dialogs.reject_appeal_prompt`, { name: acc.name }), t(`${P}.dialogs.reject_appeal_title`), {
            confirmButtonText: t(`${P}.dialogs.confirm_reject_btn`),
            cancelButtonText: t('actions.cancel'),
            inputPlaceholder: t(`${P}.dialogs.reject_reason_ph`),
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-appeal`, { action: 'reject', reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.appeal_rejected`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function approveVerify(acc) {
    try {
        await ElMessageBox.confirm(t(`${P}.dialogs.confirm_verify`, { name: acc.name }), t(`${P}.dialogs.confirm_verify_title`), { type: 'info' })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-verify`, { action: 'approve' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.verify_approved`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function rejectVerify(acc) {
    try {
        const { value } = await ElMessageBox.prompt(t(`${P}.dialogs.reject_verify_prompt`, { name: acc.name }), t(`${P}.dialogs.reject_verify_title`), {
            confirmButtonText: t(`${P}.dialogs.confirm_reject_btn`),
            cancelButtonText: t('actions.cancel'),
            inputPlaceholder: t(`${P}.dialogs.reject_reason_ph`),
        })
        await apiClient.post(`/admin/official-accounts/${acc.id}/review-verify`, { action: 'reject', reason: value || '' }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.verify_rejected`))
        await loadAccounts()
    } catch { /* ignore */ }
}

async function toggleVerify(acc) {
    try {
        const action = acc.is_verified ? t(`${P}.actions.revoke_verify`) : t(`${P}.actions.verify`)
        await ElMessageBox.confirm(t(`${P}.dialogs.confirm_toggle_verify`, { action, name: acc.name }), t(`${P}.dialogs.confirm_toggle_verify_title`, { action }))
        await apiClient.post(`/admin/official-accounts/${acc.id}/verify`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        acc.is_verified = !acc.is_verified
        ElMessage.success(acc.is_verified ? t(`${P}.msgs.verified`) : t(`${P}.msgs.verify_revoked`))
    } catch { /* ignore */ }
}

async function deleteAccount(acc) {
    try {
        await ElMessageBox.confirm(t(`${P}.dialogs.confirm_delete`, { name: acc.name }), t(`${P}.dialogs.confirm_delete_title`), { type: 'warning' })
        await apiClient.delete(`/admin/official-accounts/${acc.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t(`${P}.msgs.deleted`))
        accounts.value = accounts.value.filter(a => a.id !== acc.id)
        total.value--
    } catch { /* ignore */ }
}

async function batchToggle(status) {
    try {
        const action = status === 'active' ? t('actions.enable') : t('actions.disable')
        await ElMessageBox.confirm(t(`${P}.dialogs.batch_toggle_confirm`, { action, n: selectedIds.value.length }), t(`${P}.dialogs.batch_toggle_title`, { action }))
        const res = await apiClient.post('/admin/official-accounts/batch-toggle-status',
            { ids: selectedIds.value, status },
            { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } }
        )
        ElMessage.success(res.data?.message || t(`${P}.msgs.op_done`))
        selectedIds.value = []
        await loadAccounts()
    } catch { /* ignore */ }
}

async function batchDelete() {
    try {
        await ElMessageBox.confirm(t(`${P}.dialogs.batch_delete_confirm`, { n: selectedIds.value.length }), t(`${P}.dialogs.confirm_delete_title`), { type: 'warning' })
        const res = await apiClient.post('/admin/official-accounts/batch-delete',
            { ids: selectedIds.value },
            { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } }
        )
        ElMessage.success(res.data?.message || t(`${P}.msgs.op_done`))
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
