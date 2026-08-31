<template>
    <div class="dashboard">
        <el-tabs v-model="dashMainTab" type="border-card">
            <!-- ── Tab 1: 系统概览 ── -->
            <el-tab-pane :label="t('admin_dash.system_overview')" name="overview">
                <div class="page-header">
                    <h2>{{ t('admin_dash.title') }}</h2>
                    <el-date-picker
                        v-model="dateRange"
                        type="datetimerange"
                        :range-separator="t('admin_dash.range_to')"
                        :start-placeholder="t('admin_dash.start_ph')"
                        :end-placeholder="t('admin_dash.end_ph')"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        @change="refreshAll"
                        style="width: 280px"
                    />
                </div>

                <el-row :gutter="16" class="mb-4 stat-cards-row">
                    <el-col :xs="12" :sm="12" :md="6" v-for="stat in statCards" :key="stat.key">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-content">
                                <div class="stat-info">
                                    <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                                    <div class="stat-label">{{ t(stat.labelKey) }}</div>
                                </div>
                                <el-icon :size="40" :color="stat.color" class="stat-icon">
                                    <component :is="stat.icon" />
                                </el-icon>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="dashboard-cols">
                    <el-col :xs="24" :md="12">
                        <el-card class="mb-4">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('admin_dash.recent_licenses') }}</span>
                                    <el-link type="primary" :underline="'never'" @click="$router.push('/licenses')">{{ t('admin_dash.view_all') }}</el-link>
                                </div>
                            </template>
                            <el-empty v-if="!recentLicenses.length" :description="t('admin_dash.no_licenses')" />
                            <div v-else class="table-scroll-wrap">
                                <el-table :data="recentLicenses" stripe style="width: 100%">
                                    <el-table-column prop="license_key" :label="t('admin_dash.col_key')" min-width="180">
                                        <template #default="{ row }">
                                            <el-link type="primary" :underline="'never'" @click="$router.push(`/licenses/${row.id}`)">
                                                <code>{{ row.license_key }}</code>
                                            </el-link>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="status" :label="t('admin_dash.col_status')" width="100">
                                        <template #default="{ row }">
                                            <el-tag :type="statusType(row.status)" size="small" effect="dark">
                                                {{ statusLabel(row.status) }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="created_at" :label="t('admin_dash.col_created')" width="170" />
                                </el-table>
                            </div>
                        </el-card>

                        <el-card>
                            <template #header>
                                <span>{{ t('admin_dash.status_dist') }}</span>
                            </template>
                            <div v-if="Object.keys(licenseStats.by_status || {}).length" class="status-distribution">
                                <div v-for="(count, status) in licenseStats.by_status" :key="status" class="status-row">
                                    <span class="status-name">
                                        <el-tag :type="statusType(status)" size="small" effect="dark">
                                            {{ statusLabel(status) }}
                                        </el-tag>
                                    </span>
                                    <el-progress
                                        :percentage="calcPercent(count, licenseStats.total)"
                                        :color="statusColor(status)"
                                        :stroke-width="16"
                                        :text-inside="true"
                                    >
                                        <span>{{ count }}</span>
                                    </el-progress>
                                </div>
                            </div>
                            <el-empty v-else :description="t('admin_dash.no_data')" />
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="12">
                        <el-card class="mb-4">
                            <template #header>
                                <span>{{ t('admin_dash.quick_actions') }}</span>
                            </template>
                            <div class="quick-actions">
                                <el-row :gutter="12">
                                    <el-col :xs="12" :sm="12">
                                        <el-button type="primary" class="action-btn" @click="$router.push('/licenses')">
                                            <el-icon><Plus /></el-icon> {{ t('admin_dash.create_license') }}
                                        </el-button>
                                    </el-col>
                                    <el-col :xs="12" :sm="12">
                                        <el-button class="action-btn" @click="$router.push('/customers')">
                                            <el-icon><User /></el-icon> {{ t('admin_dash.manage_customers') }}
                                        </el-button>
                                    </el-col>
                                </el-row>
                                <el-row :gutter="12" class="mt-2">
                                    <el-col :xs="12" :sm="12">
                                        <el-button class="action-btn" @click="$router.push('/products')">
                                            <el-icon><Goods /></el-icon> {{ t('admin_dash.manage_products') }}
                                        </el-button>
                                    </el-col>
                                    <el-col :xs="12" :sm="12">
                                        <el-button class="action-btn" @click="$router.push('/mfa')">
                                            <el-icon><Lock /></el-icon> {{ t('admin_dash.mfa_settings') }}
                                        </el-button>
                                    </el-col>
                                </el-row>
                                <el-row :gutter="12" class="mt-2">
                                    <el-col :xs="12" :sm="12">
                                        <el-button class="action-btn" @click="$router.push('/billing')">
                                            <el-icon><Coin /></el-icon> {{ t('admin_dash.billing') }}
                                        </el-button>
                                    </el-col>
                                    <el-col :xs="12" :sm="12">
                                        <el-button class="action-btn" @click="$router.push('/system-health')">
                                            <el-icon><Monitor /></el-icon> {{ t('admin_dash.system_health') }}
                                        </el-button>
                                    </el-col>
                                </el-row>
                            </div>
                        </el-card>

                        <el-card>
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('admin_dash.expiring_soon') }}</span>
                                    <el-tag v-if="licenseStats.expiring_soon" type="warning" size="small">
                                        {{ t('admin_dash.count_n', { n: licenseStats.expiring_soon }) }}
                                    </el-tag>
                                </div>
                            </template>
                            <el-empty v-if="!expiringLicenses.length" :description="t('admin_dash.no_expiring')" />
                            <div v-else class="table-scroll-wrap">
                                <el-table :data="expiringLicenses" stripe>
                                    <el-table-column prop="license_key" :label="t('admin_dash.col_license')" min-width="160">
                                        <template #default="{ row }">
                                            <el-link type="primary" :underline="'never'" @click="$router.push(`/licenses/${row.id}`)">
                                                <code class="small-text">{{ row.license_key }}</code>
                                            </el-link>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="expires_at" :label="t('admin_dash.col_expires')" width="170">
                                        <template #default="{ row }">
                                            <span class="expiring-text">{{ row.expires_at }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="customer?.name" :label="t('admin_dash.col_customer')" width="120" :formatter="(r) => r.customer?.name || '-'" />
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ── Tab 2: 自定义面板 ── -->
            <el-tab-pane :label="t('admin_dash.custom_panel')" name="custom">
                <div v-if="cd_tabVisited" class="cd-content">
                    <el-card shadow="never" class="mb-4">
                        <el-row :gutter="12" justify="space-between" align="middle">
                            <el-col :span="12">
                                <el-space>
                                    <span class="cd-text-lg cd-font-medium">{{ t('custom_dashboard_page.title') }}</span>
                                    <el-select v-model="cd_currentDashboardId" :placeholder="t('custom_dashboard_page.select_dashboard_ph')" style="width:220px" @change="cd_switchDashboard">
                                        <el-option v-for="d in cd_dashboards" :key="d.id" :label="d.name" :value="d.id">
                                            <span>{{ d.name }}</span>
                                            <span v-if="d.is_default" class="ml-2">
                                                <el-tag size="small" type="warning">{{ t('report_builder_page.columns.default') }}</el-tag>
                                            </span>
                                            <span class="text-gray-400 text-xs ml-2">{{ t('custom_dashboard_page.widgets_count', { n: d.widgets_count }) }}</span>
                                        </el-option>
                                    </el-select>
                                </el-space>
                            </el-col>
                            <el-col :span="12" class="text-right">
                                <el-space>
                                    <el-button size="small" @click="cd_refreshAll">{{ t('custom_dashboard_page.refresh_all') }}</el-button>
                                    <el-button size="small" @click="cd_openWidgetLibrary">+ {{ t('custom_dashboard_page.add_widget') }}</el-button>
                                    <el-dropdown trigger="click">
                                        <el-button size="small">
                                            {{ t('custom_dashboard_page.manage') }} <el-icon><ArrowDown /></el-icon>
                                        </el-button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item @click="cd_openCreateDashboard">{{ t('custom_dashboard_page.menu.create') }}</el-dropdown-item>
                                                <el-dropdown-item @click="cd_editCurrentDashboard">{{ t('custom_dashboard_page.menu.edit_current') }}</el-dropdown-item>
                                                <el-dropdown-item @click="cd_setCurrentAsDefault" v-if="cd_currentDashboard && !cd_currentDashboard.is_default">
                                                    {{ t('custom_dashboard_page.menu.set_default') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item @click="cd_duplicateCurrent">{{ t('custom_dashboard_page.menu.duplicate') }}</el-dropdown-item>
                                                <el-dropdown-item divided @click="cd_deleteCurrent" class="text-red">{{ t('custom_dashboard_page.menu.delete') }}</el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </el-space>
                            </el-col>
                        </el-row>
                    </el-card>

                    <!-- Widget 网格 -->
                    <div v-if="cd_loading" v-loading="cd_loading" style="min-height:200px" />
                    <div v-else-if="!cd_currentDashboard" class="text-center text-gray-400 py-12">
                        {{ t('custom_dashboard_page.empty.no_dashboard') }}
                    </div>
                    <div v-else-if="!cd_currentDashboard.widgets?.length" class="text-center text-gray-400 py-12">
                        {{ t('custom_dashboard_page.empty.no_widgets') }}
                    </div>

                    <div v-if="cd_currentDashboard?.widgets?.length" class="cd-widget-grid" :style="cd_gridStyle">
                        <div v-for="widget in cd_currentDashboard.widgets" :key="widget.id"
                            class="cd-widget-item" :style="cd_widgetStyle(widget)">
                            <el-card shadow="hover" class="cd-widget-card">
                                <template #header>
                                    <div class="cd-widget-header">
                                        <span class="cd-widget-title">{{ widget.title }}</span>
                                        <el-space>
                                            <el-tag size="small" effect="plain" class="cd-widget-type-tag">{{ cd_typeLabel(widget.type) }}</el-tag>
                                            <el-dropdown trigger="click" size="small">
                                                <el-button size="small" link><el-icon><MoreFilled /></el-icon></el-button>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item @click="cd_editWidget(widget)">{{ t('actions.edit') }}</el-dropdown-item>
                                                        <el-dropdown-item @click="cd_refreshWidget(widget)">{{ t('custom_dashboard_page.widget_actions.refresh_data') }}</el-dropdown-item>
                                                        <el-dropdown-item divided @click="cd_deleteWidget(widget)" class="text-red">{{ t('actions.delete') }}</el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </el-space>
                                    </div>
                                </template>

                                <!-- Stat 统计数字 -->
                                <div v-if="widget.type === 'stat'" class="cd-widget-stat">
                                    <div v-for="(val, key) in widget.data" :key="key" class="cd-stat-item">
                                        <div class="cd-stat-key">{{ cd_statKeyLabel(key) }}</div>
                                        <div class="cd-stat-val">{{ val }}</div>
                                    </div>
                                </div>

                                <!-- Chart 图表 -->
                                <div v-else-if="widget.type === 'chart'" class="cd-widget-chart">
                                    <div v-if="widget.data?.by_status">
                                        <div v-for="(cnt, status) in widget.data.by_status" :key="status" class="cd-chart-row">
                                            <span class="cd-chart-label">{{ status }}</span>
                                            <el-progress :percentage="cd_chartPercent(cnt, widget.data.total)" :stroke-width="20" striped />
                                            <span class="cd-chart-value">{{ cnt }}</span>
                                        </div>
                                    </div>
                                    <div v-else-if="widget.data?.by_date" class="cd-chart-trend">
                                        <div v-for="d in widget.data.by_date.slice(-14)" :key="d.date" class="cd-trend-bar-wrapper">
                                            <div class="cd-trend-bar" :style="{ height: cd_trendHeight(d.cnt, widget.data.by_date) + '%' }"></div>
                                            <div class="cd-trend-label">{{ d.date?.slice(5) }}</div>
                                        </div>
                                    </div>
                                    <div v-else class="text-gray-400 text-xs">{{ t('custom_dashboard_page.empty_data.chart') }}</div>
                                </div>

                                <!-- Metric 指标卡 -->
                                <div v-else-if="widget.type === 'metric'" class="cd-widget-metric">
                                    <div v-for="(val, key) in widget.data" :key="key" class="cd-metric-card">
                                        <div class="cd-metric-label">{{ cd_statKeyLabel(key) }}</div>
                                        <div class="cd-metric-value">{{ val }}</div>
                                    </div>
                                </div>

                                <!-- List 列表 -->
                                <div v-else-if="widget.type === 'list'" class="cd-widget-list">
                                    <div v-for="(item, idx) in (widget.data?.slice(0, 8) || [])" :key="idx" class="cd-list-item">
                                        <span>{{ item.name || item.title || item.id }}</span>
                                        <span class="text-gray-400 text-xs">{{ item.created_at?.slice(0, 10) }}</span>
                                    </div>
                                    <div v-if="!widget.data?.length" class="text-gray-400 text-xs">{{ t('messages.no_data') }}</div>
                                </div>

                                <!-- Table 表格 -->
                                <div v-else-if="widget.type === 'table' && widget.data?.length" class="cd-widget-table-wrapper">
                                    <el-table :data="widget.data.slice(0, 6)" size="small" max-height="240">
                                        <el-table-column v-for="col in Object.keys(widget.data[0] || {}).slice(0, 5)" :key="col"
                                            :prop="col" :label="col" min-width="80" show-overflow-tooltip />
                                    </el-table>
                                </div>

                                <!-- Alert 告警 -->
                                <div v-else-if="widget.type === 'alert'" class="cd-widget-alert">
                                    <div v-for="alert in widget.data?.slice(0, 8) || []" :key="alert.id" class="cd-alert-item">
                                        <el-tag :type="alert.status === 'open' ? 'danger' : 'warning'" size="small" class="mr-2">
                                            {{ alert.status }}
                                        </el-tag>
                                        <span class="text-xs">{{ alert.title || alert.description }}</span>
                                    </div>
                                    <div v-if="!widget.data?.length" class="text-gray-400 text-xs">{{ t('custom_dashboard_page.empty_data.alerts') }}</div>
                                </div>

                                <!-- iFrame -->
                                <div v-else-if="widget.type === 'iframe'" class="cd-widget-iframe">
                                    <iframe :src="widget.config?.url" class="cd-iframe-content" frameborder="0" />
                                </div>

                                <!-- HTML -->
                                <div v-else-if="widget.type === 'html'" class="cd-widget-html" v-html="widget.config?.html" />

                                <!-- Report 报表快照 -->
                                <div v-else-if="widget.type === 'report'" class="cd-widget-report">
                                    <div class="text-xs text-gray-400">{{ t('custom_dashboard_page.report_snapshot', { id: widget.config?.report_id }) }}</div>
                                </div>

                                <!-- Fallback -->
                                <div v-else class="text-gray-400 text-xs py-4">{{ t('custom_dashboard_page.unknown_widget_type', { type: widget.type }) }}</div>
                            </el-card>
                        </div>
                    </div>

                    <!-- 导航点（快速切换仪表盘） -->
                    <div v-if="cd_dashboards.length > 1" class="cd-nav-dots">
                        <div v-for="d in cd_dashboards" :key="d.id" class="cd-nav-dot-wrapper">
                            <div class="cd-nav-dot" :class="{ active: d.id === cd_currentDashboardId }"
                                @click="cd_currentDashboardId = d.id; cd_switchDashboard()"
                                :title="d.name" />
                            <div class="cd-nav-dot-title">{{ d.name }}</div>
                        </div>
                    </div>

                    <!-- 对话框 -->
                    <DashboardDialog ref="cd_dashboardDialogRef" @saved="cd_loadDashboards" />
                    <WidgetDialog ref="cd_widgetDialogRef" :dashboard-id="cd_currentDashboardId" @saved="cd_loadDashboard" />
                    <WidgetLibrary ref="cd_libraryRef" :dashboard-id="cd_currentDashboardId" @added="cd_loadDashboard" />
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, markRaw, onMounted, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import licenseApi from '@/api/license';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Key, Plus, User, Goods, Lock, Monitor, Coin, Odometer,
    ArrowDown, MoreFilled,
} from '@element-plus/icons-vue';
import {
    getDashboards, getDashboard, deleteDashboard, setDefaultDashboard,
    duplicateDashboard, refreshWidgetData, deleteWidget as apiDeleteWidget,
} from '@/api/dashboard';
import DashboardDialog from './components/DashboardDialog.vue';
import WidgetDialog from './components/WidgetDialog.vue';
import WidgetLibrary from './components/WidgetLibrary.vue';

const { t } = useI18n();

// ─── Tab state ───
const dashMainTab = ref('overview');

// ═══════════════════════════════════════════
//  System Overview (original dashboard)
// ═══════════════════════════════════════════

const loading = ref(false);
const dateRange = ref(null);
const recentLicenses = ref([]);
const expiringLicenses = ref([]);
const licenseStats = reactive({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    by_status: {},
    by_type: {},
});

const statValues = reactive({ all: '0', active: '0', expiring: '0', expired: '0' });

const statCards = computed(() => [
    { key: 'all', labelKey: 'admin_dash.stat_all', value: statValues.all, icon: markRaw(Key), color: '#0f172a' },
    { key: 'active', labelKey: 'admin_dash.stat_active', value: statValues.active, icon: markRaw(Odometer), color: '#67c23a' },
    { key: 'expiring', labelKey: 'admin_dash.stat_expiring', value: statValues.expiring, icon: markRaw(Coin), color: '#e6a23c' },
    { key: 'expired', labelKey: 'admin_dash.stat_expired', value: statValues.expired, icon: markRaw(Key), color: '#f56c6c' },
]);

const STATUS_TYPE = {
    pending: 'info', active: 'success', suspended: 'warning', frozen: 'warning',
    expired: 'info', revoked: 'danger', refunded: 'danger', blacklisted: 'danger',
};

function statusType(status) { return STATUS_TYPE[status] || 'info'; }
function statusLabel(status) {
    const key = `admin_dash.st_${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
}

function statusColor(status) {
    const map = {
        pending: '#909399', active: '#67c23a', suspended: '#e6a23c',
        frozen: '#e6a23c', expired: '#909399', revoked: '#f56c6c',
        refunded: '#f56c6c', blacklisted: '#f56c6c',
    };
    return map[status] || '#0f172a';
}

function calcPercent(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
}

async function refreshAll() {
    loading.value = true;
    try {
        const { data: statsRes } = await licenseApi.stats();
        const stats = statsRes.data || {};
        licenseStats.total = stats.total || 0;
        licenseStats.active = stats.active || 0;
        licenseStats.expired = stats.expired || 0;
        licenseStats.expiring_soon = stats.expiring_soon || 0;
        licenseStats.by_status = stats.by_status || {};
        licenseStats.by_type = stats.by_type || {};

        statValues.all = String(stats.total || 0);
        statValues.active = String(stats.active || 0);
        statValues.expiring = String(stats.expiring_soon || 0);
        statValues.expired = String(stats.expired || 0);

        const params = { per_page: 8, sort: '-created_at' };
        const { data: listRes } = await licenseApi.list(params);
        recentLicenses.value = listRes.data?.data || [];
    } catch {
        ElMessage.error(t('admin_dash.load_fail'));
    } finally {
        loading.value = false;
    }
}

// ═══════════════════════════════════════════
//  Custom Dashboard (merged, cd_ prefix)
// ═══════════════════════════════════════════

const cd_tabVisited = ref(false);
watch(dashMainTab, (val) => {
    if (val === 'custom' && !cd_tabVisited.value) {
        cd_tabVisited.value = true;
    }
});

const cd_dashboards = ref([]);
const cd_currentDashboardId = ref(null);
const cd_currentDashboard = ref(null);
const cd_loading = ref(false);
const cd_dashboardDialogRef = ref(null);
const cd_widgetDialogRef = ref(null);
const cd_libraryRef = ref(null);

const cd_gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${cd_currentDashboard.value?.columns || 12}, minmax(0, 1fr))`,
}));

