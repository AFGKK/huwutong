<template>
    <div class="impersonate-page">
        <el-tabs v-model="activeTab">
            <!-- 开始模拟 -->
            <el-tab-pane :label="$t('impersonate_page.tabs.start')" name="start">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('impersonate_page.start.title') }}</span>
                            <el-tag type="warning" effect="dark">{{ $t('impersonate_page.start.super_admin_only') }}</el-tag>
                        </div>
                    </template>

                    <!-- 搜索用户 -->
                    <el-form :inline="true" class="search-form">
                        <el-form-item :label="$t('impersonate_page.start.search_label')">
                            <el-input
                                v-model="searchQuery"
                                :placeholder="$t('impersonate_page.start.search_ph')"
                                clearable
                                @input="handleSearch"
                                :prefix-icon="Search"
                            />
                        </el-form-item>
                    </el-form>

                    <!-- 用户列表 -->
                    <el-table :data="candidates" v-loading="loadingCandidates" stripe style="width: 100%">
                        <el-table-column prop="id" :label="$t('impersonate_page.cols.id')" width="80" />
                        <el-table-column prop="name" :label="$t('impersonate_page.cols.name')" min-width="150" />
                        <el-table-column prop="email" :label="$t('impersonate_page.cols.email')" min-width="200" />
                        <el-table-column :label="$t('impersonate_page.cols.role')" width="150">
                            <template #default="{ row }">
                                <el-tag v-for="role in row.roles" :key="role.id" size="small" class="mr-1">
                                    {{ role.name }}
                                </el-tag>
                                <span v-if="!row.roles?.length" class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('impersonate_page.cols.actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button
                                    type="warning"
                                    size="small"
                                    :icon="Key"
                                    @click="openImpersonateDialog(row)"
                                    :disabled="isCurrentUser(row.id)"
                                >
                                    {{ isCurrentUser(row.id) ? $t('impersonate_page.btn.self') : $t('impersonate_page.btn.impersonate') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-if="total > 0"
                        v-model:current-page="currentPage"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchCandidates"
                    />
                </el-card>
            </el-tab-pane>

            <!-- 模拟历史 -->
            <el-tab-pane :label="$t('impersonate_page.tabs.history')" name="history">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ $t('impersonate_page.history.title') }}</span>
                        </div>
                    </template>

                    <el-table :data="history" v-loading="loadingHistory" stripe style="width: 100%">
                        <el-table-column :label="$t('impersonate_page.cols.time')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('impersonate_page.cols.operator')" width="150">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('impersonate_page.cols.action')" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.action === 'impersonate_started' ? 'warning' : 'info'" size="small">
                                    {{ actionLabel(row.action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('impersonate_page.cols.description')" min-width="300">
                            <template #default="{ row }">
                                {{ row.description }}
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-pagination
                        v-if="historyTotal > 0"
                        v-model:current-page="historyPage"
                        :page-size="historyPerPage"
                        :total="historyTotal"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchHistory"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 模拟确认对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="$t('impersonate_page.dialog.title')"
            width="500px"
        >
            <el-form :model="impersonateForm" label-width="80px">
                <el-form-item :label="$t('impersonate_page.dialog.target_user')">
                    <el-tag type="warning" size="large">
                        {{ impersonateForm.target_name }}
                    </el-tag>
                    <el-tag type="info" class="ml-2">
                        {{ impersonateForm.target_email }}
                    </el-tag>
                </el-form-item>
                <el-form-item :label="$t('impersonate_page.dialog.reason')">
                    <el-input
                        v-model="impersonateForm.reason"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('impersonate_page.dialog.reason_ph')"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
                <el-alert
                    type="warning"
                    :closable="false"
                    show-icon
                    class="mt-2"
                >
                    <template #title>
                        <span>{{ $t('impersonate_page.dialog.warning') }}</span>
                    </template>
                </el-alert>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="warning" :loading="starting" @click="handleStartImpersonate">
                    {{ $t('impersonate_page.dialog.start') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Search, Key } from '@element-plus/icons-vue'
import {
    startImpersonate,
    getImpersonateCandidates,
    getImpersonateHistory,
} from '@/api/impersonate'

const { t, locale } = useI18n()

const activeTab = ref('start')

// 候选人搜索
const searchQuery = ref('')
const candidates = ref([])
const loadingCandidates = ref(false)
const currentPage = ref(1)
const perPage = ref(20)
const total = ref(0)

// 模拟历史
const history = ref([])
const loadingHistory = ref(false)
const historyPage = ref(1)
const historyPerPage = ref(20)
const historyTotal = ref(0)

// 模拟对话框
const dialogVisible = ref(false)
const impersonateForm = ref({
    target_id: null,
    target_name: '',
    target_email: '',
    reason: '',
})
const starting = ref(false)

// 存储当前用户信息（由 layout 透传，临时用 localStorage）
const currentUserId = ref(parseInt(localStorage.getItem('user_id') || '0'))

const actionLabels = computed(() => ({
    impersonate_started: t('impersonate_page.actions.started'),
    impersonate_ended: t('impersonate_page.actions.ended'),
}))

function actionLabel(action) {
    return actionLabels.value[action] || action
}

function isCurrentUser(id) {
    return currentUserId.value === id
}

// 搜索防抖 timer
let searchTimer = null
function handleSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        currentPage.value = 1
        fetchCandidates()
    }, 300)
}

async function fetchCandidates() {
    loadingCandidates.value = true
    try {
        const res = await getImpersonateCandidates({
            search: searchQuery.value || undefined,
            page: currentPage.value,
            per_page: perPage.value,
        })
        candidates.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error(t('impersonate_page.messages.fetch_candidates_failed'))
    } finally {
        loadingCandidates.value = false
    }
}

async function fetchHistory() {
    loadingHistory.value = true
    try {
        const res = await getImpersonateHistory({
            page: historyPage.value,
            per_page: historyPerPage.value,
        })
        history.value = res.data?.data?.data || []
        historyTotal.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error(t('impersonate_page.messages.fetch_history_failed'))
    } finally {
        loadingHistory.value = false
    }
}

function openImpersonateDialog(user) {
    impersonateForm.value = {
        target_id: user.id,
        target_name: user.name,
        target_email: user.email,
        reason: '',
    }
    dialogVisible.value = true
}

async function handleStartImpersonate() {
    starting.value = true
    try {
        const res = await startImpersonate(
            impersonateForm.value.target_id,
            impersonateForm.value.reason,
        )

        const token = res.data?.data?.token
        const target = res.data?.data?.target

        if (token && target) {
            // 保存到 localStorage，apiClient 的请求拦截器会自动注入
            localStorage.setItem('impersonate_token', token)
            localStorage.setItem('impersonate_target', target.name)

            ElMessage.success(t('impersonate_page.messages.impersonate_success', { name: target.name }))

            // 自动跳转到仪表盘（以模拟用户身份）
            dialogVisible.value = false
            window.location.href = '/dashboard'
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('impersonate_page.messages.impersonate_failed'))
    } finally {
        starting.value = false
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
}

onMounted(() => {
    fetchCandidates()
})
</script>

<style scoped>
.impersonate-page {
    max-width: 1200px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-form {
    margin-bottom: 16px;
}

.text-muted {
    color: #999;
}

.mr-1 {
    margin-right: 4px;
}

.ml-2 {
    margin-left: 8px;
}

.mt-2 {
    margin-top: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.justify-center {
    display: flex;
    justify-content: center;
}
</style>
