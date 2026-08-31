<template>
    <div class="drift-container">
        <el-page-header :content="t(`${P}.title`)" @back="$router.push('/admin/dashboard')" />

        <el-alert :title="t(`${P}.alert_info`)" type="info" show-icon :closable="false" class="alert-info" />

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value">{{ dashboard.total_devices }}</div>
                        <div class="stat-label">{{ t(`${D}.stat_total`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #e6a23c;">{{ dashboard.recent_drifts_30d }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.recent_drifts_30d`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #67c23a;">{{ dashboard.auto_accepted_30d }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.auto_accepted_30d`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #f56c6c;">{{ dashboard.pending_drifts?.length || 0 }}</div>
                        <div class="stat-label">{{ t(`${P}.stats.pending_drifts`) }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- 待处理漂移 -->
            <el-tab-pane :label="t(`${P}.tabs.pending`)" name="pending">
                <el-card>
                    <template #header><span>{{ t(`${P}.pending.card_title`) }}</span></template>
                    <el-table :data="pendingDrifts" stripe v-loading="loadingPending" size="small">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t(`${P}.cols.device`)" min-width="120">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="'never'" @click="showDeviceDetail(row.device_id)">
                                    {{ row.device?.platform || t(`${D}.unknown`) }}
                                </el-link>
                                <div class="text-muted">#{{ row.device_id }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.drift_type`)" width="100">
                            <template #default="{ row }">
                                <el-tag :type="driftTagType(row.drift_type)" size="small">
                                    {{ driftLabel(row.drift_type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.changed_components`)" width="120">
                            <template #default="{ row }">
                                {{ row.changed_components }}/{{ row.total_components }}
                                <el-progress
                                    :percentage="(row.changed_components / row.total_components) * 100"
                                    :status="row.changed_components >= 3 ? 'exception' : 'warning'"
                                    :stroke-width="10"
                                    style="width:80px;display:inline-block;margin-left:8px"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${D}.col_fingerprint`)" min-width="160">
                            <template #default="{ row }">
                                <code class="small-text">{{ row.fingerprint?.substring(0, 20) }}...</code>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.cols.source`)" width="80">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ row.source }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="t(`${D}.col_time`)" width="150" />
                        <el-table-column :label="t(`${D}.col_actions`)" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="handleAccept(row)">{{ t(`${P}.actions.accept_drift`) }}</el-button>
                                <el-button size="small" @click="showDeviceDetail(row.device_id)">{{ t('actions.view') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!loadingPending && !pendingDrifts.length" :description="t(`${P}.pending.empty`)" :image-size="60" />
                </el-card>
            </el-tab-pane>

            <!-- 漂移分布 -->
            <el-tab-pane :label="t(`${P}.tabs.stats`)" name="stats">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t(`${P}.stats_tab.type_distribution`) }}</span></template>
                            <div ref="pieChartRef" style="height: 300px;"></div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t(`${P}.stats_tab.legend_title`) }}</span></template>
                            <el-timeline>
                                <el-timeline-item
                                    v-for="item in driftLegendItems"
                                    :key="item.key"
                                    :timestamp="item.key"
                                    :type="item.type"
                                >{{ item.label }}</el-timeline-item>
                            </el-timeline>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <!-- 设备详情抽屉 -->
        <el-drawer v-model="drawerVisible" :title="t(`${P}.drawer.title`, { id: deviceId || '' })" size="600px">
            <div v-loading="loadingDetail">
                <template v-if="deviceSummary">
                    <el-descriptions :column="1" border size="small">
                        <el-descriptions-item :label="t(`${P}.drawer.current_fingerprint`)">
                            <code>{{ deviceSummary.fingerprint?.substring(0, 30) }}...</code>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t(`${D}.label_platform`)">{{ deviceSummary.platform || '-' }}</el-descriptions-item>
                        <el-descriptions-item :label="t(`${P}.drawer.total_snapshots`)">{{ deviceSummary.total_snapshots }}</el-descriptions-item>
                        <el-descriptions-item :label="t(`${P}.drawer.drift_events`)">{{ deviceSummary.drift_events }}</el-descriptions-item>
                    </el-descriptions>

                    <h4 class="mt-4">{{ t(`${P}.drawer.baseline_title`) }}</h4>
                    <div v-if="deviceSummary.baseline" class="baseline-card">
                        <div><strong>{{ t(`${P}.drawer.baseline_fingerprint`) }}：</strong><code>{{ deviceSummary.baseline.fingerprint?.substring(0, 30) }}...</code></div>
                        <div><strong>{{ t(`${P}.drawer.baseline_version`) }}：</strong>V{{ deviceSummary.baseline.fingerprint_version }}</div>
                        <div><strong>{{ t(`${P}.drawer.baseline_recorded_at`) }}：</strong>{{ deviceSummary.baseline.created_at }}</div>
                    </div>
                    <el-empty v-else :description="t(`${P}.drawer.no_baseline`)" :image-size="50" />

                    <h4 class="mt-4">{{ t(`${P}.drawer.recent_history`) }}</h4>
                    <el-timeline>
                        <el-timeline-item
                            v-for="h in deviceSummary.recent_history"
                            :key="h.id"
                            :type="historyType(h)"
                            :timestamp="h.created_at"
                            size="small"
                        >
                            <div>
                                <el-tag :type="driftTagType(h.drift_type)" size="small">{{ driftLabel(h.drift_type) }}</el-tag>
                                <span v-if="h.changed_components > 0" class="ml-2">
                                    {{ t(`${P}.drawer.changed_components`, { changed: h.changed_components, total: h.total_components }) }}
                                </span>
                                <span v-if="h.is_baseline" class="ml-2">
                                    <el-tag type="success" size="small">{{ t(`${P}.drawer.baseline_tag`) }}</el-tag>
                                </span>
                            </div>
                            <div v-if="h.components" class="component-detail mt-1">
                                <div v-for="(comp, key) in h.components" :key="key" class="comp-row">
                                    <span class="comp-key">{{ key }}:</span>
                                    <span v-if="comp.matched !== undefined" :class="comp.matched ? 'comp-match' : 'comp-diff'">
                                        {{ comp.matched ? t(`${P}.drawer.matched`) : t(`${P}.drawer.unmatched`) }}
                                    </span>
                                    <span class="comp-val text-muted">{{ comp.new?.substring(0, 16) || '—' }}</span>
                                </div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </template>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import fingerprintDrift from '@/api/fingerprintDrift';
import * as echarts from 'echarts';

const P = 'fingerprint_drift_page';
const D = 'devices_page';
const { t, locale } = useI18n();

const DRIFT_TYPE_KEYS = ['initial', 'gradual', 'partial', 'major', 'manual'];
const DRIFT_LEGEND_KEYS = ['initial', 'gradual', 'partial', 'major', 'manual'];
const DRIFT_LEGEND_TYPES = { initial: 'info', gradual: 'success', partial: 'warning', major: 'danger', manual: 'primary' };
const DRIFT_COLOR_MAP = { initial: '#909399', gradual: '#67c23a', partial: '#e6a23c', major: '#f56c6c', manual: '#0f172a' };

const driftLegendItems = computed(() => DRIFT_LEGEND_KEYS.map((key) => ({
    key,
    type: DRIFT_LEGEND_TYPES[key],
    label: t(`${P}.drift_legend.${key}`),
})));

const activeTab = ref('pending');
const loadingPending = ref(false);
const loadingDetail = ref(false);
const drawerVisible = ref(false);
const deviceId = ref(null);
const pieChartRef = ref(null);
let pieChartInstance = null;

const dashboard = reactive({
    total_devices: 0,
    recent_drifts_30d: 0,
    auto_accepted_30d: 0,
    pending_drifts: [],
    drift_by_type: {},
});
const pendingDrifts = ref([]);
const deviceSummary = ref(null);

function driftTagType(type) {
    const map = { initial: 'info', gradual: 'success', partial: 'warning', major: 'danger', manual: 'primary' };
    return map[type] || 'info';
}

function driftLabel(type) {
    return DRIFT_TYPE_KEYS.includes(type) ? t(`${P}.drift_types.${type}`) : type;
}

function historyType(h) {
    if (h.is_baseline) return 'primary';
    return driftTagType(h.drift_type);
}

async function loadDashboard() {
    try {
        const res = await fingerprintDrift.dashboard();
        Object.assign(dashboard, res.data.data);
    } catch {}
}

async function loadPending() {
    loadingPending.value = true;
    try {
        const res = await fingerprintDrift.pending();
        pendingDrifts.value = res.data.data || [];
    } catch {} finally {
        loadingPending.value = false;
    }
}

async function showDeviceDetail(id) {
    deviceId.value = id;
    drawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await fingerprintDrift.deviceHistory(id);
        deviceSummary.value = res.data.data;
    } catch {
        ElMessage.error(t(`${P}.messages.history_load_failed`));
    } finally {
        loadingDetail.value = false;
    }
}

async function handleAccept(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.accept_msg`, { deviceId: row.device_id }),
            t(`${P}.confirm.accept_title`),
            { confirmButtonText: t(`${P}.confirm.accept_btn`), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
    } catch { return; }

    try {
        await fingerprintDrift.acceptDrift(row.id, { notes: t(`${P}.messages.admin_manual_note`) });
        ElMessage.success(t(`${P}.messages.drift_accepted`));
        loadPending();
        loadDashboard();
    } catch {
        ElMessage.error(t('messages.failed'));
    }
}

function renderPieChart() {
    if (!pieChartRef.value) return;
    if (!pieChartInstance) {
        pieChartInstance = echarts.init(pieChartRef.value);
    }

    const data = Object.entries(dashboard.drift_by_type || {}).map(([type, count]) => ({
        name: driftLabel(type),
        value: count,
        type,
    }));

    if (!data.length) {
        data.push({ name: t('messages.no_data'), value: 1, type: null });
    }

    pieChartInstance.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        series: [{
            type: 'pie',
            radius: ['30%', '60%'],
            data,
            label: { show: true, formatter: '{b}\n{d}%' },
            itemStyle: {
                color: (p) => (p.data.type ? DRIFT_COLOR_MAP[p.data.type] : '#909399'),
                borderRadius: 4,
            },
        }],
    });
}

watch(activeTab, (tab) => {
    if (tab === 'stats') {
        nextTick(() => renderPieChart());
    }
});

watch(locale, () => {
    if (activeTab.value === 'stats') {
        nextTick(() => renderPieChart());
    }
});

onMounted(() => {
    loadDashboard();
    loadPending();
});
</script>

<style scoped>
.drift-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 12px; }
.ml-2 { margin-left: 8px; }
.mt-1 { margin-top: 4px; }
.mt-4 { margin-top: 16px; }
.small-text { font-size: 12px; font-family: monospace; }
.baseline-card { background: #f5f7fa; padding: 12px; border-radius: 6px; font-size: 13px; }
.component-detail { font-size: 12px; }
.comp-row { display: flex; gap: 6px; align-items: center; margin: 2px 0; }
.comp-key { font-weight: 600; min-width: 90px; }
.comp-match { color: #67c23a; }
.comp-diff { color: #f56c6c; }
.comp-val { font-family: monospace; }
</style>
