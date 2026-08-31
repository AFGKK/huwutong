<template>
    <div class="circuit-breaker-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('circuit_breaker_page.title') }}</h2>
                <span class="header-subtitle">{{ t('circuit_breaker_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="warning" @click="handleResetAll" :loading="resetting">
                    <el-icon><Refresh /></el-icon>
                    {{ t('circuit_breaker_page.reset_all') }}
                </el-button>
                <el-button @click="fetchData" :loading="loading">
                    <el-icon><Refresh /></el-icon>
                    {{ t('circuit_breaker_page.refresh') }}
                </el-button>
            </div>
        </div>

        <!-- 概览卡片 -->
        <div class="summary-grid">
            <div class="summary-card" :class="statusClass(summary)">
                <div class="summary-icon">
                    <el-icon :size="32" v-if="summary.all_healthy"><CircleCheckFilled /></el-icon>
                    <el-icon :size="32" v-else><WarningFilled /></el-icon>
                </div>
                <div class="summary-text">
                    <div class="summary-value">{{ summary.total }}</div>
                    <div class="summary-label">{{ t('circuit_breaker_page.summary.total_services') }}</div>
                </div>
                <div class="summary-badges">
                    <el-tag type="success" size="large">{{ summary.closed }} {{ t('circuit_breaker_page.summary.closed') }}</el-tag>
                    <el-tag v-if="summary.open > 0" type="danger" size="large">{{ summary.open }} {{ t('circuit_breaker_page.summary.open') }}</el-tag>
                    <el-tag v-if="summary.half_open > 0" type="warning" size="large">{{ summary.half_open }} {{ t('circuit_breaker_page.summary.half_open') }}</el-tag>
                </div>
            </div>
        </div>

        <!-- 服务状态卡片网格 -->
        <div class="services-grid">
            <el-card
                v-for="svc in services"
                :key="svc.service"
                :class="['service-card', `service-card--${svc.state}`]"
                shadow="hover"
            >
                <div class="service-header">
                    <div class="service-info">
                        <h3>{{ svc.label }}</h3>
                        <span class="service-key">{{ svc.service }}</span>
                    </div>
                    <el-tag
                        :type="stateTag(svc.state)"
                        size="large"
                        effect="dark"
                        class="state-tag"
                    >
                        {{ stateLabel(svc.state) }}
                    </el-tag>
                </div>

                <div class="service-metrics">
                    <div class="metric">
                        <div class="metric-value">{{ svc.failures }}</div>
                        <div class="metric-label">{{ t('circuit_breaker_page.metrics.failures') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">{{ svc.threshold }}</div>
                        <div class="metric-label">{{ t('circuit_breaker_page.metrics.threshold') }}</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">{{ svc.half_open_count }}</div>
                        <div class="metric-label">{{ t('circuit_breaker_page.metrics.half_open_probes') }}</div>
                    </div>
                </div>

                <div class="service-footer">
                    <div v-if="svc.state_changed_at" class="state-time">
                        <el-icon><Timer /></el-icon>
                        {{ t('circuit_breaker_page.state_changed', { time: formatTime(svc.state_changed_at) }) }}
                    </div>
                    <div v-else class="state-time text-muted">{{ t('circuit_breaker_page.no_recent_change') }}</div>
                    <el-button
                        v-if="svc.state !== 'closed'"
                        text
                        type="primary"
                        size="small"
                        @click="handleResetService(svc.service)"
                    >
                        {{ t('circuit_breaker_page.reset_service') }}
                    </el-button>
                </div>

                <!-- 状态指示条 -->
                <div class="state-bar">
                    <div :class="`state-bar__fill state-bar__fill--${svc.state}`"></div>
                </div>
            </el-card>
        </div>

        <!-- 最近事件日志 -->
        <el-card shadow="never" class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>{{ t('circuit_breaker_page.recent_events') }}</span>
                    <el-tag size="small" type="info">{{ t('circuit_breaker_page.log_count', { count: logs.length }) }}</el-tag>
                </div>
            </template>
            <el-table :data="logs" v-loading="logsLoading" stripe style="width: 100%" size="small">
                <el-table-column prop="timestamp" :label="t('circuit_breaker_page.columns.time')" width="180">
                    <template #default="{ row }">
                        {{ row.timestamp || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="level" :label="t('circuit_breaker_page.columns.level')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.level" size="small">
                            {{ levelLabel(row.level) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="message" :label="t('circuit_breaker_page.columns.message')" min-width="300" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, CircleCheckFilled, WarningFilled, Timer } from '@element-plus/icons-vue';
import { getCircuitBreakerStatus, resetCircuitBreaker, getCircuitBreakerLogs } from '@/api/circuit-breaker';

const { t, locale } = useI18n();

const loading = ref(false);
const resetting = ref(false);
const logsLoading = ref(false);
const services = ref([]);
const summary = reactive({ total: 0, closed: 0, open: 0, half_open: 0, all_healthy: true });
const logs = ref([]);

const stateLabels = computed(() => ({
    closed: t('circuit_breaker_page.states.closed'),
    open: t('circuit_breaker_page.states.open'),
    half_open: t('circuit_breaker_page.states.half_open'),
}));

const levelLabels = computed(() => ({
    error: t('circuit_breaker_page.levels.error'),
    warning: t('circuit_breaker_page.levels.warning'),
    info: t('circuit_breaker_page.levels.info'),
}));

let refreshTimer = null;

async function fetchStatus() {
    loading.value = true;
    try {
        const res = await getCircuitBreakerStatus();
        if (res.data) {
            services.value = res.data.services || [];
            Object.assign(summary, res.data.summary || {});
        }
    } catch {
        // silent
    } finally {
        loading.value = false;
    }
}

async function fetchLogs() {
    logsLoading.value = true;
    try {
        const res = await getCircuitBreakerLogs();
        logs.value = res.data || [];
    } catch {
        // silent
    } finally {
        logsLoading.value = false;
    }
}

function fetchData() {
    fetchStatus();
    fetchLogs();
}

async function handleResetAll() {
    try {
        await ElMessageBox.confirm(
            t('circuit_breaker_page.confirm_reset_all'),
            t('actions.confirm'),
            {
                confirmButtonText: t('circuit_breaker_page.reset_all'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
            },
        );
    } catch {
        return;
    }

    resetting.value = true;
    try {
        await resetCircuitBreaker();
        ElMessage.success(t('circuit_breaker_page.messages.reset_all_success'));
        await fetchStatus();
    } catch {
        ElMessage.error(t('circuit_breaker_page.messages.reset_failed'));
    } finally {
        resetting.value = false;
    }
}

async function handleResetService(service) {
    try {
        await resetCircuitBreaker(service);
        ElMessage.success(t('circuit_breaker_page.messages.reset_service_success', { service }));
        await fetchStatus();
    } catch {
        ElMessage.error(t('circuit_breaker_page.messages.reset_failed'));
    }
}

function stateTag(state) {
    return state === 'closed' ? 'success' : state === 'open' ? 'danger' : 'warning';
}

function stateLabel(state) {
    return stateLabels.value[state] || state;
}

function levelLabel(level) {
    return levelLabels.value[level] || levelLabels.value.info;
}

function statusClass(s) {
    return s.all_healthy ? 'summary--healthy' : 'summary--unhealthy';
}

function formatTime(ts) {
    if (!ts) return '-';
    const d = new Date(ts * 1000);
    return d.toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

onMounted(() => {
    fetchData();
    // 每 15 秒自动刷新
    refreshTimer = setInterval(fetchStatus, 15000);
});

onUnmounted(() => {
    if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.header-subtitle {
    font-size: 13px;
    color: #909399;
    margin-left: 12px;
}

/* Summary */
.summary-grid {
    margin-bottom: 20px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #f5f7fa;
    border-radius: 12px;
    padding: 24px 32px;
    border-left: 4px solid #67c23a;
}

.summary-card.summary--unhealthy {
    border-left-color: #f56c6c;
    background: #fef0f0;
}

.summary-icon {
    color: #67c23a;
}

.summary--unhealthy .summary-icon {
    color: #f56c6c;
}

.summary-text {
    flex: 1;
}

.summary-value {
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
}

.summary-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.summary-badges {
    display: flex;
    gap: 8px;
}

/* Service Cards Grid */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.service-card {
    position: relative;
    overflow: hidden;
}

.service-card :deep(.el-card__body) {
    padding: 20px;
}

.service-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.service-info h3 {
    margin: 0;
    font-size: 16px;
}

.service-key {
    font-size: 12px;
    color: #909399;
    font-family: monospace;
}

.state-tag {
    flex-shrink: 0;
}

.service-metrics {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.metric {
    flex: 1;
    text-align: center;
    background: #f5f7fa;
    border-radius: 8px;
    padding: 12px 8px;
}

.metric-value {
    font-size: 22px;
    font-weight: 700;
    color: #303133;
}

.metric-label {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

.service-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
}

.state-time {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #909399;
}

.text-muted {
    color: #c0c4cc;
}

/* State bar */
.state-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
}

.state-bar__fill {
    height: 100%;
    transition: width 0.5s;
    width: 100%;
}

.state-bar__fill--closed { background: #67c23a; }
.state-bar__fill--open { background: #f56c6c; }
.state-bar__fill--half_open { background: #e6a23c; }

/* Service card state colors */
.service-card--open { border-color: #f56c6c; }
.service-card--half_open { border-color: #e6a23c; }

.mt-4 {
    margin-top: 16px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
