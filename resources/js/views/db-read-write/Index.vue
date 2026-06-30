<template>
    <div class="db-rw-container">
        <el-page-header :content="'读写分离 & 缓存预热'" @back="$router.push('/admin/dashboard')" />

        <el-alert title="管理 MySQL 读写分离和 Redis 缓存预热。读写分离通过从库分担读负载；缓存预热在低峰期预加载热点数据。" type="info" show-icon :closable="false" class="alert-info" />

        <el-tabs v-model="activeTab">
            <!-- 读写分离 -->
            <el-tab-pane label="读写分离" name="readwrite">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>读写分离状态</span></template>
                            <el-descriptions :column="1" border size="small">
                                <el-descriptions-item label="启用状态">
                                    <el-tag :type="rwStatus.enabled ? 'success' : 'danger'">{{ rwStatus.enabled ? '已启用' : '未启用' }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="从库连接">{{ rwStatus.replica_connection }}</el-descriptions-item>
                                <el-descriptions-item label="读流量分配">{{ rwStatus.read_percent }}% → 从库</el-descriptions-item>
                                <el-descriptions-item label="从库健康">
                                    <el-tag :type="rwStatus.replica_healthy ? 'success' : 'danger'">{{ rwStatus.replica_healthy ? '正常' : '异常' }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="失败计数">{{ rwStatus.failure_count }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="actions">
                                <el-button size="small" type="primary" :loading="checking" @click="handleHealthCheck">健康检查</el-button>
                                <el-button size="small" type="warning" @click="handleResetBreaker">重置熔断器</el-button>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>从库健康详情</span></template>
                            <el-descriptions :column="1" border size="small" v-if="rwStatus.health">
                                <el-descriptions-item label="延迟">{{ rwStatus.health.lag_seconds ?? '-' }} 秒</el-descriptions-item>
                                <el-descriptions-item label="IO 线程">
                                    <el-tag :type="rwStatus.health.io_running === 'Yes' ? 'success' : 'danger'">{{ rwStatus.health.io_running }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="SQL 线程">
                                    <el-tag :type="rwStatus.health.sql_running === 'Yes' ? 'success' : 'danger'">{{ rwStatus.health.sql_running }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="最近检查">{{ rwStatus.health.checked_at || '-' }}</el-descriptions-item>
                            </el-descriptions>
                            <el-empty v-else description="暂无健康数据" :image-size="60" />
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 缓存预热 -->
            <el-tab-pane label="缓存预热" name="warmup">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>预热状态</span></template>
                            <el-descriptions :column="1" border size="small">
                                <el-descriptions-item label="启用">
                                    <el-tag :type="cacheStatus.enabled ? 'success' : 'danger'">{{ cacheStatus.enabled ? '已启用' : '未启用' }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="当前状态">
                                    <el-tag :type="cacheStatus.is_running ? 'warning' : 'success'">{{ cacheStatus.is_running ? '运行中' : '空闲' }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="定时计划">{{ cacheStatus.schedule }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="actions">
                                <el-button size="small" type="primary" :loading="warming" @click="handleWarmup('')">全量预热</el-button>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>缓存数据统计</span></template>
                            <el-table :data="cacheStatsTable" stripe size="small" v-if="cacheStatsTable.length">
                                <el-table-column prop="name" label="数据源" />
                                <el-table-column prop="count" label="缓存条目" />
                                <el-table-column label="操作" width="100">
                                    <template #default="{ row }">
                                        <el-button size="small" @click="handleWarmup(row.name)">预热</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else description="暂无数据" :image-size="60" />
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 上次运行 -->
                <el-card class="last-run-card" v-if="cacheStatus.last_run">
                    <template #header><span>上次预热结果</span></template>
                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item label="时间">{{ cacheStatus.last_run.completed_at }}</el-descriptions-item>
                        <el-descriptions-item label="加载">{{ cacheStatus.last_run.total_loaded }} 条</el-descriptions-item>
                        <el-descriptions-item label="耗时">{{ cacheStatus.last_run.elapsed_seconds }} 秒</el-descriptions-item>
                    </el-descriptions>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import dbReadWrite from '@/api/dbReadWrite';

const activeTab = ref('readwrite');
const checking = ref(false);
const warming = ref(false);

const rwStatus = reactive({
    enabled: false, read_percent: 0, replica_connection: '',
    replica_healthy: false, failure_count: 0, health: null, config: {},
});
const cacheStatus = reactive({
    enabled: false, is_running: false, last_run: null, schedule: '', stats: {},
});
const cacheStatsTable = computed(() => {
    return Object.entries(cacheStatus.stats || {}).map(([name, count]) => ({ name, count }));
});

async function loadRwStatus() {
    try { const res = await dbReadWrite.status(); Object.assign(rwStatus, res.data.data); } catch {}
}
async function loadCacheStatus() {
    try { const res = await dbReadWrite.cacheStatus(); Object.assign(cacheStatus, res.data.data); } catch {}
}
async function handleHealthCheck() {
    checking.value = true;
    try { await dbReadWrite.healthCheck(); ElMessage.success('健康检查完成'); loadRwStatus(); } catch {} finally { checking.value = false; }
}
async function handleResetBreaker() {
    try { await dbReadWrite.resetCircuitBreaker(); ElMessage.success('熔断器已重置'); loadRwStatus(); } catch {}
}
async function handleWarmup(source) {
    warming.value = true;
    try {
        const res = await dbReadWrite.triggerWarmup(source);
        ElMessage.success(`预热完成: ${res.data.data.total_loaded} 条`);
        loadCacheStatus();
    } catch {} finally { warming.value = false; }
}

onMounted(() => { loadRwStatus(); loadCacheStatus(); });
</script>

<style scoped>
.db-rw-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.actions { margin-top: 16px; }
.last-run-card { margin-top: 16px; }
</style>
