<template>
    <div class="im-integration-page">
        <div class="page-header">
            <h2>{{ t('im_integration_page.title') }}</h2>
        </div>

        <el-row :gutter="16">
            <!-- Slack -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> Slack</span>
                            <el-tag type="success" size="small">{{ t('im_integration_page.recommended') }}</el-tag>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">{{ t('im_integration_page.channel_desc.slack') }}</p>
                        <el-form :model="slackForm" label-position="top" size="small">
                            <el-form-item :label="t('im_integration_page.webhook_url')">
                                <el-input v-model="slackForm.webhook_url" :placeholder="t('im_integration_page.placeholders.slack')" />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testSlack" :loading="testing.slack" type="primary">{{ t('im_integration_page.test_connection') }}</el-button>
                                <el-button @click="sendSlackTestMsg" :loading="sending.slack">{{ t('im_integration_page.send_test_message') }}</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.slack" :class="['result-box', results.slack.success ? 'success' : 'error']">
                            {{ results.slack.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://api.slack.com/messaging/webhooks" target="_blank">{{ t('im_integration_page.help.slack') }}</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- DingTalk -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> {{ t('im_integration_page.channels.dingtalk') }}</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">{{ t('im_integration_page.channel_desc.dingtalk') }}</p>
                        <el-form :model="dingtalkForm" label-position="top" size="small">
                            <el-form-item :label="t('im_integration_page.webhook_url')">
                                <el-input v-model="dingtalkForm.webhook_url" :placeholder="t('im_integration_page.placeholders.dingtalk')" />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testDingTalk" :loading="testing.dingtalk" type="primary">{{ t('im_integration_page.test_connection') }}</el-button>
                                <el-button @click="sendDingTalkTestMsg" :loading="sending.dingtalk">{{ t('im_integration_page.send_test_message') }}</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.dingtalk" :class="['result-box', results.dingtalk.success ? 'success' : 'error']">
                            {{ results.dingtalk.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://open.dingtalk.com/document/robots/custom-robot-access" target="_blank">{{ t('im_integration_page.help.dingtalk') }}</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- WeCom -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> {{ t('im_integration_page.channels.wecom') }}</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">{{ t('im_integration_page.channel_desc.wecom') }}</p>
                        <el-form :model="wecomForm" label-position="top" size="small">
                            <el-form-item :label="t('im_integration_page.webhook_url')">
                                <el-input v-model="wecomForm.webhook_url" :placeholder="t('im_integration_page.placeholders.wecom')" />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testWeCom" :loading="testing.wecom" type="primary">{{ t('im_integration_page.test_connection') }}</el-button>
                                <el-button @click="sendWeComTestMsg" :loading="sending.wecom">{{ t('im_integration_page.send_test_message') }}</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.wecom" :class="['result-box', results.wecom.success ? 'success' : 'error']">
                            {{ results.wecom.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://developer.work.weixin.qq.com/document/path/91770" target="_blank">{{ t('im_integration_page.help.wecom') }}</a>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- Feishu -->
            <el-col :span="12" class="mb-4">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><ChatDotSquare /></el-icon> {{ t('im_integration_page.channels.feishu') }}</span>
                        </div>
                    </template>
                    <div class="channel-body">
                        <p class="channel-desc">{{ t('im_integration_page.channel_desc.feishu') }}</p>
                        <el-form :model="feishuForm" label-position="top" size="small">
                            <el-form-item :label="t('im_integration_page.webhook_url')">
                                <el-input v-model="feishuForm.webhook_url" :placeholder="t('im_integration_page.placeholders.feishu')" />
                            </el-form-item>
                            <el-form-item>
                                <el-button @click="testFeishu" :loading="testing.feishu" type="primary">{{ t('im_integration_page.test_connection') }}</el-button>
                                <el-button @click="sendFeishuTestMsg" :loading="sending.feishu">{{ t('im_integration_page.send_test_message') }}</el-button>
                            </el-form-item>
                        </el-form>
                        <div v-if="results.feishu" :class="['result-box', results.feishu.success ? 'success' : 'error']">
                            {{ results.feishu.message }}
                        </div>
                        <div class="channel-help">
                            <a href="https://open.feishu.cn/document/tools/custom-bot" target="_blank">{{ t('im_integration_page.help.feishu') }}</a>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Auto-send events -->
        <el-card shadow="never">
            <template #header>
                <span>{{ t('im_integration_page.auto_send.title') }}</span>
            </template>
            <el-table :data="autoSendEvents" stripe>
                <el-table-column prop="event" :label="t('im_integration_page.auto_send.event')" width="250" />
                <el-table-column :label="t('im_integration_page.auto_send.push_channels')">
                    <template #default="{ row }">
                        <el-tag v-for="ch in row.channels" :key="ch" :type="tagType(ch)" size="small" class="mr-1">{{ channelName(ch) }}</el-tag>
                        <span v-if="!row.channels.length" class="text-muted">{{ t('im_integration_page.auto_send.not_configured') }}</span>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Microsoft Teams -->
        <el-divider />
        <h3 style="margin:16px 0 12px;display:flex;align-items:center;gap:8px">
            <el-icon><ChatDotSquare /></el-icon> {{ t('im_integration_page.teams.title') }}
            <el-tag size="small" type="info">{{ t('im_integration_page.teams.standalone') }}</el-tag>
        </h3>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-value">{{ teamsDash.active }}</div><div class="stat-label">{{ t('im_integration_page.teams.stats.active_webhooks') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-success">{{ teamsDash.today_success }}</div><div class="stat-label">{{ t('im_integration_page.teams.stats.today_success') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-danger">{{ teamsDash.today_failed }}</div><div class="stat-label">{{ t('im_integration_page.teams.stats.today_failed') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value">{{ teamsDash.total }}</div><div class="stat-label">{{ t('im_integration_page.teams.stats.total_configs') }}</div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-value text-primary">{{ teamsDash.today_total }}</div><div class="stat-label">{{ t('im_integration_page.teams.stats.today_sent') }}</div></el-card></el-col>
        </el-row>

        <el-tabs v-model="teamsTab">
            <el-tab-pane :label="t('im_integration_page.teams.tabs.webhooks')" name="webhooks">
                <el-card shadow="never">
                    <template #header>
                        <el-space>
                            <span>{{ t('im_integration_page.teams.webhook_list') }}</span>
                            <el-button size="small" type="primary" @click="openTeamsDialog">{{ t('im_integration_page.teams.new_webhook') }}</el-button>
                        </el-space>
                    </template>
                    <el-table :data="teamsWebhooks" stripe v-loading="teamsLoading">
                        <el-table-column prop="name" :label="t('im_integration_page.teams.cols.channel_name')" width="140" />
                        <el-table-column prop="webhook_url" :label="t('im_integration_page.webhook_url')" min-width="280" show-overflow-tooltip />
                        <el-table-column :label="t('im_integration_page.teams.cols.notification_type')" width="110">
                            <template #default="{ row }"><el-tag size="small">{{ teamsTypeLabel(row.notification_type) }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('im_integration_page.teams.cols.status')" width="65">
                            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? statusLabels.enabled : statusLabels.disabled }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('im_integration_page.teams.cols.actions')" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editTeamsWebhook(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" type="success" @click="teamsTest(row)">{{ t('im_integration_page.btn_test') }}</el-button>
                                <el-popconfirm :title="t('messages.confirm_delete')" @confirm="teamsDelete(row)">
                                    <template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <el-card shadow="never" style="margin-top:16px">
                    <template #header><span>{{ t('im_integration_page.teams.manual_send') }}</span></template>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><el-space><span>{{ t('im_integration_page.teams.activation.title') }}</span><el-tag size="small">{{ t('im_integration_page.teams.activation.tag') }}</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item :label="t('im_integration_page.teams.form.license_key')"><el-input v-model="activationForm.license_key" :placeholder="t('im_integration_page.placeholders.license_key')" /></el-form-item>
                                    <el-form-item :label="t('im_integration_page.teams.form.product_name')"><el-input v-model="activationForm.product_name" :placeholder="t('im_integration_page.placeholders.product_name')" /></el-form-item>
                                    <el-form-item :label="t('im_integration_page.teams.form.customer_name')"><el-input v-model="activationForm.customer_name" :placeholder="t('im_integration_page.placeholders.customer_name')" /></el-form-item>
                                    <el-button type="primary" size="small" :loading="sendingActivation" @click="handleSendActivation">{{ t('im_integration_page.send') }}</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><el-space><span>{{ t('im_integration_page.teams.alert.title') }}</span><el-tag size="small">{{ t('im_integration_page.teams.alert.tag') }}</el-tag></el-space></template>
                                <el-form size="small" label-position="top">
                                    <el-form-item :label="t('im_integration_page.teams.form.title')"><el-input v-model="alertForm.title" :placeholder="t('im_integration_page.placeholders.alert_title')" /></el-form-item>
                                    <el-form-item :label="t('im_integration_page.teams.form.message')"><el-input v-model="alertForm.message" type="textarea" :rows="2" :placeholder="t('im_integration_page.placeholders.alert_message')" /></el-form-item>
                                    <el-form-item :label="t('im_integration_page.teams.form.severity')">
                                        <el-select v-model="alertForm.severity" style="width:120px">
                                            <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                        </el-select>
                                    </el-form-item>
                                    <el-button type="danger" size="small" :loading="sendingAlert" @click="handleSendAlert">{{ t('im_integration_page.send') }}</el-button>
                                </el-form>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('im_integration_page.teams.tabs.logs')" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <el-space>
                            <span>{{ t('im_integration_page.teams.log.title') }}</span>
                            <el-select v-model="teamsLogStatusFilter" :placeholder="t('im_integration_page.teams.log.status_ph')" clearable size="small" style="width:120px" @change="loadTeamsLogs">
                                <el-option v-for="opt in logStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                            <el-select v-model="teamsLogTypeFilter" :placeholder="t('im_integration_page.teams.log.type_ph')" clearable size="small" style="width:140px" @change="loadTeamsLogs">
                                <el-option v-for="opt in logTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="teamsLogs" stripe v-loading="teamsLogsLoading">
                        <el-table-column prop="created_at" :label="t('im_integration_page.teams.cols.time')" width="150" />
                        <el-table-column :label="t('im_integration_page.teams.cols.notification_type')" width="80"><template #default="{ row }">{{ teamsTypeLabel(row.notification_type) }}</template></el-table-column>
                        <el-table-column :label="t('im_integration_page.teams.cols.status')" width="65">
                            <template #default="{ row }"><el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status === 'success' ? statusLabels.success : statusLabels.failed }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="title" :label="t('im_integration_page.teams.cols.title')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="message" :label="t('im_integration_page.teams.cols.message')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="error_message" :label="t('im_integration_page.teams.cols.error')" min-width="180" show-overflow-tooltip />
                    </el-table>
                    <el-pagination v-if="teamsLogTotal > teamsLogPerPage" v-model:current-page="teamsLogPage" :page-size="teamsLogPerPage" :total="teamsLogTotal" layout="prev, pager, next" @current-change="loadTeamsLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="teamsDlgVisible" :title="teamsIsEditing ? t('im_integration_page.teams.dialog.edit_title') : t('im_integration_page.teams.dialog.create_title')" width="550px">
            <el-form label-position="top" size="small" :model="teamsForm">
                <el-form-item :label="t('im_integration_page.teams.dialog.channel_name')" required><el-input v-model="teamsForm.name" :placeholder="t('im_integration_page.placeholders.channel_name')" /></el-form-item>
                <el-form-item :label="t('im_integration_page.webhook_url')" required>
                    <el-input v-model="teamsForm.webhook_url" :placeholder="t('im_integration_page.placeholders.teams')" />
                    <div style="font-size:12px;color:#909399;margin-top:4px">{{ t('im_integration_page.teams.dialog.webhook_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('im_integration_page.teams.dialog.notification_type')" required>
                    <el-select v-model="teamsForm.notification_type" style="width:100%">
                        <el-option v-for="nt in teamsNotificationTypes" :key="nt.key" :label="teamsTypeLabel(nt.key)" :value="nt.key" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('im_integration_page.teams.dialog.enabled')"><el-switch v-model="teamsForm.is_active" /></el-form-item>
                <el-form-item :label="t('im_integration_page.teams.dialog.description')"><el-input v-model="teamsForm.description" type="textarea" :rows="2" :placeholder="t('im_integration_page.placeholders.description')" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="teamsDlgVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="teamsSaving" @click="saveTeamsWebhook">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { ChatDotSquare } from '@element-plus/icons-vue';
import { testSlack as apiTestSlack, testDingTalk as apiTestDingTalk, testWeCom as apiTestWeCom, testFeishu as apiTestFeishu, sendImMessage } from '@/api/imIntegration';
import teamsNotifier from '@/api/teamsNotifier';
import imConfig from '@/config/im-integration';

const { t } = useI18n();

const slackForm = reactive({ webhook_url: '' });
const dingtalkForm = reactive({ webhook_url: '' });
const wecomForm = reactive({ webhook_url: '' });
const feishuForm = reactive({ webhook_url: '' });

const testing = reactive({ slack: false, dingtalk: false, wecom: false, feishu: false });
const sending = reactive({ slack: false, dingtalk: false, wecom: false, feishu: false });
const results = reactive({ slack: null, dingtalk: null, wecom: null, feishu: null });

const autoSendEvents = ref([]);

const statusLabels = computed(() => ({
    enabled: t('im_integration_page.status.enabled'),
    disabled: t('im_integration_page.status.disabled'),
    success: t('im_integration_page.filters.success'),
    failed: t('im_integration_page.filters.failed'),
}));

const notificationTypeLabels = computed(() => ({
    all: t('im_integration_page.notification_types.all'),
    activation: t('im_integration_page.notification_types.activation'),
    alert: t('im_integration_page.notification_types.alert'),
    expiry: t('im_integration_page.notification_types.expiry'),
    test: t('im_integration_page.notification_types.test'),
}));

const channelLabels = computed(() => ({
    slack: 'Slack',
    dingtalk: t('im_integration_page.channels.dingtalk'),
    wecom: t('im_integration_page.channels.wecom'),
    feishu: t('im_integration_page.channels.feishu'),
}));

const severityOptions = computed(() => [
    { label: t('im_integration_page.severity.info'), value: 'info' },
    { label: t('im_integration_page.severity.warning'), value: 'warning' },
    { label: t('im_integration_page.severity.critical'), value: 'critical' },
]);

const logStatusOptions = computed(() => [
    { label: t('im_integration_page.filters.all'), value: '' },
    { label: t('im_integration_page.filters.success'), value: 'success' },
    { label: t('im_integration_page.filters.failed'), value: 'failed' },
]);

const logTypeOptions = computed(() => [
    { label: t('im_integration_page.filters.all'), value: '' },
    { label: t('im_integration_page.notification_types.activation'), value: 'activation' },
    { label: t('im_integration_page.notification_types.alert'), value: 'alert' },
    { label: t('im_integration_page.notification_types.expiry'), value: 'expiry' },
    { label: t('im_integration_page.notification_types.test'), value: 'test' },
]);

// ── Microsoft Teams ──
const teamsTab = ref('webhooks');
const teamsLoading = ref(false);
const teamsLogsLoading = ref(false);
const teamsSaving = ref(false);
const sendingActivation = ref(false);
const sendingAlert = ref(false);
const teamsDlgVisible = ref(false);
const teamsIsEditing = ref(false);
const teamsEditingId = ref(null);
const teamsLogStatusFilter = ref('');
const teamsLogTypeFilter = ref('');
const teamsLogPage = ref(1);
const teamsLogPerPage = ref(20);
const teamsLogTotal = ref(0);

const teamsDash = reactive({ total: 0, active: 0, today_total: 0, today_success: 0, today_failed: 0 });
const teamsWebhooks = ref([]);
const teamsLogs = ref([]);
const teamsNotificationTypes = ref([]);

const teamsForm = reactive({
    name: '', webhook_url: '', notification_type: 'all', is_active: true, description: '',
});
const activationForm = reactive({ license_key: '', product_name: '', customer_name: '' });
const alertForm = reactive({ title: '', message: '', severity: 'warning' });

function teamsTypeLabel(type) {
    return notificationTypeLabels.value[type] || type;
}

const channelName = (ch) => channelLabels.value[ch] || ch;
const tagType = (ch) => ({ slack: 'success', dingtalk: 'warning', wecom: 'primary', feishu: 'info' }[ch] || 'info');

async function loadTeamsConfig() {
    try { const res = await teamsNotifier.config(); teamsNotificationTypes.value = res.data?.data?.notification_types || []; } catch {}
}
async function loadTeamsDash() {
    try { const res = await teamsNotifier.dashboard(); Object.assign(teamsDash, res.data?.data || {}); } catch {}
}
async function loadTeamsWebhooks() {
    teamsLoading.value = true;
    try { const res = await teamsNotifier.list(); teamsWebhooks.value = res.data.data || []; } catch {} finally { teamsLoading.value = false; }
}
async function loadTeamsLogs() {
    teamsLogsLoading.value = true;
    try {
        const res = await teamsNotifier.logs({ page: teamsLogPage.value, per_page: teamsLogPerPage.value, status: teamsLogStatusFilter.value || undefined, type: teamsLogTypeFilter.value || undefined });
        teamsLogs.value = res.data.data?.data || res.data.data || [];
        teamsLogTotal.value = res.data.meta?.total || 0;
    } catch {} finally { teamsLogsLoading.value = false; }
}
function openTeamsDialog() {
    teamsIsEditing.value = false;
    teamsEditingId.value = null;
    teamsForm.name = ''; teamsForm.webhook_url = ''; teamsForm.notification_type = 'all'; teamsForm.is_active = true; teamsForm.description = '';
    teamsDlgVisible.value = true;
}
function editTeamsWebhook(row) {
    teamsIsEditing.value = true;
    teamsEditingId.value = row.id;
    teamsForm.name = row.name; teamsForm.webhook_url = row.webhook_url; teamsForm.notification_type = row.notification_type; teamsForm.is_active = row.is_active; teamsForm.description = row.description || '';
    teamsDlgVisible.value = true;
}
async function saveTeamsWebhook() {
    if (!teamsForm.name || !teamsForm.webhook_url) { ElMessage.warning(t('im_integration_page.messages.fill_required')); return; }
    teamsSaving.value = true;
    try {
        if (teamsIsEditing.value) { await teamsNotifier.update(teamsEditingId.value, teamsForm); ElMessage.success(t('im_integration_page.messages.updated')); }
        else { await teamsNotifier.create(teamsForm); ElMessage.success(t('im_integration_page.messages.created')); }
        teamsDlgVisible.value = false;
        await loadTeamsWebhooks();
    } catch (e) { ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.save_failed')); } finally { teamsSaving.value = false; }
}
async function teamsTest(row) {
    try { await teamsNotifier.test(row.id); ElMessage.success(t('im_integration_page.messages.test_success')); } catch (e) { ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.test_failed')); }
}
async function teamsDelete(row) {
    try { await teamsNotifier.delete(row.id); ElMessage.success(t('im_integration_page.messages.deleted')); await loadTeamsWebhooks(); } catch (e) { ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.delete_failed')); }
}
async function handleSendActivation() {
    if (!activationForm.license_key) { ElMessage.warning(t('im_integration_page.messages.license_key_required')); return; }
    sendingActivation.value = true;
    try { await teamsNotifier.sendActivation(activationForm); ElMessage.success(t('im_integration_page.messages.activation_sent')); } catch (e) { ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.send_failed')); } finally { sendingActivation.value = false; }
}
async function handleSendAlert() {
    if (!alertForm.title) { ElMessage.warning(t('im_integration_page.messages.title_required')); return; }
    sendingAlert.value = true;
    try { await teamsNotifier.sendAlert(alertForm); ElMessage.success(t('im_integration_page.messages.alert_sent')); } catch (e) { ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.send_failed')); } finally { sendingAlert.value = false; }
}

const testSlack = async () => {
    if (!slackForm.webhook_url) { ElMessage.warning(t('im_integration_page.messages.webhook_url_required')); return; }
    testing.slack = true;
    try {
        const res = await apiTestSlack(slackForm.webhook_url);
        results.slack = { success: true, message: res.data?.data?.message || res.data?.message || t('im_integration_page.messages.connection_ok') };
    } catch (e) {
        results.slack = { success: false, message: e.response?.data?.message || t('im_integration_page.messages.connection_failed') };
    } finally { testing.slack = false; }
};

const testDingTalk = async () => {
    if (!dingtalkForm.webhook_url) { ElMessage.warning(t('im_integration_page.messages.webhook_url_required')); return; }
    testing.dingtalk = true;
    try {
        const res = await apiTestDingTalk(dingtalkForm.webhook_url);
        results.dingtalk = { success: true, message: res.data?.data?.message || res.data?.message || t('im_integration_page.messages.connection_ok') };
    } catch (e) {
        results.dingtalk = { success: false, message: e.response?.data?.message || t('im_integration_page.messages.connection_failed') };
    } finally { testing.dingtalk = false; }
};

const testWeCom = async () => {
    if (!wecomForm.webhook_url) { ElMessage.warning(t('im_integration_page.messages.webhook_url_required')); return; }
    testing.wecom = true;
    try {
        const res = await apiTestWeCom(wecomForm.webhook_url);
        results.wecom = { success: true, message: res.data?.data?.message || res.data?.message || t('im_integration_page.messages.connection_ok') };
    } catch (e) {
        results.wecom = { success: false, message: e.response?.data?.message || t('im_integration_page.messages.connection_failed') };
    } finally { testing.wecom = false; }
};

const testFeishu = async () => {
    if (!feishuForm.webhook_url) { ElMessage.warning(t('im_integration_page.messages.webhook_url_required')); return; }
    testing.feishu = true;
    try {
        const res = await apiTestFeishu(feishuForm.webhook_url);
        results.feishu = { success: true, message: res.data?.data?.message || res.data?.message || t('im_integration_page.messages.connection_ok') };
    } catch (e) {
        results.feishu = { success: false, message: e.response?.data?.message || t('im_integration_page.messages.connection_failed') };
    } finally { testing.feishu = false; }
};

const sendTestMsg = async (channel, webhookUrl, formKey) => {
    if (!webhookUrl) { ElMessage.warning(t('im_integration_page.messages.webhook_url_required')); return; }
    sending[formKey] = true;
    try {
        await sendImMessage({
            channel,
            webhook_url: webhookUrl,
            title: t('im_integration_page.test_msg.title'),
            content: t('im_integration_page.test_msg.content', { time: new Date().toLocaleString() }),
            severity: 'low',
        });
        ElMessage.success(t('im_integration_page.messages.test_msg_sent'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('im_integration_page.messages.send_failed'));
    } finally { sending[formKey] = false; }
};

const sendSlackTestMsg = () => sendTestMsg('slack', slackForm.webhook_url, 'slack');
const sendDingTalkTestMsg = () => sendTestMsg('dingtalk', dingtalkForm.webhook_url, 'dingtalk');
const sendWeComTestMsg = () => sendTestMsg('wecom', wecomForm.webhook_url, 'wecom');
const sendFeishuTestMsg = () => sendTestMsg('feishu', feishuForm.webhook_url, 'feishu');

watch(teamsTab, (val) => {
    if (val === 'logs') loadTeamsLogs();
});

onMounted(() => {
    // 从 config 加载自动发送事件配置
    if (imConfig?.auto_send) {
        autoSendEvents.value = Object.entries(imConfig.auto_send).map(([event, channels]) => ({
            event: event.replace(/\./g, ' · '),
            channels,
        }));
    }
    loadTeamsConfig();
    loadTeamsDash();
    loadTeamsWebhooks();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.card-header { display: flex; align-items: center; gap: 8px; }
.channel-body { min-height: 180px; }
.channel-desc { font-size: 13px; color: #909399; margin-bottom: 12px; }
.result-box { padding: 8px 12px; border-radius: 4px; font-size: 13px; margin-top: 8px; }
.result-box.success { background: #f0f9eb; color: #67c23a; }
.result-box.error { background: #fef0f0; color: #f56c6c; }
.channel-help { margin-top: 8px; font-size: 12px; }
.channel-help a { color: #0f172a; text-decoration: none; }
.mr-1 { margin-right: 4px; }
.mb-4 { margin-bottom: 16px; }
.stat-value { font-size: 24px; font-weight: bold; text-align: center; }
.stat-label { font-size: 13px; color: #909399; text-align: center; margin-top: 4px; }
.pagination { margin-top: 16px; text-align: center; }
.text-muted { color: #909399; font-size: 13px; }
</style>
