<template>
    <div class="istio-manager-page">
        <el-page-header title="返回" @back="$router.back()" class="page-header">
            <template #content>
                <span class="page-title">Istio 服务网格管理</span>
            </template>
        </el-page-header>

        <!-- 状态卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value" :class="dashboard.istio_enabled ? 'text-success' : 'text-danger'">
                        {{ dashboard.istio_enabled ? '已启用' : '未启用' }}
                    </div>
                    <div class="stat-label">Istio 状态</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.services_in_mesh }}</div>
                    <div class="stat-label">网格服务数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.proxy_count }}</div>
                    <div class="stat-label">Sidecar 代理数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-success">{{ dashboard.mtls_enabled ? 'STRICT' : 'PERMISSIVE' }}</div>
                    <div class="stat-label">mTLS 模式</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab1: 服务拓扑 -->
            <el-tab-pane label="服务拓扑" name="topology">
                <el-table :data="topology" border stripe v-loading="loading" style="width:100%">
                    <el-table-column prop="name" label="服务名" width="140" />
                    <el-table-column prop="version" label="版本" width="80" />
                    <el-table-column prop="port" label="端口" width="70" />
                    <el-table-column prop="protocol" label="协议" width="70" />
                    <el-table-column label="Sidecar" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.has_sidecar ? 'success' : 'danger'" size="small">{{ row.has_sidecar ? '已注入' : '未注入' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="mTLS" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.mtls_enabled ? 'success' : 'info'" size="small">{{ row.mtls_enabled ? '开启' : '关闭' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="virtual_service" label="VirtualService" min-width="200" show-overflow-tooltip />
                </el-table>
            </el-tab-pane>

            <!-- Tab2: 流量规则 -->
            <el-tab-pane label="流量管理" name="traffic">
                <el-card class="section-card">
                    <template #header><span>VirtualService</span></template>
                    <el-table :data="trafficRules.virtual_services" border stripe v-loading="loading" style="width:100%">
                        <el-table-column prop="name" label="名称" width="200" />
                        <el-table-column prop="hosts" label="Hosts" min-width="200">
                            <template #default="{ row }">
                                <el-tag v-for="h in row.hosts" :key="h" size="small" style="margin:2px">{{ h }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="service" label="后端服务" width="120" />
                    </el-table>
                </el-card>
                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>DestinationRule</span></template>
                    <el-table :data="trafficRules.destination_rules" border stripe v-loading="loading" style="width:100%">
                        <el-table-column prop="name" label="名称" width="200" />
                        <el-table-column prop="host" label="Host" width="200" />
                        <el-table-column label="熔断配置" min-width="200">
                            <template #default="{ row }">
                                <span class="config-text">max_connections: {{ row.traffic_policy?.connection_pool?.tcp?.max_connections }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab3: 安全策略 -->
            <el-tab-pane label="安全策略" name="security">
                <el-descriptions :column="2" border style="margin-bottom:16px">
                    <el-descriptions-item label="mTLS 模式">
                        <el-tag :type="security.mtls_mode === 'STRICT' ? 'success' : 'warning'">{{ security.mtls_mode }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="PeerAuthentication 数">{{ security.peer_authentications?.length }}</el-descriptions-item>
                </el-descriptions>
                <h4>AuthorizationPolicy</h4>
                <el-table :data="security.authorization_policies" border stripe v-loading="loading" style="width:100%;margin-top:8px">
                    <el-table-column prop="name" label="名称" width="180" />
                    <el-table-column prop="action" label="动作" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.action === 'ALLOW' ? 'success' : 'danger'" size="small">{{ row.action }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="rules" label="规则" min-width="150" />
                    <el-table-column prop="principals" label="主体" min-width="200">
                        <template #default="{ row }">
                            <div v-for="p in (row.principals || [])" :key="p">
                                <code>{{ p }}</code>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- Tab4: 可观测性 -->
            <el-tab-pane label="可观测性" name="observability">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="链路追踪">
                        <el-tag :type="obs.tracing_enabled ? 'success' : 'info'" size="small">{{ obs.tracing_enabled ? '已启用' : '已关闭' }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="采样率">{{ (obs.tracing_sampling_rate * 100).toFixed(1) }}%</el-descriptions-item>
                    <el-descriptions-item label="指标收集">
                        <el-tag :type="obs.metrics_enabled ? 'success' : 'info'" size="small">{{ obs.metrics_enabled ? '已启用' : '已关闭' }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="访问日志">
                        <el-tag :type="obs.access_log_enabled ? 'success' : 'info'" size="small">{{ obs.access_log_enabled ? '已启用' : '已关闭' }}</el-tag>
                    </el-descriptions-item>
                </el-descriptions>
                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>一键访问</span></template>
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
            <el-tab-pane label="金丝雀发布" name="canary">
                <el-card class="section-card">
                    <template #header>
                        <div class="flex-between">
                            <span>进行中的金丝雀</span>
                            <el-button type="primary" size="small" @click="showCanaryDialog = true">
                                <el-icon><Plus /></el-icon> 新建金丝雀
                            </el-button>
                        </div>
                    </template>
                    <el-table :data="canaryList" border stripe style="width:100%">
                        <el-table-column prop="service" label="服务" width="140" />
                        <el-table-column prop="canary_version" label="金丝雀版本" width="140" />
                        <el-table-column prop="weight" label="流量权重" width="100">
                            <template #default="{ row }">
                                <el-progress :percentage="row.weight" :width="60" />
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'promoted' ? 'success' : row.status === 'rolled_back' ? 'danger' : 'warning'" size="small">
                                    {{ row.status === 'in_progress' ? '进行中' : row.status === 'promoted' ? '已全量' : row.status === 'rolled_back' ? '已回滚' : row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="started_at" label="开始时间" width="160" />
                        <el-table-column label="操作" width="200">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'in_progress'" size="small" type="success" @click="handlePromote(row.service)">全量发布</el-button>
                                <el-button v-if="row.status === 'in_progress'" size="small" type="danger" @click="handleRollback(row.service)">回滚</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>

                <!-- 新建金丝雀 Dialog -->
                <el-dialog v-model="showCanaryDialog" title="新建金丝雀发布" width="500px">
                    <el-form :model="canaryForm" label-width="100px">
                        <el-form-item label="服务名" required>
                            <el-select v-model="canaryForm.service" style="width:100%">
                                <el-option v-for="s in topology" :key="s.name" :label="s.name" :value="s.name" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="金丝雀版本" required>
                            <el-input v-model="canaryForm.version" placeholder="例如 v2.1.0" />
                        </el-form-item>
                        <el-form-item label="流量权重">
                            <el-slider v-model="canaryForm.weight" :min="1" :max="50" show-input style="width:300px" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showCanaryDialog = false">取消</el-button>
                        <el-button type="primary" :loading="canaryLoading" @click="handleCanaryDeploy">创建</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- Tab6: 部署指南 -->
            <el-tab-pane label="部署指南" name="guide">
                <el-card class="section-card">
                    <template #header><span>部署命令参考</span></template>
                    <div v-for="(cmd, name) in guide" :key="name" class="cmd-row">
                        <el-tag size="small" class="cmd-label">{{ nameLabel(name) }}</el-tag>
                        <code class="cmd-code">{{ cmd }}</code>
                        <el-button size="small" @click="copyText(cmd)">复制</el-button>
                    </div>
                </el-card>

                <el-card class="section-card" style="margin-top:16px">
                    <template #header><span>文件结构</span></template>
                    <el-tree :data="fileTree" :props="treeProps" default-expand-all />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, DataAnalysis, Connection, Monitor } from '@element-plus/icons-vue';
import istioApi from '@/api/istio';

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

function nameLabel(key) {
    const map = {
        install_istio: '安装', enable_injection: '注入', deploy_all: '全部部署',
        deploy_gateway: '网关', deploy_security: '安全', deploy_traffic: '流量',
        deploy_observability: '可观测', dashboard_kiali: 'Kiali', dashboard_grafana: 'Grafana',
        dashboard_jaeger: 'Jaeger', proxy_status: '代理状态',
    };
    return map[key] || key;
}

function openUrl(url) {
    if (url) window.open(url, '_blank');
    else ElMessage.warning('URL 未配置');
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        ElMessage.success('已复制到剪贴板');
    } catch {
        ElMessage.error('复制失败');
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
        ElMessage.error('加载数据失败');
    } finally {
        loading.value = false;
    }
}

async function handleCanaryDeploy() {
    if (!canaryForm.service || !canaryForm.version) {
        ElMessage.warning('请填写服务名和版本');
        return;
    }
    canaryLoading.value = true;
    try {
        await istioApi.canaryDeploy({ ...canaryForm });
        ElMessage.success('金丝雀发布已创建');
        showCanaryDialog.value = false;
        canaryForm.service = '';
        canaryForm.version = '';
        canaryForm.weight = 10;
        // reload canary list
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error('创建失败');
    } finally {
        canaryLoading.value = false;
    }
}

async function handlePromote(service) {
    try {
        await istioApi.promoteCanary(service);
        ElMessage.success('全量发布完成');
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error('全量发布失败');
    }
}

async function handleRollback(service) {
    try {
        await istioApi.rollbackCanary(service);
        ElMessage.success('已回滚');
        const res = await istioApi.canaryDeployments();
        canaryList.value = res.data ?? [];
    } catch (e) {
        ElMessage.error('回滚失败');
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
.stat-value { font-size: 24px; font-weight: 700; color: #409eff; }
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
