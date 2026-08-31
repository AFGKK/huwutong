<template>
    <div class="local-proxy-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
                </el-button>
                <el-button type="primary" @click="showRegisterDialog = true">
                    <el-icon><Plus /></el-icon> {{ t(`${P}.register_proxy`) }}
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t(`${P}.stats.total_nodes`) }}</div>
                    <div class="stat-value">{{ stats.total_nodes }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">{{ t(`${P}.stats.active_nodes`) }}</div>
                    <div class="stat-value">{{ stats.active_nodes }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-success">
                    <div class="stat-label">{{ t(`${P}.stats.healthy_nodes`) }}</div>
                    <div class="stat-value">{{ stats.healthy_nodes }}</div>
                    <div class="stat-change" v-if="stats.offline_nodes > 0" style="color:#e6a23c">
                        {{ t(`${P}.stats.offline_count`, { n: stats.offline_nodes }) }}
                    </div>
                    <div class="stat-change" v-else style="color:#67c23a">{{ t(`${P}.stats.all_online`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">{{ t(`${P}.stats.cached_licenses`) }}</div>
                    <div class="stat-value">{{ stats.cached_licenses }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">{{ t(`${P}.stats.recent_verifications_7d`) }}</div>
                    <div class="stat-value">{{ stats.recent_activations_7d }}</div>
                    <div class="stat-change" v-if="stats.denied_activations_7d > 0" style="color:#f56c6c">
                        {{ t(`${P}.stats.denied_count`, { n: stats.denied_activations_7d }) }}
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 节点列表 -->
        <el-card>
            <template #header>
                <span>{{ t(`${P}.sections.proxy_nodes`) }}</span>
            </template>

            <el-table :data="nodes" stripe v-loading="loadingTable">
                <el-table-column prop="name" :label="t(`${P}.columns.name`)" min-width="140" />
                <el-table-column :label="t(`${P}.columns.status`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'paused' ? 'warning' : 'info'"
                            size="small">
                            {{ nodeStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.health`)" width="80" align="center">
                    <template #default="{ row }">
                        <el-tooltip :content="row.is_healthy ? t(`${P}.health.healthy_tip`) : t(`${P}.health.unhealthy_tip`)" placement="top">
                            <el-tag :type="row.is_healthy ? 'success' : 'danger'" size="small" effect="plain">
                                <el-icon style="vertical-align: -2px">
                                    <component :is="row.is_healthy ? 'CircleCheck' : 'CircleClose'" />
                                </el-icon>
                            </el-tag>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="base_url" :label="t(`${P}.columns.base_url`)" min-width="160" />
                <el-table-column prop="version" :label="t(`${P}.columns.version`)" width="80" />
                <el-table-column prop="os" :label="t(`${P}.columns.os`)" width="100" />
                <el-table-column :label="t(`${P}.columns.cache`)" width="80" align="center">
                    <template #default="{ row }">{{ row.cached_licenses_count }}</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.last_heartbeat`)" width="160">
                    <template #default="{ row }">
                        <span v-if="row.last_heartbeat_at" style="font-size:12px;color:#909399">
                            {{ formatTime(row.last_heartbeat_at) }}
                        </span>
                        <span v-else style="color:#c0c4cc">{{ t(`${P}.none`) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.columns.actions`)" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewNode(row)">{{ t(`${P}.detail`) }}</el-button>
                        <el-dropdown v-if="row.status !== 'decommissioned'" trigger="click">
                            <el-button size="small">
                                {{ t('actions.more') }}<el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-if="row.status === 'pending'" @click="showActivateDialog(row)">
                                        {{ t(`${P}.activate`) }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" @click="toggleNodeStatus(row, 'paused')">
                                        {{ t(`${P}.pause`) }}
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'paused'" @click="toggleNodeStatus(row, 'active')">
                                        {{ t(`${P}.resume`) }}
                                    </el-dropdown-item>
                                    <el-dropdown-item @click="configureNode(row)">{{ t(`${P}.configure`) }}</el-dropdown-item>
                                    <el-dropdown-item divided @click="toggleNodeStatus(row, 'decommissioned')" style="color:#f56c6c">
                                        {{ t(`${P}.decommission`) }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 注册弹窗 -->
        <el-dialog v-model="showRegisterDialog" :title="t(`${P}.register_dialog.title`)" width="500px">
            <el-form :model="registerForm" ref="registerFormRef" label-position="top" :rules="registerRules">
                <el-form-item :label="t(`${P}.register_dialog.name`)" prop="name">
                    <el-input v-model="registerForm.name" :placeholder="t(`${P}.register_dialog.name_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.register_dialog.base_url`)" prop="base_url">
                    <el-input v-model="registerForm.base_url" :placeholder="t(`${P}.register_dialog.base_url_ph`)" />
                </el-form-item>
                <el-form-item :label="t(`${P}.register_dialog.capabilities`)" prop="capabilities">
                    <el-checkbox-group v-model="registerForm.capabilities">
                        <el-checkbox label="offline_auth" value="offline_auth">{{ t(`${P}.register_dialog.offline_auth`) }}</el-checkbox>
                        <el-checkbox label="heartbeat" value="heartbeat">{{ t(`${P}.register_dialog.heartbeat`) }}</el-checkbox>
                        <el-checkbox label="crl_sync" value="crl_sync">{{ t(`${P}.register_dialog.crl_sync`) }}</el-checkbox>
                        <el-checkbox label="cache" value="cache">{{ t(`${P}.register_dialog.cache`) }}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRegisterDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doRegister" :loading="registering">{{ t(`${P}.register_dialog.register`) }}</el-button>
            </template>
        </el-dialog>

        <!-- 注册结果弹窗 -->
        <el-dialog v-model="showRegisterResult" :title="t(`${P}.register_result.title`)" width="520px">
            <div class="result-info">
                <el-alert type="warning" :title="t(`${P}.register_result.alert`)" show-icon :closable="false" class="mb-4" />
                <el-descriptions :column="1" border>
                    <el-descriptions-item :label="t(`${P}.register_result.node_id`)">
                        <code class="copy-text">{{ registerResult.node_id }}</code>
                        <el-button text @click="copyToClipboard(registerResult.node_id)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.register_result.register_token`)">
                        <code class="copy-text">{{ registerResult.register_token }}</code>
                        <el-button text @click="copyToClipboard(registerResult.register_token)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.register_result.api_key`)">
                        <code class="copy-text">{{ registerResult.api_key }}</code>
                        <el-button text @click="copyToClipboard(registerResult.api_key)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <template #footer>
                <el-button type="primary" @click="showRegisterResult = false">{{ t(`${P}.register_result.saved`) }}</el-button>
            </template>
        </el-dialog>

        <!-- 激活弹窗 -->
        <el-dialog v-model="showActivateDialogVisible" :title="t(`${P}.activate_dialog.title`)" width="450px">
            <p>{{ t(`${P}.activate_dialog.hint`, { name: activateNodeData?.name }) }}</p>
            <el-form>
                <el-form-item :label="t(`${P}.activate_dialog.register_token`)">
                    <el-input v-model="activateToken" :placeholder="t(`${P}.activate_dialog.register_token_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showActivateDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doActivate" :loading="activating">{{ t(`${P}.activate`) }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" :title="t(`${P}.detail_dialog.title`)" width="800px">
            <div v-loading="loadingDetail">
                <template v-if="detailData">
                    <el-descriptions :column="2" border class="mb-4">
                        <el-descriptions-item :label="t(`${P}.detail_dialog.name`)">{{ detailData.node.name }}</el-descriptions-item>
                        <el-descriptions-item :label="t(`${P}.detail_dialog.node_id`)">
                            <code>{{ detailData.node.node_id }}</code>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t(`${P}.detail_dialog.status`)">
                            <el-tag :type="detailData.node.status === 'active' ? 'success' : 'warning'" size="small">
                                {{ nodeStatusLabel(detailData.node.status) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t(`${P}.detail_dialog.last_heartbeat`)">
                            {{ detailData.node.last_heartbeat_at ? formatTime(detailData.node.last_heartbeat_at) : t(`${P}.none`) }}
                        </el-descriptions-item>
                    </el-descriptions>

                    <el-tabs>
                        <el-tab-pane :label="t(`${P}.detail_dialog.tabs.config`)">
                            <el-descriptions :column="2" border size="small" v-if="detailData.config">
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.sync_mode`)">{{ detailData.config.sync_mode }}</el-descriptions-item>
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.sync_interval`)">{{ detailData.config.sync_interval_seconds }}s</el-descriptions-item>
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.heartbeat_interval`)">{{ detailData.config.heartbeat_interval_seconds }}s</el-descriptions-item>
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.cache_ttl`)">{{ detailData.config.cache_ttl_seconds }}s</el-descriptions-item>
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.max_cached`)">
                                    {{ t(`${P}.detail_dialog.config.max_cached_unit`, { n: detailData.config.max_cached_licenses }) }}
                                </el-descriptions-item>
                                <el-descriptions-item :label="t(`${P}.detail_dialog.config.offline_activation`)">
                                    <el-tag :type="detailData.config.allow_offline_activation ? 'success' : 'info'" size="small">
                                        {{ detailData.config.allow_offline_activation ? t(`${P}.detail_dialog.config.allowed`) : t(`${P}.detail_dialog.config.denied`) }}
                                    </el-tag>
                                </el-descriptions-item>
                            </el-descriptions>
                        </el-tab-pane>
                        <el-tab-pane :label="t(`${P}.detail_dialog.tabs.cached_licenses`)">
                            <el-table :data="detailData.cached_licenses" size="small" stripe v-if="detailData.cached_licenses?.length">
                                <el-table-column prop="license_key" :label="t(`${P}.detail_dialog.cached_cols.license_key`)" min-width="200" />
                                <el-table-column :label="t(`${P}.detail_dialog.cached_cols.status`)" width="80">
                                    <template #default="{ row }">{{ row.license_status }}</template>
                                </el-table-column>
                                <el-table-column :label="t(`${P}.detail_dialog.cached_cols.expires_at`)" width="160">
                                    <template #default="{ row }">{{ row.expires_at ? formatTime(row.expires_at) : '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t(`${P}.detail_dialog.cached_cols.verify_count`)" width="80" align="center" prop="verify_count" />
                                <el-table-column :label="t(`${P}.detail_dialog.cached_cols.expired`)" width="80" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_expired ? 'danger' : 'success'" size="small">
                                            {{ row.is_expired ? t(`${P}.common.yes`) : t(`${P}.common.no`) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else :description="t(`${P}.detail_dialog.empty_cached`)" />
                        </el-tab-pane>
                        <el-tab-pane :label="t(`${P}.detail_dialog.tabs.heartbeats`)">
                            <el-table :data="detailData.heartbeats" size="small" stripe v-if="detailData.heartbeats?.length">
                                <el-table-column :label="t(`${P}.detail_dialog.heartbeat_cols.time`)" width="160">
                                    <template #default="{ row }">{{ formatTime(row.heartbeat_at) }}</template>
                                </el-table-column>
                                <el-table-column :label="t(`${P}.detail_dialog.heartbeat_cols.status`)" width="80">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'healthy' ? 'success' : 'warning'" size="small">
                                            {{ row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t(`${P}.detail_dialog.heartbeat_cols.error`)" min-width="200" prop="error_message" />
                            </el-table>
                            <el-empty v-else :description="t(`${P}.detail_dialog.empty_heartbeats`)" />
                        </el-tab-pane>
                    </el-tabs>
                </template>
            </div>
        </el-dialog>

        <!-- 配置编辑弹窗 -->
        <el-dialog v-model="showConfigDialog" :title="t(`${P}.config_dialog.title`)" width="550px">
            <el-form :model="configForm" label-position="top" size="small">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.sync_mode`)">
                            <el-select v-model="configForm.sync_mode">
                                <el-option v-for="opt in syncModeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.sync_interval`)">
                            <el-input-number v-model="configForm.sync_interval_seconds" :min="30" :max="86400" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.heartbeat_interval`)">
                            <el-input-number v-model="configForm.heartbeat_interval_seconds" :min="10" :max="3600" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.cache_ttl`)">
                            <el-input-number v-model="configForm.cache_ttl_seconds" :min="300" :max="604800" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.max_cached`)">
                            <el-input-number v-model="configForm.max_cached_licenses" :min="10" :max="100000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${P}.config_dialog.require_cloud_validation`)">
                            <el-switch v-model="configForm.require_cloud_validation" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t(`${P}.config_dialog.allow_offline_activation`)">
                    <el-switch v-model="configForm.allow_offline_activation" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doUpdateConfig" :loading="savingConfig">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import localProxyApi from '@/api/localProxy';

const P = 'local_proxy_page';
const { t, locale } = useI18n();

const loading = ref(false);
const loadingTable = ref(false);
const loadingDetail = ref(false);

const stats = reactive({
    total_nodes: 0, active_nodes: 0, healthy_nodes: 0, offline_nodes: 0,
    cached_licenses: 0, recent_activations_7d: 0, denied_activations_7d: 0,
});
const nodes = ref([]);

// 注册
const showRegisterDialog = ref(false);
const registerFormRef = ref(null);
const registerForm = reactive({
    name: '',
    base_url: '',
    capabilities: ['offline_auth', 'heartbeat', 'crl_sync', 'cache'],
});
const registering = ref(false);
const showRegisterResult = ref(false);
const registerResult = reactive({ node_id: '', register_token: '', api_key: '' });

// 激活
const showActivateDialogVisible = ref(false);
const activateNodeData = ref(null);
const activateToken = ref('');
const activating = ref(false);

// 详情
const showDetailDialog = ref(false);
const detailData = ref(null);

// 配置
const showConfigDialog = ref(false);
const configNodeId = ref(null);
const configForm = reactive({
    sync_mode: 'poll',
    sync_interval_seconds: 300,
    heartbeat_interval_seconds: 60,
    cache_ttl_seconds: 86400,
    max_cached_licenses: 1000,
    allow_offline_activation: true,
    require_cloud_validation: false,
});
const savingConfig = ref(false);

const registerRules = computed(() => ({
    name: [{ required: true, message: t(`${P}.register_dialog.name_required`), trigger: 'blur' }],
}));

const statusActionLabels = computed(() => ({
    active: t(`${P}.status_actions.resume`),
    paused: t(`${P}.status_actions.pause`),
    decommissioned: t(`${P}.status_actions.decommission`),
}));

const statusMessageKeys = {
    active: 'resumed',
    paused: 'paused',
    decommissioned: 'decommissioned',
};

const syncModeOptions = computed(() => [
    { label: t(`${P}.sync_modes.poll`), value: 'poll' },
    { label: t(`${P}.sync_modes.push`), value: 'push' },
    { label: t(`${P}.sync_modes.hybrid`), value: 'hybrid' },
]);

function nodeStatusLabel(status) {
    const labels = {
        active: t(`${P}.status.active`),
        paused: t(`${P}.status.paused`),
        pending: t(`${P}.status.pending`),
        decommissioned: t(`${P}.status.decommissioned`),
    };
    return labels[status] || status;
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('marketplace_uploader.copied'));
    });
}

async function loadAll() {
    loading.value = true;
    loadingTable.value = true;
    try {
        const [statsRes, nodesRes] = await Promise.all([
            localProxyApi.getDashboard(),
            localProxyApi.getNodes(),
        ]);
        Object.assign(stats, statsRes.data?.data || {});
        nodes.value = nodesRes.data?.data || [];
    } catch (err) {
        console.error('Failed to load proxy data', err);
    } finally {
        loading.value = false;
        loadingTable.value = false;
    }
}

async function doRegister() {
    const valid = await registerFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    registering.value = true;
    try {
        const res = await localProxyApi.registerNode({
            name: registerForm.name,
            base_url: registerForm.base_url || undefined,
            capabilities: registerForm.capabilities,
        });
        const data = res.data?.data;
        registerResult.node_id = data.node_id;
        registerResult.register_token = data.register_token;
        registerResult.api_key = data.api_key;
        showRegisterDialog.value = false;
        showRegisterResult.value = true;
        registerForm.name = '';
        registerForm.base_url = '';
        await loadAll();
    } catch (err) {
        console.error('Register failed', err);
    } finally {
        registering.value = false;
    }
}

function showActivateDialog(node) {
    activateNodeData.value = node;
    activateToken.value = '';
    showActivateDialogVisible.value = true;
}

async function doActivate() {
    if (!activateToken.value) {
        ElMessage.warning(t(`${P}.messages.token_required`));
        return;
    }
    activating.value = true;
    try {
        await localProxyApi.activateNode({
            node_id: activateNodeData.value.node_id,
            register_token: activateToken.value,
        });
        ElMessage.success(t(`${P}.messages.node_activated`));
        showActivateDialogVisible.value = false;
        await loadAll();
    } catch (err) {
        console.error('Activate failed', err);
    } finally {
        activating.value = false;
    }
}

async function toggleNodeStatus(node, status) {
    const action = statusActionLabels.value[status];
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm.toggle`, { action, name: node.name }),
            t('actions.confirm'),
            { type: 'warning' }
        );
        await localProxyApi.updateNodeStatus(node.id, status);
        ElMessage.success(t(`${P}.messages.${statusMessageKeys[status]}`));
        await loadAll();
    } catch (err) {
        // cancelled
    }
}

async function viewNode(node) {
    showDetailDialog.value = true;
    detailData.value = null;
    loadingDetail.value = true;
    try {
        const res = await localProxyApi.getNodeDetail(node.id);
        detailData.value = res.data?.data;
    } catch (err) {
        console.error('Failed to load node detail', err);
    } finally {
        loadingDetail.value = false;
    }
}

async function configureNode(node) {
    configNodeId.value = node.id;
    const cfg = node.config || {};
    configForm.sync_mode = cfg.sync_mode || 'poll';
    configForm.sync_interval_seconds = cfg.sync_interval_seconds || 300;
    configForm.heartbeat_interval_seconds = cfg.heartbeat_interval_seconds || 60;
    configForm.cache_ttl_seconds = cfg.cache_ttl_seconds || 86400;
    configForm.max_cached_licenses = cfg.max_cached_licenses || 1000;
    configForm.allow_offline_activation = cfg.allow_offline_activation ?? true;
    configForm.require_cloud_validation = cfg.require_cloud_validation ?? false;
    showConfigDialog.value = true;
}

async function doUpdateConfig() {
    if (!configNodeId.value) return;
    savingConfig.value = true;
    try {
        await localProxyApi.updateNodeConfig(configNodeId.value, { ...configForm });
        ElMessage.success(t(`${P}.messages.config_updated`));
        showConfigDialog.value = false;
        await loadAll();
    } catch (err) {
        console.error('Update config failed', err);
    } finally {
        savingConfig.value = false;
    }
}

onMounted(loadAll);
</script>

<style scoped>
.local-proxy-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-change { font-size: 12px; margin-top: 4px; }
.stat-active .stat-value { color: #67c23a; }
.stat-success .stat-value { color: #67c23a; }
.stat-info .stat-value { color: #0f172a; }
.stat-warning .stat-value { color: #e6a23c; }

.copy-text { font-size: 13px; user-select: all; word-break: break-all; }
.result-info { padding: 8px 0; }
</style>
