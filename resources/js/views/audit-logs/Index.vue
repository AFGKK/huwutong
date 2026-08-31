<template>
    <div class="audit-logs-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('audit_logs_page.title') }}</h2>
                <span class="header-subtitle">{{ t('audit_logs_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    {{ t('audit_logs_page.refresh') }}
                </el-button>
                <el-dropdown @command="handleExport" split-button type="primary">
                    <el-icon><Download /></el-icon>
                    {{ t('actions.export') }}
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="csv">{{ t('audit_logs_page.export_csv') }}</el-dropdown-item>
                            <el-dropdown-item command="json">{{ t('audit_logs_page.export_json') }}</el-dropdown-item>
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
                        <div class="stat-label">{{ t('audit_logs_page.stat_total') }}</div>
                        <div class="stat-value">{{ stats.total || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('audit_logs_page.stat_today') }}</div>
                        <div class="stat-value text-primary">{{ stats.today || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never">
                    <div class="stat-row">
                        <span class="stat-label-sm">{{ t('audit_logs_page.by_type_label') }}</span>
                        <span v-for="(count, type) in stats.by_type" :key="type" class="type-badge">
                            <el-tag size="small" :type="typeTag(type)" effect="plain">
                                {{ typeLabel(type) }}: {{ count }}
                            </el-tag>
                        </span>
                        <span v-if="!Object.keys(stats.by_type || {}).length" class="text-muted">{{ t('messages.no_data') }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选条件 -->
        <el-card shadow="never" class="mb-4">
            <el-form :model="filters" inline>
                <el-form-item :label="t('audit_logs_page.filter_action')">
                    <el-select v-model="filters.action" clearable :placeholder="t('audit_logs_page.placeholder_all')" style="width: 200px" @change="search">
                        <el-option v-for="(count, action) in stats.top_actions" :key="action" :label="actionLabel(action)" :value="action" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_type')">
                    <el-select v-model="filters.type" clearable :placeholder="t('audit_logs_page.placeholder_all')" style="width: 140px" @change="search">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :value="opt.value" :label="opt.label" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_action_prefix')">
                    <el-select v-model="filters.action_prefix" clearable :placeholder="t('audit_logs_page.placeholder_all')" style="width: 160px" @change="search">
                        <el-option v-for="opt in actionPrefixOptions" :key="opt.value" :value="opt.value" :label="opt.label" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_related_id')">
                    <el-input v-model="filters.license_id" :placeholder="t('audit_logs_page.placeholder_license_id')" clearable style="width: 120px" @change="search" />
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_date_from')">
                    <el-date-picker v-model="filters.date_from" type="date" :placeholder="t('audit_logs_page.placeholder_date_from')" value-format="YYYY-MM-DD" @change="search" />
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_date_to')">
                    <el-date-picker v-model="filters.date_to" type="date" :placeholder="t('audit_logs_page.placeholder_date_to')" value-format="YYYY-MM-DD" @change="search" />
                </el-form-item>
                <el-form-item :label="t('audit_logs_page.filter_keyword')">
                    <el-input v-model="filters.search" :placeholder="t('audit_logs_page.placeholder_search')" clearable style="width: 180px" @keyup.enter="search" @clear="search" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="search">{{ t('audit_logs_page.query') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 日志列表 -->
        <el-card shadow="never">
            <el-table :data="logs" v-loading="loading" stripe :max-height="600">
                <el-table-column :label="t('audit_logs_page.col_time')" width="170" sortable prop="created_at">
                    <template #default="{ row }">
                        {{ formatTime(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_action')" min-width="160">
                    <template #default="{ row }">
                        <div class="action-cell">
                            <el-tag :type="actionTag(row.action)" size="small" effect="plain">
                                {{ actionLabel(row.action) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_description')" min-width="300">
                    <template #default="{ row }">
                        <div class="desc-cell">{{ row.description }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_operator')" width="140">
                    <template #default="{ row }">
                        <div class="user-cell">
                            <el-icon :size="14"><UserFilled /></el-icon>
                            <span>{{ row.user?.name || row.user?.email || t('audit_logs_page.system_actor') }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_related')" min-width="160">
                    <template #default="{ row }">
                        <div class="related-cell">
                            <span v-if="row.license" class="related-link" @click="$router.push('/licenses/' + row.license_id)">
                                {{ t('audit_logs_page.related_license', { id: row.license_id }) }}
                            </span>
                            <span v-if="row.customer" class="related-link" @click="$router.push('/customers/' + row.customer_id)">
                                {{ t('audit_logs_page.related_customer', { id: row.customer_id }) }}
                            </span>
                            <span v-if="!row.license && !row.customer && !row.device" class="text-muted">{{ emDash }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_ip')" width="130">
                    <template #default="{ row }">
                        <code>{{ row.ip_address || emDash }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('audit_logs_page.col_ops')" width="70" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
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
        <el-dialog v-model="showDetailDialog" :title="t('audit_logs_page.detail_title')" width="700px">
            <div v-if="detailLog">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('audit_logs_page.col_time')">{{ formatTime(detailLog.created_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.col_type')">
                        <el-tag :type="typeTag(detailLog.type)" size="small">{{ typeLabel(detailLog.type) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.col_action')">
                        <el-tag :type="actionTag(detailLog.action)" size="small" effect="plain">{{ actionLabel(detailLog.action) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.col_operator')">{{ detailLog.user?.name || detailLog.user?.email || t('audit_logs_page.system_actor') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.col_ip')"><code>{{ detailLog.ip_address || emDash }}</code></el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.label_user_agent')" :content-style="{ 'word-break': 'break-all' }">{{ detailLog.user_agent || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.label_license_id')" :span="1">{{ detailLog.license_id || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.label_customer_id')" :span="1">{{ detailLog.customer_id || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.label_device_id')" :span="1">{{ detailLog.device_id || emDash }}</el-descriptions-item>
                    <el-descriptions-item :label="t('audit_logs_page.label_product_id')" :span="1">{{ detailLog.product_id || emDash }}</el-descriptions-item>
                </el-descriptions>

                <div class="detail-section">
                    <h4>{{ t('audit_logs_page.section_description') }}</h4>
                    <p>{{ detailLog.description }}</p>
                </div>

                <div v-if="detailLog.payload && Object.keys(detailLog.payload).length" class="detail-section">
                    <h4>{{ t('audit_logs_page.section_payload') }}
                        <el-tag size="small" type="info" style="margin-left:8px;">
                            {{ t('audit_logs_page.payload_fields', { n: Object.keys(detailLog.payload).length }) }}
                        </el-tag>
                    </h4>
                    <!-- 当 payload 中有 diffs 字段时，使用对比展示 -->
                    <template v-if="detailLog.payload.diffs">
                        <el-table :data="diffRows" stripe size="small" border>
                            <el-table-column prop="field" :label="t('audit_logs_page.col_field')" width="160" />
                            <el-table-column :label="t('audit_logs_page.col_old_value')" min-width="150">
                                <template #default="{ row }">
                                    <span class="diff-old">{{ row.old !== undefined ? row.old : emDash }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('audit_logs_page.col_new_value')" min-width="150">
                                <template #default="{ row }">
                                    <span class="diff-new">{{ row.new !== undefined ? row.new : emDash }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                    </template>
                    <!-- 普通 payload 展示 -->
                    <pre v-else class="payload-json">{{ JSON.stringify(detailLog.payload, null, 2) }}</pre>
                </div>

                <!-- Merkle 哈希信息 -->
                <div v-if="detailLog.merkle_hash" class="detail-section merkle-section">
                    <h4>{{ t('audit_logs_page.section_merkle') }}</h4>
                    <el-descriptions :column="1" border size="small">
                        <el-descriptions-item :label="t('audit_logs_page.merkle_current')">
                            <code class="merkle-hash">{{ detailLog.merkle_hash }}</code>
                        </el-descriptions-item>
                        <el-descriptions-item v-if="detailLog.merkle_parent_id" :label="t('audit_logs_page.merkle_parent')">
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
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Download, UserFilled } from '@element-plus/icons-vue';
import auditLogApi from '@/api/auditLog';

const { t, locale } = useI18n();
const emDash = '—';

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

const typeOptions = computed(() => [
    { value: 'audit', label: t('audit_logs_page.type_audit') },
    { value: 'security', label: t('audit_logs_page.type_security') },
    { value: 'error', label: t('audit_logs_page.type_error') },
    { value: 'system', label: t('audit_logs_page.type_system') },
]);

const actionPrefixOptions = computed(() => [
    { value: 'license.', label: t('audit_logs_page.prefix_license') },
    { value: 'device.', label: t('audit_logs_page.prefix_device') },
    { value: 'user.', label: t('audit_logs_page.prefix_user') },
    { value: 'customer.', label: t('audit_logs_page.prefix_customer') },
    { value: 'security.', label: t('audit_logs_page.prefix_security') },
]);

const actionLabels = computed(() => ({
    'license.created': t('audit_logs_page.action_labels.license.created'),
    'license.activated': t('audit_logs_page.action_labels.license.activated'),
    'license.revoked': t('audit_logs_page.action_labels.license.revoked'),
    'license.suspended': t('audit_logs_page.action_labels.license.suspended'),
    'license.restored': t('audit_logs_page.action_labels.license.restored'),
    'license.expired': t('audit_logs_page.action_labels.license.expired'),
    'license.refunded': t('audit_logs_page.action_labels.license.refunded'),
    'license.updated': t('audit_logs_page.action_labels.license.updated'),
    'license.status_changed': t('audit_logs_page.action_labels.license.status_changed'),
    'license.renewed': t('audit_logs_page.action_labels.license.renewed'),
    'customer.created': t('audit_logs_page.action_labels.customer.created'),
    'customer.updated': t('audit_logs_page.action_labels.customer.updated'),
    'device.activated': t('audit_logs_page.action_labels.device.activated'),
    'device.deactivated': t('audit_logs_page.action_labels.device.deactivated'),
    'user.login': t('audit_logs_page.action_labels.user.login'),
    'user.logout': t('audit_logs_page.action_labels.user.logout'),
    'user.login_failed': t('audit_logs_page.action_labels.user.login_failed'),
    'user.created': t('audit_logs_page.action_labels.user.created'),
    'user.password_changed': t('audit_logs_page.action_labels.user.password_changed'),
    'subscription.created': t('audit_logs_page.action_labels.subscription.created'),
    'subscription.canceled': t('audit_logs_page.action_labels.subscription.canceled'),
    'subscription.renewed': t('audit_logs_page.action_labels.subscription.renewed'),
    'api_key.created': t('audit_logs_page.action_labels.api_key.created'),
    'api_key.revoked': t('audit_logs_page.action_labels.api_key.revoked'),
    'security.mfa_enabled': t('audit_logs_page.action_labels.security.mfa_enabled'),
    'security.mfa_disabled': t('audit_logs_page.action_labels.security.mfa_disabled'),
}));

const typeLabels = computed(() => ({
    audit: t('audit_logs_page.type_audit'),
    security: t('audit_logs_page.type_security'),
    error: t('audit_logs_page.type_error'),
    system: t('audit_logs_page.type_system'),
    billing: t('audit_logs_page.type_billing'),
}));

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
    return actionLabels.value[action] || action;
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
    return typeLabels.value[type] || type;
}

function typeTag(type) {
    const map = { audit: 'primary', security: 'danger', error: 'danger', system: 'info', billing: 'warning' };
    return map[type] || 'info';
}

function formatTime(time) {
    if (!time) return emDash;
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(time).toLocaleString(loc, { hour12: false });
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
        ElMessage.error(t('audit_logs_page.load_fail'));
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

    ElMessage.success(t('audit_logs_page.export_started'));
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
