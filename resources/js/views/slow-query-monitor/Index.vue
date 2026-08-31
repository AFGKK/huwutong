<template>
    <div class="slow-query-page">
        <div class="page-header">
            <div>
                <h2>{{ t('slow_query_monitor_page.title') }}</h2>
                <p class="text-muted">{{ t('slow_query_monitor_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-select v-model="timeRange" style="width:140px;margin-right:8px" @change="loadAll">
                    <el-option
                        v-for="opt in timeRangeOptions"
                        :key="opt.value"
                        :label="opt.label"
                        :value="opt.value"
                    />
                </el-select>
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('system_health_page.refresh') }}</el-button>
                <el-button type="danger" @click="handlePrune" :icon="Delete">{{ t('slow_query_monitor_page.actions.prune') }}</el-button>
            </div>
        </div>

        <!-- 告警 -->
        <el-alert v-if="alertMsg" :title="alertMsg" type="warning" show-icon :closable="true" class="mb-4" />

        <!-- ── 第一行：概览卡片 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" :md="4">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('slow_query_monitor_page.stats.total_slow') }}</div>
                            <div class="metric-value danger">{{ dashboard.total_slow }}</div>
                        </div>
                        <el-icon :size="28" color="#f56c6c"><Warning /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="5">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('apm_page.stats.avg_duration') }}</div>
                            <div class="metric-value warning">{{ dashboard.avg_duration_ms }}ms</div>
                        </div>
                        <el-icon :size="28" color="#e6a23c"><Timer /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="5">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('apm_page.columns.max_duration') }}</div>
                            <div class="metric-value danger">{{ dashboard.max_duration_ms }}ms</div>
                        </div>
                        <el-icon :size="28" color="#f56c6c"><Top /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="5">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('slow_query_monitor_page.stats.unique_sql') }}</div>
                            <div class="metric-value">{{ dashboard.unique_hashes }}</div>
                            <div class="metric-sub">{{ t('slow_query_monitor_page.stats.unique_hashes_hint') }}</div>
                        </div>
                        <el-icon :size="28" color="#0f172a"><Document /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6" :md="5">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('slow_query_monitor_page.stats.unresolved') }}</div>
                            <div class="metric-value" :class="dashboard.unresolved > 0 ? 'danger' : 'success'">{{ dashboard.unresolved }}</div>
                        </div>
                        <el-icon :size="28" :color="dashboard.unresolved > 0 ? '#f56c6c' : '#67c23a'"><CircleCheck /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 类型分布 + 表分布 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header><span><el-icon><Histogram /></el-icon> {{ t('slow_query_monitor_page.sections.type_distribution') }}</span></template>
                    <el-table :data="dashboard.type_distribution" stripe size="small" v-loading="loading">
                        <el-table-column prop="sql_type" :label="t('slow_query_monitor_page.columns.type')" width="100" />
                        <el-table-column prop="count" :label="t('slow_query_monitor_page.columns.count')" width="80" />
                        <el-table-column :label="t('apm_page.columns.avg_duration')">
                            <template #default="{ row }">{{ row.avg_duration }}ms</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!dashboard.type_distribution?.length" :description="t('messages.no_data')" />
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="hover">
                    <template #header><span><el-icon><DataBoard /></el-icon> {{ t('slow_query_monitor_page.sections.table_distribution_top') }}</span></template>
                    <el-table :data="dashboard.table_distribution" stripe size="small" v-loading="loading">
                        <el-table-column prop="table_name" :label="t('slow_query_monitor_page.columns.table_name')" min-width="140" />
                        <el-table-column prop="count" :label="t('slow_query_monitor_page.columns.count')" width="70" />
                        <el-table-column :label="t('apm_page.columns.avg_duration')" width="110">
                            <template #default="{ row }">{{ row.avg_duration }}ms</template>
                        </el-table-column>
                        <el-table-column :label="t('apm_page.columns.max_duration')" width="110">
                            <template #default="{ row }">{{ row.max_duration }}ms</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!dashboard.table_distribution?.length" :description="t('messages.no_data')" />
                </el-card>
            </el-col>
        </el-row>

        <!-- ── Top 慢查询 ── -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Top /></el-icon> {{ t('slow_query_monitor_page.sections.top_slow_sql') }} <small class="text-muted">({{ t('slow_query_monitor_page.sections.sorted_by', { field: sortFieldLabel }) }})</small></span>
                    <el-radio-group v-model="sortBy" size="small" @change="loadTop">
                        <el-radio-button
                            v-for="opt in sortOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >{{ opt.label }}</el-radio-button>
                    </el-radio-group>
                </div>
            </template>
            <el-table :data="topQueries" stripe size="small" v-loading="topLoading" @row-click="showDetail">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column :label="t('slow_query_monitor_page.columns.sql')" min-width="300">
                    <template #default="{ row }">
                        <code class="sql-preview">{{ truncate(row.sql_text, 100) }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="sql_type" :label="t('slow_query_monitor_page.columns.type')" width="80" />
                <el-table-column prop="table_name" :label="t('slow_query_monitor_page.columns.table')" width="100" />
                <el-table-column :label="t('slow_query_monitor_page.columns.count')" width="70" prop="occurrence_count" />
                <el-table-column :label="t('apm_page.columns.avg_duration')" width="110" sortable="custom">
                    <template #default="{ row }">{{ row.avg_duration_ms }}ms</template>
                </el-table-column>
                <el-table-column :label="t('apm_page.columns.max_duration')" width="110">
                    <template #default="{ row }">{{ row.max_duration_ms }}ms</template>
                </el-table-column>
                <el-table-column :label="t('slow_query_monitor_page.columns.rows_examined')" width="100">
                    <template #default="{ row }">{{ row.avg_rows_examined }} ({{ row.total_rows_examined }})</template>
                </el-table-column>
                <el-table-column :label="t('slow_query_monitor_page.columns.route')" width="150" prop="route_name" />
                <el-table-column :label="t('slow_query_monitor_page.columns.status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_resolved ? 'success' : 'danger'" size="small">
                            {{ statusLabel(row.is_resolved) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('slow_query_monitor_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click.stop="showDetail(row)">{{ t('actions.view_details') }}</el-button>
                        <el-button size="small" type="success" @click.stop="handleResolve(row)" v-if="!row.is_resolved">{{ t('slow_query_monitor_page.actions.resolve') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!topQueries.length && !topLoading" :description="t('slow_query_monitor_page.empty.no_slow_queries')" />
        </el-card>

        <!-- ── 明细列表 ── -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><List /></el-icon> {{ t('slow_query_monitor_page.sections.detail_list') }}</span>
                    <div>
                        <el-input v-model="searchText" :placeholder="t('slow_query_monitor_page.search_ph')" clearable style="width:200px;margin-right:8px" @clear="loadList" @keyup.enter="loadList" />
                        <el-button size="small" @click="loadList">{{ t('actions.search') }}</el-button>
                    </div>
                </div>
            </template>
            <el-table :data="listData.items" stripe size="small" v-loading="listLoading" @row-click="showDetail">
                <el-table-column :label="t('slow_query_monitor_page.columns.sql')" min-width="300">
                    <template #default="{ row }">
                        <code class="sql-preview">{{ truncate(row.sql_text, 80) }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="sql_type" :label="t('slow_query_monitor_page.columns.type')" width="70" />
                <el-table-column prop="table_name" :label="t('slow_query_monitor_page.columns.table')" width="100" />
                <el-table-column :label="t('apm_page.columns.duration')" width="90">
                    <template #default="{ row }"><span :class="row.duration_ms > 500 ? 'danger' : 'warning'">{{ row.duration_ms }}ms</span></template>
                </el-table-column>
                <el-table-column prop="rows_examined" :label="t('slow_query_monitor_page.columns.rows_examined')" width="80" />
                <el-table-column prop="route_name" :label="t('slow_query_monitor_page.columns.route')" width="140" />
                <el-table-column :label="t('apm_page.columns.time')" width="160">
                    <template #default="{ row }">{{ formatTime(row.occurred_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('slow_query_monitor_page.columns.status')" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_resolved ? 'success' : 'danger'" size="small">{{ row.is_resolved ? statusLabel(true) : '' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('slow_query_monitor_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click.stop="showDetail(row)">{{ t('actions.view_details') }}</el-button>
                        <el-button size="small" type="success" @click.stop="handleResolve(row)" v-if="!row.is_resolved">{{ t('slow_query_monitor_page.actions.resolve') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap" v-if="listData.last_page > 1">
                <el-pagination
                    v-model:current-page="listPage"
                    :page-size="listData.per_page"
                    :total="listData.total"
                    layout="prev, pager, next"
                    @current-change="loadList" />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('slow_query_monitor_page.dialog.detail_title')" width="800px" top="5vh">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.sql_type')">{{ detail.sql_type }}</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.table_name')">{{ detail.table_name || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('apm_page.columns.duration')">{{ detail.duration_ms }}ms</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.rows_examined')">{{ detail.rows_examined }}</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.rows_sent')">{{ detail.rows_sent }}</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.lock_wait')">{{ detail.lock_time_ms }}ms</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.route')">{{ detail.route_name || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.request_path')">{{ detail.request_path || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('slow_query_monitor_page.columns.status')" :span="2">
                        <el-tag :type="detail.is_resolved ? 'success' : 'danger'">{{ statusLabel(detail.is_resolved) }}</el-tag>
                        <span v-if="detail.resolver" class="ml-2">{{ t('slow_query_monitor_page.detail.resolver', { name: detail.resolver.name }) }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('apm_page.columns.time')" :span="2">{{ formatTime(detail.occurred_at) }}</el-descriptions-item>
                </el-descriptions>
                <div class="detail-section">
                    <h4>{{ t('slow_query_monitor_page.detail.sql_text') }}</h4>
                    <pre class="sql-block"><code>{{ detail.sql_text }}</code></pre>
                </div>
                <div class="detail-section" v-if="detail.suggestion">
                    <h4>{{ t('slow_query_monitor_page.detail.suggestion') }}</h4>
                    <pre class="suggestion-block"><code>{{ detail.suggestion }}</code></pre>
                </div>
                <div class="detail-section" v-if="explainResult">
                    <h4>{{ t('slow_query_monitor_page.detail.explain_result') }}</h4>
                    <pre class="sql-block"><code>{{ JSON.stringify(explainResult, null, 2) }}</code></pre>
                </div>
            </template>
            <template #footer>
                <el-button @click="runExplain" :loading="explainLoading" v-if="detail && !explainResult">{{ t('slow_query_monitor_page.actions.run_explain') }}</el-button>
                <el-button type="success" @click="handleResolveFromDetail" v-if="detail && !detail.is_resolved" :loading="resolving">{{ t('slow_query_monitor_page.actions.mark_resolved') }}</el-button>
                <el-button @click="detailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Delete, Warning, Timer, Top, Document, CircleCheck, Histogram, DataBoard, List } from '@element-plus/icons-vue';
import slowQueryMonitorApi from '@/api/slowQueryMonitor';

const { t, locale } = useI18n();

const loading = ref(false);
const topLoading = ref(false);
const listLoading = ref(false);
const explainLoading = ref(false);
const resolving = ref(false);
const timeRange = ref(60);
const sortBy = ref('avg_duration_ms');
const searchText = ref('');
const listPage = ref(1);
const alertMsg = ref('');

const dashboard = reactive({
    total_slow: 0, avg_duration_ms: 0, max_duration_ms: 0,
    unique_hashes: 0, unresolved: 0,
    type_distribution: [], table_distribution: [], trend: [],
});
const topQueries = ref([]);
const listData = reactive({ items: [], total: 0, page: 1, per_page: 25, last_page: 1 });
const detailVisible = ref(false);
const detail = ref(null);
const explainResult = ref(null);

const timeRangeSpec = [
    { value: 15, key: 'm15' },
    { value: 30, key: 'm30' },
    { value: 60, key: 'h1', apm: true },
    { value: 360, key: 'h6', apm: true },
    { value: 1440, key: 'h24', apm: true },
];

const timeRangeOptions = computed(() =>
    timeRangeSpec.map(({ value, key, apm }) => ({
        value,
        label: apm ? t(`apm_page.periods.${key}`) : t(`slow_query_monitor_page.periods.${key}`),
    }))
);

const sortSpec = [
    { value: 'avg_duration_ms', labelKey: 'apm_page.columns.avg_duration' },
    { value: 'occurrence_count', labelKey: 'slow_query_monitor_page.sort.occurrence_count' },
    { value: 'max_duration_ms', labelKey: 'apm_page.columns.max_duration' },
];

const sortOptions = computed(() =>
    sortSpec.map(({ value, labelKey }) => ({
        value,
        label: t(labelKey),
    }))
);

const sortFieldLabel = computed(() => {
    const match = sortSpec.find((item) => item.value === sortBy.value);
    return match ? t(match.labelKey) : '';
});

function statusLabel(isResolved) {
    return isResolved
        ? t('slow_query_monitor_page.status.resolved')
        : t('slow_query_monitor_page.status.unresolved');
}

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        await Promise.all([loadDashboard(), loadTop(), loadList(), checkAlert()]);
    } finally { loading.value = false; }
}

async function loadDashboard() {
    try {
        const res = await slowQueryMonitorApi.dashboard({ minutes: timeRange.value });
        Object.assign(dashboard, res.data?.data || {});
    } catch (e) { /* ignore */ }
}

async function loadTop() {
    topLoading.value = true;
    try {
        const res = await slowQueryMonitorApi.topSlowQueries({ minutes: timeRange.value, sort_by: sortBy.value });
        topQueries.value = res.data?.data || [];
    } finally { topLoading.value = false; }
}

async function loadList() {
    listLoading.value = true;
    try {
        const res = await slowQueryMonitorApi.list({
            minutes: timeRange.value,
            page: listPage.value,
            per_page: 25,
            search: searchText.value || undefined,
        });
        const d = res.data?.data || {};
        listData.items = d.items || [];
        listData.total = d.total || 0;
        listData.per_page = d.per_page || 25;
        listData.last_page = d.last_page || 1;
    } finally { listLoading.value = false; }
}

async function checkAlert() {
    try {
        const res = await slowQueryMonitorApi.checkAlert();
        const alert = res.data?.data?.alert;
        alertMsg.value = alert
            ? t('slow_query_monitor_page.messages.alert', {
                message: alert.message,
                count: alert.count,
                threshold: alert.threshold,
            })
            : '';
    } catch (e) { /* ignore */ }
}

async function showDetail(row) {
    detailVisible.value = true;
    detail.value = null;
    explainResult.value = null;
    try {
        const id = row.id || row.sql_hash;
        const res = await slowQueryMonitorApi.show(id);
        detail.value = res.data?.data;
        if (detail.value?.explain_result) {
            try { explainResult.value = JSON.parse(detail.value.explain_result); } catch { explainResult.value = detail.value.explain_result; }
        }
    } catch (e) { ElMessage.error(t('slow_query_monitor_page.messages.load_detail_failed')); }
}

async function runExplain() {
    if (!detail.value?.id) return;
    explainLoading.value = true;
    try {
        const res = await slowQueryMonitorApi.explain(detail.value.id);
        const d = res.data?.data || {};
        explainResult.value = d.explain_result;
        detail.value.suggestion = d.suggestion;
        ElMessage.success(t('slow_query_monitor_page.messages.explain_done'));
    } catch (e) { ElMessage.error(t('slow_query_monitor_page.messages.explain_failed')); }
    finally { explainLoading.value = false; }
}

async function handleResolve(row) {
    try {
        await ElMessageBox.confirm(
            t('slow_query_monitor_page.confirm.resolve_message'),
            t('slow_query_monitor_page.confirm.resolve_title'),
        );
        const id = row.id || row.sql_hash;
        await slowQueryMonitorApi.resolve(id);
        ElMessage.success(t('slow_query_monitor_page.messages.marked_resolved'));
        loadTop();
        loadList();
        if (detailVisible.value && detail.value) detail.value.is_resolved = true;
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('messages.failed')); }
}

async function handleResolveFromDetail() {
    if (!detail.value?.id) return;
    resolving.value = true;
    try {
        await slowQueryMonitorApi.resolve(detail.value.id);
        detail.value.is_resolved = true;
        ElMessage.success(t('slow_query_monitor_page.messages.marked_resolved'));
        loadTop();
        loadList();
    } finally { resolving.value = false; }
}

async function handlePrune() {
    try {
        await ElMessageBox.confirm(
            t('slow_query_monitor_page.confirm.prune_message'),
            t('apm_page.confirm.prune_title'),
        );
        const res = await slowQueryMonitorApi.prune();
        ElMessage.success(t('slow_query_monitor_page.messages.prune_done', { count: res.data?.data?.deleted || 0 }));
        loadAll();
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('apm_page.messages.prune_failed')); }
}

function truncate(text, len) {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '...' : text;
}

function formatTime(tVal) {
    if (!tVal) return '—';
    return new Date(tVal).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
</script>

<style scoped>
.slow-query-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card .metric-content { display: flex; justify-content: space-between; align-items: center; }
.metric-info { flex: 1; }
.metric-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.metric-value { font-size: 22px; font-weight: 700; line-height: 1.3; }
.metric-sub { font-size: 12px; color: #c0c4cc; margin-top: 2px; }
.danger { color: #f56c6c; }
.warning { color: #e6a23c; }
.success { color: #67c23a; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.sql-preview { font-size: 12px; font-family: 'SFMono-Regular', Consolas, monospace; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; line-height: 1.5; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
.detail-section { margin-top: 16px; }
.detail-section h4 { margin: 0 0 8px; font-size: 14px; }
.sql-block { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 12px; max-height: 200px; }
.suggestion-block { background: #f0f9eb; color: #333; padding: 12px; border-radius: 4px; white-space: pre-wrap; font-size: 13px; line-height: 1.6; }
.ml-2 { margin-left: 8px; }
</style>
