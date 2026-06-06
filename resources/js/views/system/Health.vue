<template>
    <div class="health-page">
        <div class="page-header">
            <h2>系统健康</h2>
            <div class="header-actions">
                <span class="last-update" v-if="lastUpdate">上次检查: {{ lastUpdate }}</span>
                <el-switch v-model="autoRefresh" active-text="自动刷新" style="margin-right: 12px" />
                <el-button type="primary" @click="fetchStatus" :loading="loading">刷新</el-button>
            </div>
        </div>

        <!-- 整体状态 -->
        <el-card :class="['overall-card', overallStatus]" class="mb-4">
            <div class="overall-content">
                <el-icon :size="48" :color="overallStatus === 'healthy' ? '#67c23a' : '#f56c6c'">
                    <CircleCheckFilled v-if="overallStatus === 'healthy'" />
                    <WarningFilled v-else />
                </el-icon>
                <div>
                    <div class="overall-title">{{ overallStatus === 'healthy' ? '所有系统正常运行' : '部分系统异常' }}</div>
                    <div class="overall-sub">
                        {{ healthyCount }}/{{ checks.length }} 服务正常
                        · 数据库: {{ dbStatus }}
                        · Redis: {{ redisStatus }}
                    </div>
                </div>
            </div>
        </el-card>

        <!-- 服务检查 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="check in checks" :key="check.name">
                <el-card shadow="hover" :class="['health-card', check.status]">
                    <div class="health-content">
                        <el-icon :size="28" :color="check.status === 'healthy' ? '#67c23a' : '#f56c6c'">
                            <CircleCheckFilled v-if="check.status === 'healthy'" />
                            <WarningFilled v-else-if="check.status === 'unhealthy'" />
                            <MoreFilled v-else />
                        </el-icon>
                        <div class="health-info">
                            <div class="health-name">{{ check.label }}</div>
                            <div class="health-ms" v-if="check.latency !== undefined">
                                {{ check.latency }}ms
                            </div>
                            <div class="health-detail" v-if="check.detail">{{ check.detail }}</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 系统信息 -->
        <el-card class="mb-4">
            <template #header>系统信息</template>
            <el-descriptions v-if="details" :column="3" border>
                <el-descriptions-item label="PHP 版本">{{ details.php_version || '-' }}</el-descriptions-item>
                <el-descriptions-item label="Laravel 版本">{{ details.laravel_version || '-' }}</el-descriptions-item>
                <el-descriptions-item label="运行环境">{{ details.app_env || '-' }}</el-descriptions-item>
                <el-descriptions-item label="运行时间">{{ details.uptime || '-' }}</el-descriptions-item>
                <el-descriptions-item label="内存使用">{{ details.memory_usage || '-' }}</el-descriptions-item>
                <el-descriptions-item label="CPU 负载">{{ details.cpu_load || '-' }}</el-descriptions-item>
                <el-descriptions-item label="磁盘使用">{{ details.disk_usage || '-' }}</el-descriptions-item>
                <el-descriptions-item label="时区">{{ details.timezone || '-' }}</el-descriptions-item>
                <el-descriptions-item label="Debug 模式">
                    <el-tag :type="details.debug_mode ? 'danger' : 'success'" size="small">
                        {{ details.debug_mode ? '开启' : '关闭' }}
                    </el-tag>
                </el-descriptions-item>
            </el-descriptions>
        </el-card>

        <!-- API 端点检查 -->
        <el-card>
            <template #header>
                <span>API 端点响应</span>
            </template>
            <el-table :data="endpointChecks" stripe>
                <el-table-column prop="name" label="端点" width="200" />
                <el-table-column prop="url" label="URL" min-width="250" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'ok' ? 'success' : 'danger'" size="small">
                            {{ row.status === 'ok' ? '正常' : '异常' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="latency" label="响应时间" width="100">
                    <template #default="{ row }">
                        <span :class="row.latency > 500 ? 'slow' : row.latency > 200 ? 'warn' : ''">
                            {{ row.latency ? `${row.latency}ms` : '-' }}
                        </span>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import apiClient from '@/api/client';
import { CircleCheckFilled, WarningFilled, MoreFilled } from '@element-plus/icons-vue';

const loading = ref(false);
const autoRefresh = ref(false);
const details = ref(null);
const lastUpdate = ref('');
let refreshTimer = null;

const checks = ref([
    { name: 'database', label: '数据库', status: 'unknown' },
    { name: 'redis', label: 'Redis', status: 'unknown' },
    { name: 'cache', label: '缓存', status: 'unknown' },
    { name: 'queue', label: '队列', status: 'unknown' },
]);

const endpointChecks = ref([
    { name: '存活检查', url: '/api/health/live', status: '-', latency: null },
    { name: '就绪检查', url: '/api/health/ready', status: '-', latency: null },
    { name: '详细状态', url: '/api/health/status', status: '-', latency: null },
]);

const overallStatus = computed(() => {
    return checks.value.every(c => c.status === 'healthy') ? 'healthy' : 'unhealthy';
});

const healthyCount = computed(() => {
    return checks.value.filter(c => c.status === 'healthy').length;
});

const dbStatus = computed(() => {
    const db = checks.value.find(c => c.name === 'database');
    return db?.status === 'healthy' ? '正常' : '异常';
});

const redisStatus = computed(() => {
    const r = checks.value.find(c => c.name === 'redis');
    return r?.status === 'healthy' ? '正常' : '异常';
});

async function fetchStatus() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/health/status');
        const status = res.data || {};
        details.value = status;

        if (status.checks) {
            checks.value.forEach(c => {
                const check = status.checks[c.name];
                c.status = check?.status === 'ok' ? 'healthy' : 'unhealthy';
                c.latency = check?.latency;
                c.detail = check?.detail || '';
            });
        }

        lastUpdate.value = new Date().toLocaleString();
    } catch {
        checks.value.forEach(c => c.status = 'unhealthy');
        lastUpdate.value = new Date().toLocaleString();
    } finally {
        loading.value = false;
    }
}

