<template>
    <div class="synthetic-page">
        <div class="page-header">
            <div>
                <h2>合成监控 · 多区域拨测</h2>
                <p class="text-muted">定时从亚太/欧洲/北美模拟激活验证请求 · 延迟+可用率 · SLA 计算 · 状态页同步</p>
            </div>
            <div class="header-actions">
                <el-button @click="seedRegions" size="small">初始化区域</el-button>
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="showCreate = true" :icon="Plus">新建拨测</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" :md="4"><el-card shadow="hover" class="metric-card"><div class="metric-label">拨测任务</div><div class="metric-value">{{ dash.total_probes }}</div><div class="metric-sub">活跃 {{ dash.active_probes }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="5"><el-card shadow="hover" class="metric-card"><div class="metric-label">全球可用率</div><div class="metric-value" :class="dash.overall_availability >= 99.9 ? 'success' : (dash.overall_availability >= 99 ? 'warning' : 'danger')">{{ dash.overall_availability }}%</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="5"><el-card shadow="hover" class="metric-card"><div class="metric-label">全球延迟</div><div class="metric-value" :class="dash.global_avg_latency_ms < 500 ? 'success' : (dash.global_avg_latency_ms < 2000 ? 'warning' : 'danger')">{{ dash.global_avg_latency_ms }}ms</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="5"><el-card shadow="hover" class="metric-card"><div class="metric-label">24h 检测数</div><div class="metric-value">{{ dash.total_results_24h }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="5"><el-card shadow="hover" class="metric-card"><div class="metric-label">区域数</div><div class="metric-value">{{ dash.regions?.length || 0 }}</div></el-card></el-col>
        </el-row>

        <!-- 区域对比卡片 -->
        <el-row :gutter="16" class="mb-4" v-if="dash.regions?.length">
            <el-col v-for="r in dash.regions" :key="r.code" :xs="24" :sm="8">
                <el-card shadow="hover" :class="['region-card', r.avg_latency_ms < 500 ? '' : (r.avg_latency_ms < 2000 ? 'border-warning' : 'border-danger')]">
                    <div class="region-header">
                        <span class="region-name">{{ r.name }}</span>
                        <el-tag :type="r.down_probes > 0 ? 'danger' : 'success'" size="small" effect="dark">
                            {{ r.up_probes }}/{{ r.total_probes }} 在线
                        </el-tag>
                    </div>
                    <div class="region-stats">
                        <div class="region-stat"><span class="stat-label">可用率</span><span :class="r.avg_latency_ms < 500 ? 'success' : 'danger'">{{ r.avg_latency_ms ? '—' : '—' }}%</span></div>
                        <div class="region-stat"><span class="stat-label">延迟</span><span :class="r.avg_latency_ms < 500 ? 'success' : (r.avg_latency_ms < 2000 ? 'warning' : 'danger')">{{ r.avg_latency_ms || '—' }}ms</span></div>
                        <div class="region-stat"><span class="stat-label">位置</span><span>{{ Array.isArray(r.locations) ? r.locations.join(', ') : (r.locations || '—') }}</span></div>
                    </div>
                    <el-button size="small" style="margin-top:8px" @click="viewRegion(r.code)">查看详情</el-button>
                </el-card>
            </el-col>
        </el-row>

        <!-- 拨测列表 -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Monitor /></el-icon> 拨测任务列表</span>
                    <el-select v-model="filterRegion" placeholder="区域" clearable size="small" style="width:130px" @change="loadProbes">
                        <el-option v-for="r in dash.regions" :key="r.code" :label="r.name" :value="r.code" />
                    </el-select>
                </div>
            </template>
            <el-table :data="probes" stripe v-loading="probeLoading" size="small">
                <el-table-column prop="name" label="名称" min-width="140" />
                <el-table-column prop="url" label="URL" min-width="200" show-overflow-tooltip />
                <el-table-column prop="method" label="方法" width="70" />
                <el-table-column label="区域" width="100"><template #default="{row}"><el-tag size="small">{{ row.region_code }}</el-tag></template></el-table-column>
                <el-table-column label="状态" width="80"><template #default="{row}"><el-tag :type="row.last_status === 'up' ? 'success' : (row.last_status === 'down' ? 'danger' : 'info')" size="small">{{ row.last_status || '未探测' }}</el-tag></template></el-table-column>
                <el-table-column label="延迟" width="80"><template #default="{row}">{{ row.last_response_time_ms ? row.last_response_time_ms + 'ms' : '—' }}</template></el-table-column>
                <el-table-column label="间隔" width="70" prop="interval_minutes" />
                <el-table-column label="活跃" width="60"><template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template></el-table-column>
                <el-table-column label="结果数" width="60" prop="results_count" />
                <el-table-column label="上次探测" width="140"><template #default="{row}">{{ row.last_probed_at ? fmtTime(row.last_probed_at) : '—' }}</template></el-table-column>
            </el-table>
            <el-empty v-if="!probes.length && !probeLoading" description="暂无拨测任务，点击「新建拨测」创建" />
        </el-card>

        <!-- 新建拨测对话框 -->
        <el-dialog v-model="showCreate" title="新建多区域拨测" width="550px">
            <el-form :model="createForm" label-width="120px">
                <el-form-item label="名称" prop="name" :rules="[{required:true}]"><el-input v-model="createForm.name" placeholder="例如: 亚太区激活验证" /></el-form-item>
                <el-form-item label="URL" prop="url" :rules="[{required:true}]"><el-input v-model="createForm.url" placeholder="https://api.huwutong.com/api/license/activate" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="方法" prop="method" :rules="[{required:true}]"><el-select v-model="createForm.method" style="width:100%"><el-option label="GET" value="GET" /><el-option label="POST" value="POST" /><el-option label="PUT" value="PUT" /></el-select></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="超时(s)"><el-input-number v-model="createForm.timeout_seconds" :min="5" :max="120" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="拨测区域"><el-checkbox-group v-model="createForm.regions">
                    <el-checkbox label="ap-asia">亚太区</el-checkbox>
                    <el-checkbox label="eu-europe">欧洲区</el-checkbox>
                    <el-checkbox label="us-north-america">北美区</el-checkbox>
                </el-checkbox-group></el-form-item>
                <el-form-item label="间隔(分钟)"><el-input-number v-model="createForm.interval_minutes" :min="1" :max="1440" style="width:100%" /></el-form-item>
                <el-form-item label="期望状态码"><el-input-number v-model="createForm.expected_status" :min="100" :max="599" style="width:100%" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreate = false">取消</el-button><el-button type="primary" @click="submitCreate" :loading="saving">创建并拨测</el-button></template>
        </el-dialog>

        <!-- 区域详情对话框 -->
        <el-dialog v-model="showRegionDetail" :title="'区域详情: ' + (regionDetail?.region?.name || '')" width="800px" top="5vh">
            <template v-if="regionDetail">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">可用率</div><div class="metric-value" :class="regionDetail.availability >= 99.9 ? 'success' : 'danger'">{{ regionDetail.availability }}%</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">平均延迟</div><div class="metric-value" :class="regionDetail.avg_latency_ms < 500 ? 'success' : 'warning'">{{ regionDetail.avg_latency_ms }}ms</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">P95</div><div class="metric-value">{{ regionDetail.p95_latency_ms }}ms</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">P99</div><div class="metric-value">{{ regionDetail.p99_latency_ms }}ms</div></el-card></el-col>
                </el-row>
                <el-table :data="regionDetail.timeline" stripe size="small" max-height="300" v-if="regionDetail.timeline?.length">
                    <el-table-column prop="time" label="时间" width="130" />
                    <el-table-column label="可用率" width="90"><template #default="{row}"><el-tag :type="row.availability >= 99 ? 'success' : (row.availability >= 90 ? 'warning' : 'danger')" size="small">{{ row.availability }}%</el-tag></template></el-table-column>
                    <el-table-column label="延迟" width="100"><template #default="{row}">{{ row.avg_latency }}ms</template></el-table-column>
                    <el-table-column label="检测数" width="70" prop="total_checks" />
                </el-table>
                <el-empty v-else description="暂无拨测数据" />
            </template>
        </el-dialog>

        <!-- SLA 报告 -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><DataBoard /></el-icon> SLA 报告</span>
                    <div>
                        <el-radio-group v-model="slaDays" size="small" @change="loadSla">
                            <el-radio-button :value="7">7天</el-radio-button>
                            <el-radio-button :value="30">30天</el-radio-button>
                            <el-radio-button :value="90">90天</el-radio-button>
                        </el-radio-group>
                        <el-button size="small" style="margin-left:8px" @click="syncStatusPage" :loading="syncing">同步状态页</el-button>
                    </div>
                </div>
            </template>
            <div v-loading="slaLoading">
                <el-table :data="slaData.regions" stripe size="small" v-if="slaData.regions?.length">
                    <el-table-column prop="region_name" label="区域" min-width="120" />
                    <el-table-column label="可用率" width="120"><template #default="{row}">
                        <el-tag :type="row.sla_met ? 'success' : 'danger'" size="large">{{ row.availability }}%</el-tag>
                    </template></el-table-column>
                    <el-table-column label="SLA 目标" width="100"><template #default="{row}">{{ row.sla_target }}%</template></el-table-column>
                    <el-table-column label="达标" width="70"><template #default="{row}"><el-icon :color="row.sla_met ? '#67c23a' : '#f56c6c'">{{ row.sla_met ? 'CircleCheck' : 'CircleClose' }}</el-icon></template></el-table-column>
                    <el-table-column label="延迟" width="100"><template #default="{row}">{{ row.avg_latency_ms }}ms</template></el-table-column>
                    <el-table-column label="延迟级别" width="100"><template #default="{row}">
                        <el-tag :type="row.latency_level === 'good' ? 'success' : (row.latency_level === 'warning' ? 'warning' : 'danger')" size="small">{{ {good:'良好', warning:'警告', critical:'严重'}[row.latency_level] }}</el-tag>
                    </template></el-table-column>
                    <el-table-column label="检测数" width="70" prop="total_checks" />
                    <el-table-column label="失败数" width="70" prop="failed_checks" />
                </el-table>
                <div v-if="slaData.regions?.length" class="sla-summary">
                    <span>整体可用率: <b :class="slaData.overall_sla_met ? 'success' : 'danger'">{{ slaData.overall_availability }}%</b></span>
                    <span style="margin-left:16px">SLA 目标: {{ slaData.sla_target }}%</span>
                    <span style="margin-left:16px">期间: {{ slaData.period_label }}</span>
                </div>
                <el-empty v-else description="暂无 SLA 数据" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, Plus, Monitor, CircleCheck, DataBoard } from '@element-plus/icons-vue';
import syntheticMonitorApi from '@/api/syntheticMonitor';

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
    await syntheticMonitorApi.seedRegions(); ElMessage.success('区域已初始化'); loadDashboard();
}
async function submitCreate() {
    saving.value = true;
    try { await syntheticMonitorApi.createProbe(createForm); ElMessage.success('拨测任务已创建'); showCreate.value = false; loadAll(); } catch { ElMessage.error('创建失败'); } finally { saving.value = false; }
}
async function viewRegion(code) {
    try { const r = await syntheticMonitorApi.regionStats(code, { hours: 24 }); regionDetail.value = r.data?.data; showRegionDetail.value = true; } catch { ElMessage.error('加载失败'); }
}
async function syncStatusPage() {
    syncing.value = true;
    try { const r = await syntheticMonitorApi.syncToStatusPage(); ElMessage.success('状态页同步完成'); } catch { ElMessage.error('同步失败'); } finally { syncing.value = false; }
}
function fmtTime(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
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
