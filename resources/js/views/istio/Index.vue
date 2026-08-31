<template>
    <div class="istio-manager-page">
        <el-page-header :title="t('actions.back')" @back="$router.back()" class="page-header">
            <template #content>
                <span class="page-title">{{ t('istio_page.title') }}</span>
            </template>
        </el-page-header>

        <!-- 状态卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" :class="dashboard.istio_enabled ? 'text-success' : 'text-danger'">
                        {{ dashboard.istio_enabled ? t('istio_page.status.enabled') : t('istio_page.status.disabled') }}
                    </div>
                    <div class="stat-label">{{ t('istio_page.stats.istio_status') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.services_in_mesh }}</div>
                    <div class="stat-label">{{ t('istio_page.stats.services_in_mesh') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.proxy_count }}</div>
                    <div class="stat-label">{{ t('istio_page.stats.proxy_count') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-success">{{ dashboard.mtls_enabled ? 'STRICT' : 'PERMISSIVE' }}</div>
                    <div class="stat-label">{{ t('istio_page.stats.mtls_mode') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab1: 服务拓扑 -->
            <el-tab-pane :label="t('istio_page.tabs.topology')" name="topology">
                <el-table :data="topology" border stripe v-loading="loading" style="width:100%">
                    <el-table-column prop="name" :label="t('istio_page.cols.service_name')" width="140" />
                    <el-table-column prop="version" :label="t('istio_page.cols.version')" width="80" />
                    <el-table-column prop="port" :label="t('istio_page.cols.port')" width="70" />
                    <el-table-column prop="protocol" :label="t('istio_page.cols.protocol')" width="70" />
                    <el-table-column :label="t('istio_page.cols.sidecar')" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.has_sidecar ? 'success' : 'danger'" size="small">
                                {{ row.has_sidecar ? t('istio_page.status.injected') : t('istio_page.status.not_injected') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('istio_page.cols.mtls')" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.mtls_enabled ? 'success' : 'info'" size="small">
                                {{ row.mtls_enabled ? t('istio_page.status.on') : t('istio_page.status.off') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="virtual_service" :label="t('istio_page.cols.virtual_service')" min-width="200" show-overflow-tooltip />
                </el-table>
            </el-tab-pane>

            <!-- Tab2: 流量规则 -->
            <el-tab-pane :label="t('istio_page.tabs.traffic')" name="traffic">
                <el-card class="section-card">
                    <template #header><span>{{ t('istio_page.sections.virtual_service') }}</span></template>
                    <el-table :data="trafficRules.virtual_services" border stripe v-loading="loading" style="width:100%">
                        <el-table-column prop="name" :label="t('istio_page.cols.name')" width="200" />
                        <el-table-column prop="hosts" :label="t('istio_page.cols.hosts')" min-width="200">
                            <template #default="{ row }">
                                <el-tag v-for="h in row.hosts" :key="h" size="small" style="margin:2px">{{ h }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="service" :label="t('istio_page.cols.backend_service')" width="120" />
                    </el-table>
                </el-card>
                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>{{ t('istio_page.sections.destination_rule') }}</span></template>
                    <el-table :data="trafficRules.destination_rules" border stripe v-loading="loading" style="width:100%">
                        <el-table-column prop="name" :label="t('istio_page.cols.name')" width="200" />
                        <el-table-column prop="host" :label="t('istio_page.cols.host')" width="200" />
                        <el-table-column :label="t('istio_page.cols.circuit_breaker')" min-width="200">
                            <template #default="{ row }">
                                <span class="config-text">max_connections: {{ row.traffic_policy?.connection_pool?.tcp?.max_connections }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab3: 安全策略 -->
            <el-tab-pane :label="t('istio_page.tabs.security')" name="security">
                <el-descriptions :column="2" border style="margin-bottom:16px">
                    <el-descriptions-item :label="t('istio_page.sections.mtls_mode')">
                        <el-tag :type="security.mtls_mode === 'STRICT' ? 'success' : 'warning'">{{ security.mtls_mode }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('istio_page.sections.peer_auth_count')">{{ security.peer_authentications?.length }}</el-descriptions-item>
                </el-descriptions>
                <h4>{{ t('istio_page.sections.authorization_policy') }}</h4>
                <el-table :data="security.authorization_policies" border stripe v-loading="loading" style="width:100%;margin-top:8px">
                    <el-table-column prop="name" :label="t('istio_page.cols.name')" width="180" />
                    <el-table-column prop="action" :label="t('istio_page.cols.action')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.action === 'ALLOW' ? 'success' : 'danger'" size="small">{{ row.action }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="rules" :label="t('istio_page.cols.rules')" min-width="150" />
                    <el-table-column prop="principals" :label="t('istio_page.cols.principals')" min-width="200">
                        <template #default="{ row }">
                            <div v-for="p in (row.principals || [])" :key="p">
                                <code>{{ p }}</code>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- Tab4: 可观测性 -->
            <el-tab-pane :label="t('istio_page.tabs.observability')" name="observability">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('istio_page.observability.tracing')">
                        <el-tag :type="obs.tracing_enabled ? 'success' : 'info'" size="small">
                            {{ obs.tracing_enabled ? t('istio_page.status.enabled') : t('istio_page.status.disabled') }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('istio_page.observability.sampling_rate')">{{ (obs.tracing_sampling_rate * 100).toFixed(1) }}%</el-descriptions-item>
                    <el-descriptions-item :label="t('istio_page.observability.metrics')">
                        <el-tag :type="obs.metrics_enabled ? 'success' : 'info'" size="small">
                            {{ obs.metrics_enabled ? t('istio_page.status.enabled') : t('istio_page.status.disabled') }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('istio_page.observability.access_log')">
                        <el-tag :type="obs.access_log_enabled ? 'success' : 'info'" size="small">
                            {{ obs.access_log_enabled ? t('istio_page.status.enabled') : t('istio_page.status.disabled') }}
                        </el-tag>
                    </el-descriptions-item>
                </el-descriptions>
                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>{{ t('istio_page.sections.quick_access') }}</span></template>
                    <el-row :gutter="16">
                        <el-col :span="8">
                            <el-button type="primary" @click="openUrl(obs.grafana_dashboard)" style="width:100%">
                                <el-icon><DataAnalysis /></el-icon> Grafana
                            </el-button>
                        </el-col>
                        <el-col :span="8">
                            <el-button type="warning" @click="openUrl(obs.jaeger_url)" style="width:100%">
                                <el-icon><Connection /></el-icon> Jaeger
                            </el-button>
                        </el-col>
                        <el-col :span="8">
                            <el-button type="success" @click="openUrl(obs.kiali_url)" style="width:100%">
                                <el-icon><Monitor /></el-icon> Kiali
                            </el-button>
                        </el-col>
                    </el-row>
                </el-card>
            </el-tab-pane>

            <!-- Tab5: 金丝雀发布 -->
            <el-tab-pane :label="t('istio_page.tabs.canary')" name="canary">
                <el-card class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>{{ t('istio_page.sections.active_canaries') }}</span>
                            <el-button type="primary" size="small" @click="showCanaryDialog = true">
                                <el-icon><Plus /></el-icon> {{ t('istio_page.canary.new_canary') }}
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="canaryList" border stripe style="width:100%">
                        <el-table-column prop="service" :label="t('istio_page.cols.service')" width="140" />
                        <el-table-column prop="canary_version" :label="t('istio_page.cols.canary_version')" width="140" />
                        <el-table-column prop="weight" :label="t('istio_page.cols.weight')" width="100">
                            <template #default="{ row }">
                                <el-progress :percentage="row.weight" :width="60" />
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" :label="t('istio_page.cols.status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'promoted' ? 'success' : row.status === 'rolled_back' ? 'danger' : 'warning'" size="small">
                                    {{ canaryStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="started_at" :label="t('istio_page.cols.started_at')" width="160" />
                        <el-table-column :label="t('istio_page.cols.actions')" width="200">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'in_progress'" size="small" type="success" @click="handlePromote(row.service)">{{ t('istio_page.canary.promote') }}</el-button>
                                <el-button v-if="row.status === 'in_progress'" size="small" type="danger" @click="handleRollback(row.service)">{{ t('istio_page.canary.rollback') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 新建金丝雀 Dialog -->
                <el-dialog v-model="showCanaryDialog" :title="t('istio_page.canary.dialog_title')" width="500px">
                    <el-form :model="canaryForm" label-width="100px">
                        <el-form-item :label="t('istio_page.form.service_name')" required>
                            <el-select v-model="canaryForm.service" style="width:100%">
                                <el-option v-for="s in topology" :key="s.name" :label="s.name" :value="s.name" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('istio_page.form.canary_version')" required>
                            <el-input v-model="canaryForm.version" :placeholder="t('istio_page.canary.version_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('istio_page.form.traffic_weight')">
                            <el-slider v-model="canaryForm.weight" :min="1" :max="50" show-input style="width:300px" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showCanaryDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="canaryLoading" @click="handleCanaryDeploy">{{ t('actions.create') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- Tab6: 部署指南 -->
            <el-tab-pane :label="t('istio_page.tabs.guide')" name="guide">
                <el-card class="section-card">
                    <template #header><span>{{ t('istio_page.sections.deploy_commands') }}</span></template>
                    <div v-for="(cmd, name) in guide" :key="name" class="cmd-row">
                        <el-tag size="small" class="cmd-label">{{ nameLabel(name) }}</el-tag>
                        <code class="cmd-code">{{ cmd }}</code>
                        <el-button size="small" @click="copyText(cmd)">{{ t('actions.copy') }}</el-button>
                    </div>
                </el-card>

                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>{{ t('istio_page.sections.file_structure') }}</span></template>
                    <el-tree :data="fileTree" :props="treeProps" default-expand-all />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, DataAnalysis, Connection, Monitor } from '@element-plus/icons-vue';
import istioApi from '@/api/istio';

const { t } = useI18n();

const activeTab = ref('topology');
const loading = ref(false);
const canaryLoading = ref(false);
const showCanaryDialog = ref(false);

const dashboard = reactive({
    istio_enabled: false,
    mtls_enabled: true,
    services_in_mesh: 0,
    proxy_count: 0,
});

const topology = ref([]);
const trafficRules = reactive({ virtual_services: [], destination_rules: [], gateways: {} });
const security = reactive({ mtls_mode: 'STRICT', authorization_policies: [], peer_authentications: [] });
const obs = reactive({});
const canaryList = ref([]);
const guide = ref({});
const canaryForm = reactive({ service: '', version: '', weight: 10 });

const fileTree = [
    { label: 'deploy/istio/', children: [
        { label: '01-service-accounts.yaml' },
        { label: '02-sidecar-injection.yaml' },
        { label: 'kustomization.yaml' },
        { label: 'gateway/', children: [{ label: '01-ingress-gateway.yaml' }] },
        { label: 'traffic/', children: [
            { label: '01-virtual-services.yaml' },
            { label: '02-destination-rules.yaml' },
            { label: '03-traffic-management.yaml' },
        ]},
        { label: 'security/', children: [{ label: '01-mtls-authz.yaml' }] },
        { label: 'observability/', children: [{ label: '01-telemetry.yaml' }] },
    ]},
];

const treeProps = { children: 'children', label: 'label' };

const guideLabelMap = computed(() => ({
    install_istio: t('istio_page.guide_labels.install_istio'),
    enable_injection: t('istio_page.guide_labels.enable_injection'),
    deploy_all: t('istio_page.guide_labels.deploy_all'),
    deploy_gateway: t('istio_page.guide_labels.deploy_gateway'),
    deploy_security: t('istio_page.guide_labels.deploy_security'),
    deploy_traffic: t('istio_page.guide_labels.deploy_traffic'),
    deploy_observability: t('istio_page.guide_labels.deploy_observability'),
    dashboard_kiali: t('istio_page.guide_labels.dashboard_kiali'),
    dashboard_grafana: t('istio_page.guide_labels.dashboard_grafana'),
    dashboard_jaeger: t('istio_page.guide_labels.dashboard_jaeger'),
    proxy_status: t('istio_page.guide_labels.proxy_status'),
}));

const canaryStatusLabels = computed(() => ({
    in_progress: t('istio_page.canary_status.in_progress'),
    promoted: t('istio_page.canary_status.promoted'),
    rolled_back: t('istio_page.canary_status.rolled_back'),
}));

function nameLabel(key) {
    return guideLabelMap.value[key] || key;
}

function canaryStatusLabel(status) {
    return canaryStatusLabels.value[status] || status;
}

function openUrl(url) {
    if (url) window.open(url, '_blank');
    else ElMessage.warning(t('istio_page.messages.url_not_configured'));
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        ElMessage.success(t('marketplace_uploader.copied'));
    } catch {
        ElMessage.error(t('istio_page.messages.copy_fail'));
    }
}

async function loadData() {
    loading.value = true;
    try {
        const [dashRes, topoRes, trafficRes, secRes, obsRes, canaryRes, guideRes] = await Promise.all([
            istioApi.dashboard(),
            istioApi.topology(),
            istioApi.trafficRules(),
            istioApi.security(),
            istioApi.observability(),
            istioApi.canaryDeployments(),
            istioApi.deploymentGuide(),
        ]);
        Object.assign(dashboard, dashRes.data ?? {});
        topology.value = topoRes.data ?? [];
        Object.assign(trafficRules, trafficRes.data ?? {});
        Object.assign(security, secRes.data ?? {});
        Object.assign(obs, obsRes.data ?? {});
        canaryList.value = canaryRes.data ?? [];
        guide.value = guideRes.data ?? {};
    } catch (e) {
        ElMessage.error(t('messages.load_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleCanaryDeploy() {
    if (!canaryForm.service || !canaryForm.version) {
        ElMessage.warning(t('istio_page.messages.fill_required'));
        return;
    }
    canaryLoading.value = true;
    try {
        await istioApi.canaryDeploy({ ...canaryForm });
        ElMessage.success(t('istio_page.messages.canary_created'));
        showCanaryDialog.value = false;
        canaryForm.service = '';
        canaryForm.version = '';
        canaryForm.weight = 10;
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error(t('messages.failed'));
    } finally {
        canaryLoading.value = false;
    }
}

async function handlePromote(service) {
    try {
        await istioApi.promoteCanary(service);
        ElMessage.success(t('istio_page.messages.promote_ok'));
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error(t('istio_page.messages.promote_fail'));
    }
}

async function handleRollback(service) {
    try {
        await istioApi.rollbackCanary(service);
        ElMessage.success(t('istio_page.messages.rollback_ok'));
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error(t('istio_page.messages.rollback_fail'));
    }
}

onMounted(loadData);
</script>

<style scoped>
.istio-manager-page { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 600; }
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.section-card { margin-top: 8px; }
.flex-between { display: flex; justify-content: space-between; align-items: center; }
.config-text { font-size: 12px; font-family: monospace; }
.cmd-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.cmd-label { flex-shrink: 0; width: 70px; }
.cmd-code { flex: 1; font-size: 12px; background: #f5f7fa; padding: 6px 10px; border-radius: 4px; overflow-x: auto; white-space: nowrap; }
</style>
