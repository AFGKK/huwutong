<template>
    <div class="seat-pool-page">
        <!-- 页面标题 + 导航 -->
        <div class="page-header">
            <el-button text @click="goBack">
                <el-icon><ArrowLeft /></el-icon> 返回 License 列表
            </el-button>
            <h2>席位池管理 <small class="text-gray-400 ml-2">{{ license?.license_key }}</small></h2>
        </div>

        <el-loading v-model:loading="loading" :text="'加载中...'">
            <template v-if="poolStatus">
                <!-- 席位概览卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-label">席位模式</div>
                            <div class="stat-value">{{ modeLabel }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-label">席位利用率</div>
                            <div class="stat-value">{{ poolStatus.utilization_percent }}%</div>
                            <el-progress :percentage="poolStatus.utilization_percent" :stroke-width="6" />
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card stat-active">
                            <div class="stat-label">活跃 / 总席位</div>
                            <div class="stat-value">{{ poolStatus.active }} <small>/ {{ poolStatus.total_seats }}</small></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card stat-warning">
                            <div class="stat-label">
                                排队等待
                                <el-tag v-if="poolStatus.waiting_queue > 0" type="warning" size="small" class="ml-1">
                                    {{ poolStatus.waiting_queue }}
                                </el-tag>
                            </div>
                            <div class="stat-value">{{ poolStatus.available }}</div>
                            <div class="stat-label text-muted">可用席位</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 操作栏 -->
                <el-card class="mb-4">
                    <div class="flex items-center gap-2">
                        <el-button type="primary" @click="showAssignDialog = true">
                            <el-icon><Plus /></el-icon> 手动分配席位
                        </el-button>
                        <el-button @click="showConfigDialog = true">
                            <el-icon><Setting /></el-icon> 池配置
                        </el-button>
                        <el-button @click="handleBatchReleaseExpired">
                            <el-icon><Refresh /></el-icon> 清理过期席位
                        </el-button>
                        <el-button @click="loadPoolData" :loading="loading">
                            <el-icon><Refresh /></el-icon> 刷新
                        </el-button>
                    </div>
                </el-card>

                <!-- Tab: 活跃席位 / 排队队列 -->
                <el-card>
                    <el-tabs v-model="activeTab">
                        <el-tab-pane label="席位分配" name="assignments">
                            <el-table :data="assignments" v-loading="loadingAssignments" stripe>
                                <el-table-column prop="seat_identifier" label="席位标识" min-width="150" />
                                <el-table-column prop="label" label="备注" width="180" />
                                <el-table-column prop="status" label="状态" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                            {{ row.status === 'active' ? '活跃' : '已释放' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="设备" width="200">
                                    <template #default="{ row }">
                                        <span v-if="row.device">{{ row.device.hostname || row.device.fingerprint }}</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="assigned_at" label="分配时间" width="170">
                                    <template #default="{ row }">{{ formatTime(row.assigned_at) }}</template>
                                </el-table-column>
                                <el-table-column prop="last_active_at" label="最后活跃" width="170">
                                    <template #default="{ row }">{{ formatTime(row.last_active_at) || '-' }}</template>
                                </el-table-column>
                                <el-table-column label="操作" width="120" fixed="right">
                                    <template #default="{ row }">
                                        <el-button v-if="row.status === 'active'" size="small" type="danger" text
                                            @click="handleRelease(row)">
                                            释放
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

                        <el-tab-pane label="排队队列" name="queue">
                            <el-table :data="queueData" v-loading="loadingQueue" stripe>
                                <el-table-column prop="queue_position" label="#排队" width="70" align="center" />
                                <el-table-column prop="seat_identifier" label="席位标识" min-width="150" />
                                <el-table-column prop="label" label="备注" width="180" />
                                <el-table-column prop="status" label="状态" width="100">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.status === 'waiting'" type="warning" size="small">等待中</el-tag>
                                        <el-tag v-else-if="row.status === 'assigned'" type="success" size="small">已分配</el-tag>
                                        <el-tag v-else-if="row.status === 'cancelled'" type="info" size="small">已取消</el-tag>
                                        <el-tag v-else-if="row.status === 'expired'" type="danger" size="small">已过期</el-tag>
                                        <span v-else>{{ row.status }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="expires_at" label="过期时间" width="170">
                                    <template #default="{ row }">{{ formatTime(row.expires_at) || '-' }}</template>
                                </el-table-column>
                                <el-table-column prop="created_at" label="创建时间" width="170">
                                    <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </template>
        </el-loading>

        <!-- 手动分配对话框 -->
        <el-dialog v-model="showAssignDialog" title="手动分配席位" width="500px">
            <el-form :model="assignForm" label-width="120px" ref="assignFormRef">
                <el-form-item label="席位标识" prop="seat_identifier" required>
                    <el-input v-model="assignForm.seat_identifier" placeholder="设备指纹或用户ID" />
                </el-form-item>
                <el-form-item label="备注" prop="label">
                    <el-input v-model="assignForm.label" placeholder="席位名称/备注" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">取消</el-button>
                <el-button type="primary" @click="handleAssign" :loading="assigning">分配</el-button>
            </template>
        </el-dialog>

        <!-- 池配置对话框 -->
        <el-dialog v-model="showConfigDialog" title="席位池配置" width="550px">
            <el-form :model="configForm" label-width="140px" ref="configFormRef">
                <el-form-item label="席位池模式" prop="pool_mode">
                    <el-radio-group v-model="configForm.pool_mode">
                        <el-radio value="shared">共享模式 - N个席位共用，先到先得</el-radio>
                        <el-radio value="exclusive">独占模式 - 每个设备独占一个席位</el-radio>
                        <el-radio value="auto">自动排队 - 超限后自动排队等待</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="自动回收超时(分)" prop="pool_timeout_minutes">
                    <el-input-number v-model="configForm.pool_timeout_minutes" :min="1" :max="1440" />
                    <span class="ml-2 text-gray-400">超过此时间无心跳的席位自动释放</span>
                </el-form-item>
                <el-form-item v-if="configForm.pool_mode === 'auto'" label="排队队列上限" prop="pool_waiting_limit">
                    <el-input-number v-model="configForm.pool_waiting_limit" :min="1" :max="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">取消</el-button>
                <el-button type="primary" @click="handleUpdateConfig" :loading="configSaving">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseApi from '@/api/license';

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

const modeLabel = computed(() => {
    const map = { shared: '共享模式', exclusive: '独占模式', auto: '自动排队' };
    return map[poolStatus.value?.pool_mode] || poolStatus.value?.pool_mode || '-';
});

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
        // 获取License基本信息
        const licenseRes = await licenseApi.show(licenseId.value);
        license.value = licenseRes.data?.data;

        // 获取池状态
        const statusRes = await licenseApi.poolStatus(licenseId.value);
        poolStatus.value = statusRes.data?.data;

        // 填充配置表单
        configForm.value.pool_mode = poolStatus.value.pool_mode || 'shared';
        configForm.value.pool_timeout_minutes = poolStatus.value.timeout_minutes || 30;
        configForm.value.pool_waiting_limit = poolStatus.value.waiting_limit || 50;
    } catch (err) {
        ElMessage.error('加载席位池数据失败');
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
        ElMessage.success('席位分配成功');
        showAssignDialog.value = false;
        assignForm.value = { seat_identifier: '', label: '' };
        await loadPoolData();
        await loadAssignments();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '分配失败');
    } finally {
        assigning.value = false;
    }
}

async function handleRelease(row) {
    try {
        await ElMessageBox.confirm(`确认释放席位 "${row.seat_identifier}"？`, '确认释放');
        await licenseApi.poolRelease(licenseId.value, { assignment_id: row.id });
        ElMessage.success('席位已释放');
        await loadPoolData();
        await loadAssignments();
    } catch { }
}

async function handleUpdateConfig() {
    configSaving.value = true;
    try {
        await licenseApi.poolUpdateConfig(licenseId.value, configForm.value);
        ElMessage.success('席位池配置已更新');
        showConfigDialog.value = false;
        await loadPoolData();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '配置更新失败');
    } finally {
        configSaving.value = false;
    }
}

async function handleBatchReleaseExpired() {
    try {
        await ElMessageBox.confirm('确认清理所有过期未活跃的席位？', '确认清理');
        const res = await licenseApi.poolBatchReleaseExpired();
        ElMessage.success(`已清理 ${res.data?.data?.licenses_affected || 0} 个License的过期席位`);
        await loadPoolData();
        await loadAssignments();
    } catch { }
}

// 切换tab时加载数据
import { watch } from 'vue';
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
