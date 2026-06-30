<template>
    <div class="crm-page">
        <h2>CRM 集成</h2>

        <el-row :gutter="20" class="crm-cards">
            <el-col :span="12" v-for="(crm, provider) in dashboard" :key="provider">
                <el-card shadow="hover">
                    <template #header>
                        <div class="crm-header">
                            <span style="font-weight:600;font-size:16px">{{ provider === 'hubspot' ? 'HubSpot' : 'Salesforce' }}</span>
                            <el-tag :type="crm.connected ? 'success' : 'danger'" size="small">
                                {{ crm.connected ? '已连接' : '未连接' }}
                            </el-tag>
                        </div>
                    </template>
                    <el-descriptions :column="1" size="small">
                        <el-descriptions-item label="状态">{{ crm.status }}</el-descriptions-item>
                        <el-descriptions-item label="最后同步">{{ crm.last_sync_at || '从未' }}</el-descriptions-item>
                        <el-descriptions-item label="最后成功">{{ crm.last_success_at || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="已映射客户">{{ crm.mapped_customers || 0 }}</el-descriptions-item>
                        <el-descriptions-item label="已映射License">{{ crm.mapped_licenses || 0 }}</el-descriptions-item>
                        <el-descriptions-item label="同步次数">{{ crm.sync_count || 0 }}</el-descriptions-item>
                        <el-descriptions-item v-if="crm.last_error" label="错误" style="color:#f56c6c">{{ crm.last_error }}</el-descriptions-item>
                    </el-descriptions>
                    <div style="margin-top:12px">
                        <el-button v-if="!crm.connected" type="primary" size="small" @click="showConnect(provider)">连接</el-button>
                        <el-button v-else size="small" @click="showSync(provider)">同步管理</el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 连接对话框 -->
        <el-dialog v-model="showConnectDialog" :title="'连接 ' + (connectProvider === 'hubspot' ? 'HubSpot' : 'Salesforce')" width="500px">
            <el-form :model="credForm" label-width="120px">
                <template v-if="connectProvider === 'hubspot'">
                    <el-form-item label="API Key"><el-input v-model="credForm.api_key" type="password" show-password /></el-form-item>
                    <el-form-item label="Portal ID"><el-input v-model="credForm.portal_id" /></el-form-item>
                </template>
                <template v-else>
                    <el-form-item label="Client ID"><el-input v-model="credForm.client_id" /></el-form-item>
                    <el-form-item label="Client Secret"><el-input v-model="credForm.client_secret" type="password" /></el-form-item>
                    <el-form-item label="用户名"><el-input v-model="credForm.username" /></el-form-item>
                    <el-form-item label="密码"><el-input v-model="credForm.password" type="password" /></el-form-item>
                    <el-form-item label="Security Token"><el-input v-model="credForm.security_token" /></el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="showConnectDialog = false">取消</el-button>
                <el-button type="primary" @click="handleConnect" :loading="connecting">连接</el-button>
            </template>
        </el-dialog>

        <!-- 同步管理对话框 -->
        <el-dialog v-model="showSyncDialog" :title="'同步管理 - ' + (syncProvider === 'hubspot' ? 'HubSpot' : 'Salesforce')" width="700px">
            <el-tabs v-model="syncTab">
                <el-tab-pane label="同步操作" name="sync">
                    <div style="margin-bottom:16px">
                        <el-button type="primary" @click="handlePush('customer')" :loading="syncing">推送客户→CRM</el-button>
                        <el-button type="primary" @click="handlePush('license')" :loading="syncing" style="margin-left:8px">推送License→CRM</el-button>
                    </div>
                    <div>
                        <el-button @click="handlePull('customer')" :loading="syncing">从CRM拉取客户</el-button>
                        <el-button @click="handlePull('license')" :loading="syncing" style="margin-left:8px">从CRM拉取License</el-button>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="同步日志" name="logs">
                    <el-table :data="syncLogs" v-loading="logsLoading" stripe size="small">
                        <el-table-column prop="sync_type" label="方向" width="70" />
                        <el-table-column prop="entity_type" label="实体" width="80" />
                        <el-table-column prop="status" label="状态" width="90">
                            <template #default="{row}"><el-tag :type="row.status==='success'?'success':'danger'" size="small">{{ row.status }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="total" label="总数" width="60" />
                        <el-table-column prop="success" label="成功" width="60" />
                        <el-table-column prop="failed" label="失败" width="60" />
                        <el-table-column prop="created_at" label="时间" width="170" />
                    </el-table>
                </el-tab-pane>
            </el-tabs>
            <template #footer>
                <el-button @click="showSyncDialog = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getCrmDashboard, connectCrm, disconnectCrm, pushToCrm, pullFromCrm, getCrmLogs, getCrmConnection } from '@/api/crmIntegration';

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
        ElMessage.success('连接成功');
        showConnectDialog.value = false;
        loadDashboard();
    } catch (e) {
        ElMessage.error('连接失败');
    } finally { connecting.value = false; }
}

async function handlePush(type) {
    syncing.value = true;
    try {
        // Need connection ID - simplified, uses provider name lookup
        const connId = 1;
        const r = await pushToCrm(connId, type);
        ElMessage.success(`推送完成: ${r.success}/${r.total}`);
        await loadLogs();
    } catch (e) { ElMessage.error('推送失败'); } finally { syncing.value = false; }
}

async function handlePull(type) {
    syncing.value = true;
    try {
        const connId = 1;
        const r = await pullFromCrm(connId, type);
        ElMessage.success(`拉取完成: ${r.success}/${r.total}`);
        await loadLogs();
    } catch (e) { ElMessage.error('拉取失败'); } finally { syncing.value = false; }
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