const cd_widgetTypeKeys = ['stat', 'chart', 'list', 'metric', 'table', 'iframe', 'html', 'alert', 'report'];

const cd_typeLabels = computed(() => {
    const labels = {};
    for (const key of cd_widgetTypeKeys) {
        if (key === 'table') {
            labels[key] = t('report_builder_page.chart_types.table');
        } else {
            labels[key] = t(`custom_dashboard_page.widget_types.${key}`);
        }
    }
    return labels;
});

const cd_statKeyLabelMap = computed(() => ({
    total_licenses: t('custom_dashboard_page.stat_keys.total_licenses'),
    active_licenses: t('custom_dashboard_page.stat_keys.active_licenses'),
    total_subscriptions: t('custom_dashboard_page.stat_keys.total_subscriptions'),
    active_subscriptions: t('custom_dashboard_page.stat_keys.active_subscriptions'),
    total_users: t('custom_dashboard_page.stat_keys.total_users'),
    today_logs: t('custom_dashboard_page.stat_keys.today_logs'),
    total: t('custom_dashboard_page.stat_keys.total'),
    expiring_soon: t('custom_dashboard_page.stat_keys.expiring_soon'),
    monthly_revenue: t('custom_dashboard_page.stat_keys.monthly_revenue'),
    active_today: t('custom_dashboard_page.stat_keys.active_today'),
    new_last_30d: t('custom_dashboard_page.stat_keys.new_last_30d'),
    period_days: t('custom_dashboard_page.stat_keys.period_days'),
}));