async function checkEndpoints() {
    for (const ep of endpointChecks.value) {
        const start = performance.now();
        try {
            await apiClient.get(ep.url.replace('/api', ''), { timeout: 5000 });
            ep.status = 'ok';
        } catch {
            ep.status = 'error';
        }
        ep.latency = Math.round(performance.now() - start);
    }
}

watch(autoRefresh, (val) => {
    if (val) {
        refreshTimer = setInterval(() => {
            fetchStatus();
            checkEndpoints();
        }, 30000);
    } else {
        clearInterval(refreshTimer);
    }
});

onMounted(() => {
    fetchStatus();
    checkEndpoints();
});

onUnmounted(() => {
    clearInterval(refreshTimer);
});
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; }
.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.last-update {
    font-size: 12px;
    color: #909399;
}
.mb-4 { margin-bottom: 16px; }

.overall-card {
    border-left: 4px solid #67c23a;
}
.overall-card.unhealthy {
    border-left-color: #f56c6c;
}
.overall-content {
    display: flex;
    align-items: center;
    gap: 16px;
}
.overall-title {
    font-size: 18px;
    font-weight: 600;
    color: #303133;
}
.overall-sub {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.health-card {
    margin-bottom: 16px;
}
.health-card.healthy { border-left: 4px solid #67c23a; }
.health-card.unhealthy { border-left: 4px solid #f56c6c; }
.health-card.unknown { border-left: 4px solid #c0c4cc; }

.health-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.health-info { flex: 1; }
.health-name { font-size: 14px; font-weight: 600; }
.health-ms { font-size: 12px; color: #909399; }
.health-detail {
    font-size: 11px;
    color: #c0c4cc;
    margin-top: 2px;
}

.slow { color: #f56c6c; font-weight: 600; }
.warn { color: #e6a23c; }
</style>