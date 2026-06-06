<template>
    <div class="status-page">
        <div class="status-container">
            <!-- Header -->
            <div class="status-header">
                <h1 class="brand">{{ settings.site_name || 'HWT' }} 系统状态</h1>
                <div class="overall-status" :class="overallStatus">
                    <el-icon :size="48">
                        <CircleCheck v-if="overallStatus === 'operational'" />
                        <WarningFilled v-else />
                    </el-icon>
                    <div class="status-text">
                        <h2>{{ statusLabel }}</h2>
                        <p>{{ overallStatus === 'operational' ? '所有系统正常运行' : '部分系统出现异常' }}</p>
                    </div>
                </div>
            </div>

            <!-- Service Checks -->
            <el-card class="checks-card" shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>服务状态</span>
                        <el-button text size="small" @click="fetchStatus" :loading="loading">
                            刷新
                        </el-button>
                    </div>
                </template>

                <div class="checks-list">
                    <div v-for="(check, name) in statusData.checks" :key="name" class="check-item">
                        <div class="check-info">
                            <el-icon :size="18" :class="check.status">
                                <CircleCheck v-if="check.status === 'operational'" />
                                <CloseBold v-else />
                            </el-icon>
                            <span class="check-name">{{ check.description }}</span>
                        </div>
                        <el-tag :type="check.status === 'operational' ? 'success' : 'danger'" size="small">
                            {{ check.status === 'operational' ? '正常' : '异常' }}
                        </el-tag>
                    </div>
                </div>

                <div class="uptime-info">
                    运行时长：<strong>{{ statusData.uptime }}</strong>
                </div>
            </el-card>

            <!-- Incident Timeline -->
            <el-card class="incidents-card" shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>事件时间线</span>
                    </div>
                </template>

                <div v-if="statusData.incidents?.length === 0" class="no-incidents">
                    <el-empty description="近期无事件记录" :image-size="60" />
                </div>

                <div v-else class="incident-timeline">
                    <div v-for="incident in statusData.incidents" :key="incident.id" class="incident-item">
                        <div class="incident-dot" :class="incident.severity" />
                        <div class="incident-content">
                            <div class="incident-header">
                                <span class="incident-title">{{ incident.title }}</span>
                                <el-tag
                                    :type="incident.severity === 'critical' ? 'danger' : incident.severity === 'major' ? 'warning' : 'info'"
                                    size="small"
                                >
                                    {{ severityLabel(incident.severity) }}
                                </el-tag>
                            </div>
                            <p class="incident-desc">{{ incident.description }}</p>
                            <div class="incident-meta">
                                <el-tag size="small" type="default" effect="plain">
                                    {{ statusLabel(incident.status) }}
                                </el-tag>
                                <span class="incident-time">{{ formatTime(incident.reported_at) }}</span>
                            </div>
                            <div v-if="incident.updates?.length" class="incident-updates">
                                <div v-for="(update, idx) in incident.updates" :key="idx" class="update-item">
                                    <span class="update-badge">{{ statusLabel(update.status) }}</span>
                                    <span class="update-msg">{{ update.message }}</span>
                                    <span class="update-time">{{ formatTime(update.updated_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </el-card>

            <!-- Subscribe -->
            <el-card class="subscribe-card" shadow="never">
                <template #header>
                    <div class="card-header">
                        <span>订阅通知</span>
                    </div>
                </template>
                <p class="subscribe-desc">当系统出现异常或恢复时，第一时间通过邮件通知您。</p>
                <el-form :model="subscribeForm" @submit.prevent="handleSubscribe" class="subscribe-form">
                    <el-input
                        v-model="subscribeForm.email"
                        placeholder="输入您的邮箱地址"
                        :prefix-icon="Message"
                    />
                    <el-button type="primary" @click="handleSubscribe" :loading="subscribing">
                        订阅
                    </el-button>
                </el-form>
            </el-card>

            <!-- Footer -->
            <div class="status-footer">
                <p>{{ new Date().getFullYear() }} {{ settings.site_name || 'HWT License' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { CircleCheck, WarningFilled, CloseBold, Message } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const subscribing = ref(false);
const statusData = reactive({
    status: 'operational',
    checks: {},
    uptime: '',
    incidents: [],
});
const settings = reactive({
    site_name: 'HWT',
});

const overallStatus = computed(() => statusData.status || 'operational');

const statusLabel = computed(() => (status) => {
    const map = {
        operational: '正常运行',
        degraded: '部分降级',
        down: '服务中断',
        investigating: '调查中',
        identified: '已确认',
        monitoring: '监控中',
        resolved: '已解决',
    };
    return (status ? map[status] : map[statusData.status]) || status;
});

function severityLabel(severity) {
    const map = { critical: '严重', major: '主要', minor: '轻微' };
    return map[severity] || severity;
}

function formatTime(time) {
    if (!time) return '';
    const date = new Date(time);
    return date.toLocaleString('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const subscribeForm = reactive({
    email: '',
});

async function fetchStatus() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/status');
        if (data) {
            Object.assign(statusData, data);
        }
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
}

async function handleSubscribe() {
    if (!subscribeForm.email) {
        ElMessage.warning('请输入邮箱地址');
        return;
    }
    subscribing.value = true;
    try {
        await axios.post('/api/status/subscribe', { email: subscribeForm.email });
        ElMessage.success('订阅成功');
        subscribeForm.email = '';
    } catch {
        ElMessage.error('订阅失败，请稍后重试');
    } finally {
        subscribing.value = false;
    }
}

onMounted(() => {
    fetchStatus();
    // 每 60 秒自动刷新
    setInterval(fetchStatus, 60000);
});
</script>

<style scoped>
.status-page {
    min-height: 100vh;
    background: #f0f2f5;
    display: flex;
    justify-content: center;
    padding: 40px 16px;
}

.status-container {
    max-width: 720px;
    width: 100%;
}

.status-header {
    text-align: center;
    margin-bottom: 32px;
}

.brand {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 24px;
}

.overall-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 24px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.overall-status.operational { color: #67c23a; }
.overall-status.degraded { color: #e6a23c; }
.overall-status.down { color: #f56c6c; }

.status-text {
    text-align: left;
}

.status-text h2 {
    font-size: 24px;
    margin: 0 0 4px;
}

.status-text p {
    color: #909399;
    margin: 0;
    font-size: 14px;
}

.checks-card,
.incidents-card,
.subscribe-card {
    margin-bottom: 20px;
    border-radius: 8px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.check-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.check-item:last-child {
    border-bottom: none;
}

.check-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.check-info .operational { color: #67c23a; }
.check-info .down { color: #f56c6c; }

.check-name {
    font-size: 15px;
    color: #303133;
}

.uptime-info {
    padding: 12px 0 0;
    font-size: 13px;
    color: #909399;
}

.incident-timeline {
    position: relative;
}

.incident-item {
    display: flex;
    gap: 16px;
    padding: 16px 0;
    border-bottom: 1px solid #f0f0f0;
    position: relative;
}

.incident-item:last-child {
    border-bottom: none;
}

.incident-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.incident-dot.critical { background: #f56c6c; }
.incident-dot.major { background: #e6a23c; }
.incident-dot.minor { background: #409eff; }

.incident-content {
    flex: 1;
    min-width: 0;
}

.incident-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.incident-title {
    font-weight: 600;
    font-size: 15px;
    color: #303133;
}

.incident-desc {
    font-size: 14px;
    color: #606266;
    margin: 4px 0;
}

.incident-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}

.incident-time {
    font-size: 12px;
    color: #909399;
}

.incident-updates {
    margin-top: 8px;
    padding-left: 12px;
    border-left: 2px solid #e0e0e0;
}

.update-item {
    font-size: 13px;
    color: #606266;
    margin: 4px 0;
    display: flex;
    gap: 6px;
    align-items: baseline;
}

.update-badge {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 3px;
    background: #ecf5ff;
    color: #409eff;
}

.update-msg {
    flex: 1;
}

.update-time {
    font-size: 11px;
    color: #909399;
    white-space: nowrap;
}

.no-incidents {
    padding: 20px 0;
}

.subscribe-desc {
    font-size: 14px;
    color: #606266;
    margin: 0 0 12px;
}

.subscribe-form {
    display: flex;
    gap: 12px;
}

.subscribe-form .el-input {
    flex: 1;
}

.status-footer {
    text-align: center;
    padding: 24px 0;
    font-size: 13px;
    color: #909399;
}
</style>
