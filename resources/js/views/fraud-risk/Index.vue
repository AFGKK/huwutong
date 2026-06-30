<template>
    <div class="fraud-risk-dashboard">
        <h2>AI 风控 & 行为风控引擎</h2>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total_evaluations || 0 }}</div>
                        <div class="stat-label">总评估次数 (30天)</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value danger">{{ stats.by_level?.critical || 0 }}</div>
                        <div class="stat-label">严重风险</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value warning">{{ stats.by_level?.high || 0 }}</div>
                        <div class="stat-label">高风险</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value info">{{ behaviorStats.total_analyses || 0 }}</div>
                        <div class="stat-label">行为分析总数</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: 异常记录 -->
            <el-tab-pane label="风控异常记录" name="anomalies">
                <div class="toolbar">
                    <el-select v-model="filterLevel" placeholder="风险等级" clearable style="width: 140px; margin-right: 12px;">
                        <el-option label="严重" value="critical" />
                        <el-option label="高" value="high" />
                        <el-option label="中" value="medium" />
                        <el-option label="低" value="low" />
                    </el-select>
                    <el-button type="primary" @click="loadAnomalies">刷新</el-button>
                    <el-button type="warning" @click="runBatchEval">批量评估</el-button>
                </div>

                <el-table :data="anomalies" v-loading="loading" stripe style="width: 100%">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="license_key" label="License Key" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="risk_level" label="风险等级" width="100">
                        <template #default="{ row }">
                            <el-tag :type="levelTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="risk_score" label="风险分" width="80" />
                    <el-table-column label="信号" min-width="200" show-overflow-tooltip>
                        <template #default="{ row }">
                            <span v-if="row.signals">{{ row.signals.join(', ') }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="action_taken" label="动作" width="100" />
                    <el-table-column prop="detected_at" label="检测时间" width="170" />
                </el-table>

                <div class="pagination-wrap">
                    <el-pagination
                        v-model:current-page="page"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next, total"
                        @current-change="loadAnomalies"
                    />
                </div>
            </el-tab-pane>

            <!-- Tab 2: 行为分析 -->
            <el-tab-pane label="行为分析" name="behavior">
                <el-card shadow="never">
                    <template #header>
                        <span>请求行为分析</span>
                    </template>
                    <el-form :model="behaviorForm" inline>
                        <el-form-item label="License Key">
                            <el-input v-model="behaviorForm.license_key" placeholder="输入 License Key" />
                        </el-form-item>
                        <el-form-item label="设备指纹">
                            <el-input v-model="behaviorForm.device_fingerprint" placeholder="设备指纹" />
                        </el-form-item>
                        <el-form-item label="端点">
                            <el-select v-model="behaviorForm.endpoint" style="width: 160px;">
                                <el-option label="激活" value="activate" />
                                <el-option label="验证" value="validate" />
                                <el-option label="其他" value="other" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="runAnalysis" :loading="analyzing">开始分析</el-button>
                        </el-form-item>
                    </el-form>

                    <el-alert
                        v-if="analysisResult"
                        :type="resultAlertType"
                        show-icon
                        :closable="false"
                        style="margin-top: 12px;"
                    >
                        <template #title>
                            风险评分: {{ analysisResult.risk_score }} —
                            动作: {{ analysisResult.action }}
                            <el-tag v-if="analysisResult.is_blocked" type="danger" size="small" style="margin-left: 8px;">已封禁</el-tag>
                        </template>
                        <div v-if="analysisResult.signals?.length" style="margin-top: 8px;">
                            <div v-for="s in analysisResult.signals" :key="s.signal" class="signal-item">
                                <el-tag size="small" :type="signalTagType(s.score)">{{ s.signal }}</el-tag>
                                <span style="margin-left: 8px; font-size: 13px;">{{ s.detail }}</span>
                            </div>
                        </div>
                    </el-alert>
                </el-card>

                <el-card shadow="never" style="margin-top: 16px;">
                    <template #header>
                        <span>封禁管理</span>
                    </template>
                    <el-form :model="unbanForm" inline>
                        <el-form-item label="类型">
                            <el-select v-model="unbanForm.type" style="width: 120px;">
                                <el-option label="IP" value="ip" />
                                <el-option label="设备" value="device" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="值">
                            <el-input v-model="unbanForm.value" placeholder="IP 或设备指纹" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="success" @click="handleUnban">解封</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 统计 -->
            <el-tab-pane label="风险统计" name="stats">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>按风险等级</span></template>
                            <div class="chart-placeholder">
                                <el-table :data="levelChartData" stripe size="small">
                                    <el-table-column prop="level" label="等级" />
                                    <el-table-column prop="count" label="数量" />
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>按处理动作</span></template>
                            <div class="chart-placeholder">
                                <el-table :data="actionChartData" stripe size="small">
                                    <el-table-column prop="action" label="动作" />
                                    <el-table-column prop="count" label="数量" />
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { getFraudStats, getAnomalies, batchEvaluate, analyzeBehavior, unban, getBehaviorStats } from '@/api/fraudRisk';

const activeTab = ref('anomalies');
const loading = ref(false);
const analyzing = ref(false);

// 统计
const stats = ref({});
const behaviorStats = ref({});

// 异常列表
const anomalies = ref([]);
const page = ref(1);
const perPage = 20;
const total = ref(0);
const filterLevel = ref('');

// 分析表单
const behaviorForm = reactive({
    license_key: '',
    device_fingerprint: '',
    endpoint: 'activate',
});
const analysisResult = ref(null);

// 解封
const unbanForm = reactive({
    type: 'ip',
    value: '',
});

const levelTag = (level) => {
    const map = { critical: 'danger', high: 'warning', medium: 'info', low: 'success' };
    return map[level] || 'info';
};

const resultAlertType = computed(() => {
    if (!analysisResult.value) return 'info';
    if (analysisResult.value.is_blocked) return 'error';
    if (analysisResult.value.risk_score >= 50) return 'warning';
    return 'success';
});

const signalTagType = (score) => {
    if (score >= 30) return 'danger';
    if (score >= 15) return 'warning';
    return 'info';
};

const levelChartData = computed(() => {
    if (!stats.value?.by_level) return [];
    return Object.entries(stats.value.by_level).map(([level, count]) => ({ level, count }));
});

const actionChartData = computed(() => {
    if (!stats.value?.by_action) return [];
    return Object.entries(stats.value.by_action).map(([action, count]) => ({ action, count }));
});

async function loadFraudStats() {
    try {
        stats.value = await getFraudStats();
    } catch (e) {
        console.error('Failed to load fraud stats', e);
    }
}

async function loadBehaviorStats() {
    try {
        behaviorStats.value = await getBehaviorStats();
    } catch (e) {
        console.error('Failed to load behavior stats', e);
    }
}

async function loadAnomalies() {
    loading.value = true;
    try {
        const res = await getAnomalies({ page: page.value, per_page: perPage, risk_level: filterLevel.value || undefined });
        anomalies.value = res.data || [];
        total.value = res.meta?.total || res.total || 0;
    } catch (e) {
        console.error('Failed to load anomalies', e);
    } finally {
        loading.value = false;
    }
}

async function runBatchEval() {
    try {
        await batchEvaluate();
        await loadAnomalies();
        ElMessage.success('批量评估完成');
    } catch (e) {
        ElMessage.error('批量评估失败');
    }
}

async function runAnalysis() {
    analyzing.value = true;
    try {
        analysisResult.value = await analyzeBehavior(behaviorForm);
    } catch (e) {
        ElMessage.error('分析失败');
    } finally {
        analyzing.value = false;
    }
}

async function handleUnban() {
    if (!unbanForm.value) {
        ElMessage.warning('请输入解封值');
        return;
    }
    try {
        await unban(unbanForm);
        ElMessage.success('解封成功');
        unbanForm.value = '';
    } catch (e) {
        ElMessage.error('解封失败');
    }
}

onMounted(() => {
    loadFraudStats();
    loadBehaviorStats();
    loadAnomalies();
});
</script>

<style scoped>
.fraud-risk-dashboard {
    padding: 20px;
}
.stats-row {
    margin-bottom: 20px;
}
.stat-card {
    text-align: center;
    padding: 10px 0;
}
.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #409eff;
}
.stat-value.danger {
    color: #f56c6c;
}
.stat-value.warning {
    color: #e6a23c;
}
.stat-value.info {
    color: #909399;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 6px;
}
.toolbar {
    margin-bottom: 16px;
    display: flex;
    align-items: center;
}
.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
.signal-item {
    margin-bottom: 4px;
}
.chart-placeholder {
    min-height: 120px;
}
</style>
