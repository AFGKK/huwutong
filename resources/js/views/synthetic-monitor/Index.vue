<template>
    <div class="synthetic-page">
        <div class="page-header">
            <div>
                <h2>{{ t('synthetic_monitor_page.title') }}</h2>
                <p class="text-muted">{{ t('synthetic_monitor_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="seedRegions" size="small">{{ t('synthetic_monitor_page.seed_regions') }}</el-button>
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('sla_probes_page.refresh') }}</el-button>
                <el-button type="primary" @click="showCreate = true" :icon="Plus">{{ t('synthetic_monitor_page.create_btn') }}</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col v-for="card in overviewCards" :key="card.key" :xs="12" :sm="6" :md="card.md">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">{{ card.label }}</div>
                    <div class="metric-value" :class="card.valueClass">{{ card.value }}</div>
                    <div v-if="card.sub" class="metric-sub">{{ card.sub }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 区域对比卡片 -->
        <el-row :gutter="16" class="mb-4" v-if="dash.regions?.length">
            <el-col v-for="r in dash.regions" :key="r.code" :xs="24" :sm="8">
                <el-card shadow="hover" :class="['region-card', r.avg_latency_ms < 500 ? '' : (r.avg_latency_ms < 2000 ? 'border-warning' : 'border-danger')]">
                    <div class="region-header">
                        <span class="region-name">{{ r.name }}</span>
                        <el-tag :type="r.down_probes > 0 ? 'danger' : 'success'" size="small" effect="dark">
                            {{ t('synthetic_monitor_page.region.online', { up: r.up_probes, total: r.total_probes }) }}
                        </el-tag>
                    </div>
                    <div class="region-stats">
                        <div class="region-stat"><span class="stat-label">{{ t('synthetic_monitor_page.region.availability') }}</span><span :class="r.avg_latency_ms < 500 ? 'success' : 'danger'">{{ emDash }}%</span></div>
                        <div class="region-stat"><span class="stat-label">{{ t('synthetic_monitor_page.region.latency') }}</span><span :class="r.avg_latency_ms < 500 ? 'success' : (r.avg_latency_ms < 2000 ? 'warning' : 'danger')">{{ r.avg_latency_ms || emDash }}ms</span></div>
                        <div class="region-stat"><span class="stat-label">{{ t('synthetic_monitor_page.region.location') }}</span><span>{{ Array.isArray(r.locations) ? r.locations.join(', ') : (r.locations || emDash) }}</span></div>
                    </div>
                    <el-button size="small" style="margin-top:8px" @click="viewRegion(r.code)">{{ t('actions.view_details') }}</el-button>
                </el-card>
            </el-col>
        </el-row>

        <!-- 拨测列表 -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Monitor /></el-icon> {{ t('synthetic_monitor_page.probes.list_title') }}</span>
                    <el-select v-model="filterRegion" :placeholder="t('synthetic_monitor_page.probes.region_filter_ph')" clearable size="small" style="width:130px" @change="loadProbes">
                        <el-option v-for="r in dash.regions" :key="r.code" :label="r.name" :value="r.code" />
                    </el-select>
                </div>
            </template>
            <el-table :data="probes" stripe v-loading="probeLoading" size="small">
                <el-table-column prop="name" :label="t('sla_probes_page.col_name')" min-width="140" />
                <el-table-column prop="url" :label="t('sla_probes_page.col_url')" min-width="200" show-overflow-tooltip />
                <el-table-column prop="method" :label="t('sla_probes_page.col_method')" width="70" />
                <el-table-column :label="t('synthetic_monitor_page.cols.region')" width="100"><template #default="{row}"><el-tag size="small">{{ row.region_code }}</el-tag></template></el-table-column>
                <el-table-column :label="t('synthetic_monitor_page.cols.status')" width="80"><template #default="{row}"><el-tag :type="row.last_status === 'up' ? 'success' : (row.last_status === 'down' ? 'danger' : 'info')" size="small">{{ probeStatusLabel(row.last_status) }}</el-tag></template></el-table-column>
                <el-table-column :label="t('synthetic_monitor_page.cols.latency')" width="80"><template #default="{row}">{{ row.last_response_time_ms ? row.last_response_time_ms + 'ms' : emDash }}</template></el-table-column>
                <el-table-column :label="t('synthetic_monitor_page.cols.interval')" width="70" prop="interval_minutes" />
                <el-table-column :label="t('synthetic_monitor_page.cols.active')" width="60"><template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template></el-table-column>
                <el-table-column :label="t('synthetic_monitor_page.cols.results_count')" width="60" prop="results_count" />
                <el-table-column :label="t('synthetic_monitor_page.cols.last_probed')" width="140"><template #default="{row}">{{ row.last_probed_at ? fmtTime(row.last_probed_at) : emDash }}</template></el-table-column>
            </el-table>
            <el-empty v-if="!probes.length && !probeLoading" :description="t('synthetic_monitor_page.probes.empty')" />
        </el-card>

        <!-- 新建拨测对话框 -->
        <el-dialog v-model="showCreate" :title="t('synthetic_monitor_page.create_dialog.title')" width="550px">
            <el-form :model="createForm" label-width="120px">
                <el-form-item :label="t('sla_probes_page.form_name')" prop="name" :rules="[{required:true}]"><el-input v-model="createForm.name" :placeholder="t('synthetic_monitor_page.placeholders.name')" /></el-form-item>
                <el-form-item :label="t('sla_probes_page.form_url')" prop="url" :rules="[{required:true}]"><el-input v-model="createForm.url" :placeholder="t('synthetic_monitor_page.placeholders.url')" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('sla_probes_page.form_method')" prop="method" :rules="[{required:true}]"><el-select v-model="createForm.method" style="width:100%"><el-option label="GET" value="GET" /><el-option label="POST" value="POST" /><el-option label="PUT" value="PUT" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('synthetic_monitor_page.create_dialog.timeout')"><el-input-number v-model="createForm.timeout_seconds" :min="5" :max="120" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t('synthetic_monitor_page.create_dialog.regions')"><el-checkbox-group v-model="createForm.regions">
                    <el-checkbox v-for="opt in regionOptions" :key="opt.value" :label="opt.value">{{ opt.label }}</el-checkbox>
                </el-checkbox-group></el-form-item>
                <el-form-item :label="t('sla_probes_page.form_interval')"><el-input-number v-model="createForm.interval_minutes" :min="1" :max="1440" style="width:100%" /></el-form-item>
                <el-form-item :label="t('synthetic_monitor_page.create_dialog.expected_status')"><el-input-number v-model="createForm.expected_status" :min="100" :max="599" style="width:100%" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitCreate" :loading="saving">{{ t('synthetic_monitor_page.create_dialog.submit') }}</el-button></template>
        </el-dialog>

        <!-- 区域详情对话框 -->
        <el-dialog v-model="showRegionDetail" :title="t('synthetic_monitor_page.region_detail.title', { name: regionDetail?.region?.name || '' })" width="800px" top="5vh">
            <template v-if="regionDetail">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('synthetic_monitor_page.region.availability') }}</div><div class="metric-value" :class="regionDetail.availability >= 99.9 ? 'success' : 'danger'">{{ regionDetail.availability }}%</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('synthetic_monitor_page.cols.avg_latency') }}</div><div class="metric-value" :class="regionDetail.avg_latency_ms < 500 ? 'success' : 'warning'">{{ regionDetail.avg_latency_ms }}ms</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">P95</div><div class="metric-value">{{ regionDetail.p95_latency_ms }}ms</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">P99</div><div class="metric-value">{{ regionDetail.p99_latency_ms }}ms</div></el-card></el-col>
                </el-row>
                <el-table :data="regionDetail.timeline" stripe size="small" max-height="300" v-if="regionDetail.timeline?.length">
                    <el-table-column prop="time" :label="t('synthetic_monitor_page.cols.time')" width="130" />
                    <el-table-column :label="t('synthetic_monitor_page.region.availability')" width="90"><template #default="{row}"><el-tag :type="row.availability >= 99 ? 'success' : (row.availability >= 90 ? 'warning' : 'danger')" size="small">{{ row.availability }}%</el-tag></template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.latency')" width="100"><template #default="{row}">{{ row.avg_latency }}ms</template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.checks')" width="70" prop="total_checks" />
                </el-table>
                <el-empty v-else :description="t('synthetic_monitor_page.region_detail.empty')" />
            </template>
        </el-dialog>

        <!-- SLA 报告 -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><DataBoard /></el-icon> {{ t('synthetic_monitor_page.sla.title') }}</span>
                    <div>
                        <el-radio-group v-model="slaDays" size="small" @change="loadSla">
                            <el-radio-button v-for="opt in slaDayOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio-button>
                        </el-radio-group>
                        <el-button size="small" style="margin-left:8px" @click="syncStatusPage" :loading="syncing">{{ t('synthetic_monitor_page.sla.sync_status_page') }}</el-button>
                    </div>
                </div>
            </template>
            <div v-loading="slaLoading">
                <el-table :data="slaData.regions" stripe size="small" v-if="slaData.regions?.length">
                    <el-table-column prop="region_name" :label="t('synthetic_monitor_page.cols.region')" min-width="120" />
                    <el-table-column :label="t('synthetic_monitor_page.region.availability')" width="120"><template #default="{row}">
                        <el-tag :type="row.sla_met ? 'success' : 'danger'" size="large">{{ row.availability }}%</el-tag>
                    </template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.sla_target')" width="100"><template #default="{row}">{{ row.sla_target }}%</template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.met')" width="70"><template #default="{row}"><el-icon :color="row.sla_met ? '#67c23a' : '#f56c6c'">{{ row.sla_met ? 'CircleCheck' : 'CircleClose' }}</el-icon></template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.latency')" width="100"><template #default="{row}">{{ row.avg_latency_ms }}ms</template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.latency_level')" width="100"><template #default="{row}">
                        <el-tag :type="row.latency_level === 'good' ? 'success' : (row.latency_level === 'warning' ? 'warning' : 'danger')" size="small">{{ latencyLevelLabel(row.latency_level) }}</el-tag>
                    </template></el-table-column>
                    <el-table-column :label="t('synthetic_monitor_page.cols.checks')" width="70" prop="total_checks" />
                    <el-table-column :label="t('synthetic_monitor_page.cols.failed')" width="70" prop="failed_checks" />
                </el-table>
                <div v-if="slaData.regions?.length" class="sla-summary">
                    <span>{{ t('synthetic_monitor_page.sla.overall_availability') }}: <b :class="slaData.overall_sla_met ? 'success' : 'danger'">{{ slaData.overall_availability }}%</b></span>
                    <span style="margin-left:16px">{{ t('synthetic_monitor_page.sla.sla_target') }}: {{ slaData.sla_target }}%</span>
                    <span style="margin-left:16px">{{ t('synthetic_monitor_page.sla.period') }}: {{ slaData.period_label }}</span>
                </div>
                <el-empty v-else :description="t('synthetic_monitor_page.sla.empty')" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Plus, Monitor, CircleCheck, DataBoard } from '@element-plus/icons-vue';
