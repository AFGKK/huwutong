<template>
    <div class="scheduled-notification-container">
        <el-page-header :content="t(`${P}.title`)" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t(`${P}.alert`)"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t(`${P}.filters.start_date`)">
                    <el-date-picker v-model="filters.start_date" type="date" :placeholder="t(`${P}.filters.start_date`)" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.end_date`)">
                    <el-date-picker v-model="filters.end_date" type="date" :placeholder="t(`${P}.filters.end_date`)" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('actions.search') }}</el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreateDialog">{{ t(`${P}.create_btn`) }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.total`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.stats.sent }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.sent`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dashboard.stats.scheduled }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.scheduled`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.stats.draft }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.draft`) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t(`${P}.tabs.list`)" name="list">
                <el-card>
                    <el-table :data="notifications" stripe v-loading="loading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="title" :label="t(`${P}.columns.title`)" min-width="200" show-overflow-tooltip />
                        <el-table-column :label="t(`${P}.columns.type`)" width="100">
                            <template #default="{ row }">
                                <el-tag size="small">{{ typeLabel(row.type) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.columns.channel`)" width="80">
                            <template #default="{ row }">
                                <el-tag size="small" type="info">{{ channelLabel(row.channel) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('notifications_page.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.columns.recipients`)" width="100">
                            <template #default="{ row }">{{ row.total_recipients }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.columns.success_failure`)" width="120">
                            <template #default="{ row }">
                                <span class="text-success">{{ row.success_count }}</span> /
                                <span class="text-danger">{{ row.failure_count }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${P}.columns.creator`)" width="120">
                            <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="scheduled_at" :label="t(`${P}.columns.scheduled_at`)" width="160" />
                        <el-table-column :label="t(`${P}.columns.actions`)" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="showDetail(row)">{{ t(`${P}.actions.detail`) }}</el-button>
                                <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" size="small" @click="editNotification(row)">{{ t('actions.edit') }}</el-button>
                                <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" size="small" type="primary" @click="handleSend(row)">{{ t(`${P}.actions.send`) }}</el-button>
                                <el-button v-if="row.status === 'sending' || row.status === 'scheduled'" size="small" type="warning" @click="handleCancel(row)">{{ t(`${P}.actions.revoke`) }}</el-button>
                                <el-popconfirm v-if="row.status === 'draft'" :title="t(`${P}.confirm_delete`)" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="pagination.total > 0" v-model:current-page="pagination.page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.logs`)" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t(`${P}.tabs.logs`) }}</span>
                            <el-select v-model="logNotificationId" :placeholder="t(`${P}.log_filters.select_notification`)" filterable size="small" style="width: 300px" @change="loadLogs">
                                <el-option v-for="n in notifications" :key="n.id" :label="n.title" :value="n.id" />
                            </el-select>
                            <el-select v-model="logStatusFilter" :placeholder="t('notifications_page.status')" clearable size="small" style="width: 120px" @change="loadLogs">
                                <el-option :label="t('notifications_page.all')" value="" />
                                <el-option :label="t(`${P}.status.sent`)" value="sent" />
                                <el-option :label="t(`${P}.status.failed`)" value="failed" />
                                <el-option :label="t(`${P}.status.pending`)" value="pending" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="deliveryLogs" stripe v-loading="logsLoading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t(`${P}.columns.user`)" min-width="150">
                            <template #default="{ row }">{{ row.user?.name || '-' }} ({{ row.user?.email || row.email || '-' }})</template>
                        </el-table-column>
                        <el-table-column :label="t('notifications_page.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'sent' ? 'success' : row.status === 'failed' ? 'danger' : 'info'" size="small">
                                    {{ logStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="error_message" :label="t(`${P}.columns.error_message`)" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="sent_at" :label="t(`${P}.columns.sent_at`)" width="160" />
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px">
            <el-form label-position="top" size="small" :model="form">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.title`)" required>
                            <el-input v-model="form.title" :placeholder="t(`${P}.form.title_ph`)" maxlength="200" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t(`${P}.form.type`)" required>
                            <el-select v-model="form.type" style="width:100%" @change="applyTemplate">
                                <el-option v-for="(label, key) in typeOptions" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t(`${P}.form.channel`)" required>
                            <el-select v-model="form.channel" style="width:100%">
                                <el-option v-for="(label, key) in channelOptions" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.content`)" required>
                    <el-input v-model="form.content" type="textarea" :rows="6" maxlength="10000" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.action_text`)">
                            <el-input v-model="form.action_text" :placeholder="t(`${P}.form.action_text_ph`)" maxlength="100" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.form.action_url`)">
                            <el-input v-model="form.action_url" :placeholder="t(`${P}.form.action_url_ph`)" maxlength="500" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.form.scheduled_at`)">
                    <el-date-picker v-model="form.scheduled_at" type="datetime" :placeholder="t(`${P}.form.scheduled_at_ph`)" format="YYYY-MM-DD HH:mm" value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t(`${P}.form.recipient_filters`)">
                    <el-select v-model="form.filters" multiple :placeholder="t(`${P}.form.recipient_filters_ph`)" style="width:100%">
                        <el-option :label="t(`${P}.form.recipient_all`)" value="all" />
                    </el-select>
                    <div class="text-muted" style="font-size:12px;margin-top:4px">
                        {{ t(`${P}.form.variables_hint`) }}
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button @click="handlePreview">{{ t(`${P}.actions.preview`) }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveNotification">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 预览对话框 -->
        <el-dialog v-model="previewVisible" :title="t(`${P}.dialog.preview_title`)" width="600px">
            <el-descriptions :column="1" border>
                <el-descriptions-item :label="t(`${P}.form.title`)">{{ previewData.title }}</el-descriptions-item>
                <el-descriptions-item :label="t(`${P}.form.channel`)">{{ channelLabel(previewData.channel) }}</el-descriptions-item>
                <el-descriptions-item :label="t(`${P}.columns.recipients`)">{{ previewData.recipient_count }}</el-descriptions-item>
            </el-descriptions>
            <div class="preview-content" v-html="previewHtml"></div>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t(`${P}.dialog.detail_title`)" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t(`${P}.form.title`)" :span="2">{{ detailData.title }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.form.type`)">{{ typeLabel(detailData.type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.form.channel`)">{{ channelLabel(detailData.channel) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('notifications_page.status')">
                        <el-tag :type="statusType(detailData.status)" size="small">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.columns.recipients`)">{{ detailData.total_recipients }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.success`)">{{ detailData.success_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.detail.failure`)">{{ detailData.failure_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.columns.scheduled_at`)" :span="2">{{ detailData.scheduled_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.columns.sent_at`)" :span="2">{{ detailData.sent_at || '-' }}</el-descriptions-item>
                </el-descriptions>
                <h4 style="margin:16px 0 8px">{{ t(`${P}.form.content`) }}</h4>
                <div class="preview-content" style="white-space:pre-wrap">{{ detailData.content }}</div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import scheduledNotification from '@/api/scheduledNotification';

const { t } = useI18n();
const P = 'scheduled_notification_page';

const typeKeys = ['maintenance', 'holiday', 'promotion', 'announcement', 'update', 'policy', 'custom'];
const channelKeys = ['email', 'in_app', 'sms'];
const statusKeys = ['draft', 'scheduled', 'sending', 'sent', 'partial', 'cancelled', 'failed', 'pending'];

const typeOptions = computed(() =>
    Object.fromEntries(typeKeys.map((key) => [key, t(`${P}.types.${key}`)])),
);
const channelOptions = computed(() =>
    Object.fromEntries(channelKeys.map((key) => [key, t(`${P}.channels.${key}`)])),
);
const statusOptions = computed(() =>
    Object.fromEntries(statusKeys.map((key) => [key, t(`${P}.status.${key}`)])),
);
const dialogTitle = computed(() =>
    isEditing.value ? t(`${P}.dialog.edit_title`) : t(`${P}.dialog.create_title`),
);

function typeLabel(key) {
    return typeOptions.value[key] || options.types[key] || key;
}
function channelLabel(key) {
    return channelOptions.value[key] || options.channels[key] || key;
}
function statusLabel(s) {
    return statusOptions.value[s] || s;
}
function logStatusLabel(s) {
    if (s === 'cancelled') return t(`${P}.status.log_cancelled`);
    return statusOptions.value[s] || s;
}

const activeTab = ref('list');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const previewVisible = ref(false);
const detailVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const logNotificationId = ref(null);
const logStatusFilter = ref('');

const filters = reactive({ start_date: '', end_date: '' });
const form = reactive({ title: '', type: 'announcement', channel: 'email', content: '', action_text: '', action_url: '', scheduled_at: null, filters: [] });
const options = reactive({ channels: {}, types: {}, templates: {}, sending: {} });
const notifications = ref([]);
const deliveryLogs = ref([]);
const previewData = reactive({ title: '', content: '', channel: '', recipient_count: 0 });
const detailData = ref(null);

const dashboard = reactive({ stats: { total: 0, sent: 0, scheduled: 0, draft: 0, cancelled: 0, total_recipients: 0, total_success: 0, total_failure: 0 }, by_type: [], by_channel: [] });

const pagination = reactive({ total: 0, page: 1, per_page: 20 });
const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

const previewHtml = computed(() => {
    if (!previewData.content) return '';
    return previewData.content.replace(/\n/g, '<br>');
});

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now); thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

function statusType(s) {
    const map = { draft: 'info', scheduled: 'primary', sending: 'warning', sent: 'success', partial: 'warning', cancelled: 'danger', failed: 'danger' };
    return map[s] || 'info';
}

function applyTemplate(type) {
    const tmpl = options.templates[type];
    if (tmpl && !isEditing.value) {
        form.title = tmpl.subject;
        form.content = tmpl.body;
    }
}

async function loadOptions() {
    try { const res = await scheduledNotification.options(); Object.assign(options, res.data.data); } catch (e) { console.error(e); }
}

async function loadData() {
    await Promise.all([loadDashboard(), loadList()]);
}

async function loadDashboard() {
    try { const res = await scheduledNotification.dashboard({ start_date: filters.start_date, end_date: filters.end_date }); Object.assign(dashboard, res.data.data); } catch (e) { console.error(e); }
}

async function loadList(page) {
    loading.value = true;
    try {
        const res = await scheduledNotification.list({ ...filters, page: page || pagination.page, per_page: pagination.per_page });
        notifications.value = res.data.data.items || [];
        pagination.total = res.data.data.total;
        pagination.page = res.data.data.page;
    } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs() {
    if (!logNotificationId.value) { deliveryLogs.value = []; return; }
    logsLoading.value = true;
    try {
        const params = { page: logPage.value, per_page: logPerPage.value };
        if (logStatusFilter.value) params.status = logStatusFilter.value;
        const res = await scheduledNotification.deliveryLogs(logNotificationId.value, params);
        deliveryLogs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

function openCreateDialog() {
    isEditing.value = false; editingId.value = null;
    form.title = ''; form.type = 'announcement'; form.channel = 'email'; form.content = '';
    form.action_text = ''; form.action_url = ''; form.scheduled_at = null; form.filters = [];
    dialogVisible.value = true;
}

function editNotification(row) {
    isEditing.value = true; editingId.value = row.id;
    form.title = row.title; form.type = row.type; form.channel = row.channel; form.content = row.content;
    form.action_text = row.action_text || ''; form.action_url = row.action_url || '';
    form.scheduled_at = row.scheduled_at; form.filters = row.filters || [];
    dialogVisible.value = true;
}

async function saveNotification() {
    if (!form.title || !form.content) { ElMessage.warning(t(`${P}.messages.title_content_required`)); return; }
    saving.value = true;
    try {
        const payload = { ...form, filters: form.filters.length > 0 ? { type: form.filters } : null };
        if (isEditing.value) {
            await scheduledNotification.update(editingId.value, payload);
            ElMessage.success(t(`${P}.messages.updated`));
        } else {
            await scheduledNotification.create(payload);
            ElMessage.success(t(`${P}.messages.created`));
        }
        dialogVisible.value = false;
        loadList();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function handlePreview() {
    if (!editingId.value) { ElMessage.warning(t(`${P}.messages.save_before_preview`)); return; }
    try {
        const res = await scheduledNotification.preview(editingId.value);
        Object.assign(previewData, res.data.data);
        previewVisible.value = true;
    } catch (e) { console.error(e); }
}

async function handleSend(row) {
    try { await scheduledNotification.send(row.id); ElMessage.success(t(`${P}.messages.send_done`)); loadList(); } catch (e) { console.error(e); }
}

async function handleCancel(row) {
    try { await scheduledNotification.cancel(row.id); ElMessage.success(t(`${P}.messages.revoked`)); loadList(); } catch (e) { console.error(e); }
}

async function handleDelete(row) {
    try { await scheduledNotification.destroy(row.id); ElMessage.success(t(`${P}.messages.deleted`)); loadList(); } catch (e) { console.error(e); }
}

async function showDetail(row) {
    try { const res = await scheduledNotification.detail(row.id); detailData.value = res.data.data; detailVisible.value = true; } catch (e) { console.error(e); }
}

watch(logNotificationId, () => { logPage.value = 1; loadLogs(); });
watch(activeTab, (tab) => { if (tab === 'logs') loadLogs(); });

onMounted(() => { setDefaultDates(); loadOptions(); loadData(); });
</script>

<style scoped>
.scheduled-notification-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.filter-card { margin-bottom: 16px; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-primary { color: #0f172a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-muted { color: #909399; }
.pagination { margin-top: 16px; text-align: center; }
.preview-content { background: #f5f7fa; padding: 16px; border-radius: 4px; margin-top: 12px; line-height: 1.8; }
</style>
