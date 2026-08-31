<template>
    <div class="fraud-risk-dashboard">
        <h2>{{ t('fraud_risk_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total_evaluations || 0 }}</div>
                        <div class="stat-label">{{ t('fraud_risk_page.stats.evaluations') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value danger">{{ stats.by_level?.critical || 0 }}</div>
                        <div class="stat-label">{{ t('fraud_risk_page.stats.critical') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value warning">{{ stats.by_level?.high || 0 }}</div>
                        <div class="stat-label">{{ t('fraud_risk_page.stats.high') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value info">{{ behaviorStats.total_analyses || 0 }}</div>
                        <div class="stat-label">{{ t('fraud_risk_page.stats.behavior') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t('fraud_risk_page.tabs.anomalies')" name="anomalies">
                <div class="toolbar">
                    <el-select v-model="filterLevel" :placeholder="t('fraud_risk_page.risk_level')" clearable style="width: 140px; margin-right: 12px;">
                        <el-option :label="t('fraud_risk_page.levels.critical')" value="critical" />
                        <el-option :label="t('fraud_risk_page.levels.high')" value="high" />
                        <el-option :label="t('fraud_risk_page.levels.medium')" value="medium" />
                        <el-option :label="t('fraud_risk_page.levels.low')" value="low" />
                    </el-select>
                    <el-button type="primary" @click="loadAnomalies">{{ t('actions.refresh') }}</el-button>
                    <el-button type="warning" @click="runBatchEval">{{ t('fraud_risk_page.batch_eval') }}</el-button>
                </div>

                <el-table :data="anomalies" v-loading="loading" stripe style="width: 100%">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="license_key" label="License Key" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="risk_level" :label="t('fraud_risk_page.risk_level')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="levelTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="risk_score" :label="t('fraud_risk_page.cols.score')" width="80" />
                    <el-table-column :label="t('fraud_risk_page.cols.signals')" min-width="200" show-overflow-tooltip>
                        <template #default="{ row }">
                            <span v-if="row.signals">{{ row.signals.join(', ') }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="action_taken" :label="t('fraud_risk_page.cols.action')" width="100" />
                    <el-table-column prop="detected_at" :label="t('fraud_risk_page.cols.detected')" width="170" />
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

            <el-tab-pane :label="t('fraud_risk_page.tabs.behavior')" name="behavior">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('fraud_risk_page.behavior_title') }}</span>
                    </template>
                    <el-form :model="behaviorForm" inline>
                        <el-form-item label="License Key">
                            <el-input v-model="behaviorForm.license_key" :placeholder="t('fraud_risk_page.license_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('fraud_risk_page.fingerprint')">
                            <el-input v-model="behaviorForm.device_fingerprint" :placeholder="t('fraud_risk_page.fingerprint')" />
                        </el-form-item>
                        <el-form-item :label="t('fraud_risk_page.endpoint')">
                            <el-select v-model="behaviorForm.endpoint" style="width: 160px;">
                                <el-option :label="t('fraud_risk_page.endpoints.activate')" value="activate" />
                                <el-option :label="t('fraud_risk_page.endpoints.validate')" value="validate" />
                                <el-option :label="t('fraud_risk_page.endpoints.other')" value="other" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="runAnalysis" :loading="analyzing">{{ t('fraud_risk_page.start_analysis') }}</el-button>
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
                            {{ t('fraud_risk_page.result_title', { score: analysisResult.risk_score, action: analysisResult.action }) }}
                            <el-tag v-if="analysisResult.is_blocked" type="danger" size="small" style="margin-left: 8px;">{{ t('fraud_risk_page.blocked') }}</el-tag>
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
                        <span>{{ t('fraud_risk_page.unban_title') }}</span>
                    </template>
                    <el-form :model="unbanForm" inline>
                        <el-form-item :label="t('fraud_risk_page.unban_type')">
                            <el-select v-model="unbanForm.type" style="width: 120px;">
                                <el-option label="IP" value="ip" />
                                <el-option :label="t('fraud_risk_page.device')" value="device" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('fraud_risk_page.unban_value')">
                            <el-input v-model="unbanForm.value" :placeholder="t('fraud_risk_page.unban_value_ph')" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="success" @click="handleUnban">{{ t('fraud_risk_page.unban') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('fraud_risk_page.tabs.stats')" name="stats">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('fraud_risk_page.by_level') }}</span></template>
                            <div class="chart-placeholder">
                                <el-table :data="levelChartData" stripe size="small">
                                    <el-table-column prop="level" :label="t('fraud_risk_page.cols.level')" />
                                    <el-table-column prop="count" :label="t('fraud_risk_page.cols.count')" />
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('fraud_risk_page.by_action') }}</span></template>
                            <div class="chart-placeholder">
                                <el-table :data="actionChartData" stripe size="small">
                                    <el-table-column prop="action" :label="t('fraud_risk_page.cols.action')" />
                                    <el-table-column prop="count" :label="t('fraud_risk_page.cols.count')" />
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
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getFraudStats, getAnomalies, batchEvaluate, analyzeBehavior, unban, getBehaviorStats } from '@/api/fraudRisk'

const { t } = useI18n()

const activeTab = ref('anomalies')
const loading = ref(false)
const analyzing = ref(false)

const stats = ref({})
const behaviorStats = ref({})

const anomalies = ref([])
const page = ref(1)
const perPage = 20
const total = ref(0)
const filterLevel = ref('')

const behaviorForm = reactive({
    license_key: '',
    device_fingerprint: '',
    endpoint: 'activate',
})
const analysisResult = ref(null)

const unbanForm = reactive({
    type: 'ip',
    value: '',
})

const levelTag = (level) => {
    const map = { critical: 'danger', high: 'warning', medium: 'info', low: 'success' }
    return map[level] || 'info'
}

const resultAlertType = computed(() => {
    if (!analysisResult.value) return 'info'
    if (analysisResult.value.is_blocked) return 'error'
    if (analysisResult.value.risk_score >= 50) return 'warning'
    return 'success'
})

const signalTagType = (score) => {
    if (score >= 30) return 'danger'
    if (score >= 15) return 'warning'
    return 'info'
}

const levelChartData = computed(() => {
    if (!stats.value?.by_level) return []
    return Object.entries(stats.value.by_level).map(([level, count]) => ({ level, count }))
})

const actionChartData = computed(() => {
    if (!stats.value?.by_action) return []
    return Object.entries(stats.value.by_action).map(([action, count]) => ({ action, count }))
})

async function loadFraudStats() {
    try {
        stats.value = await getFraudStats()
    } catch (e) {
        console.error('Failed to load fraud stats', e)
    }
}

async function loadBehaviorStats() {
    try {
        behaviorStats.value = await getBehaviorStats()
    } catch (e) {
        console.error('Failed to load behavior stats', e)
    }
}

async function loadAnomalies() {
    loading.value = true
    try {
        const res = await getAnomalies({ page: page.value, per_page: perPage, risk_level: filterLevel.value || undefined })
        anomalies.value = res.data || []
        total.value = res.meta?.total || res.total || 0
    } catch (e) {
        console.error('Failed to load anomalies', e)
    } finally {
        loading.value = false
    }
}

async function runBatchEval() {
    try {
        await batchEvaluate()
        await loadAnomalies()
        ElMessage.success(t('fraud_risk_page.messages.batch_ok'))
    } catch (e) {
        ElMessage.error(t('fraud_risk_page.messages.batch_failed'))
    }
}

async function runAnalysis() {
    analyzing.value = true
    try {
        analysisResult.value = await analyzeBehavior(behaviorForm)
    } catch (e) {
        ElMessage.error(t('fraud_risk_page.messages.analysis_failed'))
    } finally {
        analyzing.value = false
    }
}

async function handleUnban() {
    if (!unbanForm.value) {
        ElMessage.warning(t('fraud_risk_page.messages.unban_required'))
        return
    }
    try {
        await unban(unbanForm)
        ElMessage.success(t('fraud_risk_page.messages.unban_ok'))
        unbanForm.value = ''
    } catch (e) {
        ElMessage.error(t('fraud_risk_page.messages.unban_failed'))
    }
}

onMounted(() => {
    loadFraudStats()
    loadBehaviorStats()
    loadAnomalies()
})
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
    color: #0f172a;
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
