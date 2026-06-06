<template>
    <div class="audit-logs-page">
        <div class="page-header">
            <div class="header-left">
                <h2>审计日志</h2>
                <span class="header-subtitle">查看系统内所有操作记录，用于安全审计和合规追踪</span>
            </div>
            <div class="header-right">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
                <el-button @click="exportLogs" :disabled="logs.length === 0">
                    <el-icon><Download /></el-icon>
                    导出
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总日志数</div>
                        <div class="stat-value">{{ stats.total || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">今日新增</div>
                        <div class="stat-value text-primary">{{ stats.today || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <div class="stat-row">
                        <span class="stat-label-sm">按类型分布：</span>
                        <span v-for="(count, type) in stats.by_type" :key="type" class="type-badge">
                            <el-tag size="small" :type="typeTag(type)" effect="plain">
                                {{ typeLabel(type) }}: {{ count }}
                            </el-tag>
                        </span>
                        <span v-if="!Object.keys(stats.by_type || {}).length" class="text-muted">暂无数据</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选条件 -->
        <el-card shadow="never" class="mb-4">
            <el-form :model="filters" inline>
                <el-form-item label="操作类型">
                    <el-select v-model="filters.action" clearable placeholder="全部" style="width: 200px" @change="search">
                        <el-option v-for="(count, action) in stats.top_actions" :key="action" :label="actionLabel(action)" :value="action" />
                    </el-select>
                </el-form-item>
                <el-form-item label="日志类型">
                    <el-select v-model="filters.type" clearable placeholder="全部" style="width: 140px" @change="search">
                        <el-option v-for="(count, type) in stats.by_type" :key="type" :label="typeLabel(type)" :value="type" />
                    </el-select>
                </el-form-item>
                <el-form-item label="开始日期">
                    <el-date-picker v-model="filters.date_from" type="date" placeholder="开始日期" value-format="YYYY-MM-DD" @change="search" />
                </el-form-item>
                <el-form-item label="结束日期">
                    <el-date-picker v-model="filters.date_to" type="date" placeholder="结束日期" value-format="YYYY-MM-DD" @change="search" />
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input v-model="filters.search" placeholder="搜索描述..." clearable style="width: 180px" @keyup.enter="search" @clear="search" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="search">查询</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 日志列表 -->
        <el-card shadow="never">
            <el-table :data="logs" v-loading="loading" stripe :max-height="600">
                <el-table-column label="时间" width="170" sortable prop="created_at">
                    <template #default="{ row }">
                        {{ formatTime(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" min-width="160">
                    <template #default="{ row }">
                        <div class="action-cell">
                            <el-tag :type="actionTag(row.action)" size="small" effect="plain">
                                {{ actionLabel(row.action) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="描述" min-width="300">
                    <template #default="{ row }">
                        <div class="desc-cell">{{ row.description }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="操作者" width="140">
                    <template #default="{ row }">
                        <div class="user-cell">
                            <el-icon :size="14"><UserFilled /></el-icon>
                            <span>{{ row.user?.name || row.user?.email || '系统' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="关联资源" min-width="160">
                    <template #default="{ row }">
                        <div class="related-cell">
                            <span v-if="row.license" class="related-link" @click="$router.push('/licenses/' + row.license_id)">
                                License #{{ row.license_id }}
                            </span>
                            <span v-if="row.customer" class="related-link" @click="$router.push('/customers/' + row.customer_id)">
                                客户 #{{ row.customer_id }}
                            </span>
                            <span v-if="!row.license && !row.customer" class="text-muted">—</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="IP 地址" width="130">
                    <template #default="{ row }">
                        <code>{{ row.ip_address || '—' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="70" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="showDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[15, 30, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchLogs"
                    @size-change="fetchLogs"
                />
            </div>
        </el-card>

        <!-- 详情 Dialog -->
        <el-dialog v-model="showDetailDialog" title="日志详情" width="640px">
            <div v-if="detailLog">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="时间">{{ formatTime(detailLog.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <el-tag :type="typeTag(detailLog.type)" size="small">{{ typeLabel(detailLog.type) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="操作">
                        <el-tag :type="actionTag(detailLog.action)" size="small" effect="plain">{{ actionLabel(detailLog.action) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="操作者">{{ detailLog.user?.name || detailLog.user?.email || '系统' }}</el-descriptions-item>
                    <el-descriptions-item label="IP 地址"><code>{{ detailLog.ip_address || '—' }}</code></el-descriptions-item>
                    <el-descriptions-item label="User Agent" :content-style="{ 'word-break': 'break-all' }">{{ detailLog.user_agent || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="License" :span="2">
                        {{ detailLog.license?.license_key || '—' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="客户" :span="2">
                        {{ detailLog.customer?.name || '—' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="设备" :span="2">
                        {{ detailLog.device?.fingerprint || detailLog.device?.name || '—' }}
                    </el-descriptions-item>
                </el-descriptions>

                <div class="detail-section">
                    <h4>描述</h4>
                    <p>{{ detailLog.description }}</p>
                </div>

                <div v-if="detailLog.payload" class="detail-section">
                    <h4>请求载荷</h4>
                    <pre class="payload-json">{{ JSON.stringify(detailLog.payload, null, 2) }}</pre>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Download, UserFilled } from '@element-plus/icons-vue';
import auditLogApi from '@/api/auditLog';

const loading = ref(false);
const showDetailDialog = ref(false);
const detailLog = ref(null);
const logs = ref([]);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);

const stats = reactive({
    total: 0, today: 0, by_type: {}, top_actions: {},
});

const filters = reactive({
    action: '',
    type: '',
    date_from: '',
    date_to: '',
    search: '',
});

function actionLabel(action) {
    const map = {
        'license.created': '创建 License',
        'license.activated': '激活 License',
        'license.revoked': '吊销 License',
        'license.suspended': '暂停 License',
        'license.restored': '恢复 License',
        'license.expired': '过期 License',
        'license.refunded': '退款 License',
        'license.updated': '更新 License',
        'license.status_changed': '状态变更',
        'customer.created': '创建客户',
        'customer.updated': '更新客户',
        'user.login': '用户登录',
        'user.logout': '用户登出',
        'user.login_failed': '登录失败',
        'subscription.created': '创建订阅',
        'subscription.canceled': '取消订阅',
        'subscription.renewed': '续费订阅',
    };
    return map[action] || action;
}

function actionTag(action) {
    if (!action) return 'info';
    const positive = ['created', 'activated', 'published', 'login', 'renewed'];
    const negative = ['revoked', 'suspended', 'expired', 'refunded', 'failed', 'canceled', 'deleted'];
    if (positive.some(p => action.includes(p))) return 'success';
    if (negative.some(n => action.includes(n))) return 'danger';
    if (action.includes('updated')) return 'warning';
    return 'info';
}

function typeLabel(type) {
    const map = { audit: '审计', security: '安全', system: '系统', billing: '计费' };
    return map[type] || type;
}

function typeTag(type) {
    const map = { audit: 'primary', security: 'danger', system: 'info', billing: 'warning' };
    return map[type] || 'info';
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

async function loadStats() {
    try {
        const { data: res } = await auditLogApi.stats();
        if (res.success) {
            Object.assign(stats, res.data || {});
        }
    } catch { /* ignore */ }
}

async function fetchLogs() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        const filter = {};
        if (filters.action) filter.action = filters.action;
        if (filters.type) filter.type = filters.type;
        if (Object.keys(filter).length) params.filter = filter;
        if (filters.date_from) params.date_from = filters.date_from;
        if (filters.date_to) params.date_to = filters.date_to;
        if (filters.search) params.search = filters.search;

        const { data: res } = await auditLogApi.list(params);
        if (res.success) {
            logs.value = res.data || [];
            total.value = res.meta?.total || 0;
        }
    } catch {
        ElMessage.error('加载审计日志失败');
    } finally {
        loading.value = false;
    }
}

function search() {
    page.value = 1;
    fetchLogs();
}

function resetFilters() {
    filters.action = '';
    filters.type = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.search = '';
    page.value = 1;
    fetchLogs();
}

async function showDetail(row) {
    showDetailDialog.value = true;
    detailLog.value = null;
    try {
        const { data: res } = await auditLogApi.show(row.id);
        if (res.success) {
            detailLog.value = res.data;
        }
    } catch {
        detailLog.value = row;
    }
}

function exportLogs() {
    const headers = ['时间', '操作', '类型', '描述', '操作者', 'IP地址'];
    const rows = logs.value.map(l => [
        formatTime(l.created_at),
        actionLabel(l.action),
        typeLabel(l.type),
        l.description,
        l.user?.name || '系统',
        l.ip_address || '',
    ]);
    const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    ElMessage.success('日志已导出');
}

function loadAll() {
    loadStats();
    fetchLogs();
}

onMounted(() => {
    loadAll();
});
</script>

<style scoped>
.audit-logs-page { padding: 20px; }

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
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-primary { color: var(--el-color-primary); }
.text-muted { color: var(--el-text-color-placeholder); }

.stat-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
}
.stat-label-sm {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    white-space: nowrap;
}

.action-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}

.desc-cell {
    font-size: 13px;
    color: var(--el-text-color-regular);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
}

.related-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.related-link {
    color: var(--el-color-primary);
    cursor: pointer;
    font-size: 13px;
}
.related-link:hover {
    text-decoration: underline;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

/* Detail */
.detail-section {
    margin-top: 20px;
}
.detail-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px;
}
.detail-section p {
    font-size: 14px;
    line-height: 1.6;
    color: var(--el-text-color-regular);
}
.payload-json {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    max-height: 300px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

:deep(.el-card__body) { padding: 16px; }
</style>
