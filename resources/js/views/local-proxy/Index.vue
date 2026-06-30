<template>
    <div class="local-proxy-page">
        <div class="page-header">
            <h2>本地 License 代理</h2>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
                <el-button type="primary" @click="showRegisterDialog = true">
                    <el-icon><Plus /></el-icon> 注册代理
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">总节点</div>
                    <div class="stat-value">{{ stats.total_nodes }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-active">
                    <div class="stat-label">活跃节点</div>
                    <div class="stat-value">{{ stats.active_nodes }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-success">
                    <div class="stat-label">健康节点</div>
                    <div class="stat-value">{{ stats.healthy_nodes }}</div>
                    <div class="stat-change" v-if="stats.offline_nodes > 0" style="color:#e6a23c">
                        {{ stats.offline_nodes }} 个离线
                    </div>
                    <div class="stat-change" v-else style="color:#67c23a">全部在线</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-info">
                    <div class="stat-label">缓存License数</div>
                    <div class="stat-value">{{ stats.cached_licenses }}</div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="hover" class="stat-warning">
                    <div class="stat-label">近7天验证</div>
                    <div class="stat-value">{{ stats.recent_activations_7d }}</div>
                    <div class="stat-change" v-if="stats.denied_activations_7d > 0" style="color:#f56c6c">
                        {{ stats.denied_activations_7d }} 次拒绝
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 节点列表 -->
        <el-card>
            <template #header>
                <span>代理节点</span>
            </template>

            <el-table :data="nodes" stripe v-loading="loadingTable">
                <el-table-column prop="name" label="节点名称" min-width="140" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'paused' ? 'warning' : 'info'"
                            size="small">
                            {{ row.status === 'active' ? '活跃' : row.status === 'paused' ? '暂停' : row.status === 'pending' ? '待激活' : '已退役' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="健康" width="80" align="center">
                    <template #default="{ row }">
                        <el-tooltip :content="row.is_healthy ? '最近10分钟内有心跳' : '超过10分钟无心跳'" placement="top">
                            <el-tag :type="row.is_healthy ? 'success' : 'danger'" size="small" effect="plain">
                                <el-icon style="vertical-align: -2px">
                                    <component :is="row.is_healthy ? 'CircleCheck' : 'CircleClose'" />
                                </el-icon>
                            </el-tag>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="base_url" label="内网地址" min-width="160" />
                <el-table-column prop="version" label="版本" width="80" />
                <el-table-column prop="os" label="操作系统" width="100" />
                <el-table-column label="缓存" width="80" align="center">
                    <template #default="{ row }">{{ row.cached_licenses_count }}</template>
                </el-table-column>
                <el-table-column label="最近心跳" width="160">
                    <template #default="{ row }">
                        <span v-if="row.last_heartbeat_at" style="font-size:12px;color:#909399">
                            {{ formatTime(row.last_heartbeat_at) }}
                        </span>
                        <span v-else style="color:#c0c4cc">无</span>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewNode(row)">详情</el-button>
                        <el-dropdown v-if="row.status !== 'decommissioned'" trigger="click">
                            <el-button size="small">
                                更多<el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-if="row.status === 'pending'" @click="showActivateDialog(row)">
                                        激活
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'active'" @click="toggleNodeStatus(row, 'paused')">
                                        暂停
                                    </el-dropdown-item>
                                    <el-dropdown-item v-if="row.status === 'paused'" @click="toggleNodeStatus(row, 'active')">
                                        恢复
                                    </el-dropdown-item>
                                    <el-dropdown-item @click="configureNode(row)">配置</el-dropdown-item>
                                    <el-dropdown-item divided @click="toggleNodeStatus(row, 'decommissioned')" style="color:#f56c6c">
                                        退役
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 注册弹窗 -->
        <el-dialog v-model="showRegisterDialog" title="注册代理节点" width="500px">
            <el-form :model="registerForm" ref="registerFormRef" label-position="top">
                <el-form-item label="节点名称" prop="name" :rules="[{ required: true, message: '请输入节点名称' }]">
                    <el-input v-model="registerForm.name" placeholder="例如: 华为内网-北京机房" />
                </el-form-item>
                <el-form-item label="内网地址" prop="base_url">
                    <el-input v-model="registerForm.base_url" placeholder="例如: http://192.168.1.100:8080" />
                </el-form-item>
                <el-form-item label="能力" prop="capabilities">
                    <el-checkbox-group v-model="registerForm.capabilities">
                        <el-checkbox label="offline_auth" value="offline_auth">离线验证</el-checkbox>
                        <el-checkbox label="heartbeat" value="heartbeat">心跳上报</el-checkbox>
                        <el-checkbox label="crl_sync" value="crl_sync">CRL同步</el-checkbox>
                        <el-checkbox label="cache" value="cache">缓存</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRegisterDialog = false">取消</el-button>
                <el-button type="primary" @click="doRegister" :loading="registering">注册</el-button>
            </template>
        </el-dialog>

        <!-- 注册结果弹窗 -->
        <el-dialog v-model="showRegisterResult" title="注册成功" width="520px">
            <div class="result-info">
                <el-alert type="warning" title="请立即保存以下信息！注册令牌只显示一次。" show-icon :closable="false" class="mb-4" />
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="节点ID">
                        <code class="copy-text">{{ registerResult.node_id }}</code>
                        <el-button text @click="copyToClipboard(registerResult.node_id)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item label="注册令牌">
                        <code class="copy-text">{{ registerResult.register_token }}</code>
                        <el-button text @click="copyToClipboard(registerResult.register_token)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                    <el-descriptions-item label="API密钥">
                        <code class="copy-text">{{ registerResult.api_key }}</code>
                        <el-button text @click="copyToClipboard(registerResult.api_key)" size="small">
                            <el-icon><CopyDocument /></el-icon>
                        </el-button>
                    </el-descriptions-item>
                </el-descriptions>
            </div>
            <template #footer>
                <el-button type="primary" @click="showRegisterResult = false">已保存</el-button>
            </template>
        </el-dialog>

        <!-- 激活弹窗 -->
        <el-dialog v-model="showActivateDialogVisible" title="激活代理节点" width="450px">
            <p>节点 <strong>{{ activateNodeData?.name }}</strong> 需要输入注册令牌以完成激活。</p>
            <el-form>
                <el-form-item label="注册令牌">
                    <el-input v-model="activateToken" placeholder="输入注册令牌" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showActivateDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="doActivate" :loading="activating">激活</el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" title="节点详情" width="800px">
            <div v-loading="loadingDetail">
                <template v-if="detailData">
                    <el-descriptions :column="2" border class="mb-4">
                        <el-descriptions-item label="名称">{{ detailData.node.name }}</el-descriptions-item>
                        <el-descriptions-item label="节点ID">
                            <code>{{ detailData.node.node_id }}</code>
                        </el-descriptions-item>
                        <el-descriptions-item label="状态">
                            <el-tag :type="detailData.node.status === 'active' ? 'success' : 'warning'" size="small">
                                {{ detailData.node.status }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="最近心跳">
                            {{ detailData.node.last_heartbeat_at ? formatTime(detailData.node.last_heartbeat_at) : '无' }}
                        </el-descriptions-item>
                    </el-descriptions>

                    <el-tabs>
                        <el-tab-pane label="配置">
                            <el-descriptions :column="2" border size="small" v-if="detailData.config">
                                <el-descriptions-item label="同步模式">{{ detailData.config.sync_mode }}</el-descriptions-item>
                                <el-descriptions-item label="轮询间隔">{{ detailData.config.sync_interval_seconds }}s</el-descriptions-item>
                                <el-descriptions-item label="心跳间隔">{{ detailData.config.heartbeat_interval_seconds }}s</el-descriptions-item>
                                <el-descriptions-item label="缓存有效期">{{ detailData.config.cache_ttl_seconds }}s</el-descriptions-item>
                                <el-descriptions-item label="最大缓存">{{ detailData.config.max_cached_licenses }} 个</el-descriptions-item>
                                <el-descriptions-item label="离线激活">
                                    <el-tag :type="detailData.config.allow_offline_activation ? 'success' : 'info'" size="small">
                                        {{ detailData.config.allow_offline_activation ? '允许' : '禁止' }}
                                    </el-tag>
                                </el-descriptions-item>
                            </el-descriptions>
                        </el-tab-pane>
                        <el-tab-pane label="缓存License">
                            <el-table :data="detailData.cached_licenses" size="small" stripe v-if="detailData.cached_licenses?.length">
                                <el-table-column prop="license_key" label="License Key" min-width="200" />
                                <el-table-column label="状态" width="80">
                                    <template #default="{ row }">{{ row.license_status }}</template>
                                </el-table-column>
                                <el-table-column label="过期时间" width="160">
                                    <template #default="{ row }">{{ row.expires_at ? formatTime(row.expires_at) : '-' }}</template>
                                </el-table-column>
                                <el-table-column label="验证次数" width="80" align="center" prop="verify_count" />
                                <el-table-column label="已过期" width="80" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_expired ? 'danger' : 'success'" size="small">
                                            {{ row.is_expired ? '是' : '否' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else description="暂无缓存License" />
                        </el-tab-pane>
                        <el-tab-pane label="最近心跳">
                            <el-table :data="detailData.heartbeats" size="small" stripe v-if="detailData.heartbeats?.length">
                                <el-table-column label="时间" width="160">
                                    <template #default="{ row }">{{ formatTime(row.heartbeat_at) }}</template>
                                </el-table-column>
                                <el-table-column label="状态" width="80">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'healthy' ? 'success' : 'warning'" size="small">
                                            {{ row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="错误" min-width="200" prop="error_message" />
                            </el-table>
                            <el-empty v-else description="暂无心跳数据" />
                        </el-tab-pane>
                    </el-tabs>
                </template>
            </div>
        </el-dialog>

        <!-- 配置编辑弹窗 -->
        <el-dialog v-model="showConfigDialog" title="编辑代理配置" width="550px">
            <el-form :model="configForm" label-position="top" size="small">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="同步模式">
                            <el-select v-model="configForm.sync_mode">
                                <el-option label="轮询" value="poll" />
                                <el-option label="推送" value="push" />
                                <el-option label="混合" value="hybrid" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="轮询间隔(秒)">
                            <el-input-number v-model="configForm.sync_interval_seconds" :min="30" :max="86400" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="心跳间隔(秒)">
                            <el-input-number v-model="configForm.heartbeat_interval_seconds" :min="10" :max="3600" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="缓存有效期(秒)">
                            <el-input-number v-model="configForm.cache_ttl_seconds" :min="300" :max="604800" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="最大缓存License数">
                            <el-input-number v-model="configForm.max_cached_licenses" :min="10" :max="100000" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="强制云端验证">
                            <el-switch v-model="configForm.require_cloud_validation" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="允许离线激活">
                    <el-switch v-model="configForm.allow_offline_activation" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showConfigDialog = false">取消</el-button>
                <el-button type="primary" @click="doUpdateConfig" :loading="savingConfig">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import localProxyApi from '@/api/localProxy';

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

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleString('zh-CN');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板');
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
        ElMessage.warning('请输入注册令牌');
        return;
    }
    activating.value = true;
    try {
        await localProxyApi.activateNode({
            node_id: activateNodeData.value.node_id,
            register_token: activateToken.value,
        });
        ElMessage.success('节点已激活');
        showActivateDialogVisible.value = false;
        await loadAll();
    } catch (err) {
        console.error('Activate failed', err);
    } finally {
        activating.value = false;
    }
}

async function toggleNodeStatus(node, status) {
    const labels = { active: '恢复', paused: '暂停', decommissioned: '退役' };
    try {
        await ElMessageBox.confirm(
            `确定${labels[status]}节点「${node.name}」吗？`,
            '确认操作',
            { type: 'warning' }
        );
        await localProxyApi.updateNodeStatus(node.id, status);
        ElMessage.success(`节点已${labels[status]}`);
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
    // Populate form from node config
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
        ElMessage.success('配置已更新');
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
.stat-info .stat-value { color: #409eff; }
.stat-warning .stat-value { color: #e6a23c; }

.copy-text { font-size: 13px; user-select: all; word-break: break-all; }
.result-info { padding: 8px 0; }
</style>
