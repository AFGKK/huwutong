<template>
    <div class="customer-smtp-container">
        <el-page-header :content="t('customer_smtp_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('customer_smtp_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value">{{ dash.stats.total }}</div>
                    <div class="stat-label">{{ t('customer_smtp_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-success">{{ dash.stats.active }}</div>
                    <div class="stat-label">{{ t('customer_smtp_page.stats.active') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-danger">{{ dash.stats.failed }}</div>
                    <div class="stat-label">{{ t('customer_smtp_page.stats.failed') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-value text-primary">{{ dash.stats.primary?.host || t('customer_smtp_page.system_default') }}</div>
                    <div class="stat-label">{{ t('customer_smtp_page.stats.primary') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 降级配置卡片 -->
        <el-card class="fallback-card">
            <template #header><el-space><span>{{ t('customer_smtp_page.fallback.title') }}</span><el-tag type="warning">M2-84</el-tag></el-space></template>
            <el-descriptions :column="4" border size="small">
                <el-descriptions-item :label="t('customer_smtp_page.fallback.failure_threshold')">{{ dash.fallback_config?.failure_threshold }} {{ t('customer_smtp_page.fallback.times_unit') }}</el-descriptions-item>
                <el-descriptions-item :label="t('customer_smtp_page.fallback.recovery_interval')">{{ dash.fallback_config?.recovery_interval }} {{ t('customer_smtp_page.fallback.minutes_unit') }}</el-descriptions-item>
                <el-descriptions-item :label="t('customer_smtp_page.fallback.system_default')">{{ dash.system_default?.host }}:{{ dash.system_default?.port }}</el-descriptions-item>
                <el-descriptions-item :label="t('customer_smtp_page.fallback.alert_email')">{{ dash.fallback_config?.alert_email }}</el-descriptions-item>
            </el-descriptions>
            <div class="fallback-flow">
                <strong>{{ t('customer_smtp_page.fallback.chain_label') }}</strong>
                <el-tag type="success">{{ t('smtp_fallback_page.primary_smtp') }}</el-tag> →
                <el-tag type="warning">{{ t('smtp_fallback_page.backup_smtp') }}</el-tag> →
                <el-tag type="info">{{ t('customer_smtp_page.fallback.system_default_smtp') }}</el-tag> →
                <el-tag type="danger">{{ t('customer_smtp_page.fallback.alert_notify') }}</el-tag>
            </div>
        </el-card>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('customer_smtp_page.tabs.configs')" name="configs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('customer_smtp_page.configs.list_title') }}</span>
                            <el-button size="small" type="primary" @click="openCreateDialog">{{ t('customer_smtp_page.configs.new') }}</el-button>
                            <el-button size="small" @click="handleRecover">{{ t('customer_smtp_page.configs.recover_check') }}</el-button>
                        </el-space>
                    </template>
                    <el-table :data="configs" stripe v-loading="loading">
                        <el-table-column :label="t('smtp_fallback_page.columns.provider')" width="120">
                            <template #default="{ row }">
                                <el-tag size="small">{{ providers[row.provider]?.name || row.provider }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="host" :label="t('smtp_fallback_page.columns.host')" width="180" />
                        <el-table-column prop="port" :label="t('customer_smtp_page.form.port')" width="70" />
                        <el-table-column prop="encryption" :label="t('customer_smtp_page.columns.encryption')" width="70" />
                        <el-table-column prop="from_address" :label="t('customer_smtp_page.columns.from_address')" width="200" />
                        <el-table-column :label="t('customer_smtp_page.columns.primary')" width="60" align="center">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_primary" type="success" size="small">{{ t('smtp_fallback_page.role.primary') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('smtp_fallback_page.columns.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                    {{ configStatusLabels[row.status] || row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('customer_smtp_page.columns.actions')" width="320" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="editConfig(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" type="success" @click="handleTest(row)">{{ t('customer_smtp_page.btn.test') }}</el-button>
                                <el-button v-if="!row.is_primary" size="small" type="primary" @click="handleSetPrimary(row)">{{ t('customer_smtp_page.btn.set_primary') }}</el-button>
                                <el-popconfirm :title="t('customer_smtp_page.confirm_delete')" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('customer_smtp_page.tabs.logs')" name="logs">
                <el-card>
                    <template #header>
                        <el-space>
                            <span>{{ t('customer_smtp_page.logs.title') }}</span>
                            <el-select v-model="logEventFilter" :placeholder="t('customer_smtp_page.event_filter_ph')" clearable size="small" style="width:140px" @change="loadLogs">
                                <el-option v-for="opt in logEventFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-space>
                    </template>
                    <el-table :data="logs" stripe v-loading="logsLoading">
                        <el-table-column prop="created_at" :label="t('customer_smtp_page.columns.time')" width="160" />
                        <el-table-column :label="t('customer_smtp_page.columns.event')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.event_type === 'alert' ? 'danger' : row.event_type === 'failover' ? 'warning' : row.event_type === 'recovery' ? 'success' : 'info'" size="small">
                                    {{ eventLabels[row.event_type] || row.event_type }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('smtp_fallback_page.columns.status')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ logStatusLabels[row.status] || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="from_address" :label="t('customer_smtp_page.columns.from_address')" width="180" />
                        <el-table-column prop="to_address" :label="t('customer_smtp_page.columns.to_address')" width="180" />
                        <el-table-column prop="subject" :label="t('customer_smtp_page.columns.subject')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="error_message" :label="t('customer_smtp_page.columns.error')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="fallback_action" :label="t('customer_smtp_page.columns.fallback_action')" width="140">
                            <template #default="{ row }">
                                <el-tag v-if="row.fallback_action" size="small" type="warning">{{ fallbackActionLabels[row.fallback_action] || row.fallback_action }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="logTotal > logPerPage" v-model:current-page="logPage" :page-size="logPerPage" :total="logTotal" layout="prev, pager, next" @current-change="loadLogs" class="pagination" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 新建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? t('customer_smtp_page.dialog.edit_title') : t('customer_smtp_page.dialog.create_title')" width="600px">
            <el-form label-position="top" size="small" :model="form">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.provider')" required>
                            <el-select v-model="form.provider" style="width:100%" @change="applyProviderTemplate">
                                <el-option v-for="(p, key) in providers" :key="key" :label="p.name" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.name')">
                            <el-input v-model="form.name" :placeholder="t('customer_smtp_page.form.name_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.host')" required>
                            <el-input v-model="form.host" placeholder="smtp.example.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t('customer_smtp_page.form.port')" required>
                            <el-input-number v-model="form.port" :min="1" :max="65535" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="t('customer_smtp_page.form.encryption')">
                            <el-select v-model="form.encryption" style="width:100%">
                                <el-option v-for="opt in encryptionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.username')">
                            <el-input v-model="form.username" :placeholder="t('customer_smtp_page.form.username_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.password')">
                            <el-input v-model="form.password" type="password" show-password :placeholder="t('customer_smtp_page.form.password_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.from_address')">
                            <el-input v-model="form.from_address" placeholder="noreply@yourdomain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('customer_smtp_page.form.from_name')">
                            <el-input v-model="form.from_name" :placeholder="t('customer_smtp_page.form.from_name_ph')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item>
                    <el-checkbox v-model="form.is_primary">{{ t('customer_smtp_page.form.is_primary') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveConfig">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import customerSmtp from '@/api/customerSmtp';

const { t } = useI18n();

const activeTab = ref('configs');
const loading = ref(false);
const logsLoading = ref(false);
const saving = ref(false);
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const logEventFilter = ref('');

const dash = reactive({ stats: { total: 0, active: 0, failed: 0, primary: null }, providers: {}, fallback_config: {}, system_default: {} });
const providers = reactive({});
const configs = ref([]);
const logs = ref([]);

const form = reactive({
    provider: 'custom', name: '', host: '', port: 587, encryption: 'tls',
    username: '', password: '', from_address: '', from_name: '', is_primary: false,
});

const logTotal = ref(0);
const logPage = ref(1);
const logPerPage = ref(20);

const configStatusLabels = computed(() => ({
    active: t('customer_smtp_page.status.active'),
    failed: t('customer_smtp_page.status.failed'),
}));

const logStatusLabels = computed(() => ({
    success: t('customer_smtp_page.status.success'),
    failed: t('customer_smtp_page.status.failed'),
}));

const eventLabels = computed(() => ({
    send: t('customer_smtp_page.events.send'),
    test: t('customer_smtp_page.events.test'),
    failover: t('customer_smtp_page.events.failover'),
    recovery: t('customer_smtp_page.events.recovery'),
    alert: t('customer_smtp_page.events.alert'),
}));

const fallbackActionLabels = computed(() => ({
    switch_to_backup: t('customer_smtp_page.fallback_actions.switch_to_backup'),
    use_system_default: t('customer_smtp_page.fallback_actions.use_system_default'),
    alert: t('customer_smtp_page.fallback_actions.alert'),
}));

const logEventFilterOptions = computed(() => [
    { label: t('customer_smtp_page.events.all'), value: '' },
    { label: t('customer_smtp_page.events.send'), value: 'send' },
    { label: t('customer_smtp_page.events.test'), value: 'test' },
    { label: t('customer_smtp_page.events.failover'), value: 'failover' },
    { label: t('customer_smtp_page.events.recovery'), value: 'recovery' },
    { label: t('customer_smtp_page.events.alert'), value: 'alert' },
]);

const encryptionOptions = computed(() => [
    { label: t('customer_smtp_page.encryption.none'), value: '' },
    { label: t('customer_smtp_page.encryption.ssl'), value: 'ssl' },
    { label: t('customer_smtp_page.encryption.tls'), value: 'tls' },
]);

async function loadProviders() {
    try {
        const res = await customerSmtp.providers();
        Object.assign(providers, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadDashboard() {
    try {
        const res = await customerSmtp.dashboard();
        Object.assign(dash, res.data.data);
    } catch (e) { console.error(e); }
}

async function loadConfigs() {
    loading.value = true;
    try {
        const res = await customerSmtp.list();
        configs.value = res.data.data || [];
    } catch (e) { console.error(e); } finally { loading.value = false; }
}

async function loadLogs(page) {
    logsLoading.value = true;
    try {
        const params = { page: page || logPage.value, per_page: logPerPage.value };
        if (logEventFilter.value) params.event_type = logEventFilter.value;
        const res = await customerSmtp.logs(params);
        logs.value = res.data.data.items || [];
        logTotal.value = res.data.data.total;
        logPage.value = res.data.data.page;
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

function applyProviderTemplate(key) {
    const p = providers[key];
    if (p && key !== 'custom') {
        form.host = p.host;
        form.port = p.port;
        form.encryption = p.encryption;
    }
}

function openCreateDialog() {
    isEditing.value = false; editingId.value = null;
    form.provider = 'custom'; form.name = ''; form.host = ''; form.port = 587;
    form.encryption = 'tls'; form.username = ''; form.password = '';
    form.from_address = ''; form.from_name = ''; form.is_primary = false;
    dialogVisible.value = true;
}

function editConfig(row) {
    isEditing.value = true; editingId.value = row.id;
    form.provider = row.provider; form.name = row.name || ''; form.host = row.host;
    form.port = row.port; form.encryption = row.encryption || 'tls';
    form.username = row.username || ''; form.password = '';
    form.from_address = row.from_address || ''; form.from_name = row.from_name || '';
    form.is_primary = row.is_primary;
    dialogVisible.value = true;
}

async function saveConfig() {
    if (!form.host || !form.port) { ElMessage.warning(t('customer_smtp_page.messages.host_port_required')); return; }
    saving.value = true;
    try {
        const data = { ...form };
        if (!data.password) delete data.password;
        if (isEditing.value) {
            await customerSmtp.update(editingId.value, data);
            ElMessage.success(t('customer_smtp_page.messages.updated'));
        } else {
            await customerSmtp.create(data);
            ElMessage.success(t('customer_smtp_page.messages.created'));
        }
        dialogVisible.value = false;
        loadConfigs();
    } catch (e) { console.error(e); } finally { saving.value = false; }
}

async function handleTest(row) {
    try {
        const res = await customerSmtp.test(row.id);
        if (res.data.success) {
            ElMessage.success(t('customer_smtp_page.messages.test_success'));
        } else {
            ElMessage.error(res.data.message || t('customer_smtp_page.messages.test_failed'));
        }
    } catch (e) { console.error(e); }
}

async function handleSetPrimary(row) {
    try {
        await customerSmtp.setPrimary(row.id);
        ElMessage.success(t('customer_smtp_page.messages.set_primary_ok'));
        loadConfigs();
    } catch (e) { console.error(e); }
}

async function handleDelete(row) {
    try { await customerSmtp.destroy(row.id); ElMessage.success(t('customer_smtp_page.messages.deleted')); loadConfigs(); } catch (e) { console.error(e); }
}

async function handleRecover() {
    try {
        const res = await customerSmtp.recover();
        ElMessage.success(t('customer_smtp_page.messages.recover_done', { count: res.data.data.recovered.length }));
        loadConfigs();
    } catch (e) { console.error(e); }
}

onMounted(() => { loadProviders(); loadDashboard(); loadConfigs(); });
</script>

<style scoped>
.customer-smtp-container { padding: 20px; }
.alert-info { margin: 16px 0; }
.stat-cards { margin-bottom: 16px; }
.stat-cards .el-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: bold; color: #303133; overflow: hidden; text-overflow: ellipsis; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-primary { color: #0f172a; }
.text-danger { color: #f56c6c; }
.pagination { margin-top: 16px; text-align: center; }
.fallback-card { margin-bottom: 16px; }
.fallback-flow { margin-top: 12px; padding: 8px 12px; background: #f5f7fa; border-radius: 4px; display: flex; align-items: center; gap: 8px; font-size: 13px; }
</style>
