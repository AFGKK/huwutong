<template>
    <div class="drift-container">
        <el-page-header :content="'设备指纹漂移追踪'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="追踪设备指纹随时间的渐变过程，识别硬件逐步更换（安全漂移）vs 突然变更（可能被盗/克隆），支持自动更新基准指纹。" type="info" show-icon :closable="false" class="alert-info" />

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value">{{ dashboard.total_devices }}</div>
                        <div class="stat-label">设备总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #e6a23c;">{{ dashboard.recent_drifts_30d }}</div>
                        <div class="stat-label">近30天漂移事件</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #67c23a;">{{ dashboard.auto_accepted_30d }}</div>
                        <div class="stat-label">自动接受(30天)</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #f56c6c;">{{ dashboard.pending_drifts?.length || 0 }}</div>
                        <div class="stat-label">待处理漂移</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- 待处理漂移 -->
            <el-tab-pane label="待处理漂移" name="pending">
                <el-card>
                    <template #header><span>需人工确认的指纹漂移</span></template>
                    <el-table :data="pendingDrifts" stripe v-loading="loadingPending" size="small">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="设备" min-width="120">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="'never'" @click="showDeviceDetail(row.device_id)">
                                    {{ row.device?.platform || '未知设备' }}
                                </el-link>
                                <div class="text-muted">#{{ row.device_id }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="漂移类型" width="100">
                            <template #default="{ row }">
                                <el-tag :type="driftTagType(row.drift_type)" size="small">
                                    {{ driftLabel(row.drift_type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="变更组件" width="120">
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
                        <el-table-column label="设备指纹" min-width="160">
                            <template #default="{ row }">
                                <code class="small-text">{{ row.fingerprint?.substring(0, 20) }}...</code>
                            </template>
                        </el-table-column>
                        <el-table-column label="来源" width="80">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ row.source }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" label="时间" width="150" />
                        <el-table-column label="操作" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="handleAccept(row)">接受漂移</el-button>
                                <el-button size="small" @click="showDeviceDetail(row.device_id)">查看</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!loadingPending && !pendingDrifts.length" description="暂无待处理漂移" :image-size="60" />
                </el-card>
            </el-tab-pane>

            <!-- 漂移分布 -->
            <el-tab-pane label="漂移分布" name="stats">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>漂移类型分布</span></template>
                            <div ref="pieChartRef" style="height: 300px;"></div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>漂移说明</span></template>
                            <el-timeline>
                                <el-timeline-item timestamp="initial" type="info">首次记录/无变更 — 自动设为基准</el-timeline-item>
                                <el-timeline-item timestamp="gradual" type="success">1个组件渐变 — 自动接受，安全漂移</el-timeline-item>
                                <el-timeline-item timestamp="partial" type="warning">2个组件变更 — 需人工确认</el-timeline-item>
                                <el-timeline-item timestamp="major" type="danger">≥3个组件变更 — 高风险，立即审查</el-timeline-item>
                                <el-timeline-item timestamp="manual" type="primary">手动确认 — 管理员主动接受</el-timeline-item>
                            </el-timeline>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>

        <!-- 设备详情抽屉 -->
        <el-drawer v-model="drawerVisible" :title="'设备 #' + (deviceId || '')" size="600px">
            <div v-loading="loadingDetail">
                <template v-if="deviceSummary">
                    <el-descriptions :column="1" border size="small">
                        <el-descriptions-item label="当前指纹">
                            <code>{{ deviceSummary.fingerprint?.substring(0, 30) }}...</code>
                        </el-descriptions-item>
                        <el-descriptions-item label="平台">{{ deviceSummary.platform || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="快照总数">{{ deviceSummary.total_snapshots }}</el-descriptions-item>
                        <el-descriptions-item label="漂移事件">{{ deviceSummary.drift_events }}</el-descriptions-item>
                    </el-descriptions>

                    <h4 class="mt-4">基准指纹</h4>
                    <div v-if="deviceSummary.baseline" class="baseline-card">
                        <div><strong>指纹：</strong><code>{{ deviceSummary.baseline.fingerprint?.substring(0, 30) }}...</code></div>
                        <div><strong>版本：</strong>V{{ deviceSummary.baseline.fingerprint_version }}</div>
                        <div><strong>记录时间：</strong>{{ deviceSummary.baseline.created_at }}</div>
                    </div>
                    <el-empty v-else description="暂无基准指纹" :image-size="50" />

                    <h4 class="mt-4">最近记录</h4>
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
                                    变更 {{ h.changed_components }}/{{ h.total_components }} 组件
                                </span>
                                <span v-if="h.is_baseline" class="ml-2">
                                    <el-tag type="success" size="small">基准</el-tag>
                                </span>
                            </div>
                            <div v-if="h.components" class="component-detail mt-1">
                                <div v-for="(comp, key) in h.components" :key="key" class="comp-row">
                                    <span class="comp-key">{{ key }}:</span>
                                    <span v-if="comp.matched !== undefined" :class="comp.matched ? 'comp-match' : 'comp-diff'">
                                        {{ comp.matched ? '✅' : '❌' }}
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
import { ElMessage, ElMessageBox } from 'element-plus';
import fingerprintDrift from '@/api/fingerprintDrift';
import * as echarts from 'echarts';

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
    const map = { initial: '初始', gradual: '渐变', partial: '部分', major: '重大', manual: '手动' };
    return map[type] || type;
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
        ElMessage.error('获取设备指纹历史失败');
    } finally {
        loadingDetail.value = false;
    }
}

async function handleAccept(row) {
    try {
        await ElMessageBox.confirm(
            `接受此指纹漂移将更新设备 #${row.device_id} 的基准指纹。确认继续？`,
            '接受漂移',
            { confirmButtonText: '接受', cancelButtonText: '取消', type: 'warning' }
        );
    } catch { return; }

    try {
        await fingerprintDrift.acceptDrift(row.id, { notes: '管理员手动确认' });
        ElMessage.success('漂移已接受，基准指纹已更新');
        loadPending();
        loadDashboard();
    } catch {
        ElMessage.error('操作失败');
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
    }));

    if (!data.length) {
        data.push({ name: '暂无数据', value: 1 });
    }

    const colorMap = { 初始: '#909399', 渐变: '#67c23a', 部分: '#e6a23c', 重大: '#f56c6c', 手动: '#409eff' };

    pieChartInstance.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        series: [{
            type: 'pie',
            radius: ['30%', '60%'],
            data,
            label: { show: true, formatter: '{b}\n{d}%' },
            itemStyle: {
                color: (p) => colorMap[p.name] || '#909399',
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
.stat-value { font-size: 32px; font-weight: 700; color: #409eff; }
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
