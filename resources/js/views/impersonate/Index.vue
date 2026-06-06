<template>
    <div class="impersonate-page">
        <el-tabs v-model="activeTab">
            <!-- 开始模拟 -->
            <el-tab-pane label="开始模拟" name="start">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>选择要模拟的用户</span>
                            <el-tag type="warning" effect="dark">仅超管可用</el-tag>
                        </div>
                    </template>

                    <!-- 搜索用户 -->
                    <el-form :inline="true" class="search-form">
                        <el-form-item label="搜索用户">
                            <el-input
                                v-model="searchQuery"
                                placeholder="输入用户名或邮箱..."
                                clearable
                                @input="handleSearch"
                                :prefix-icon="Search"
                            />
                        </el-form-item>
                    </el-form>

                    <!-- 用户列表 -->
                    <el-table :data="candidates" v-loading="loadingCandidates" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="name" label="用户名" min-width="150" />
                        <el-table-column prop="email" label="邮箱" min-width="200" />
                        <el-table-column label="角色" width="150">
                            <template #default="{ row }">
                                <el-tag v-for="role in row.roles" :key="role.id" size="small" class="mr-1">
                                    {{ role.name }}
                                </el-tag>
                                <span v-if="!row.roles?.length" class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button
                                    type="warning"
                                    size="small"
                                    :icon="Key"
                                    @click="openImpersonateDialog(row)"
                                    :disabled="isCurrentUser(row.id)"
                                >
                                    {{ isCurrentUser(row.id) ? '自己' : '模拟登录' }}
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
            <el-tab-pane label="模拟历史" name="history">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>模拟登录审计日志</span>
                        </div>
                    </template>

                    <el-table :data="history" v-loading="loadingHistory" stripe style="width: 100%">
                        <el-table-column label="时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作者" width="150">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.action === 'impersonate_started' ? 'warning' : 'info'" size="small">
                                    {{ row.action === 'impersonate_started' ? '开始模拟' : '结束模拟' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="描述" min-width="300">
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
            title="确认模拟登录"
            width="500px"
        >
            <el-form :model="impersonateForm" label-width="80px">
                <el-form-item label="目标用户">
                    <el-tag type="warning" size="large">
                        {{ impersonateForm.target_name }}
                    </el-tag>
                    <el-tag type="info" class="ml-2">
                        {{ impersonateForm.target_email }}
                    </el-tag>
                </el-form-item>
                <el-form-item label="模拟原因">
                    <el-input
                        v-model="impersonateForm.reason"
                        type="textarea"
                        :rows="3"
                        placeholder="选填：说明模拟原因（会记入审计日志）"
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
                        <span>你将以此用户身份操作，所有操作记录均会关联到你的超管账号。结束模拟或关闭页面后自动退出。</span>
                    </template>
                </el-alert>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="warning" :loading="starting" @click="handleStartImpersonate">
                    开始模拟
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Key } from '@element-plus/icons-vue'
import {
    startImpersonate,
    getImpersonateCandidates,
    getImpersonateHistory,
} from '@/api/impersonate'

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
        ElMessage.error('获取用户列表失败')
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
        ElMessage.error('获取模拟历史失败')
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

            ElMessage.success(`已模拟登录为 ${target.name}`)

            // 自动跳转到仪表盘（以模拟用户身份）
            dialogVisible.value = false
            window.location.href = '/dashboard'
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '模拟登录失败')
    } finally {
        starting.value = false
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
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
