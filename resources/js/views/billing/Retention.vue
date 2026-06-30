<template>
    <div class="retention-page">
        <div class="page-header">
            <div class="header-left">
                <h2>续费失败流水线</h2>
                <span class="header-subtitle">监控自动续费失败情况、处理重试和人工介入</span>
            </div>
            <div class="header-right">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总尝试</div>
                        <div class="stat-value">{{ stats.total_attempts }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总失败</div>
                        <div class="stat-value text-danger">{{ stats.total_failures }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">失败率</div>
                        <div class="stat-value" :class="failureRateClass">{{ stats.failure_rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">待重试</div>
                        <div class="stat-value text-warning">{{ stats.pending_retries }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">待人工介入</div>
                        <div class="stat-value text-danger">{{ stats.escalated_pending }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">近 7 天失败</div>
                        <div class="stat-value text-warning">{{ stats.recent_7d_failures }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tab: 待处理介入 / 查询失败记录 / 续费策略配置 -->
        <el-tabs v-model="activeTab" class="mb-4">
            <el-tab-pane label="待处理人工介入" name="escalations">
                <el-card shadow="never">
                    <el-table :data="escalations" v-loading="loadingEscalations" stripe style="width: 100%">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="id" label="介入 ID" width="80" />
                        <el-table-column label="客户" min-width="140">
                            <template #default="{ row }">
                                {{ row.subscription?.customer?.name || row.subscription?.customer_id || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="订阅" min-width="120">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">
                                    #{{ row.subscription_id }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reason" label="升级原因" min-width="200">
                            <template #default="{ row }">
                                <span class="reason-text">{{ row.reason || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="retry_count" label="已重试次数" width="100" />
                        <el-table-column prop="created_at" label="创建时间" width="170">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button type="primary" size="small" @click="openResolveDialog(row)">
                                    处理
                                </el-button>
                                <el-button size="small" @click="viewSubscriptionFailures(row.subscription_id)">
                                    查看失败
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="escalations.length === 0 && !loadingEscalations" description="暂无待处理介入" :image-size="60" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="订阅失败记录查询" name="query">
                <el-card shadow="never">
                    <div class="query-section">
                        <el-form :inline="true">
                            <el-form-item label="订阅 ID">
                                <el-input v-model="querySubscriptionId" placeholder="输入订阅 ID" style="width: 200px" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="handleQuerySubscription">
                                    <el-icon><Search /></el-icon>
                                    查询
                                </el-button>
                            </el-form-item>
                        </el-form>
                    </div>

                    <div v-if="subscriptionFailures" class="subscription-failures">
                        <h4 class="subsection-title">重试记录</h4>
                        <el-table :data="subscriptionFailures.attempts || []" stripe style="width: 100%" size="small">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="attempt_number" label="重试次数" width="100" />
                            <el-table-column prop="status" label="状态" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                        {{ row.status === 'success' ? '成功' : '失败' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="error_message" label="错误信息" min-width="240" />
                            <el-table-column prop="next_retry_at" label="下次重试" width="170">
                                <template #default="{ row }">
                                    {{ row.next_retry_at ? formatDate(row.next_retry_at) : '-' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="created_at" label="时间" width="170">
                                <template #default="{ row }">
                                    {{ formatDate(row.created_at) }}
                                </template>
                            </el-table-column>
                        </el-table>

                        <h4 class="subsection-title mt-4">升级记录</h4>
                        <el-table :data="subscriptionFailures.escalations || []" stripe style="width: 100%" size="small">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="reason" label="原因" min-width="200" />
                            <el-table-column prop="status" label="状态" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">
                                        {{ row.status === 'resolved' ? '已解决' : '待处理' }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="resolution_note" label="处理备注" min-width="200" />
                            <el-table-column prop="created_at" label="创建时间" width="170">
                                <template #default="{ row }">
                                    {{ formatDate(row.created_at) }}
                                </template>
                            </el-table-column>
                        </el-table>

                        <el-empty v-if="!subscriptionFailures.attempts?.length && !subscriptionFailures.escalations?.length" description="暂无失败记录" :image-size="60" />
                    </div>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="续费策略配置" name="configs">
                <el-card shadow="never">
                    <div class="config-toolbar">
                        <span class="toolbar-title">续费策略列表</span>
                        <el-button type="primary" size="small" @click="openCreateConfig">
                            <el-icon><Plus /></el-icon>
                            新建策略
                        </el-button>
                    </div>

                    <el-table :data="configs" v-loading="loadingConfigs" stripe style="width: 100%">
                        <el-table-column prop="name" label="策略名称" min-width="140" />
                        <el-table-column prop="description" label="描述" min-width="160" />
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="最大重试" width="90" align="center">
                            <template #default="{ row }">{{ row.max_attempts }}</template>
                        </el-table-column>
                        <el-table-column label="降级节点" width="90" align="center">
                            <template #default="{ row }">{{ row.downgrade_after_attempt }}</template>
                        </el-table-column>
                        <el-table-column label="升级节点" width="90" align="center">
                            <template #default="{ row }">{{ row.escalate_after_attempt }}</template>
                        </el-table-column>
                        <el-table-column label="挽留券" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.retention_coupon_enabled ? 'success' : 'info'" size="small">
                                    {{ row.retention_coupon_enabled ? '开启' : '关闭' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" plain @click="openEditConfig(row)">编辑</el-button>
                                <el-button
                                    size="small"
                                    :type="row.is_active ? 'warning' : 'success'"
                                    plain
                                    @click="handleToggleConfig(row)"
                                >
                                    {{ row.is_active ? '停用' : '启用' }}
                                </el-button>
                                <el-popconfirm title="确认删除此策略？" @confirm="handleDeleteConfig(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger" plain>删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="configs.length === 0 && !loadingConfigs" description="暂无策略配置" :image-size="60" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 处理介入 Dialog -->
        <el-dialog v-model="showResolveDialog" title="处理人工介入" width="500px">
            <el-form ref="resolveFormRef" :model="resolveForm" :rules="resolveRules" label-position="top">
                <el-form-item label="介入 ID">
                    <el-tag>{{ resolveEscalation?.id }}</el-tag>
                </el-form-item>
                <el-form-item label="升级原因">
                    <div class="reason-display">{{ resolveEscalation?.message || '-' }}</div>
                </el-form-item>
                <el-form-item label="处理备注" prop="resolution_note">
                    <el-input
                        v-model="resolveForm.resolution_note"
                        type="textarea"
                        :rows="4"
                        placeholder="详细描述处理方式"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showResolveDialog = false">取消</el-button>
                <el-button type="primary" @click="handleResolve" :loading="resolving">
                    确认处理
                </el-button>
            </template>
        </el-dialog>

        <!-- 续费策略配置 Dialog -->
        <el-dialog v-model="showConfigDialog" :title="configForm.id ? '编辑策略' : '新建策略'" width="640px" :close-on-click-modal="false">
            <el-form ref="configFormRef" :model="configForm" :rules="configRules" label-position="top" size="small">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="策略名称" prop="name">
                            <el-input v-model="configForm.name" placeholder="如：默认策略" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="描述">
                            <el-input v-model="configForm.description" placeholder="策略说明" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider content-position="left">重试策略</el-divider>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="最大重试次数" prop="max_attempts">
                            <el-input-number v-model="configForm.max_attempts" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="降级节点（第N次）" prop="downgrade_after_attempt">
                            <el-input-number v-model="configForm.downgrade_after_attempt" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="人工介入节点" prop="escalate_after_attempt">
                            <el-input-number v-model="configForm.escalate_after_attempt" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="重试间隔（各次对应的天数）">
                    <div class="retry-intervals">
                        <div v-for="(_, idx) in configForm.retry_intervals_days" :key="idx" class="retry-interval-item">
                            <span class="interval-label">第 {{ idx + 1 }} 次</span>
                            <el-input-number v-model="configForm.retry_intervals_days[idx]" :min="1" :max="90" size="small" :controls-position="'right'" style="width:120px" />
                            <span class="interval-unit">天</span>
                            <el-button v-if="configForm.retry_intervals_days.length > 1" text type="danger" size="small" @click="configForm.retry_intervals_days.splice(idx, 1)">移除</el-button>
                        </div>
                        <el-button text type="primary" size="small" @click="configForm.retry_intervals_days.push(7)">
                            + 添加重试间隔
                        </el-button>
                    </div>
                </el-form-item>

                <el-divider content-position="left">通知策略</el-divider>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="提醒渠道">
                            <el-checkbox-group v-model="configForm.notification_channels">
                                <el-checkbox label="database">站内信</el-checkbox>
                                <el-checkbox label="mail">邮件</el-checkbox>
                                <el-checkbox label="sms">短信</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="提前提醒天数" prop="reminder_days_before">
                            <el-input-number v-model="configForm.reminder_days_before" :min="0" :max="90" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="提醒节奏（过期前天数）">
                    <div class="retry-intervals">
                        <el-tag v-for="(day, idx) in configForm.reminder_schedule" :key="idx" closable @close="configForm.reminder_schedule.splice(idx, 1)" class="mr-1">
                            {{ day }} 天
                        </el-tag>
                        <el-select v-model="newScheduleDay" placeholder="添加天数" size="small" style="width:120px" @change="addScheduleDay">
                            <el-option v-for="d in [1, 3, 7, 14, 30, 60, 90]" :key="d" :label="`${d} 天`" :value="d" />
                        </el-select>
                    </div>
                </el-form-item>

                <el-divider content-position="left">挽留优惠券</el-divider>

                <el-form-item>
                    <el-switch v-model="configForm.retention_coupon_enabled" active-text="启用挽留优惠券" />
                </el-form-item>

                <template v-if="configForm.retention_coupon_enabled">
                    <el-row :gutter="16">
                        <el-col :span="8">
                            <el-form-item label="折扣 (%)" prop="retention_coupon_discount_percent">
                                <el-input-number v-model="configForm.retention_coupon_discount_percent" :min="0" :max="100" :precision="1" style="width:100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item label="有效期（天）" prop="retention_coupon_valid_days">
                                <el-input-number v-model="configForm.retention_coupon_valid_days" :min="1" :max="365" style="width:100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item label="最大使用次数" prop="retention_coupon_max_uses">
                                <el-input-number v-model="configForm.retention_coupon_max_uses" :min="1" :max="100" style="width:100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item label="最大减免金额（可选）">
                        <el-input-number v-model="configForm.retention_coupon_max_discount" :min="0" :precision="2" style="width:200px" />
                    </el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">取消</el-button>
                <el-button type="primary" @click="handleSaveConfig" :loading="savingConfig">
                    {{ configForm.id ? '更新' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 处理介入 Dialog -->
        <el-dialog v-model="showResolveDialog" title="处理人工介入" width="500px">
            <el-form ref="resolveFormRef" :model="resolveForm" :rules="resolveRules" label-position="top">
                <el-form-item label="介入 ID">
                    <el-tag>{{ resolveEscalation?.id }}</el-tag>
                </el-form-item>
                <el-form-item label="升级原因">
                    <div class="reason-display">{{ resolveEscalation?.reason || '-' }}</div>
                </el-form-item>
                <el-form-item label="处理备注" prop="resolution_note">
                    <el-input
                        v-model="resolveForm.resolution_note"
                        type="textarea"
                        :rows="4"
                        placeholder="详细描述处理方式"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showResolveDialog = false">取消</el-button>
                <el-button type="primary" @click="handleResolve" :loading="resolving">
                    确认处理
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Search, Plus } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import retentionApi from '@/api/retention';

const loading = ref(false);
const loadingEscalations = ref(false);
const resolving = ref(false);
const activeTab = ref('escalations');

const stats = reactive({
    total_attempts: 0, total_failures: 0,
    pending_retries: 0, escalated_pending: 0,
    recent_7d_failures: 0, failure_rate: 0,
});

const escalations = ref([]);
const subscriptionFailures = ref(null);
const querySubscriptionId = ref('');

const showResolveDialog = ref(false);
const resolveEscalation = ref(null);
const resolveFormRef = ref(null);
const resolveForm = reactive({ resolution_note: '' });
const resolveRules = {
    resolution_note: [
        { required: true, message: '请输入处理备注', trigger: 'blur' },
        { max: 500, message: '最多 500 字', trigger: 'blur' },
    ],
};

// Config management
const configs = ref([]);
const loadingConfigs = ref(false);
const savingConfig = ref(false);
const showConfigDialog = ref(false);
const configFormRef = ref(null);
const newScheduleDay = ref(null);

const defaultConfigForm = () => ({
    id: null,
    name: '',
    description: '',
    is_active: false,
    max_attempts: 5,
    retry_intervals_days: [3, 7, 7],
    downgrade_after_attempt: 3,
    escalate_after_attempt: 4,
    notification_channels: ['database', 'mail', 'sms'],
    reminder_days_before: 7,
    reminder_schedule: [30, 14, 7, 3, 1],
    retention_coupon_enabled: false,
    retention_coupon_discount_percent: 10,
    retention_coupon_max_uses: 1,
    retention_coupon_valid_days: 30,
    retention_coupon_max_discount: null,
});

const configForm = reactive(defaultConfigForm());

const configRules = {
    name: [{ required: true, message: '请输入策略名称', trigger: 'blur' }],
    max_attempts: [{ required: true, type: 'number', min: 1, max: 20, message: '请输入重试次数', trigger: 'blur' }],
};

const failureRateClass = computed(() => {
    if (stats.failure_rate > 20) return 'text-danger';
    if (stats.failure_rate > 10) return 'text-warning';
    return '';
});

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadStats() {
    try {
        const { data: res } = await apiClient.get('/retention/failure-stats');
        Object.assign(stats, res.data || {});
    } catch { /* ignore */ }
}

async function loadEscalations() {
    loadingEscalations.value = true;
    try {
        const { data: res } = await apiClient.get('/retention/escalations');
        const paginatedData = res.data;
        escalations.value = paginatedData?.data || paginatedData || [];
    } catch {
        escalations.value = [];
    } finally {
        loadingEscalations.value = false;
    }
}

async function handleQuerySubscription() {
    if (!querySubscriptionId.value) {
        ElMessage.warning('请输入订阅 ID');
        return;
    }
    subscriptionFailures.value = null;
    try {
        const { data: res } = await apiClient.get(`/retention/subscriptions/${querySubscriptionId.value}/failures`);
        subscriptionFailures.value = res.data || { attempts: [], escalations: [] };
        if (!subscriptionFailures.value.attempts?.length && !subscriptionFailures.value.escalations?.length) {
            ElMessage.info('该订阅暂无失败记录');
        }
    } catch (err) {
        if (err?.response?.status === 404) {
            ElMessage.warning('订阅不存在');
        } else {
            ElMessage.error('查询失败');
        }
        subscriptionFailures.value = null;
    }
}

function viewSubscriptionFailures(subscriptionId) {
    querySubscriptionId.value = subscriptionId;
    activeTab.value = 'query';
    handleQuerySubscription();
}

function openResolveDialog(escalation) {
    resolveEscalation.value = escalation;
    resolveForm.resolution_note = '';
    showResolveDialog.value = true;
}

async function handleResolve() {
    const valid = await resolveFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    if (!resolveEscalation.value) return;

    resolving.value = true;
    try {
        await apiClient.post(`/retention/escalations/${resolveEscalation.value.id}/resolve`, {
            resolution_note: resolveForm.resolution_note,
        });
        ElMessage.success('介入已处理');
        showResolveDialog.value = false;
        loadEscalations();
        loadStats();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '处理失败');
    } finally {
        resolving.value = false;
    }
}

function loadAll() {
    loadStats();
    loadEscalations();
    loadConfigs();
}

// ── Config Methods ──

async function loadConfigs() {
    loadingConfigs.value = true;
    try {
        const { data: res } = await retentionApi.getConfigs();
        configs.value = res.data || [];
    } catch {
        configs.value = [];
    } finally {
        loadingConfigs.value = false;
    }
}

function openCreateConfig() {
    Object.assign(configForm, defaultConfigForm());
    showConfigDialog.value = true;
}

function openEditConfig(config) {
    Object.assign(configForm, {
        id: config.id,
        name: config.name,
        description: config.description || '',
        is_active: config.is_active ?? false,
        max_attempts: config.max_attempts ?? 5,
        retry_intervals_days: config.retry_intervals_days || [3, 7, 7],
        downgrade_after_attempt: config.downgrade_after_attempt ?? 3,
        escalate_after_attempt: config.escalate_after_attempt ?? 4,
        notification_channels: config.notification_channels || ['database', 'mail', 'sms'],
        reminder_days_before: config.reminder_days_before ?? 7,
        reminder_schedule: config.reminder_schedule || [30, 14, 7, 3, 1],
        retention_coupon_enabled: config.retention_coupon_enabled ?? false,
        retention_coupon_discount_percent: config.retention_coupon_discount_percent ?? 10,
        retention_coupon_max_uses: config.retention_coupon_max_uses ?? 1,
        retention_coupon_valid_days: config.retention_coupon_valid_days ?? 30,
        retention_coupon_max_discount: config.retention_coupon_max_discount ?? null,
    });
    showConfigDialog.value = true;
}

async function handleSaveConfig() {
    const valid = await configFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    savingConfig.value = true;
    try {
        const payload = { ...configForm };
        const id = payload.id;
        delete payload.id;

        await retentionApi.saveConfig(payload, id);
        ElMessage.success(id ? '策略已更新' : '策略已创建');
        showConfigDialog.value = false;
        loadConfigs();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '保存失败');
    } finally {
        savingConfig.value = false;
    }
}

async function handleToggleConfig(config) {
    try {
        await retentionApi.toggleConfig(config.id);
        ElMessage.success('策略状态已切换');
        loadConfigs();
    } catch {
        ElMessage.error('操作失败');
    }
}

async function handleDeleteConfig(config) {
    try {
        await retentionApi.deleteConfig(config.id);
        ElMessage.success('策略已删除');
        loadConfigs();
    } catch {
        ElMessage.error('删除失败');
    }
}

function addScheduleDay(day) {
    if (day && !configForm.reminder_schedule.includes(day)) {
        configForm.reminder_schedule.push(day);
        configForm.reminder_schedule.sort((a, b) => b - a);
    }
    newScheduleDay.value = null;
}

onMounted(() => {
    loadAll();
});
</script>

<style scoped>
.retention-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-danger { color: var(--el-color-danger); }
.text-warning { color: var(--el-color-warning); }

.reason-text {
    font-size: 13px;
    color: var(--el-text-color-regular);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.query-section {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--el-border-color-light);
}

.subsection-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin: 0 0 12px 0;
}
.mt-4 { margin-top: 16px; }

.reason-display {
    font-size: 14px;
    color: var(--el-text-color-regular);
    padding: 8px 12px;
    background: var(--el-color-info-light-9);
    border-radius: 4px;
}

:deep(.el-card__body) { padding: 16px; }

.config-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.toolbar-title {
    font-size: 15px;
    font-weight: 600;
}
.retry-intervals {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.retry-interval-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.interval-label {
    min-width: 50px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.interval-unit {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.mr-1 { margin-right: 4px; }
</style>