import syntheticMonitorApi from '@/api/syntheticMonitor';

const { t, locale } = useI18n();

const emDash = '—';

const loading = ref(false);
const saving = ref(false);
const probeLoading = ref(false);
const syncing = ref(false);
const slaLoading = ref(false);
const dash = reactive({ total_probes: 0, active_probes: 0, overall_availability: 0, global_avg_latency_ms: 0, total_results_24h: 0, regions: [] });
const probes = ref([]);
const slaData = reactive({ regions: [], overall_availability: 0, sla_target: 99.9, overall_sla_met: false, period_label: '' });
const filterRegion = ref('');
const slaDays = ref(30);

const overviewCardMeta = [
    { key: 'total_probes', labelKey: 'synthetic_monitor_page.stats.total_probes', md: 4, subKey: 'active', format: (d) => d.total_probes },
    { key: 'availability', labelKey: 'synthetic_monitor_page.stats.global_availability', md: 5, format: (d) => `${d.overall_availability}%`, valueClass: (d) => d.overall_availability >= 99.9 ? 'success' : (d.overall_availability >= 99 ? 'warning' : 'danger') },
    { key: 'latency', labelKey: 'synthetic_monitor_page.stats.global_latency', md: 5, format: (d) => `${d.global_avg_latency_ms}ms`, valueClass: (d) => d.global_avg_latency_ms < 500 ? 'success' : (d.global_avg_latency_ms < 2000 ? 'warning' : 'danger') },
    { key: 'checks_24h', labelKey: 'synthetic_monitor_page.stats.checks_24h', md: 5, format: (d) => d.total_results_24h },
    { key: 'region_count', labelKey: 'synthetic_monitor_page.stats.region_count', md: 5, format: (d) => d.regions?.length || 0 },
];