function cd_widgetStyle(w) {
    const layout = w.layout || { w: 4, h: 2 };
    return {
        gridColumn: `span ${layout.w || 4}`,
        gridRow: `span ${layout.h || 2}`,
    };
}

function cd_typeLabel(type) { return cd_typeLabels.value[type] || type; }
function cd_statKeyLabel(k) { return cd_statKeyLabelMap.value[k] || k.replace(/_/g, ' '); }
function cd_chartPercent(cnt, total) { return total ? Math.round((cnt / total) * 100) : 0; }
function cd_trendHeight(cnt, data) {
    const max = Math.max(...data.map(d => d.cnt), 1);
    return Math.max(5, (cnt / max) * 100);
}

async function cd_loadDashboards() {
    try {
        const { data } = await getDashboards();
        cd_dashboards.value = data || [];
        if (!cd_currentDashboardId.value && cd_dashboards.value.length) {
            const def = cd_dashboards.value.find(d => d.is_default) || cd_dashboards.value[0];
            cd_currentDashboardId.value = def.id;
            await cd_loadDashboard();
        }
    } catch (e) {
        ElMessage.error(t('custom_dashboard_page.messages.load_list_failed'));
    }
}

async function cd_loadDashboard() {
    if (!cd_currentDashboardId.value) return;
    cd_loading.value = true;
    try {
        const { data } = await getDashboard(cd_currentDashboardId.value);
        cd_currentDashboard.value = data;
    } catch (e) {
        ElMessage.error(t('custom_dashboard_page.messages.load_dashboard_failed'));
    } finally {
        cd_loading.value = false;
    }
}

