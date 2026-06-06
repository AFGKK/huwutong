<template>
    <div class="circuit-breaker-page">
        <div class="page-header">
            <div class="header-left">
                <h2>断路器监控面板</h2>
                <span class="header-subtitle">监控各服务熔断状态，手动重置已熔断服务</span>
            </div>
            <div class="header-right">
                <el-button type="warning" @click="handleResetAll" :loading="resetting">
                    <el-icon><Refresh /></el-icon>
                    重置全部
                </el-button>
                <el-button @click="fetchData" :loading="loading">
                    <el-icon><Refresh /></el-icon>
                    刷新
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
                    <div class="summary-label">服务总数</div>
                </div>
                <div class="summary-badges">
                    <el-tag type="success" size="large">{{ summary.closed }} 正常</el-tag>
                    <el-tag v-if="summary.open > 0" type="danger" size="large">{{ summary.open }} 熔断</el-tag>
                    <el-tag v-if="summary.half_open > 0" type="warning" size="large">{{ summary.half_open }} 半开</el-tag>
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
                        <div class="metric-label">失败次数</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">{{ svc.threshold }}</div>
                        <div class="metric-label">熔断阈值</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">{{ svc.half_open_count }}</div>
                        <div class="metric-label">半开试探</div>
                    </div>
                </div>

                <div class="service-footer">
                    <div v-if="svc.state_changed_at" class="state-time">
                        <el-icon><Timer /></el-icon>
                        状态变更：{{ formatTime(svc.state_changed_at) }}
                    </div>
                    <div v-else class="state-time text-muted">最近无变更</div>
                    <el-button
                        v-if="svc.state !== 'closed'"
                        text
                        type="primary"
                        size="small"
                        @click="handleResetService(svc.service)"
                    >
                        重置此服务
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
                    <span>最近熔断事件</span>
                    <el-tag size="small" type="info">{{ logs.length }} 条</el-tag>
                </div>
            </template>
            <el-table :data="logs" v-loading="logsLoading" stripe style="width: 100%" size="small">
                <el-table-column prop="timestamp" label="时间" width="180">
                    <template #default="{ row }">
                        {{ row.timestamp || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="level" label="级别" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.level" size="small">
                            {{ row.level === 'error' ? '错误' : row.level === 'warning' ? '警告' : '信息' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="message" label="事件内容" min-width="300" />
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, CircleCheckFilled, WarningFilled, Timer } from '@element-plus/icons-vue';
import { getCircuitBreakerStatus, resetCircuitBreaker, getCircuitBreakerLogs } from '@/api/circuit-breaker';

const loading = ref(false);
const resetting = ref(false);
const logsLoading = ref(false);
const services = ref([]);
const summary = reactive({ total: 0, closed: 0, open: 0, half_open: 0, all_healthy: true });
const logs = ref([]);

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
        await ElMessageBox.confirm('确定要重置所有服务的熔断状态吗？', '确认', {
            confirmButtonText: '重置全部',
            cancelButtonText: '取消',
            type: 'warning',
        });
    } catch {
        return;
    }

    resetting.value = true;
    try {
        await resetCircuitBreaker();
        ElMessage.success('已重置所有熔断状态');
        await fetchStatus();
    } catch {
        ElMessage.error('重置失败');
    } finally {
        resetting.value = false;
    }
}

async function handleResetService(service) {
    try {
        await resetCircuitBreaker(service);
        ElMessage.success(`已重置 ${service} 熔断状态`);
        await fetchStatus();
    } catch {
        ElMessage.error('重置失败');
    }
}

function stateTag(state) {
    return state === 'closed' ? 'success' : state === 'open' ? 'danger' : 'warning';
}

function stateLabel(state) {
    return state === 'closed' ? '正常' : state === 'open' ? '熔断中' : '半开恢复';
}

function statusClass(s) {
    return s.all_healthy ? 'summary--healthy' : 'summary--unhealthy';
}

function formatTime(ts) {
    if (!ts) return '-';
    const d = new Date(ts * 1000);
    return d.toLocaleString('zh-CN', {
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
