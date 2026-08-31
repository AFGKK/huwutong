<template>
    <div class="db-rw-container">
        <el-page-header :content="t('db_read_write_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert :title="t('db_read_write_page.info_alert')" type="info" show-icon :closable="false" class="alert-info" />

        <el-tabs v-model="activeTab">
            <!-- 读写分离 -->
            <el-tab-pane :label="tabLabels.readwrite" name="readwrite">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t('db_read_write_page.rw.status_title') }}</span></template>
                            <el-descriptions :column="1" border size="small">
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_enabled')">
                                    <el-tag :type="rwStatus.enabled ? 'success' : 'danger'">{{ rwStatus.enabled ? statusLabels.enabled : statusLabels.disabled }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_replica_connection')">{{ rwStatus.replica_connection }}</el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_read_percent')">{{ t('db_read_write_page.rw.read_percent_value', { percent: rwStatus.read_percent }) }}</el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_replica_health')">
                                    <el-tag :type="rwStatus.replica_healthy ? 'success' : 'danger'">{{ rwStatus.replica_healthy ? statusLabels.healthy : statusLabels.unhealthy }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_failure_count')">{{ rwStatus.failure_count }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="actions">
                                <el-button size="small" type="primary" :loading="checking" @click="handleHealthCheck">{{ t('db_read_write_page.rw.btn_health_check') }}</el-button>
                                <el-button size="small" type="warning" @click="handleResetBreaker">{{ t('db_read_write_page.rw.btn_reset_breaker') }}</el-button>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t('db_read_write_page.rw.health_detail_title') }}</span></template>
                            <el-descriptions :column="1" border size="small" v-if="rwStatus.health">
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_lag')">
                                    {{ rwStatus.health.lag_seconds != null ? t('db_read_write_page.unit_seconds', { n: rwStatus.health.lag_seconds }) : '-' }}
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_io_thread')">
                                    <el-tag :type="rwStatus.health.io_running === 'Yes' ? 'success' : 'danger'">{{ rwStatus.health.io_running }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_sql_thread')">
                                    <el-tag :type="rwStatus.health.sql_running === 'Yes' ? 'success' : 'danger'">{{ rwStatus.health.sql_running }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.rw.label_last_check')">{{ rwStatus.health.checked_at || '-' }}</el-descriptions-item>
                            </el-descriptions>
                            <el-empty v-else :description="t('db_read_write_page.rw.no_health_data')" :image-size="60" />
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 缓存预热 -->
            <el-tab-pane :label="tabLabels.warmup" name="warmup">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t('db_read_write_page.warmup.status_title') }}</span></template>
                            <el-descriptions :column="1" border size="small">
                                <el-descriptions-item :label="t('db_read_write_page.warmup.label_enabled')">
                                    <el-tag :type="cacheStatus.enabled ? 'success' : 'danger'">{{ cacheStatus.enabled ? statusLabels.enabled : statusLabels.disabled }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.warmup.label_current_status')">
                                    <el-tag :type="cacheStatus.is_running ? 'warning' : 'success'">{{ cacheStatus.is_running ? warmupStatusLabels.running : warmupStatusLabels.idle }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('db_read_write_page.warmup.label_schedule')">{{ cacheStatus.schedule }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="actions">
                                <el-button size="small" type="primary" :loading="warming" @click="handleWarmup('')">{{ t('db_read_write_page.warmup.btn_full_warmup') }}</el-button>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card>
                            <template #header><span>{{ t('db_read_write_page.warmup.stats_title') }}</span></template>
                            <el-table :data="cacheStatsTable" stripe size="small" v-if="cacheStatsTable.length">
                                <el-table-column prop="name" :label="t('db_read_write_page.warmup.col_source')" />
                                <el-table-column prop="count" :label="t('db_read_write_page.warmup.col_entries')" />
                                <el-table-column :label="t('db_read_write_page.warmup.col_actions')" width="100">
                                    <template #default="{ row }">
                                        <el-button size="small" @click="handleWarmup(row.name)">{{ t('db_read_write_page.warmup.btn_warmup') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 上次运行 -->
                <el-card class="last-run-card" v-if="cacheStatus.last_run">
                    <template #header><span>{{ t('db_read_write_page.warmup.last_run_title') }}</span></template>
                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item :label="t('db_read_write_page.warmup.label_time')">{{ cacheStatus.last_run.completed_at }}</el-descriptions-item>
                        <el-descriptions-item :label="t('db_read_write_page.warmup.label_loaded')">{{ t('db_read_write_page.unit_entries', { n: cacheStatus.last_run.total_loaded }) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('db_read_write_page.warmup.label_elapsed')">{{ t('db_read_write_page.unit_seconds', { n: cacheStatus.last_run.elapsed_seconds }) }}</el-descriptions-item>
                    </el-descriptions>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import dbReadWrite from '@/api/dbReadWrite';

const { t } = useI18n();

const activeTab = ref('readwrite');
const checking = ref(false);
const warming = ref(false);

const tabLabels = computed(() => ({
    readwrite: t('db_read_write_page.tabs.readwrite'),
    warmup: t('db_read_write_page.tabs.warmup'),
}));

const statusLabels = computed(() => ({
    enabled: t('db_read_write_page.status.enabled'),
    disabled: t('db_read_write_page.status.disabled'),
    healthy: t('db_read_write_page.status.healthy'),
    unhealthy: t('db_read_write_page.status.unhealthy'),
}));

const warmupStatusLabels = computed(() => ({
    running: t('db_read_write_page.warmup.status_running'),
    idle: t('db_read_write_page.warmup.status_idle'),
}));

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
    try { await dbReadWrite.healthCheck(); ElMessage.success(t('db_read_write_page.messages.health_check_done')); loadRwStatus(); } catch {} finally { checking.value = false; }
}
async function handleResetBreaker() {
    try { await dbReadWrite.resetCircuitBreaker(); ElMessage.success(t('db_read_write_page.messages.breaker_reset')); loadRwStatus(); } catch {}
}
async function handleWarmup(source) {
    warming.value = true;
    try {
        const res = await dbReadWrite.triggerWarmup(source);
        ElMessage.success(t('db_read_write_page.messages.warmup_done', { count: res.data.data.total_loaded }));
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