function cd_switchDashboard() {
    cd_loadDashboard();
}

async function cd_refreshAll() {
    ElMessage.info(t('custom_dashboard_page.messages.refreshing'));
    await cd_loadDashboard();
    ElMessage.success(t('custom_dashboard_page.messages.refresh_done'));
}

async function cd_refreshWidget(widget) {
    try {
        const { data } = await refreshWidgetData(widget.id);
        widget.data = data;
        ElMessage.success(t('custom_dashboard_page.messages.refresh_done'));
    } catch (e) {
        ElMessage.error(t('custom_dashboard_page.messages.refresh_failed'));
    }
}

// ─── 仪表盘操作 ───
function cd_openCreateDashboard() { cd_dashboardDialogRef.value?.open('create'); }
function cd_editCurrentDashboard() { cd_dashboardDialogRef.value?.open('edit', cd_currentDashboard.value); }

async function cd_setCurrentAsDefault() {
    try {
        await setDefaultDashboard(cd_currentDashboardId.value);
        ElMessage.success(t('custom_dashboard_page.messages.set_default_ok'));
        cd_loadDashboards();
    } catch (e) { ElMessage.error(t('messages.failed')); }
}

async function cd_duplicateCurrent() {
    try {
        const { data } = await duplicateDashboard(cd_currentDashboardId.value);
        ElMessage.success(t('custom_dashboard_page.messages.duplicate_ok'));
        cd_loadDashboards();
        cd_currentDashboardId.value = data.id;
        cd_loadDashboard();
    } catch (e) { ElMessage.error(t('custom_dashboard_page.messages.duplicate_failed')); }
}

