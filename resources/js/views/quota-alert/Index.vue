<template>
    <div class="quota-alert-container">
        <el-page-header :content="t('quota_alert_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('quota_alert_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card class="filter-card">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t('quota_alert_page.start_date')">
                    <el-date-picker v-model="filters.start_date" type="date" :placeholder="t('quota_alert_page.start_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item :label="t('quota_alert_page.end_date')">
                    <el-date-picker v-model="filters.end_date" type="date" :placeholder="t('quota_alert_page.end_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadData">{{ t('quota_alert_page.query') }}</el-button>
                    <el-button @click="handleCheckAll">{{ t('quota_alert_page.check_all') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dashboard.stats.total }}</div>
                    <div class="stat-label">{{ t('quota_alert_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dashboard.stats.normal }}</div>
                    <div class="stat-label">{{ t('quota_alert_page.levels.normal') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-warning">{{ dashboard.stats.warning }}</div>
                    <div class="stat-label">{{ t('quota_alert_page.stats.warning_pct', { pct: cfg.thresholds?.warning || 80 }) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dashboard.stats.critical + dashboard.stats.exceeded }}</div>
                    <div class="stat-label">{{ t('quota_alert_page.stats.critical_plus') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('quota_alert_page.tabs.list')" name="list">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('quota_alert_page.list_title') }}</span>
                            <el-select v-model="levelFilter" :placeholder="t('quota_alert_page.level')" clearable size="small" style="width:120px" @change="loadList">
                                <el-option :label="t('quota_alert_page.all')" value="" />
                                <el-option :label="t('quota_alert_page.levels.normal')" value="normal" />
                                <el-option :label="t('quota_alert_page.levels.warning')" value="warning" />
                                <el-option :label="t('quota_alert_page.levels.critical')" value="critical" />
                                <el-option :label="t('quota_alert_page.levels.exceeded')" value="exceeded" />
                            </el-select>
                            <el-select v-model="typeFilter" :placeholder="t('quota_alert_page.type')" clearable size="small" style="width:140px" @change="loadList">
                                <el-option :label="t('quota_alert_page.all')" value="" />
                                <el-option v-for="(name, key) in cfg.types" :key="key" :label="name" :value="key" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="alerts" stripe v-loading="loading">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column :label="t('quota_alert_page.cols.type')" width="120">
                            <template #default="{ row }">{{ cfg.types[row.quota_type] || row.quota_type }}</template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.level')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="levelType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.usage')" min-width="200">
                            <template #default="{ row }">
                                <div class="usage-bar">
                                    <el-progress :percentage="row.usage_percent" :color="progressColor(row.usage_percent)" :stroke-width="16">
                                        <span>{{ row.current_usage }} / {{ row.quota_limit }}</span>
                                    </el-progress>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.notify')" width="80" align="center">
                            <template #default="{ row }">
                                <el-switch :model-value="row.notifications_enabled" @change="toggleNotifications(row)" />
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.last_checked')" width="160">
                            <template #default="{ row }">{{ row.last_checked_at || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
                                <el-button size="small" @click="openEditLimit(row)">{{ t('quota_alert_page.edit_limit') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="pagination.total > 0" v-model:current-page="pagination.page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="loadList" class="pagination" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('quota_alert_page.tabs.logs')" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('quota_alert_page.logs_title') }}</span>
                            <el-select v-model="logLevelFilter" :placeholder="t('quota_alert_page.level')" clearable size="small" style="width:120px" @change="loadLogs">
                                <el-option :label="t('quota_alert_page.all')" value="" />
                                <el-option :label="t('quota_alert_page.levels.warning')" value="warning" />
                                <el-option :label="t('quota_alert_page.levels.critical')" value="critical" />
                                <el-option :label="t('quota_alert_page.levels.exceeded')" value="exceeded" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="logs" stripe v-loading="logsLoading">
                        <el-table-column prop="created_at" :label="t('quota_alert_page.cols.time')" width="160" />
                        <el-table-column :label="t('quota_alert_page.cols.type')" width="120">
                            <template #default="{ row }">{{ cfg.types[row.quota_type] || row.quota_type }}</template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.level')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="levelType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.usage')" width="160">
                            <template #default="{ row }">{{ row.current_usage }} / {{ row.quota_limit }} ({{ row.usage_percent }}%)</template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.channel')" width="80">
                            <template #default="{ row }">{{ cfg.channels[row.channel] || row.channel }}</template>
                        </el-table-column>
                        <el-table-column :label="t('quota_alert_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'sent' ? 'success' : 'danger'" size="small">{{ row.status === 'sent' ? t('quota_alert_page.sent') : t('quota_alert_page.failed') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="message" :label="t('quota_alert_page.cols.message')" min-width="300" show-overflow-tooltip />
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="detailVisible" :title="t('quota_alert_page.detail_title')" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('quota_alert_page.cols.type')">{{ cfg.types[detailData.quota_type] }}</el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.cols.level')">
                        <el-tag :type="levelType(detailData.level)" size="small">{{ levelLabel(detailData.level) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.current_usage')">{{ detailData.current_usage }}</el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.quota_limit')">{{ detailData.quota_limit }}</el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.usage_pct')">{{ detailData.usage_percent }}%</el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.cols.notify')">
                        <el-switch :model-value="detailData.notifications_enabled" disabled />
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.cols.last_checked')">{{ detailData.last_checked_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('quota_alert_page.last_notified')">{{ detailData.last_notified_at || '-' }}</el-descriptions-item>
                </el-descriptions>
            </template>
        </el-dialog>

        <el-dialog v-model="limitDialogVisible" :title="t('quota_alert_page.limit_dialog_title')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('quota_alert_page.current_limit')">
                    <el-input :model-value="editingAlert?.quota_limit" disabled />
                </el-form-item>
                <el-form-item :label="t('quota_alert_page.new_limit')" required>
                    <el-input-number v-model="newLimit" :min="1" style="width:100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="limitDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveLimit">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import quotaAlert from '@/api/quotaAlert';

const { t } = useI18n();

const activeTab = ref('list');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const detailVisible = ref(false);
const limitDialogVisible = ref(false);
const levelFilter = ref('');
const typeFilter = ref('');
const logLevelFilter = ref('');

const filters = reactive({ start_date: '', end_date: '' });
const dashboard = reactive({ stats: { total: 0, normal: 0, warning: 0, critical: 0, exceeded: 0 }, by_type: [], recent_logs: [], thresholds: {}, types: {}, channels: {} });
const cfg = reactive({ types: {}, thresholds: {}, channels: {} });
const alerts = ref([]);
const logs = ref([]);
const detailData = ref(null);
const editingAlert = ref(null);
const newLimit = ref(0);

const pagination = reactive({ total: 0, page: 1, per_page: 20 });
const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

function setDefaultDates() {
    const now = new Date();
    const thirtyDaysAgo = new Date(now); thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    filters.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
    filters.end_date = now.toISOString().slice(0, 10);
}

function levelType(l) {
    return { normal: 'success', warning: 'warning', critical: 'danger', exceeded: 'danger' }[l] || 'info';
}
function levelLabel(l) {
    const key = { normal: 'normal', warning: 'warning', critical: 'critical', exceeded: 'exceeded' }[l];
    return key ? t(`quota_alert_page.levels.${key}`) : l;
}
function progressColor(p) {
    if (p >= 100) return '#f56c6c';
    if (p >= 90) return '#e6a23c';
    if (p >= 80) return '#f56c6c';
    return '#67c23a';
}

async function loadConfig() {
    try { const res = await quotaAlert.config(); Object.assign(cfg, res.data.data); } catch (e) { console.error(e); }
}

async function loadData() {
    await Promise.all([loadDashboard(), loadList()]);
}

async function loadDashboard() {
    try {
        const res = await quotaAlert.dashboard({ start_date: filters.start_date, end_date: filters.end_date });
        Object.assign(dashboard, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadList(page) {
    loading.value = true;
    try {
        const params = { page: page || pagination.page, per_page: pagination.per_page };
        if (levelFilter.value) params.level = levelFilter.value;
        if (typeFilter.value) params.quota_type = typeFilter.value;
        const res = await quotaAlert.list(params);
        alerts.value = res.data.data.items || [];
        pagination.total = res.data.data.total;
        pagination.page = res.data.data.page;
    } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs(page) {
    logsLoading.value = true;
    try {
        const params = { page: page || logPage.value, per_page: logPerPage.value };
        if (logLevelFilter.value) params.level = logLevelFilter.value;
        const res = await quotaAlert.logs(params);
        logs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
        logPage.value = res.data.data.page;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

async function showDetail(row) {
    try { const res = await quotaAlert.detail(row.id); detailData.value = res.data.data; detailVisible.value = true; } catch (e) { console.error(e); }
}

function openEditLimit(row) {
    editingAlert.value = row;
    newLimit.value = row.quota_limit;
    limitDialogVisible.value = true;
}

async function saveLimit() {
    if (!newLimit.value || newLimit.value < 1) { ElMessage.warning(t('quota_alert_page.messages.invalid_limit')); return; }
    saving.value = true;
    try {
        await quotaAlert.updateLimit(editingAlert.value.id, { quota_limit: newLimit.value });
        ElMessage.success(t('quota_alert_page.messages.limit_updated'));
        limitDialogVisible.value = false;
        loadList();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function toggleNotifications(row) {
    try {
        await quotaAlert.toggleNotifications(row.id);
        ElMessage.success(row.notifications_enabled ? t('quota_alert_page.messages.notify_off') : t('quota_alert_page.messages.notify_on'));
        loadList();
    } catch (e) { console.error(e); }
}

async function handleCheckAll() {
    try {
        const res = await quotaAlert.checkAll();
        ElMessage.success(t('quota_alert_page.messages.check_done', { n: res.data.data.checked }));
        loadData();
    } catch (e) { console.error(e); }
}

onMounted(() => { setDefaultDates(); loadConfig(); loadData(); });
</script>

<style scoped>
.quota-alert-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.filter-card { margin-bottom: 16px; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
.usage-bar { padding: 4px 0; }
</style>
