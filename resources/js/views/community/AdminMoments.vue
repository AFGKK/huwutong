<template>
    <div>
        <el-page-header :content="t('admin_moments_page.title', { n: total })" @back="$router.push('/')" />
        <div class="mt-4 flex items-center gap-3 mb-4">
            <el-input v-model="search" :placeholder="t('admin_moments_page.search_ph')" size="small" style="width:240px" clearable @input="loadPosts" />
            <el-select v-model="statusFilter" :placeholder="t('admin_moments_page.cols.status')" size="small" style="width:120px" clearable @change="loadPosts">
                <el-option :label="t('admin_moments_page.published')" value="published" />
                <el-option :label="t('admin_moments_page.draft')" value="draft" />
            </el-select>
            <el-select v-model="pinnedFilter" :placeholder="t('admin_moments_page.cols.pinned')" size="small" style="width:100px" clearable @change="loadPosts">
                <el-option :label="t('admin_moments_page.pinned_yes')" value="1" />
                <el-option :label="t('admin_moments_page.pinned_no')" value="0" />
            </el-select>
        </div>
        <el-table :data="posts" v-loading="loading" border stripe size="small" @row-click="showDetail">
            <el-table-column label="ID" prop="id" width="60" />
            <el-table-column :label="t('admin_moments_page.cols.content')" min-width="260">
                <template #default="{ row }">
                    <div class="flex gap-2 items-start">
                        <img v-if="row.images?.[0]" :src="row.images[0]" class="w-10 h-10 rounded object-cover flex-shrink-0" @click.stop="showDetail(row)" />
                        <div class="text-sm line-clamp-2 flex-1 cursor-pointer hover:text-blue-500" v-html="row.content?.substring(0, 200)" @click.stop="showDetail(row)"></div>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.author')" width="120">
                <template #default="{ row }">
                    <div class="flex items-center gap-1">
                        <el-avatar :size="20" :src="row.user?.avatar_url" v-if="row.user?.avatar_url" />
                        <span>{{ row.user?.name || t('admin_moments_page.anonymous') }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.template')" width="60" align="center">
                <template #default="{ row }">{{ row.template || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.views')" prop="views_count" width="60" align="center" />
            <el-table-column :label="t('admin_moments_page.cols.replies')" prop="replies_count" width="60" align="center" />
            <el-table-column :label="t('admin_moments_page.cols.likes')" prop="likes_count" width="60" align="center" />
            <el-table-column :label="t('admin_moments_page.cols.pinned')" width="60" align="center">
                <template #default="{ row }">
                    <el-tag v-if="row.is_pinned" size="small" type="warning">{{ t('admin_moments_page.pin') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.status')" width="70" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.time')" width="140">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('admin_moments_page.cols.actions')" width="200" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click.stop="showDetail(row)">{{ t('admin_moments_page.detail') }}</el-button>
                    <el-button size="small" text type="primary" @click.stop="togglePin(row)">{{ row.is_pinned ? t('admin_moments_page.unpin') : t('admin_moments_page.pin') }}</el-button>
                    <el-button size="small" text type="danger" @click.stop="deletePost(row)">{{ t('actions.delete') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
        <div v-if="hasMore" class="text-center py-4">
            <el-button :loading="loadingMore" size="small" @click="loadMore">{{ t('admin_moments_page.load_more') }}</el-button>
        </div>

        <el-dialog v-model="detailVisible" :title="t('admin_moments_page.detail_title', { id: detail?.id || '' })" width="680px" top="5vh">
            <template v-if="detailLoading">
                <div class="text-center py-8"><el-icon class="is-loading" :size="32"><Loading /></el-icon></div>
            </template>
            <template v-else-if="detail">
                <div class="flex items-center gap-2 mb-3">
                    <el-avatar :size="28" :src="detail.user?.avatar_url" />
                    <span class="font-bold">{{ detail.user?.name || t('admin_moments_page.anonymous') }}</span>
                    <el-tag v-if="detail.is_pinned" size="small" type="warning">{{ t('admin_moments_page.pin') }}</el-tag>
                    <el-tag :type="detail.status === 'published' ? 'success' : 'info'" size="small">{{ detail.status }}</el-tag>
                </div>
                <div class="p-3 bg-gray-50 rounded mb-3" v-html="detail.content"></div>
                <div class="flex gap-2 mb-3 flex-wrap" v-if="detail.images?.length">
                    <img v-for="(img, i) in detail.images" :key="i" :src="img" class="w-24 h-24 rounded object-cover border" />
                </div>
                <el-descriptions :column="3" border size="small">
                    <el-descriptions-item :label="t('admin_moments_page.cols.template')">{{ detail.template || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.cols.views')">{{ detail.views_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.cols.replies')">{{ detail.replies_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.cols.likes')">{{ detail.likes_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.favorites')">{{ detail.favorites_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.paid')" v-if="detail.is_paid">{{ detail.price }} {{ detail.price_type }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.created')">{{ formatTime(detail.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_moments_page.updated')">{{ formatTime(detail.updated_at) }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const { t, locale } = useI18n()

const posts = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const total = ref(0)
const search = ref('')
const statusFilter = ref('')
const pinnedFilter = ref('')
const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref(null)

function formatTime(time) {
    if (!time) return ''
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(time).toLocaleString(loc)
}

async function loadPosts() {
    loading.value = true; page.value = 1
    try {
        const params = { per_page: 20, page: 1 }
        if (search.value) params.q = search.value
        if (statusFilter.value) params.status = statusFilter.value
        if (pinnedFilter.value !== '') params.pinned = pinnedFilter.value
        const res = await apiClient.get('/admin/moments', { params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        posts.value = res.data?.data || []
        total.value = res.data?.meta?.total || 0
        hasMore.value = (res.data?.meta?.last_page || 1) > 1
    } catch { posts.value = []; total.value = 0 }
    finally { loading.value = false }
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value) return
    loadingMore.value = true; page.value++
    try {
        const params = { per_page: 20, page: page.value }
        if (search.value) params.q = search.value
        if (statusFilter.value) params.status = statusFilter.value
        if (pinnedFilter.value !== '') params.pinned = pinnedFilter.value
        const res = await apiClient.get('/admin/moments', { params,
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        posts.value = [...posts.value, ...(res.data?.data || [])]
        hasMore.value = page.value < (res.data?.meta?.last_page || 1)
    } catch { /* ignore */ }
    finally { loadingMore.value = false }
}

async function showDetail(post) {
    detailVisible.value = true; detailLoading.value = true; detail.value = null
    try {
        const res = await apiClient.get(`/admin/moments/${post.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        detail.value = res.data?.data
    } catch { /* ignore */ }
    finally { detailLoading.value = false }
}

async function togglePin(post) {
    try {
        const res = await apiClient.post(`/moments/${post.id}/pin`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        post.is_pinned = res.data?.data?.is_pinned ?? !post.is_pinned
        ElMessage.success(post.is_pinned ? t('admin_moments_page.messages.pinned') : t('admin_moments_page.messages.unpinned'))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

async function deletePost(post) {
    try {
        await ElMessageBox.confirm(t('admin_moments_page.confirm_delete'), t('admin_moments_page.confirm_title'), { type: 'warning' })
        await apiClient.delete(`/admin/moments/${post.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success(t('admin_moments_page.messages.deleted'))
        posts.value = posts.value.filter(p => p.id !== post.id)
        total.value--
    } catch { /* ignore */ }
}

onMounted(() => loadPosts())
</script>
