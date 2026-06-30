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
                <el-dropdown @command="handleExport" split-button type="primary">
                    <el-icon><Download /></el-icon>
                    导出
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="csv">导出 CSV</el-dropdown-item>
                            <el-dropdown-item command="json">导出 JSON</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
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
                        <el-option value="audit" label="审计" />
                        <el-option value="security" label="安全" />
                        <el-option value="error" label="错误" />
                        <el-option value="system" label="系统" />
                    </el-select>
                </el-form-item>
                <el-form-item label="动作前缀">
                    <el-select v-model="filters.action_prefix" clearable placeholder="全部" style="width: 160px" @change="search">
                        <el-option label="License 相关" value="license." />
                        <el-option label="设备相关" value="device." />
                        <el-option label="用户相关" value="user." />
                        <el-option label="客户相关" value="customer." />
                        <el-option label="安全事件" value="security." />
                    </el-select>
                </el-form-item>
                <el-form-item label="关联 ID">
                    <el-input v-model="filters.license_id" placeholder="License ID" clearable style="width: 120px" @change="search" />
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
                            <span v-if="!row.license && !row.customer && !row.device" class="text-muted">—</span>
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
                    :page-sizes="[20, 50, 100]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchLogs"
                    @size-change="fetchLogs"
                />
            </div>
        </el-card>

        <!-- 详情 Dialog -->
        <el-dialog v-model="showDetailDialog" title="日志详情" width="700px">
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
                    <el-descriptions-item label="License ID" :span="1">{{ detailLog.license_id || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="客户 ID" :span="1">{{ detailLog.customer_id || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="设备 ID" :span="1">{{ detailLog.device_id || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="产品 ID" :span="1">{{ detailLog.product_id || '—' }}</el-descriptions-item>
                </el-descriptions>

                <div class="detail-section">
                    <h4>描述</h4>
                    <p>{{ detailLog.description }}</p>
                </div>

                <div v-if="detailLog.payload && Object.keys(detailLog.payload).length" class="detail-section">
                    <h4>请求载荷（Payload）
                        <el-tag size="small" type="info" style="margin-left:8px;">
                            {{ Object.keys(detailLog.payload).length }} 字段
                        </el-tag>
                    </h4>
                    <!-- 当 payload 中有 diffs 字段时，使用对比展示 -->
                    <template v-if="detailLog.payload.diffs">
                        <el-table :data="diffRows" stripe size="small" border>
                            <el-table-column prop="field" label="字段" width="160" />
                            <el-table-column label="原值" min-width="150">
                                <template #default="{ row }">
                                    <span class="diff-old">{{ row.old !== undefined ? row.old : '—' }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="新值" min-width="150">
                                <template #default="{ row }">
                                    <span class="diff-new">{{ row.new !== undefined ? row.new : '—' }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </template>
                    <!-- 普通 payload 展示 -->
                    <pre v-else class="payload-json">{{ JSON.stringify(detailLog.payload, null, 2) }}</pre>
                </div>

                <!-- Merkle 哈希信息 -->
                <div v-if="detailLog.merkle_hash" class="detail-section merkle-section">
                    <h4>Merkle 链信息</h4>
                    <el-descriptions :column="1" border size="small">
                        <el-descriptions-item label="当前哈希">
                            <code class="merkle-hash">{{ detailLog.merkle_hash }}</code>
                        </el-descriptions-item>
                        <el-descriptions-item v-if="detailLog.merkle_parent_id" label="上级哈希">
                            <code class="merkle-hash">{{ detailLog.merkle_parent_id }}</code>
                        </el-descriptions-item>
                    </el-descriptions>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Download, UserFilled } from '@element-plus/icons-vue';
import auditLogApi from '@/api/auditLog';

const loading = ref(false);
const showDetailDialog = ref(false);
const detailLog = ref(null);
const logs = ref([]);
const page = ref(1);
const perPage = ref(20);
const total = ref(0);

const stats = reactive({
    total: 0, today: 0, by_type: {}, top_actions: {},
});

const filters = reactive({
    action: '',
    type: '',
    action_prefix: '',
    license_id: '',
    date_from: '',
    date_to: '',
    search: '',
});

// 将 diffs 对象转为表格行
const diffRows = computed(() => {
    if (!detailLog.value?.payload?.diffs) return [];
    return Object.entries(detailLog.value.payload.diffs).map(([field, vals]) => ({
        field,
        old: vals.old,
        new: vals.new,
    }));
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
        'license.renewed': '续费',
        'customer.created': '创建客户',
        'customer.updated': '更新客户',
        'device.activated': '设备激活',
        'device.deactivated': '设备解绑',
        'user.login': '用户登录',
        'user.logout': '用户登出',
        'user.login_failed': '登录失败',
        'user.created': '创建用户',
        'user.password_changed': '修改密码',
        'subscription.created': '创建订阅',
        'subscription.canceled': '取消订阅',
        'subscription.renewed': '续费订阅',
        'api_key.created': '创建 API 密钥',
        'api_key.revoked': '吊销 API 密钥',
        'security.mfa_enabled': '启用 MFA',
        'security.mfa_disabled': '禁用 MFA',
    };
    return map[action] || action;
}

function actionTag(action) {
    if (!action) return 'info';
    const positive = ['created', 'activated', 'published', 'login', 'renewed', 'restored'];
    const negative = ['revoked', 'suspended', 'expired', 'refunded', 'failed', 'canceled', 'deleted', 'login_failed'];
    if (positive.some(p => action.includes(p))) return 'success';
    if (negative.some(n => action.includes(n))) return 'danger';
    if (action.includes('updated') || action.includes('changed')) return 'warning';
    return 'info';
}

function typeLabel(type) {
    const map = { audit: '审计', security: '安全', error: '错误', system: '系统', billing: '计费' };
    return map[type] || type;
}

function typeTag(type) {
    const map = { audit: 'primary', security: 'danger', error: 'danger', system: 'info', billing: 'warning' };
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

function buildFilterParams() {
    const filter = {};
    if (filters.action) filter.action = filters.action;
    if (filters.type) filter.type = filters.type;
    if (filters.action_prefix) filter.action_prefix = filters.action_prefix;
    if (filters.license_id) filter.license_id = filters.license_id;
    return filter;
}

async function fetchLogs() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
            filter: buildFilterParams(),
        };
        if (Object.keys(params.filter).length === 0) delete params.filter;
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
    filters.action_prefix = '';
    filters.license_id = '';
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

function handleExport(format) {
    // 使用服务端导出端点，传递当前筛选条件
    const params = new URLSearchParams();
    params.set('format', format);

    const filter = buildFilterParams();
    if (Object.keys(filter).length) {
        for (const [k, v] of Object.entries(filter)) {
            params.set('filter[' + k + ']', v);
        }
    }
    if (filters.date_from) params.set('date_from', filters.date_from);
    if (filters.date_to) params.set('date_to', filters.date_to);
    if (filters.search) params.set('search', filters.search);

    const url = '/api/audit-logs/export?' + params.toString();
    window.open(url, '_blank');

    ElMessage.success('正在导出，请稍候...');
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

.diff-old {
    color: #F56C6C;
    text-decoration: line-through;
}
.diff-new {
    color: #67C23A;
    font-weight: 600;
}

.merkle-section {
    margin-top: 20px;
}
.merkle-hash {
    font-size: 11px;
    word-break: break-all;
}

:deep(.el-card__body) { padding: 16px; }
</style>