const overviewCards = computed(() => overviewCardMeta.map((m) => {
    const valueClass = m.valueClass ? m.valueClass(dash) : '';
    const card = {
        key: m.key,
        md: m.md,
        label: t(m.labelKey),
        value: m.format(dash),
        valueClass,
        sub: null,
    };
    if (m.subKey === 'active') {
        card.sub = t('synthetic_monitor_page.stats.active_sub', { count: dash.active_probes });
    }
    return card;
}));

const regionOptionMeta = [
    { value: 'ap-asia', labelKey: 'synthetic_monitor_page.regions.ap_asia' },
    { value: 'eu-europe', labelKey: 'synthetic_monitor_page.regions.eu_europe' },
    { value: 'us-north-america', labelKey: 'synthetic_monitor_page.regions.us_north_america' },
];

const regionOptions = computed(() => regionOptionMeta.map((m) => ({
    value: m.value,
    label: t(m.labelKey),
})));

const slaDayMeta = [
    { value: 7, labelKey: 'synthetic_monitor_page.sla.days_7' },
    { value: 30, labelKey: 'synthetic_monitor_page.sla.days_30' },
    { value: 90, labelKey: 'synthetic_monitor_page.sla.days_90' },
];

const slaDayOptions = computed(() => slaDayMeta.map((m) => ({
    value: m.value,
    label: t(m.labelKey),
})));

