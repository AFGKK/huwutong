<template>
    <div class="retention-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('billing_retention_page.title') }}</h2>
                <span class="header-subtitle">{{ t('billing_retention_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    {{ t('billing_retention_page.refresh') }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.total_attempts') }}</div>
                        <div class="stat-value">{{ stats.total_attempts }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.total_failures') }}</div>
                        <div class="stat-value text-danger">{{ stats.total_failures }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.failure_rate') }}</div>
                        <div class="stat-value" :class="failureRateClass">{{ stats.failure_rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.pending_retries') }}</div>
                        <div class="stat-value text-warning">{{ stats.pending_retries }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.escalated_pending') }}</div>
                        <div class="stat-value text-danger">{{ stats.escalated_pending }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('billing_retention_page.stats.recent_7d_failures') }}</div>
                        <div class="stat-value text-warning">{{ stats.recent_7d_failures }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" class="mb-4">
            <el-tab-pane :label="t('billing_retention_page.tabs.escalations')" name="escalations">
                <el-card shadow="never">
                    <el-table :data="escalations" v-loading="loadingEscalations" stripe style="width: 100%">
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="id" :label="t('billing_retention_page.cols.escalation_id')" width="80" />
                        <el-table-column :label="t('billing_page.col_customer')" min-width="140">
                            <template #default="{ row }">
                                {{ row.subscription?.customer?.name || row.subscription?.customer_id || '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_retention_page.cols.subscription')" min-width="120">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">
                                    #{{ row.subscription_id }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reason" :label="t('billing_retention_page.cols.reason')" min-width="200">
                            <template #default="{ row }">
                                <span class="reason-text">{{ row.reason || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="retry_count" :label="t('billing_retention_page.cols.retry_count')" width="100" />
                        <el-table-column prop="created_at" :label="t('billing_page.col_created')" width="170">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button type="primary" size="small" @click="openResolveDialog(row)">
                                    {{ t('billing_retention_page.actions.resolve') }}
                                </el-button>
                                <el-button size="small" @click="viewSubscriptionFailures(row.subscription_id)">
                                    {{ t('billing_retention_page.actions.view_failures') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="escalations.length === 0 && !loadingEscalations" :description="t('billing_retention_page.empty.escalations')" :image-size="60" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('billing_retention_page.tabs.query')" name="query">
                <el-card shadow="never">
                    <div class="query-section">
                        <el-form :inline="true">
                            <el-form-item :label="t('billing_retention_page.query.subscription_id')">
                                <el-input v-model="querySubscriptionId" :placeholder="t('billing_retention_page.query.subscription_id_ph')" style="width: 200px" />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="handleQuerySubscription">
                                    <el-icon><Search /></el-icon>
                                    {{ t('billing_retention_page.query.btn') }}
                                </el-button>
                            </el-form-item>
                        </el-form>
                    </div>

                    <div v-if="subscriptionFailures" class="subscription-failures">
                        <h4 class="subsection-title">{{ t('billing_retention_page.sections.retry_attempts') }}</h4>
                        <el-table :data="subscriptionFailures.attempts || []" stripe style="width: 100%" size="small">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="attempt_number" :label="t('billing_retention_page.cols.attempt_number')" width="100" />
                            <el-table-column prop="status" :label="t('billing_page.col_status')" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                        {{ attemptStatusLabel(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="error_message" :label="t('billing_retention_page.cols.error_message')" min-width="240" />
                            <el-table-column prop="next_retry_at" :label="t('billing_retention_page.cols.next_retry')" width="170">
                                <template #default="{ row }">
                                    {{ row.next_retry_at ? formatDate(row.next_retry_at) : '-' }}
                                </template>
                            </el-table-column>
                            <el-table-column prop="created_at" :label="t('billing_retention_page.cols.time')" width="170">
                                <template #default="{ row }">
                                    {{ formatDate(row.created_at) }}
                                </template>
                            </el-table-column>
                        </el-table>

                        <h4 class="subsection-title mt-4">{{ t('billing_retention_page.sections.escalations') }}</h4>
                        <el-table :data="subscriptionFailures.escalations || []" stripe style="width: 100%" size="small">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="reason" :label="t('billing_retention_page.cols.reason_short')" min-width="200" />
                            <el-table-column prop="status" :label="t('billing_page.col_status')" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">
                                        {{ escalationStatusLabel(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="resolution_note" :label="t('billing_retention_page.cols.resolution_note')" min-width="200" />
                            <el-table-column prop="created_at" :label="t('billing_page.col_created')" width="170">
                                <template #default="{ row }">
                                    {{ formatDate(row.created_at) }}
                                </template>
                            </el-table-column>
                        </el-table>

                        <el-empty v-if="!subscriptionFailures.attempts?.length && !subscriptionFailures.escalations?.length" :description="t('billing_retention_page.empty.failures')" :image-size="60" />
                    </div>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('billing_retention_page.tabs.configs')" name="configs">
                <el-card shadow="never">
                    <div class="config-toolbar">
                        <span class="toolbar-title">{{ t('billing_retention_page.configs.toolbar_title') }}</span>
                        <el-button type="primary" size="small" @click="openCreateConfig">
                            <el-icon><Plus /></el-icon>
                            {{ t('billing_retention_page.configs.new_btn') }}
                        </el-button>
                    </div>

                    <el-table :data="configs" v-loading="loadingConfigs" stripe style="width: 100%">
                        <el-table-column prop="name" :label="t('billing_retention_page.cols.config_name')" min-width="140" />
                        <el-table-column prop="description" :label="t('billing_page.form_description')" min-width="160" />
                        <el-table-column :label="t('billing_page.col_status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ configActiveLabel(row.is_active) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_retention_page.cols.max_attempts')" width="90" align="center">
                            <template #default="{ row }">{{ row.max_attempts }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_retention_page.cols.downgrade_node')" width="90" align="center">
                            <template #default="{ row }">{{ row.downgrade_after_attempt }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_retention_page.cols.escalate_node')" width="90" align="center">
                            <template #default="{ row }">{{ row.escalate_after_attempt }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_retention_page.cols.retention_coupon')" width="80" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.retention_coupon_enabled ? 'success' : 'info'" size="small">
                                    {{ retentionCouponLabel(row.retention_coupon_enabled) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" plain @click="openEditConfig(row)">{{ t('actions.edit') }}</el-button>
                                <el-button
                                    size="small"
                                    :type="row.is_active ? 'warning' : 'success'"
                                    plain
                                    @click="handleToggleConfig(row)"
                                >
                                    {{ row.is_active ? t('actions.disable') : t('actions.enable') }}
                                </el-button>
                                <el-popconfirm :title="t('billing_retention_page.configs.delete_confirm')" @confirm="handleDeleteConfig(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="configs.length === 0 && !loadingConfigs" :description="t('billing_retention_page.configs.empty')" :image-size="60" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showResolveDialog" :title="t('billing_retention_page.dialog.resolve_title')" width="500px">
            <el-form ref="resolveFormRef" :model="resolveForm" :rules="resolveRules" label-position="top">
                <el-form-item :label="t('billing_retention_page.form.escalation_id')">
                    <el-tag>{{ resolveEscalation?.id }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('billing_retention_page.form.escalation_reason')">
                    <div class="reason-display">{{ resolveEscalation?.reason || '-' }}</div>
                </el-form-item>
                <el-form-item :label="t('billing_retention_page.form.resolution_note')" prop="resolution_note">
                    <el-input
                        v-model="resolveForm.resolution_note"
                        type="textarea"
                        :rows="4"
                        :placeholder="t('billing_retention_page.form.resolution_note_ph')"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showResolveDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleResolve" :loading="resolving">
                    {{ t('billing_retention_page.actions.confirm_resolve') }}
                </el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showConfigDialog" :title="configForm.id ? t('billing_retention_page.dialog.edit_config') : t('billing_retention_page.dialog.create_config')" width="640px" :close-on-click-modal="false">
            <el-form ref="configFormRef" :model="configForm" :rules="configRules" label-position="top" size="small">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_retention_page.form.name')" prop="name">
                            <el-input v-model="configForm.name" :placeholder="t('billing_retention_page.form.name_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_retention_page.form.description')">
                            <el-input v-model="configForm.description" :placeholder="t('billing_retention_page.form.description_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-divider content-position="left">{{ t('billing_retention_page.sections.retry_policy') }}</el-divider>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('billing_retention_page.form.max_attempts')" prop="max_attempts">
                            <el-input-number v-model="configForm.max_attempts" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_retention_page.form.downgrade_after')" prop="downgrade_after_attempt">
                            <el-input-number v-model="configForm.downgrade_after_attempt" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('billing_retention_page.form.escalate_after')" prop="escalate_after_attempt">
                            <el-input-number v-model="configForm.escalate_after_attempt" :min="1" :max="20" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="t('billing_retention_page.form.retry_intervals')">
                    <div class="retry-intervals">
                        <div v-for="(_, idx) in configForm.retry_intervals_days" :key="idx" class="retry-interval-item">
                            <span class="interval-label">{{ t('billing_retention_page.form.attempt_nth', { n: idx + 1 }) }}</span>
                            <el-input-number v-model="configForm.retry_intervals_days[idx]" :min="1" :max="90" size="small" :controls-position="'right'" style="width:120px" />
                            <span class="interval-unit">{{ t('billing_retention_page.form.unit_days') }}</span>
                            <el-button v-if="configForm.retry_intervals_days.length > 1" text type="danger" size="small" @click="configForm.retry_intervals_days.splice(idx, 1)">{{ t('billing_retention_page.form.remove_interval') }}</el-button>
                        </div>
                        <el-button text type="primary" size="small" @click="configForm.retry_intervals_days.push(7)">
                            + {{ t('billing_retention_page.form.add_interval') }}
                        </el-button>
                    </div>
                </el-form-item>

                <el-divider content-position="left">{{ t('billing_retention_page.sections.notification_policy') }}</el-divider>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('billing_retention_page.form.notification_channels')">
                            <el-checkbox-group v-model="configForm.notification_channels">
                                <el-checkbox label="database">{{ t('billing_retention_page.form.channel_database') }}</el-checkbox>
                                <el-checkbox label="mail">{{ t('billing_retention_page.form.channel_mail') }}</el-checkbox>
                                <el-checkbox label="sms">{{ t('billing_retention_page.form.channel_sms') }}</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('billing_retention_page.form.reminder_days_before')" prop="reminder_days_before">
                            <el-input-number v-model="configForm.reminder_days_before" :min="0" :max="90" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="t('billing_retention_page.form.reminder_schedule')">
                    <div class="retry-intervals">
                        <el-tag v-for="(day, idx) in configForm.reminder_schedule" :key="idx" closable @close="configForm.reminder_schedule.splice(idx, 1)" class="mr-1">
                            {{ t('billing_page.days_suffix', { n: day }) }}
                        </el-tag>
                        <el-select v-model="newScheduleDay" :placeholder="t('billing_retention_page.form.add_days_ph')" size="small" style="width:120px" @change="addScheduleDay">
                            <el-option v-for="d in scheduleDayOptions" :key="d" :label="t('billing_page.days_suffix', { n: d })" :value="d" />
                        </el-select>
                    </div>
                </el-form-item>

                <el-divider content-position="left">{{ t('billing_retention_page.sections.retention_coupon') }}</el-divider>

                <el-form-item>
                    <el-switch v-model="configForm.retention_coupon_enabled" :active-text="t('billing_retention_page.form.retention_coupon_switch')" />
                </el-form-item>

                <template v-if="configForm.retention_coupon_enabled">
                    <el-row :gutter="16">
                        <el-col :span="8">
                            <el-form-item :label="t('billing_retention_page.form.discount_percent')" prop="retention_coupon_discount_percent">
                                <el-input-number v-model="configForm.retention_coupon_discount_percent" :min="0" :max="100" :precision="1" style="width:100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item :label="t('billing_retention_page.form.valid_days')" prop="retention_coupon_valid_days">
                                <el-input-number v-model="configForm.retention_coupon_valid_days" :min="1" :max="365" style="width:100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="8">
                            <el-form-item :label="t('billing_retention_page.form.max_uses')" prop="retention_coupon_max_uses">
                                <el-input-number v-model="configForm.retention_coupon_max_uses" :min="1" :max="100" style="width:100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item :label="t('billing_retention_page.form.max_discount')">
                        <el-input-number v-model="configForm.retention_coupon_max_discount" :min="0" :precision="2" style="width:200px" />
                    </el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSaveConfig" :loading="savingConfig">
                    {{ configForm.id ? t('actions.update') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Search, Plus } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import retentionApi from '@/api/retention';

const { t, locale } = useI18n();

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

const configs = ref([]);
const loadingConfigs = ref(false);
const savingConfig = ref(false);
const showConfigDialog = ref(false);
const configFormRef = ref(null);
const newScheduleDay = ref(null);

const scheduleDayOptions = [1, 3, 7, 14, 30, 60, 90];

const attemptStatusLabels = computed(() => ({
    success: t('billing_retention_page.status.success'),
    failure: t('billing_retention_page.status.failure'),
}));

const escalationStatusLabels = computed(() => ({
    resolved: t('billing_retention_page.status.resolved'),
    pending: t('billing_retention_page.status.pending'),
}));

const configActiveLabels = computed(() => ({
    true: t('billing_retention_page.status.active'),
    false: t('billing_retention_page.status.inactive'),
}));

const resolveRules = computed(() => ({
    resolution_note: [
        { required: true, message: t('billing_retention_page.validation.resolution_note_required'), trigger: 'blur' },
        { max: 500, message: t('billing_retention_page.validation.resolution_note_max'), trigger: 'blur' },
    ],
}));

const configRules = computed(() => ({
    name: [{ required: true, message: t('billing_retention_page.validation.name_required'), trigger: 'blur' }],
    max_attempts: [{ required: true, type: 'number', min: 1, max: 20, message: t('billing_retention_page.validation.max_attempts_required'), trigger: 'blur' }],
}));

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

const failureRateClass = computed(() => {
    if (stats.failure_rate > 20) return 'text-danger';
    if (stats.failure_rate > 10) return 'text-warning';
    return '';
});

function attemptStatusLabel(status) {
    return attemptStatusLabels.value[status] || status;
}

function escalationStatusLabel(status) {
    return escalationStatusLabels.value[status] || status;
}

function configActiveLabel(isActive) {
    return configActiveLabels.value[String(!!isActive)] || String(isActive);
}

function retentionCouponLabel(enabled) {
    return enabled ? t('billing_page.auto_renew_on') : t('billing_page.auto_renew_off');
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
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
        ElMessage.warning(t('billing_retention_page.messages.enter_subscription_id'));
        return;
    }
    subscriptionFailures.value = null;
    try {
        const { data: res } = await apiClient.get(`/retention/subscriptions/${querySubscriptionId.value}/failures`);
        subscriptionFailures.value = res.data || { attempts: [], escalations: [] };
        if (!subscriptionFailures.value.attempts?.length && !subscriptionFailures.value.escalations?.length) {
            ElMessage.info(t('billing_retention_page.messages.no_failures_for_sub'));
        }
    } catch (err) {
        if (err?.response?.status === 404) {
            ElMessage.warning(t('billing_retention_page.messages.subscription_not_found'));
        } else {
            ElMessage.error(t('billing_retention_page.messages.query_failed'));
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
        ElMessage.success(t('billing_retention_page.messages.escalation_resolved'));
        showResolveDialog.value = false;
        loadEscalations();
        loadStats();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('billing_retention_page.messages.resolve_failed'));
    } finally {
        resolving.value = false;
    }
}

function loadAll() {
    loadStats();
    loadEscalations();
    loadConfigs();
}

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
        ElMessage.success(id ? t('billing_retention_page.messages.config_updated') : t('billing_retention_page.messages.config_created'));
        showConfigDialog.value = false;
        loadConfigs();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('billing_retention_page.messages.save_failed'));
    } finally {
        savingConfig.value = false;
    }
}

async function handleToggleConfig(config) {
    try {
        await retentionApi.toggleConfig(config.id);
        ElMessage.success(t('billing_retention_page.messages.config_toggled'));
        loadConfigs();
    } catch {
        ElMessage.error(t('messages.failed'));
    }
}

async function handleDeleteConfig(config) {
    try {
        await retentionApi.deleteConfig(config.id);
        ElMessage.success(t('billing_retention_page.messages.config_deleted'));
        loadConfigs();
    } catch {
        ElMessage.error(t('billing_retention_page.messages.delete_failed'));
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