function cd_deleteCurrent() {
    ElMessageBox.confirm(
        t('custom_dashboard_page.delete_confirm.dashboard_body', { name: cd_currentDashboard.value?.name }),
        t('custom_dashboard_page.delete_confirm.dashboard_title'),
        {
            confirmButtonText: t('actions.delete'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        },
    ).then(async () => {
        try {
            await deleteDashboard(cd_currentDashboardId.value);
            ElMessage.success(t('custom_dashboard_page.messages.deleted_ok'));
            cd_currentDashboardId.value = null;
            cd_currentDashboard.value = null;
            cd_loadDashboards();
        } catch (e) { ElMessage.error(t('custom_dashboard_page.messages.delete_failed')); }
    }).catch(() => {});
}

// ─── Widget 操作 ───
function cd_editWidget(widget) { cd_widgetDialogRef.value?.open('edit', widget); }
function cd_deleteWidget(widget) {
    ElMessageBox.confirm(
        t('custom_dashboard_page.delete_confirm.widget_body', { title: widget.title }),
        t('custom_dashboard_page.delete_confirm.widget_title'),
        {
            confirmButtonText: t('actions.delete'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        },
    ).then(async () => {
        try {
            await apiDeleteWidget(widget.id);
            ElMessage.success(t('custom_dashboard_page.messages.deleted_ok'));
            cd_loadDashboard();
        } catch (e) { ElMessage.error(t('custom_dashboard_page.messages.delete_failed')); }
    }).catch(() => {});
}

function cd_openWidgetLibrary() { cd_libraryRef.value?.open(); }

// ═══════════════════════════════════════════
//  Lifecycle
// ═══════════════════════════════════════════

onMounted(refreshAll);
</script>

<style scoped>
/* ── System Overview styles ── */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card {
    cursor: default;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
}
.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.quick-actions .action-btn {
    width: 100%;
    margin-bottom: 0;
}

.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.status-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.status-name {
    min-width: 80px;
}
.small-text { font-size: 11px; }
.expiring-text { color: #e6a23c; font-weight: 500; }

/* ── Custom Dashboard styles (scoped under .cd-content) ── */
.cd-content .mb-4 { margin-bottom: 16px; }

.cd-content .cd-text-lg { font-size: 16px; }
.cd-content .cd-font-medium { font-weight: 500; }
.cd-content .text-right { text-align: right; }
.cd-content .text-center { text-align: center; }
.cd-content .text-gray-400 { color: #909399; }
.cd-content .text-xs { font-size: 12px; }
.cd-content .text-red { color: #f56c6c; }
.cd-content .ml-2 { margin-left: 8px; }
.cd-content .mr-2 { margin-right: 8px; }
.cd-content .py-12 { padding: 48px 0; }
.cd-content .py-4 { padding: 16px 0; }

.cd-content .cd-widget-grid {
    display: grid;
    gap: 12px;
    grid-auto-rows: minmax(120px, auto);
}
.cd-content .cd-widget-item { min-height: 0; }
.cd-content .cd-widget-card { height: 100%; }
.cd-content .cd-widget-card :deep(.el-card__body) { overflow: auto; max-height: 360px; }
.cd-content .cd-widget-header { display: flex; justify-content: space-between; align-items: center; }
.cd-content .cd-widget-title { font-weight: 600; font-size: 14px; }
.cd-content .cd-widget-type-tag { font-size: 11px; }

.cd-content .cd-widget-stat { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cd-content .cd-stat-item { text-align: center; padding: 8px; background: #f5f7fa; border-radius: 6px; }
.cd-content .cd-stat-key { font-size: 11px; color: #909399; margin-bottom: 4px; }
.cd-content .cd-stat-val { font-size: 22px; font-weight: 700; color: #0f172a; }

.cd-content .cd-chart-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.cd-content .cd-chart-label { width: 80px; font-size: 12px; text-transform: capitalize; }
.cd-content .cd-chart-value { width: 40px; text-align: right; font-size: 12px; font-weight: 600; }
.cd-content .cd-chart-trend { display: flex; gap: 4px; align-items: flex-end; height: 120px; padding-top: 20px; }
.cd-content .cd-trend-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; }
.cd-content .cd-trend-bar { width: 100%; max-width: 24px; background: linear-gradient(to top, #0f172a, #94a3b8); border-radius: 3px 3px 0 0; min-height: 4px; }
.cd-content .cd-trend-label { font-size: 10px; color: #909399; margin-top: 4px; }

.cd-content .cd-metric-card { padding: 12px; background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border-radius: 8px; margin-bottom: 8px; }
.cd-content .cd-metric-label { font-size: 11px; color: #606266; }
.cd-content .cd-metric-value { font-size: 20px; font-weight: 700; color: #303133; }

.cd-content .cd-list-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.cd-content .cd-widget-table-wrapper :deep(.el-table) { font-size: 12px; }

.cd-content .cd-alert-item { display: flex; align-items: center; margin-bottom: 6px; }

.cd-content .cd-widget-iframe .cd-iframe-content { width: 100%; height: 200px; border: none; }
.cd-content .cd-widget-html { font-size: 13px; }

.cd-content .cd-nav-dots { display: flex; justify-content: center; gap: 24px; margin-top: 20px; padding: 12px; }
.cd-content .cd-nav-dot-wrapper { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.cd-content .cd-nav-dot { width: 12px; height: 12px; border-radius: 50%; background: #dcdfe6; cursor: pointer; transition: all 0.2s; }
.cd-content .cd-nav-dot.active { background: #0f172a; transform: scale(1.3); }
.cd-content .cd-nav-dot-title { font-size: 11px; color: #909399; white-space: nowrap; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    .page-header :deep(.el-date-picker) {
        width: 100% !important;
        max-width: 100%;
    }
    .stat-cards-row .el-col {
        margin-bottom: 12px;
    }
    .stat-value {
        font-size: 24px;
    }
    .stat-icon {
        display: none;
    }
    .dashboard-cols .el-col {
        margin-bottom: 16px;
    }
    .quick-actions .action-btn {
        font-size: 12px;
        padding: 8px 4px;
    }
    .quick-actions .action-btn .el-icon {
        margin-right: 2px;
    }
    .status-row {
        flex-wrap: wrap;
        gap: 6px;
    }
    .status-name {
        min-width: auto;
    }
}
</style>