const latencyLevelKeys = {
    good: 'synthetic_monitor_page.latency_level.good',
    warning: 'synthetic_monitor_page.latency_level.warning',
    critical: 'synthetic_monitor_page.latency_level.critical',
};

function latencyLevelLabel(level) {
    return t(latencyLevelKeys[level] || level);
}

function probeStatusLabel(status) {
    if (status === 'up') return t('synthetic_monitor_page.status.up');
    if (status === 'down') return t('synthetic_monitor_page.status.down');
    return t('synthetic_monitor_page.status.not_probed');
}

// Dialogs
const showCreate = ref(false);
const showRegionDetail = ref(false);
const regionDetail = ref(null);
const createForm = reactive({
    name: '', url: '', method: 'GET', regions: ['ap-asia', 'eu-europe', 'us-north-america'],
    timeout_seconds: 30, interval_minutes: 5, expected_status: 200,
});

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadDashboard(), loadProbes(), loadSla()]); } finally { loading.value = false; }
}
async function loadDashboard() {
    try { const r = await syntheticMonitorApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadProbes() {
    probeLoading.value = true;
    try {
        const params = { per_page: 50 };
        if (filterRegion.value) params.region_code = filterRegion.value;
        const r = await syntheticMonitorApi.listProbes(params); probes.value = r.data?.data?.items || [];
    } finally { probeLoading.value = false; }
}
async function loadSla() {
    slaLoading.value = true;
    try { const r = await syntheticMonitorApi.slaReport({ days: slaDays.value }); Object.assign(slaData, r.data?.data || {}); } finally { slaLoading.value = false; }
}
async function seedRegions() {
    await syntheticMonitorApi.seedRegions(); ElMessage.success(t('synthetic_monitor_page.messages.regions_seeded')); loadDashboard();
}
async function submitCreate() {
    saving.value = true;
    try {
        await syntheticMonitorApi.createProbe(createForm);
        ElMessage.success(t('synthetic_monitor_page.messages.probe_created'));
        showCreate.value = false;
        loadAll();
    } catch {
        ElMessage.error(t('sla_probes_page.save_fail'));
    } finally { saving.value = false; }
}
async function viewRegion(code) {
    try { const r = await syntheticMonitorApi.regionStats(code, { hours: 24 }); regionDetail.value = r.data?.data; showRegionDetail.value = true; } catch { ElMessage.error(t('messages.load_failed')); }
}
async function syncStatusPage() {
    syncing.value = true;
    try { await syntheticMonitorApi.syncToStatusPage(); ElMessage.success(t('synthetic_monitor_page.messages.sync_ok')); } catch { ElMessage.error(t('synthetic_monitor_page.messages.sync_failed')); } finally { syncing.value = false; }
}
function fmtTime(tVal) {
    if (!tVal) return emDash;
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(tVal).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.synthetic-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.metric-card .metric-sub { font-size: 11px; color: #c0c4cc; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.region-card { margin-bottom: 8px; }
.region-card.border-warning { border-left: 3px solid #e6a23c; }
.region-card.border-danger { border-left: 3px solid #f56c6c; }
.region-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.region-name { font-size: 16px; font-weight: 600; }
.region-stats { display: flex; gap: 16px; flex-wrap: wrap; }
.region-stat { display: flex; flex-direction: column; gap: 2px; }
.stat-label { font-size: 11px; color: #909399; }
.sla-summary { margin-top: 12px; padding: 12px; background: #f5f7fa; border-radius: 4px; font-size: 14px; }
</style>
