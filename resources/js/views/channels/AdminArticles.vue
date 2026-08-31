<template>
    <div>
        <el-page-header :content="t('admin_articles_page.title', { n: total })" @back="$router.push('/')" />
        <div class="mt-4 flex items-center gap-3 mb-4 flex-wrap">
            <el-input v-model="search" :placeholder="t('admin_articles_page.search_ph')" size="small" style="width:200px" clearable @input="loadArticles" />
            <el-select v-model="statusFilter" :placeholder="t('admin_articles_page.cols.status')" size="small" style="width:110px" clearable @change="loadArticles">
                <el-option :label="t('admin_articles_page.filters.pending')" value="pending" />
                <el-option :label="t('admin_articles_page.filters.published')" value="published" />
                <el-option :label="t('admin_articles_page.filters.draft')" value="draft" />
            </el-select>
            <el-select v-model="accountFilter" :placeholder="t('admin_articles_page.cols.account')" size="small" style="width:160px" clearable filterable @change="loadArticles">
                <el-option v-for="a in allAccounts" :key="a.id" :label="a.name" :value="a.id" />
            </el-select>
        </div>

        <div v-if="statusFilter === 'pending'">
            <div v-if="pendingSubmissions.length" class="space-y-2">
                <div v-for="sub in pendingSubmissions" :key="sub.id" class="bg-white rounded-lg border border-amber-100 p-4 flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 truncate">{{ sub.title || t('admin_articles_page.untitled') }}</div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                            <span>{{ sub.account?.name || '—' }}</span>
                            <span>{{ sub.user?.name || t('admin_articles_page.anonymous') }}</span>
                            <span>{{ formatTime(sub.created_at) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <el-button size="small" type="success" @click="reviewSubmission(sub, 'approve')">{{ t('actions.approve') }}</el-button>
                        <el-button size="small" type="danger" @click="reviewSubmission(sub, 'reject')">{{ t('actions.reject') }}</el-button>
                    </div>
                </div>
            </div>
            <el-empty v-else :description="t('admin_articles_page.empty_pending')" :image-size="80" class="py-12" />
        </div>

        <div v-else>
            <el-table :data="articles" v-loading="loading" border stripe size="small">
                <el-table-column label="ID" prop="id" width="60" />
                <el-table-column :label="t('admin_articles_page.cols.title')" min-width="220">
                    <template #default="{ row }">
                        <span class="font-medium cursor-pointer hover:text-blue-500" @click="showDetail(row)">{{ row.title }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('admin_articles_page.cols.account')" width="140">
                    <template #default="{ row }">{{ row.account?.name || '—' }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_articles_page.cols.author')" width="100">
                    <template #default="{ row }">{{ row.author?.name || '—' }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_articles_page.cols.status')" width="80" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : row.status === 'draft' ? 'info' : 'warning'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('admin_articles_page.cols.comments')" prop="comments_count" width="60" align="center" />
                <el-table-column :label="t('admin_articles_page.cols.created')" width="140">
                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_articles_page.cols.actions')" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
                        <el-button size="small" text @click="togglePin(row)">{{ row.is_global_pinned ? t('admin_articles_page.unpin') : t('admin_articles_page.pin') }}</el-button>
                        <el-button size="small" text :type="row.status === 'published' ? 'warning' : 'success'" @click="toggleStatus(row)">
                            {{ row.status === 'published' ? t('admin_articles_page.unpublish') : t('admin_articles_page.publish') }}
                        </el-button>
                        <el-button size="small" text type="danger" @click="deleteArticle(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="text-center py-4">
                <el-button :loading="loadingMore" size="small" @click="loadMore" v-if="hasMore">{{ t('admin_articles_page.load_more') }}</el-button>
            </div>
        </div>

        <el-drawer v-model="detailVisible" :title="detail?.title" size="600px" @close="detail = null">
            <template v-if="detail">
                <div class="text-xs text-gray-400 mb-4 flex items-center gap-3">
                    <span>{{ detail.account?.name || '—' }}</span>
                    <span>{{ detail.author?.name || '—' }}</span>
                    <span>{{ formatTime(detail.created_at) }}</span>
                    <el-tag :type="detail.status === 'published' ? 'success' : 'info'" size="small">
                        {{ statusLabel(detail.status) }}
                    </el-tag>
                </div>
                <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap" v-html="detail.content || t('admin_articles_page.no_content')"></div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/api/client'

const { t, locale } = useI18n()

const articles = ref([])
const pendingSubmissions = ref([])
const allAccounts = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const total = ref(0)
const search = ref('')
const statusFilter = ref('')
const accountFilter = ref('')
const detailVisible = ref(false)
const detail = ref(null)

function formatTime(time) {
    if (!time) return ''
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(time).toLocaleString(loc)
}

function statusLabel(status) {
    if (status === 'published') return t('admin_articles_page.filters.published')
    if (status === 'draft') return t('admin_articles_page.filters.draft')
    return status
}

async function loadArticles() {
    loading.value = true; page.value = 1
    try {
        const params = { per_page: 20, page: 1 }
        if (search.value) params.q = search.value
        if (statusFilter.value) {
            if (statusFilter.value === 'pending') {
                params.pending_submissions = 1
            } else {
                params.status = statusFilter.value
            }
        }
        if (accountFilter.value) params.account_id = accountFilter.value

        const res = await apiClient.get('/admin/articles/manage', {
            params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        articles.value = res.data?.data?.articles || []
        pendingSubmissions.value = res.data?.data?.pending_submissions || []
        total.value = res.data?.data?.meta?.total || 0
        hasMore.value = articles.value.length >= 20
    } catch { /* ignore */ }
    finally { loading.value = false }
}

async function loadMore() {
    loadingMore.value = true; page.value++
    try {
        const params = { per_page: 20, page: page.value }
        if (search.value) params.q = search.value
        if (statusFilter.value && statusFilter.value !== 'pending') params.status = statusFilter.value
        if (accountFilter.value) params.account_id = accountFilter.value

        const res = await apiClient.get('/admin/articles/manage', {
            params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        const newItems = res.data?.data?.articles || []
        articles.value.push(...newItems)
        hasMore.value = newItems.length >= 20
    } catch { /* ignore */ }
    finally { loadingMore.value = false }
}

async function showDetail(row) {
    try {
        const res = await apiClient.get(`/admin/articles/${row.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        detail.value = res.data?.data
        detailVisible.value = true
    } catch { ElMessage.error(t('messages.load_failed')) }
}

async function reviewSubmission(sub, action) {
    try {
        if (action === 'reject') {
            const { value } = await ElMessageBox.prompt(
                t('admin_articles_page.reject_prompt'),
                t('admin_articles_page.reject_title'),
                { inputPlaceholder: t('admin_articles_page.reject_ph') }
            )
            await apiClient.post(`/admin/submissions/${sub.id}/review`, { action, reason: value || '' }, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
        } else {
            await apiClient.post(`/admin/submissions/${sub.id}/review`, { action }, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
        }
        ElMessage.success(action === 'approve' ? t('admin_articles_page.messages.approved') : t('admin_articles_page.messages.rejected'))
        pendingSubmissions.value = pendingSubmissions.value.filter(s => s.id !== sub.id)
    } catch { /* ignore */ }
}

async function toggleStatus(row) {
    try {
        const label = row.status === 'published' ? t('admin_articles_page.unpublish') : t('admin_articles_page.publish')
        await ElMessageBox.confirm(
            t('admin_articles_page.status_confirm', { action: label }),
            t('admin_articles_page.status_confirm_title', { action: label })
        )
        const res = await apiClient.post(`/admin/articles/${row.id}/toggle-status`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        row.status = res.data?.data?.status
        ElMessage.success(row.status === 'published' ? t('admin_articles_page.messages.published') : t('admin_articles_page.messages.unpublished'))
    } catch { /* ignore */ }
}

async function togglePin(row) {
    try {
        const res = await apiClient.post(`/admin/articles/${row.id}/pin`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        row.is_global_pinned = res.data?.data?.is_global_pinned
        ElMessage.success(row.is_global_pinned ? t('admin_articles_page.messages.pinned') : t('admin_articles_page.messages.unpinned'))
    } catch { /* ignore */ }
}

async function deleteArticle(row) {
    try {
        await ElMessageBox.confirm(
            t('admin_articles_page.delete_confirm', { title: row.title }),
            t('messages.confirm_delete'),
            { type: 'warning' }
        )
        await apiClient.delete(`/admin/articles/${row.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t('admin_articles_page.messages.deleted'))
        articles.value = articles.value.filter(a => a.id !== row.id)
        total.value--
    } catch { /* ignore */ }
}

onMounted(() => { loadArticles() })
</script>
