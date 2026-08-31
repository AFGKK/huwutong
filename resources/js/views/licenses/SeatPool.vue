<template>
    <div class="seat-pool-page">
        <!-- 页面标题 + 导航 -->
        <div class="page-header">
            <el-button text @click="goBack">
                <el-icon><ArrowLeft /></el-icon> {{ t('seat_pool_page.back_to_list') }}
            </el-button>
            <h2>{{ t('seat_pool_page.title') }} <small class="text-gray-400 ml-2">{{ license?.license_key }}</small></h2>
        </div>

        <el-loading v-model:loading="loading" :text="t('actions.loading')">
            <template v-if="poolStatus">
                <!-- 席位概览卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-label">{{ t('seat_pool_page.stat_mode') }}</div>
                            <div class="stat-value">{{ modeLabel }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-label">{{ t('seat_pool_page.stat_utilization') }}</div>
                            <div class="stat-value">{{ poolStatus.utilization_percent }}%</div>
                            <el-progress :percentage="poolStatus.utilization_percent" :stroke-width="6" />
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card stat-active">
                            <div class="stat-label">{{ t('seat_pool_page.stat_active_total') }}</div>
                            <div class="stat-value">{{ poolStatus.active }} <small>/ {{ poolStatus.total_seats }}</small></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card stat-warning">
                            <div class="stat-label">
                                {{ t('seat_pool_page.stat_queue_waiting') }}
                                <el-tag v-if="poolStatus.waiting_queue > 0" type="warning" size="small" class="ml-1">
                                    {{ poolStatus.waiting_queue }}
                                </el-tag>
                            </div>
                            <div class="stat-value">{{ poolStatus.available }}</div>
                            <div class="stat-label text-muted">{{ t('seat_pool_page.stat_available') }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 操作栏 -->
                <el-card class="mb-4">
                    <div class="flex items-center gap-2">
                        <el-button type="primary" @click="showAssignDialog = true">
                            <el-icon><Plus /></el-icon> {{ t('seat_pool_page.btn_assign') }}
                        </el-button>
                        <el-button @click="showConfigDialog = true">
                            <el-icon><Setting /></el-icon> {{ t('seat_pool_page.btn_config') }}
                        </el-button>
                        <el-button @click="handleBatchReleaseExpired">
                            <el-icon><Refresh /></el-icon> {{ t('seat_pool_page.btn_cleanup_expired') }}
                        </el-button>
                        <el-button @click="loadPoolData" :loading="loading">
                            <el-icon><Refresh /></el-icon> {{ t('seat_pool_page.refresh') }}
                        </el-button>
                    </div>
                </el-card>

                <!-- Tab: 活跃席位 / 排队队列 -->
                <el-card>
                    <el-tabs v-model="activeTab">
                        <el-tab-pane :label="t('seat_pool_page.tab_assignments')" name="assignments">
                            <el-table :data="assignments" v-loading="loadingAssignments" stripe>
                                <el-table-column prop="seat_identifier" :label="t('seat_pool_page.col_seat_identifier')" min-width="150" />
                                <el-table-column prop="label" :label="t('seat_pool_page.col_label')" width="180" />
                                <el-table-column prop="status" :label="t('licenses_page.status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                            {{ assignmentStatusLabel(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('seat_pool_page.col_device')" width="200">
                                    <template #default="{ row }">
                                        <span v-if="row.device">{{ row.device.hostname || row.device.fingerprint }}</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="assigned_at" :label="t('seat_pool_page.col_assigned_at')" width="170">
                                    <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
                                </el-table-column>
                                <el-table-column prop="last_active_at" :label="t('seat_pool_page.col_last_active')" width="170">
                                    <template #default="{ row }">{{ formatTime(row.last_active_at) || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('licenses_page.col_actions')" width="120" fixed="right">
                                    <template #default="{ row }">
                                        <el-button v-if="row.status === 'active'" size="small" type="danger" text
                                            @click="handleRelease(row)">
                                            {{ t('seat_pool_page.release') }}
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div class="pagination-wrap" v-if="assignmentsMeta">
                                <el-pagination
                                    v-model:current-page="assignmentsMeta.current_page"
                                    :page-size="assignmentsMeta.per_page"
                                    :total="assignmentsMeta.total"
                                    layout="total, prev, pager, next"
                                    @current-change="loadAssignments"
                                />
                            </div>
                        </el-tab-pane>

                        <el-tab-pane :label="t('seat_pool_page.tab_queue')" name="queue">
                            <el-table :data="queueData" v-loading="loadingQueue" stripe>
                                <el-table-column prop="queue_position" :label="t('seat_pool_page.col_queue_position')" width="70" align="center" />
                                <el-table-column prop="seat_identifier" :label="t('seat_pool_page.col_seat_identifier')" min-width="150" />
                                <el-table-column prop="label" :label="t('seat_pool_page.col_label')" width="180" />
                                <el-table-column prop="status" :label="t('licenses_page.status')" width="100">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.status === 'waiting'" type="warning" size="small">{{ queueStatusLabels.waiting }}</el-tag>
                                        <el-tag v-else-if="row.status === 'assigned'" type="success" size="small">{{ queueStatusLabels.assigned }}</el-tag>
                                        <el-tag v-else-if="row.status === 'cancelled'" type="info" size="small">{{ queueStatusLabels.cancelled }}</el-tag>
                                        <el-tag v-else-if="row.status === 'expired'" type="danger" size="small">{{ queueStatusLabels.expired }}</el-tag>
                                        <span v-else>{{ row.status }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="expires_at" :label="t('licenses_page.col_expires_at')" width="170">
                                    <template #default="{ row }">{{ formatTime(row.expires_at) || '-' }}</template>
                                </el-table-column>
                                <el-table-column prop="created_at" :label="t('licenses_page.created_at')" width="170">
                                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </template>
        </el-loading>

        <!-- 手动分配对话框 -->
        <el-dialog v-model="showAssignDialog" :title="t('seat_pool_page.assign_dialog_title')" width="500px">
            <el-form :model="assignForm" label-width="120px" ref="assignFormRef">
                <el-form-item :label="t('seat_pool_page.col_seat_identifier')" prop="seat_identifier" required>
                    <el-input v-model="assignForm.seat_identifier" :placeholder="t('seat_pool_page.seat_identifier_ph')" />
                </el-form-item>
                <el-form-item :label="t('seat_pool_page.col_label')" prop="label">
                    <el-input v-model="assignForm.label" :placeholder="t('seat_pool_page.label_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleAssign" :loading="assigning">{{ t('seat_pool_page.assign') }}</el-button>
            </template>
        </el-dialog>

        <!-- 池配置对话框 -->
        <el-dialog v-model="showConfigDialog" :title="t('seat_pool_page.config_dialog_title')" width="550px">
            <el-form :model="configForm" label-width="140px" ref="configFormRef">
                <el-form-item :label="t('seat_pool_page.pool_mode')" prop="pool_mode">
                    <el-radio-group v-model="configForm.pool_mode">
                        <el-radio v-for="opt in poolModeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('seat_pool_page.timeout_minutes')" prop="pool_timeout_minutes">
                    <el-input-number v-model="configForm.pool_timeout_minutes" :min="1" :max="1440" />
                    <span class="ml-2 text-gray-400">{{ t('seat_pool_page.timeout_hint') }}</span>
                </el-form-item>
                <el-form-item v-if="configForm.pool_mode === 'auto'" :label="t('seat_pool_page.queue_limit')" prop="pool_waiting_limit">
                    <el-input-number v-model="configForm.pool_waiting_limit" :min="1" :max="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpdateConfig" :loading="configSaving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseApi from '@/api/license';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const licenseId = computed(() => Number(route.params.id));
const loading = ref(false);
const license = ref(null);
const poolStatus = ref(null);
const activeTab = ref('assignments');

// 席位列席
const assignments = ref([]);
const assignmentsMeta = ref(null);
const loadingAssignments = ref(false);

// 排队队列
const queueData = ref([]);
const loadingQueue = ref(false);

// 分配对话框
const showAssignDialog = ref(false);
const assignForm = ref({ seat_identifier: '', label: '' });
const assigning = ref(false);
const assignFormRef = ref(null);

// 配置对话框
const showConfigDialog = ref(false);
const configForm = ref({ pool_mode: 'shared', pool_timeout_minutes: 30, pool_waiting_limit: 50 });
const configSaving = ref(false);
const configFormRef = ref(null);

const modeLabels = computed(() => ({
    shared: t('seat_pool_page.mode_shared'),
    exclusive: t('seat_pool_page.mode_exclusive'),
    auto: t('seat_pool_page.mode_auto'),
}));

const modeLabel = computed(() => {
    const mode = poolStatus.value?.pool_mode;
    return modeLabels.value[mode] || mode || '-';
});

const assignmentStatusLabels = computed(() => ({
    active: t('seat_pool_page.status_active'),
    released: t('seat_pool_page.status_released'),
}));

const queueStatusLabels = computed(() => ({
    waiting: t('seat_pool_page.queue_waiting'),
    assigned: t('seat_pool_page.queue_assigned'),
    cancelled: t('seat_pool_page.queue_cancelled'),
    expired: t('seat_pool_page.queue_expired'),
}));

const poolModeOptions = computed(() => [
    { value: 'shared', label: t('seat_pool_page.mode_shared_option') },
    { value: 'exclusive', label: t('seat_pool_page.mode_exclusive_option') },
    { value: 'auto', label: t('seat_pool_page.mode_auto_option') },
]);

function assignmentStatusLabel(status) {
    return assignmentStatusLabels.value[status] || status;
}

function formatTime(val) {
    if (!val) return null;
    try {
        const d = new Date(val);
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch { return val; }
}

function goBack() {
    router.push('/licenses');
}

async function loadPoolData() {
    loading.value = true;
    try {
        const licenseRes = await licenseApi.show(licenseId.value);
        license.value = licenseRes.data?.data;

        const statusRes = await licenseApi.poolStatus(licenseId.value);
        poolStatus.value = statusRes.data?.data;

        configForm.value.pool_mode = poolStatus.value.pool_mode || 'shared';
        configForm.value.pool_timeout_minutes = poolStatus.value.timeout_minutes || 30;
        configForm.value.pool_waiting_limit = poolStatus.value.waiting_limit || 50;
    } catch (err) {
        ElMessage.error(t('seat_pool_page.messages.load_failed'));
    } finally {
        loading.value = false;
    }
}

async function loadAssignments(page = 1) {
    loadingAssignments.value = true;
    try {
        const res = await licenseApi.poolAssignments(licenseId.value, { page, per_page: 20 });
        assignments.value = res.data?.data || [];
        assignmentsMeta.value = res.data?.meta;
    } catch { } finally {
        loadingAssignments.value = false;
    }
}

async function loadQueue() {
    loadingQueue.value = true;
    try {
        const res = await licenseApi.poolQueue(licenseId.value);
        queueData.value = res.data?.data || [];
    } catch { } finally {
        loadingQueue.value = false;
    }
}

async function handleAssign() {
    assigning.value = true;
    try {
        await licenseApi.poolAssign(licenseId.value, assignForm.value);
        ElMessage.success(t('seat_pool_page.messages.assign_ok'));
        showAssignDialog.value = false;
        assignForm.value = { seat_identifier: '', label: '' };
        await loadPoolData();
        await loadAssignments();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('seat_pool_page.messages.assign_fail'));
    } finally {
        assigning.value = false;
    }
}

async function handleRelease(row) {
    try {
        await ElMessageBox.confirm(
            t('seat_pool_page.confirm_release', { id: row.seat_identifier }),
            t('seat_pool_page.confirm_release_title'),
        );
        await licenseApi.poolRelease(licenseId.value, { assignment_id: row.id });
        ElMessage.success(t('seat_pool_page.messages.released'));
        await loadPoolData();
        await loadAssignments();
    } catch { }
}

async function handleUpdateConfig() {
    configSaving.value = true;
    try {
        await licenseApi.poolUpdateConfig(licenseId.value, configForm.value);
        ElMessage.success(t('seat_pool_page.messages.config_ok'));
        showConfigDialog.value = false;
        await loadPoolData();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('seat_pool_page.messages.config_fail'));
    } finally {
        configSaving.value = false;
    }
}

async function handleBatchReleaseExpired() {
    try {
        await ElMessageBox.confirm(
            t('seat_pool_page.confirm_cleanup'),
            t('seat_pool_page.confirm_cleanup_title'),
        );
        const res = await licenseApi.poolBatchReleaseExpired();
        ElMessage.success(t('seat_pool_page.messages.cleanup_ok', {
            n: res.data?.data?.licenses_affected || 0,
        }));
        await loadPoolData();
        await loadAssignments();
    } catch { }
}

watch(activeTab, (tab) => {
    if (tab === 'assignments') loadAssignments();
    if (tab === 'queue') loadQueue();
});

onMounted(async () => {
    await loadPoolData();
    await loadAssignments();
    await loadQueue();
});
</script>

<style scoped>
.seat-pool-page { padding: 20px; }
.page-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.stat-card { cursor: default; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; }
.stat-card .stat-value small { font-size: 14px; font-weight: 400; color: #909399; }
.stat-active .stat-value { color: #67c23a; }
.stat-warning .stat-value { color: #e6a23c; }
.stat-danger .stat-value { color: #f56c6c; }
.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 8px; }
.ml-1 { margin-left: 4px; }
.ml-2 { margin-left: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-gray-400 { color: #909399; }
.text-muted { font-size: 12px; color: #c0c4cc; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
</style>
