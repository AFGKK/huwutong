<template>
    <div class="login-history-page">
        <div class="page-header">
            <div class="header-left">
                <h2>登录历史</h2>
                <span class="header-subtitle">查看近期登录和登出活动记录</span>
            </div>
            <div class="header-right">
                <el-button @click="loadHistory">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
        </div>

        <!-- 统计数据 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总记录</div>
                        <div class="stat-value">{{ totalRecords }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">成功登录</div>
                        <div class="stat-value text-success">{{ successCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">失败尝试</div>
                        <div class="stat-value text-danger">{{ failedCount }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">最近 7 天</div>
                        <div class="stat-value text-primary">{{ recentCount }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="登录方式">
                    <el-select v-model="filters.provider" clearable placeholder="全部方式" style="width: 140px" @change="doSearch">
                        <el-option label="邮箱密码" value="email" />
                        <el-option label="手机号" value="phone" />
                        <el-option label="微信" value="wechat" />
                        <el-option label="Google" value="google" />
                        <el-option label="GitHub" value="github" />
                        <el-option label="Apple" value="apple" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.success" clearable placeholder="全部" style="width: 110px" @change="doSearch">
                        <el-option label="成功" :value="true" />
                        <el-option label="失败" :value="false" />
                    </el-select>
                </el-form-item>
                <el-form-item label="日期范围">
                    <el-date-picker
                        v-model="filters.dateRange"
                        type="daterange"
                        range-separator="至"
                        start-placeholder="开始日期"
                        end-placeholder="结束日期"
                        value-format="YYYY-MM-DD"
                        style="width: 240px"
                        @change="doSearch"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 登录历史列表 -->
        <el-card shadow="never">
            <el-table
                :data="logs"
                v-loading="loading"
                stripe
                style="width: 100%"
                @sort-change="handleSortChange"
            >
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="created_at" label="时间" width="170" sortable="custom">
                    <template #default="{ row }">
                        <div class="log-time">{{ formatDate(row.created_at) }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="action" label="操作" width="100">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.action === 'login' ? 'primary' : 'info'"
                            size="small"
                            effect="plain"
                        >
                            {{ row.action === 'login' ? '登录' : '登出' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="success" label="状态" width="80">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.success ? 'success' : 'danger'"
                            size="small"
                        >
                            {{ row.success ? '成功' : '失败' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="provider" label="登录方式" width="120">
                    <template #default="{ row }">
                        <div class="provider-cell">
                            <el-icon :size="14" style="margin-right: 4px;">
                                <component :is="providerIcon(row.provider)" />
                            </el-icon>
                            {{ providerLabel(row.provider) }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="ip_address" label="IP 地址" width="140" />
                <el-table-column prop="user_agent" label="设备信息" min-width="200">
                    <template #default="{ row }">
                        <div class="ua-text" :title="row.user_agent">
                            {{ row.user_agent ? shortenUA(row.user_agent) : '-' }}
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="failure_reason" label="失败原因" min-width="160">
                    <template #default="{ row }">
                        <span v-if="row.failure_reason" class="failure-reason">{{ row.failure_reason }}</span>
                        <span v-else class="no-reason">-</span>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadHistory"
                    @current-change="loadHistory"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Search, Refresh, Monitor, Message, Iphone } from '@element-plus/icons-vue';
import { ChromeFilled, Apple, ChatDotSquare } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loading = ref(false);
const logs = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);

const totalRecords = ref(0);
const successCount = ref(0);
const failedCount = ref(0);
const recentCount = ref(0);

const filters = reactive({
    provider: '',
    success: '',
    dateRange: null,
    sort: '-created_at',
});

function providerIcon(provider) {
    const map = {
        email: Message,
        phone: Iphone,
        wechat: ChatDotSquare,
        google: ChromeFilled,
        github: ChromeFilled,
        apple: Apple,
    };
    return map[provider] || Monitor;
}

function providerLabel(provider) {
    const map = {
        email: '邮箱密码',
        phone: '手机号',
        wechat: '微信',
        google: 'Google',
        github: 'GitHub',
        apple: 'Apple',
    };
    return map[provider] || provider || '未知';
}

function shortenUA(ua) {
    if (!ua) return '-';
    if (ua.length > 80) return ua.substring(0, 80) + '...';
    return ua;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

async function loadHistory() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: filters.sort || '-created_at',
        };
        if (filters.provider) params['filter.provider'] = filters.provider;
        if (filters.success !== '') params['filter.success'] = filters.success;
        if (filters.dateRange && filters.dateRange.length === 2) {
            params['filter.created_at_from'] = filters.dateRange[0];
            params['filter.created_at_to'] = filters.dateRange[1];
        }

        const { data: res } = await apiClient.get('/login-history', { params });
        const paginatedData = res.data;
        logs.value = paginatedData.data || [];
        total.value = paginatedData.total || 0;
        totalRecords.value = paginatedData.total || 0;

        // 计算统计数据
        successCount.value = logs.value.filter(l => l.success).length;
        failedCount.value = logs.value.filter(l => !l.success).length;

        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
        recentCount.value = logs.value.filter(l => new Date(l.created_at) > sevenDaysAgo).length;
    } catch {
        logs.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadHistory();
}

function handleSortChange({ order }) {
    if (order === 'ascending') filters.sort = 'created_at';
    else if (order === 'descending') filters.sort = '-created_at';
    else filters.sort = '-created_at';
    doSearch();
}

onMounted(() => {
    loadHistory();
});
</script>

<style scoped>
.login-history-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-primary { color: var(--el-color-primary); }

.filter-card { margin-bottom: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }

.log-time {
    font-size: 13px;
    white-space: nowrap;
}

.provider-cell {
    display: flex;
    align-items: center;
}

.ua-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.failure-reason {
    color: var(--el-color-danger);
    font-size: 13px;
}
.no-reason {
    color: var(--el-text-color-placeholder);
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
