<template>
    <div class="status-page">
        <div class="status-container">
            <!-- Header -->
            <div class="status-header">
                <h1 class="brand">{{ siteName }} 系统状态</h1>
                <div class="overall-status" :class="overallStatus">
                    <el-icon :size="48">
                        <CircleCheck v-if="overallStatus === 'operational'" />
                        <WarningFilled v-else-if="overallStatus === 'degraded_performance'" />
                        <CloseBold v-else />
                    </el-icon>
                    <div class="status-text">
                        <h2>{{ statusTitle }}</h2>
                        <p>{{ statusDescription }}</p>
                    </div>
                </div>
            </div>

            <div v-if="loading" class="loading-state" v-loading="loading">
                <span>加载系统状态...</span>
            </div>

            <template v-else>
                <!-- Service Components -->
                <el-card class="checks-card" shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>服务状态</span>
                            <div class="header-right">
                                <el-tag :type="uptimeTag" size="small" class="uptime-tag">
                                    {{ uptime }}
                                </el-tag>
                                <el-button text size="small" @click="fetchStatus" :loading="refreshing">
                                    刷新
                                </el-button>
                            </div>
                        </div>
                    </template>

                    <div class="checks-list">
                        <template v-for="(group, gIdx) in groupedComponents" :key="gIdx">
                            <div class="group-label" v-if="gIdx !== '_ungrouped'">{{ groupLabel(gIdx) }}</div>
                            <div v-for="comp in group" :key="comp.slug" class="check-item">
                                <div class="check-info">
                                    <el-icon :size="18" :class="'status-' + comp.status">
                                        <CircleCheck v-if="comp.status === 'operational'" />
                                        <WarningFilled v-else-if="comp.status === 'degraded_performance'" />
                                        <CloseBold v-else />
                                    </el-icon>
                                    <span class="check-name">{{ comp.name }}</span>
                                    <span v-if="comp.description" class="check-desc">{{ comp.description }}</span>
                                </div>
                                <el-tag :type="comp.status === 'operational' ? 'success' : 'danger'" size="small">
                                    {{ comp.status_label || statusLabel(comp.status) }}
                                </el-tag>
                            </div>
                        </template>
                    </div>

                    <div class="uptime-info">
                        <span>所有系统正常运行</span>
                        <span class="uptime-badge" v-if="uptimePercent !== null">
                            {{ uptimeDays }}天 Uptime: <strong>{{ uptimePercent }}%</strong>
                        </span>
                    </div>
                </el-card>

                <!-- Incidents -->
                <el-card class="incidents-card" shadow="never" v-if="incidents.length > 0">
                    <template #header>
                        <span>近期事件</span>
                    </template>

                    <div v-for="inc in incidents" :key="inc.id" class="incident-item">
                        <div class="incident-header">
                            <el-tag :type="inc.severity === 'critical' ? 'danger' : inc.severity === 'major' ? 'warning' : 'info'" size="small">
                                {{ inc.severity_label }}
                            </el-tag>
                            <el-tag :type="inc.status === 'resolved' ? 'success' : 'warning'" size="small" effect="plain">
                                {{ inc.status_label }}
                            </el-tag>
                            <span class="incident-date">{{ formatTime(inc.occurred_at || inc.created_at) }}</span>
                        </div>
                        <h4 class="incident-title">{{ inc.title }}</h4>
                        <div class="incident-components" v-if="inc.components?.length">
                            影响: <span v-for="(c, ci) in inc.components" :key="c.id">
                                {{ c.name }}{{ ci < inc.components.length - 1 ? ', ' : '' }}
                            </span>
                        </div>

                        <!-- Updates -->
                        <div class="incident-updates" v-if="inc.updates?.length">
                            <div v-for="up in inc.updates" :key="up.created_at" class="update-item">
                                <div class="update-dot" :class="'dot-' + up.status"></div>
                                <div class="update-content">
                                    <div class="update-header">
                                        <strong>{{ statusLabel(up.status) }}</strong>
                                        <span class="update-time">{{ formatTime(up.created_at) }}</span>
                                    </div>
                                    <p class="update-message">{{ up.message }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-card>

                <el-card class="incidents-card empty" shadow="never" v-else>
                    <div class="no-incidents">
                        <el-icon :size="40" color="#67c23a"><CircleCheck /></el-icon>
                        <p>最近 7 天无事件报告</p>
                    </div>
                </el-card>

                <!-- Subscribe -->
                <el-card class="subscribe-card" shadow="never">
                    <div class="subscribe-form">
                        <h3>获取状态更新通知</h3>
                        <p>当系统出现事件时，通过邮件通知您</p>
                        <div class="subscribe-input">
                            <el-input v-model="subscribeEmail" placeholder="输入您的邮箱地址"
                                :disabled="subscribed" />
                            <el-button type="primary" @click="handleSubscribe" :loading="subscribing"
                                :disabled="subscribed || !subscribeEmail.trim()">
                                {{ subscribed ? '已订阅' : '订阅' }}
                            </el-button>
                        </div>
                        <p v-if="subscribeMessage" class="subscribe-message">{{ subscribeMessage }}</p>
                    </div>
                </el-card>

                <!-- Footer -->
                <div class="status-footer">
                    <p>{{ siteName }} — 互物通授权管理系统</p>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { CircleCheck, WarningFilled, CloseBold } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import statusPageApi from '@/api/statusPage';

const loading = ref(true);
const refreshing = ref(false);
const siteName = ref('HWT');

const overallStatus = ref('operational');
const components = ref([]);
const incidents = ref([]);
const uptime = ref('');
const uptimePercent = ref(null);
const uptimeDays = ref(30);

// Subscribe
const subscribeEmail = ref('');
const subscribed = ref(false);
const subscribing = ref(false);
const subscribeMessage = ref('');

const statusTitle = computed(() => {
    const map = {
        'operational': '所有系统正常运行',
        'degraded_performance': '部分系统性能下降',
        'partial_outage': '部分系统中断',
        'major_outage': '重大系统中断',
    };
    return map[overallStatus.value] || '系统状态未知';
});

const statusDescription = computed(() => {
    const map = {
        'operational': '所有服务均正常运行',
        'degraded_performance': '部分服务响应时间变长',
        'partial_outage': '部分客户可能无法访问某些功能',
        'major_outage': '主要服务不可用',
    };
    return map[overallStatus.value] || '';
});

const uptimeTag = computed(() => {
    const v = uptimePercent.value;
    if (v === null) return 'info';
    return v >= 99.9 ? 'success' : v >= 99.0 ? 'warning' : 'danger';
});

const groupedComponents = computed(() => {
    const groups = {};
    for (const comp of components.value) {
        const key = comp.group || '_ungrouped';
        if (!groups[key]) groups[key] = [];
        groups[key].push(comp);
    }
    return groups;
});

function groupLabel(key) {
    const map = {
        'core': '核心服务',
        'services': '业务服务',
        'infrastructure': '基础设施',
        'third_party': '第三方服务',
    };
    return map[key] || key;
}

function statusLabel(status) {
    const map = {
        'operational': '正常',
        'degraded_performance': '性能下降',
        'partial_outage': '部分中断',
        'major_outage': '重大中断',
        'unknown': '未知',
        'investigating': '调查中',
        'identified': '已确认',
        'monitoring': '监控中',
        'resolved': '已解决',
        'postmortem': '事后分析',
    };
    return map[status] || status;
}

function formatTime(t) {
    if (!t) return '';
    return new Date(t).toLocaleDateString('zh-CN', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

async function fetchStatus() {
    refreshing.value = true;
    try {
        const { data: res } = await statusPageApi.getStatus();
        if (res.success) {
            const d = res.data;
            overallStatus.value = d.overall_status;
            components.value = d.components || [];
            incidents.value = d.incidents || [];
            uptime.value = d.uptime || '';
        }
    } catch {
        ElMessage.error('获取状态失败');
    } finally {
        loading.value = false;
        refreshing.value = false;
    }

    // Fetch history for uptime %
    try {
        const { data: histRes } = await statusPageApi.getHistory({ days: 30 });
        if (histRes.success) {
            uptimePercent.value = histRes.data.uptime_percent;
        }
    } catch { /* ignore */ }
}

async function handleSubscribe() {
    if (!subscribeEmail.value.trim() || subscribed.value) return;
    subscribing.value = true;
    subscribeMessage.value = '';
    try {
        const { data: res } = await statusPageApi.subscribe(subscribeEmail.value.trim());
        if (res.success) {
            subscribed.value = true;
            subscribeMessage.value = '✅ 订阅成功！当系统状态变化时会收到通知。';
        }
    } catch {
        subscribeMessage.value = '❌ 订阅失败，请稍后重试。';
    } finally {
        subscribing.value = false;
    }
}

onMounted(() => {
    fetchStatus();
    // Auto-refresh every 60s
    setInterval(fetchStatus, 60000);
});
</script>

<style scoped>
.status-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding: 40px 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.status-container {
    max-width: 720px;
    margin: 0 auto;
    padding: 0 20px;
}

.status-header { margin-bottom: 24px; }

.brand {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0 0 24px;
}

.overall-status {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px;
    border-radius: 8px;
    color: #fff;
}

.overall-status.operational { background: linear-gradient(135deg, #67c23a, #85ce61); }
.overall-status.degraded_performance { background: linear-gradient(135deg, #e6a23c, #ebb563); }
.overall-status.partial_outage, .overall-status.major_outage { background: linear-gradient(135deg, #f56c6c, #f89898); }

.status-text h2 { margin: 0 0 4px; font-size: 22px; }
.status-text p { margin: 0; font-size: 14px; opacity: 0.9; }

.loading-state { text-align: center; padding: 80px 0; color: #909399; }

.card-header { display: flex; justify-content: space-between; align-items: center; }
.header-right { display: flex; align-items: center; gap: 8px; }
.uptime-tag { font-size: 12px; }

.checks-card { margin-bottom: 16px; }
.checks-list { padding: 4px 0; }
.group-label { font-size: 13px; font-weight: 600; color: #606266; padding: 12px 0 6px; }
.group-label:first-child { padding-top: 0; }

.check-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}
.check-item:last-child { border-bottom: none; }

.check-info { display: flex; align-items: center; gap: 8px; }
.check-name { font-size: 14px; color: #303133; }
.check-desc { font-size: 12px; color: #909399; }
.status-operational { color: #67c23a; }
.status-degraded_performance { color: #e6a23c; }
.status-partial_outage, .status-major_outage { color: #f56c6c; }

.uptime-info {
    display: flex;
    justify-content: space-between;
    padding-top: 12px;
    font-size: 13px;
    color: #909399;
}
.uptime-badge { color: #606266; }

.incidents-card { margin-bottom: 16px; }
.incident-item {
    padding: 16px 0;
    border-bottom: 1px solid #f0f0f0;
}
.incident-item:last-child { border-bottom: none; }

.incident-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.incident-date { font-size: 12px; color: #909399; margin-left: auto; }
.incident-title { margin: 0 0 6px; font-size: 16px; color: #303133; }
.incident-components { font-size: 13px; color: #909399; margin-bottom: 12px; }

.incident-updates { padding-left: 8px; }
.update-item { display: flex; gap: 12px; padding: 8px 0; }
.update-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
}
.dot-investigating { background: #e6a23c; }
.dot-identified { background: #409eff; }
.dot-monitoring { background: #909399; }
.dot-resolved { background: #67c23a; }

.update-content { flex: 1; }
.update-header { display: flex; gap: 8px; align-items: center; margin-bottom: 2px; }
.update-time { font-size: 12px; color: #909399; }
.update-message { margin: 0; font-size: 14px; color: #606266; line-height: 1.5; }

.no-incidents { text-align: center; padding: 40px; color: #909399; }
.no-incidents p { margin-top: 12px; }

.subscribe-card { margin-bottom: 16px; }
.subscribe-form { text-align: center; padding: 12px; }
.subscribe-form h3 { margin: 0 0 4px; font-size: 16px; }
.subscribe-form p { margin: 0 0 16px; color: #909399; font-size: 13px; }
.subscribe-input { display: flex; gap: 8px; max-width: 400px; margin: 0 auto; }
.subscribe-message { margin-top: 8px !important; font-size: 14px !important; }

.status-footer { text-align: center; padding: 20px 0; font-size: 13px; color: #909399; }
</style>
