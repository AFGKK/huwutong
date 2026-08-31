<template>
    <div class="crm-page">
        <h2>{{ t('crm_integration_page.title') }}</h2>

        <el-row :gutter="20" class="crm-cards">
            <el-col :span="12" v-for="(crm, provider) in dashboard" :key="provider">
                <el-card shadow="hover">
                    <template #header>
                        <div class="crm-header">
                            <span style="font-weight:600;font-size:16px">{{ providerLabel(provider) }}</span>
                            <el-tag :type="crm.connected ? 'success' : 'danger'" size="small">
                                {{ crm.connected ? t('crm_integration_page.connected') : t('crm_integration_page.disconnected') }}
                            </el-tag>
                        </div>
                    </template>
                    <el-descriptions :column="1" size="small">
                        <el-descriptions-item :label="t('crm_integration_page.labels.status')">{{ crm.status }}</el-descriptions-item>
                        <el-descriptions-item :label="t('crm_integration_page.labels.last_sync')">{{ crm.last_sync_at || t('crm_integration_page.never') }}</el-descriptions-item>
                        <el-descriptions-item :label="t('crm_integration_page.labels.last_success')">{{ crm.last_success_at || t('crm_integration_page.empty_value') }}</el-descriptions-item>
                        <el-descriptions-item :label="t('crm_integration_page.labels.mapped_customers')">{{ crm.mapped_customers || 0 }}</el-descriptions-item>
                        <el-descriptions-item :label="t('crm_integration_page.labels.mapped_licenses')">{{ crm.mapped_licenses || 0 }}</el-descriptions-item>
                        <el-descriptions-item :label="t('crm_integration_page.labels.sync_count')">{{ crm.sync_count || 0 }}</el-descriptions-item>
                        <el-descriptions-item v-if="crm.last_error" :label="t('crm_integration_page.labels.error')" style="color:#f56c6c">{{ crm.last_error }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-button v-if="!crm.connected" type="primary" size="small" @click="showConnect(provider)">{{ t('crm_integration_page.connect') }}</el-button>
                        <el-button v-else size="small" @click="showSync(provider)">{{ t('crm_integration_page.sync_manage') }}</el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 连接对话框 -->
        <el-dialog v-model="showConnectDialog" :title="connectDialogTitle" width="500px">
            <el-form :model="credForm" label-width="120px">
                <template v-if="connectProvider === 'hubspot'">
                    <el-form-item :label="t('crm_integration_page.form.api_key')"><el-input v-model="credForm.api_key" type="password" show-password /></el-form-item>
                    <el-form-item :label="t('crm_integration_page.form.portal_id')"><el-input v-model="credForm.portal_id" /></el-form-item>
                </template>
                <template v-else>
                    <el-form-item :label="t('crm_integration_page.form.client_id')"><el-input v-model="credForm.client_id" /></el-form-item>
                    <el-form-item :label="t('crm_integration_page.form.client_secret')"><el-input v-model="credForm.client_secret" type="password" show-password /></el-form-item>
                    <el-form-item :label="t('crm_integration_page.form.username')"><el-input v-model="credForm.username" /></el-form-item>
                    <el-form-item :label="t('crm_integration_page.form.password')"><el-input v-model="credForm.password" type="password" show-password /></el-form-item>
                    <el-form-item :label="t('crm_integration_page.form.security_token')"><el-input v-model="credForm.security_token" /></el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="showConnectDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleConnect" :loading="connecting">{{ t('crm_integration_page.connect') }}</el-button>
            </template>
        </el-dialog>

        <!-- 同步管理对话框 -->
        <el-dialog v-model="showSyncDialog" :title="syncDialogTitle" width="700px">
            <el-tabs v-model="syncTab">
                <el-tab-pane :label="t('crm_integration_page.tabs.sync')" name="sync">
                    <div style="margin-bottom:16px">
                        <el-button type="primary" @click="handlePush('customer')" :loading="syncing">{{ t('crm_integration_page.push_customer') }}</el-button>
                        <el-button type="primary" @click="handlePush('license')" :loading="syncing" style="margin-left:8px">{{ t('crm_integration_page.push_license') }}</el-button>
                    </div>
                    <div>
                        <el-button @click="handlePull('customer')" :loading="syncing">{{ t('crm_integration_page.pull_customer') }}</el-button>
                        <el-button @click="handlePull('license')" :loading="syncing" style="margin-left:8px">{{ t('crm_integration_page.pull_license') }}</el-button>
                    </div>
                </el-tab-pane>
                <el-tab-pane :label="t('crm_integration_page.tabs.logs')" name="logs">
                    <el-table :data="syncLogs" v-loading="logsLoading" stripe size="small">
                        <el-table-column prop="sync_type" :label="t('crm_integration_page.cols.direction')" width="70" />
                        <el-table-column prop="entity_type" :label="t('crm_integration_page.cols.entity')" width="80" />
                        <el-table-column prop="status" :label="t('crm_integration_page.cols.status')" width="90">
                            <template #default="{row}"><el-tag :type="row.status==='success'?'success':'danger'" size="small">{{ row.status }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="total" :label="t('crm_integration_page.cols.total')" width="60" />
                        <el-table-column prop="success" :label="t('crm_integration_page.cols.success')" width="60" />
                        <el-table-column prop="failed" :label="t('crm_integration_page.cols.failed')" width="60" />
                        <el-table-column prop="created_at" :label="t('crm_integration_page.cols.time')" width="170" />
                    </el-table>
                </el-tab-pane>
            </el-tabs>
            <template #footer>
                <el-button @click="showSyncDialog = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { getCrmDashboard, connectCrm, disconnectCrm, pushToCrm, pullFromCrm, getCrmLogs, getCrmConnection } from '@/api/crmIntegration';

const { t } = useI18n();

const dashboard = ref({});
const showConnectDialog = ref(false);
const showSyncDialog = ref(false);
const connectProvider = ref('');
const syncProvider = ref('');
const syncTab = ref('sync');
const connecting = ref(false);
const syncing = ref(false);
const logsLoading = ref(false);
const syncLogs = ref([]);
const currentConnectionId = ref(null);

const credForm = reactive({
    api_key: '', portal_id: '',
    client_id: '', client_secret: '', username: '', password: '', security_token: '',
});

const providerLabels = computed(() => ({
    hubspot: t('crm_integration_page.providers.hubspot'),
    salesforce: t('crm_integration_page.providers.salesforce'),
}));

function providerLabel(provider) {
    return providerLabels.value[provider] || provider;
}

const connectDialogTitle = computed(() =>
    t('crm_integration_page.connect_dialog_title', { provider: providerLabel(connectProvider.value) }),
);

const syncDialogTitle = computed(() =>
    t('crm_integration_page.sync_dialog_title', { provider: providerLabel(syncProvider.value) }),
);

async function loadDashboard() {
    try { dashboard.value = await getCrmDashboard(); } catch (e) { console.error(e); }
}

function showConnect(provider) {
    connectProvider.value = provider;
    Object.keys(credForm).forEach(k => credForm[k] = '');
    showConnectDialog.value = true;
}

async function showSync(provider) {
    syncProvider.value = provider;
    syncTab.value = 'sync';
    showSyncDialog.value = true;
    // Find connection ID
    const conn = await getCrmConnection(provider); // simplified
    await loadLogs();
}

async function handleConnect() {
    connecting.value = true;
    try {
        await connectCrm(connectProvider.value, { ...credForm });
        ElMessage.success(t('crm_integration_page.messages.connect_ok'));
        showConnectDialog.value = false;
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('crm_integration_page.messages.connect_fail'));
    } finally { connecting.value = false; }
}

async function handlePush(type) {
    syncing.value = true;
    try {
        // Need connection ID - simplified, uses provider name lookup
        const connId = 1;
        const r = await pushToCrm(connId, type);
        ElMessage.success(t('crm_integration_page.messages.push_ok', { success: r.success, total: r.total }));
        await loadLogs();
    } catch (e) { ElMessage.error(t('crm_integration_page.messages.push_fail')); } finally { syncing.value = false; }
}

async function handlePull(type) {
    syncing.value = true;
    try {
        const connId = 1;
        const r = await pullFromCrm(connId, type);
        ElMessage.success(t('crm_integration_page.messages.pull_ok', { success: r.success, total: r.total }));
        await loadLogs();
    } catch (e) { ElMessage.error(t('crm_integration_page.messages.pull_fail')); } finally { syncing.value = false; }
}

async function loadLogs() {
    logsLoading.value = true;
    try {
        const connId = 1;
        const r = await getCrmLogs(connId);
        syncLogs.value = r.data || [];
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}

onMounted(() => { loadDashboard(); });
</script>

<style scoped>
.crm-page { padding: 20px; }
.crm-cards { margin-bottom: 20px; }
.crm-header { display: flex; justify-content: space-between; align-items: center; }
</style>
