<template>
    <div class="innovation-auth-page">
        <h2>{{ t('innovation_page.title') }}</h2>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- M3-14 区块链 -->
            <el-tab-pane :label="t('innovation_page.tabs.blockchain')" name="blockchain">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ bcStats.total_nfts || 0 }}</div><div class="stat-label">{{ t('innovation_page.blockchain.stats.total_nfts') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ bcStats.active_nfts || 0 }}</div><div class="stat-label">{{ t('innovation_page.blockchain.stats.active_nfts') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ (bcStats.enabled_chains || []).length }}</div><div class="stat-label">{{ t('innovation_page.blockchain.stats.enabled_chains') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><el-button type="primary" size="small" @click="showChallenge = true">{{ t('innovation_page.blockchain.wallet_verify') }}</el-button></div></el-card></el-col>
                </el-row>
                <el-table :data="bcLicenses" v-loading="bcLoading" stripe>
                    <el-table-column prop="chain" :label="t('innovation_page.blockchain.cols.chain')" width="90" />
                    <el-table-column prop="contract_address" :label="t('innovation_page.blockchain.cols.contract_address')" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="token_id" :label="t('innovation_page.blockchain.cols.token_id')" width="120" />
                    <el-table-column prop="wallet_address" :label="t('innovation_page.blockchain.cols.wallet_address')" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="status" :label="t('innovation_page.blockchain.cols.status')" width="90">
                        <template #default="{row}"><el-tag :type="row.status==='active'?'success':'danger'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t('innovation_page.blockchain.cols.bound_at')" width="170" />
                </el-table>

                <el-dialog v-model="showChallenge" :title="t('innovation_page.blockchain.challenge_dialog.title')" width="450px">
                    <el-form :model="challengeForm" label-width="100px">
                        <el-form-item :label="t('innovation_page.blockchain.challenge_dialog.wallet_address')"><el-input v-model="challengeForm.wallet_address" :placeholder="t('innovation_page.blockchain.challenge_dialog.wallet_ph')" /></el-form-item>
                    </el-form>
                    <div v-if="challengeResult" style="margin-top:12px">
                        <p><strong>{{ t('innovation_page.blockchain.challenge_dialog.message_label') }}:</strong></p>
                        <pre style="background:#f5f7fa;padding:8px;font-size:12px;border-radius:4px;">{{ challengeResult.message }}</pre>
                        <el-input v-model="signature" type="textarea" :rows="3" :placeholder="t('innovation_page.blockchain.challenge_dialog.signature_ph')" style="margin-top:8px" />
                        <el-button type="primary" size="small" style="margin-top:8px">{{ t('innovation_page.blockchain.challenge_dialog.verify_signature') }}</el-button>
                    </div>
                    <template #footer>
                        <el-button @click="showChallenge = false">{{ t('actions.close') }}</el-button>
                        <el-button type="primary" @click="generateChallenge" :loading="bcLoading">{{ t('innovation_page.blockchain.challenge_dialog.generate_message') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- M3-15 MCP / AI Agent -->
            <el-tab-pane :label="t('innovation_page.tabs.mcp')" name="mcp">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ mcpStats.servers || 0 }}</div><div class="stat-label">{{ t('innovation_page.mcp.stats.servers') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ mcpStats.agents || 0 }}</div><div class="stat-label">{{ t('innovation_page.mcp.stats.agents') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ mcpStats.activeServers || 0 }}</div><div class="stat-label">{{ t('innovation_page.mcp.stats.active_servers') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ mcpStats.totalTokens || 0 }}</div><div class="stat-label">{{ t('innovation_page.mcp.stats.token_usage') }}</div></div></el-card></el-col>
                </el-row>
                <el-tabs v-model="mcpSubTab">
                    <el-tab-pane :label="t('innovation_page.mcp.sub_tabs.servers')" name="servers">
                        <div class="toolbar"><el-button type="primary" @click="showMcpDialog = true">{{ t('innovation_page.mcp.register_server') }}</el-button></div>
                        <el-table :data="mcpServers" v-loading="mcpLoading" stripe>
                            <el-table-column prop="name" :label="t('innovation_page.mcp.cols.name')" min-width="140" />
                            <el-table-column prop="protocol" :label="t('innovation_page.mcp.cols.protocol')" width="100" />
                            <el-table-column prop="endpoint" :label="t('innovation_page.mcp.cols.endpoint')" min-width="200" show-overflow-tooltip />
                            <el-table-column prop="status" :label="t('innovation_page.mcp.cols.status')" width="90" />
                            <el-table-column prop="last_active_at" :label="t('innovation_page.mcp.cols.last_active')" width="170" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t('innovation_page.mcp.sub_tabs.agents')" name="agents">
                        <div class="toolbar"><el-button type="primary" @click="showAgentDialog = true">{{ t('innovation_page.mcp.register_agent') }}</el-button></div>
                        <el-table :data="aiAgents" v-loading="mcpLoading" stripe>
                            <el-table-column prop="name" :label="t('innovation_page.mcp.cols.name')" min-width="140" />
                            <el-table-column prop="framework" :label="t('innovation_page.mcp.cols.framework')" width="120" />
                            <el-table-column :label="t('innovation_page.mcp.cols.token_quota')" width="180">
                                <template #default="{row}">{{ row.tokens_used || 0 }} / {{ row.monthly_token_quota || 0 }}</template>
                            </el-table-column>
                            <el-table-column prop="status" :label="t('innovation_page.mcp.cols.status')" width="90" />
                        </el-table>
                    </el-tab-pane>
                </el-tabs>

                <el-dialog v-model="showMcpDialog" :title="t('innovation_page.mcp.server_dialog.title')" width="500px">
                    <el-form :model="mcpForm" label-width="100px">
                        <el-form-item :label="t('innovation_page.mcp.cols.name')"><el-input v-model="mcpForm.name" /></el-form-item>
                        <el-form-item :label="t('innovation_page.mcp.cols.protocol')"><el-select v-model="mcpForm.protocol" style="width:100%"><el-option v-for="opt in protocolOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('innovation_page.mcp.cols.endpoint')"><el-input v-model="mcpForm.endpoint" :placeholder="t('innovation_page.mcp.server_dialog.endpoint_ph')" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showMcpDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleRegisterMcp">{{ t('innovation_page.register') }}</el-button></template>
                </el-dialog>

                <el-dialog v-model="showAgentDialog" :title="t('innovation_page.mcp.agent_dialog.title')" width="500px">
                    <el-form :model="agentForm" label-width="120px">
                        <el-form-item :label="t('innovation_page.mcp.cols.name')"><el-input v-model="agentForm.name" /></el-form-item>
                        <el-form-item :label="t('innovation_page.mcp.cols.framework')"><el-select v-model="agentForm.framework" style="width:100%"><el-option v-for="opt in frameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('innovation_page.mcp.agent_dialog.monthly_token_quota')"><el-input-number v-model="agentForm.monthly_token_quota" :min="0" :step="100000" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showAgentDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleRegisterAgent">{{ t('innovation_page.register') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- M3-16 Serverless -->
            <el-tab-pane :label="t('innovation_page.tabs.serverless')" name="serverless">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ slStats.total || 0 }}</div><div class="stat-label">{{ t('innovation_page.serverless.stats.total_functions') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ slStats.active || 0 }}</div><div class="stat-label">{{ t('innovation_page.serverless.stats.active_functions') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ slStats.totalInvocations || 0 }}</div><div class="stat-label">{{ t('innovation_page.serverless.stats.total_invocations') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><el-button type="primary" size="small" @click="showSlDialog = true">{{ t('innovation_page.serverless.register_function') }}</el-button></div></el-card></el-col>
                </el-row>
                <el-table :data="slFunctions" v-loading="slLoading" stripe>
                    <el-table-column prop="name" :label="t('innovation_page.serverless.cols.name')" min-width="140" />
                    <el-table-column prop="runtime" :label="t('innovation_page.serverless.cols.runtime')" width="90" />
                    <el-table-column :label="t('innovation_page.serverless.cols.qps_limit')" width="90"><template #default="{row}">{{ row.qps_limit }}/s</template></el-table-column>
                    <el-table-column :label="t('innovation_page.serverless.cols.invocations')" width="160"><template #default="{row}">{{ row.invocations_used || 0 }} / {{ row.monthly_invocation_limit || 0 }}</template></el-table-column>
                    <el-table-column prop="status" :label="t('innovation_page.serverless.cols.status')" width="90" />
                    <el-table-column :label="t('innovation_page.serverless.cols.actions')" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="success" @click="generateSlToken(row)">{{ t('innovation_page.serverless.generate_token') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showTokenDialog" :title="t('innovation_page.serverless.token_dialog.title')" width="500px">
                    <el-alert v-if="slToken" :title="slToken.token" type="success" show-icon :closable="false" style="margin-bottom:12px" />
                    <p v-if="slToken">{{ t('innovation_page.serverless.token_dialog.validity', { seconds: slToken.expires_in, function_id: slToken.function_id }) }}</p>
                    <p v-else>{{ t('innovation_page.serverless.token_dialog.generating') }}</p>
                    <template #footer><el-button @click="showTokenDialog = false">{{ t('actions.close') }}</el-button></template>
                </el-dialog>

                <el-dialog v-model="showSlDialog" :title="t('innovation_page.serverless.register_dialog.title')" width="500px">
                    <el-form :model="slForm" label-width="120px">
                        <el-form-item :label="t('innovation_page.serverless.cols.name')"><el-input v-model="slForm.name" /></el-form-item>
                        <el-form-item :label="t('innovation_page.serverless.cols.runtime')"><el-select v-model="slForm.runtime" style="width:100%"><el-option v-for="opt in runtimeOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('innovation_page.serverless.register_dialog.qps_limit')"><el-input-number v-model="slForm.qps_limit" :min="1" :max="1000" style="width:100%" /></el-form-item>
                        <el-form-item :label="t('innovation_page.serverless.register_dialog.monthly_invocation_limit')"><el-input-number v-model="slForm.monthly_invocation_limit" :min="0" :step="10000" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showSlDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleRegisterSl">{{ t('innovation_page.register') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- M3-17 Edge -->
            <el-tab-pane :label="t('innovation_page.tabs.edge')" name="edge">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ edgeStats.nodes || 0 }}</div><div class="stat-label">{{ t('innovation_page.edge.stats.total_nodes') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ edgeStats.activeNodes || 0 }}</div><div class="stat-label">{{ t('innovation_page.edge.stats.active_nodes') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ edgeStats.healthyNodes || 0 }}</div><div class="stat-label">{{ t('innovation_page.edge.stats.healthy_nodes') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ edgeStats.totalTokens || 0 }}</div><div class="stat-label">{{ t('innovation_page.edge.stats.token_usage') }}</div></div></el-card></el-col>
                </el-row>
                <div class="toolbar"><el-button type="primary" @click="showEdgeDialog = true">{{ t('innovation_page.edge.register_node') }}</el-button></div>
                <el-table :data="edgeNodes" v-loading="edgeLoading" stripe>
                    <el-table-column prop="name" :label="t('innovation_page.edge.cols.name')" min-width="140" />
                    <el-table-column prop="node_type" :label="t('innovation_page.edge.cols.node_type')" width="100" />
                    <el-table-column prop="region" :label="t('innovation_page.edge.cols.region')" width="120" />
                    <el-table-column prop="status" :label="t('innovation_page.edge.cols.status')" width="90">
                        <template #default="{row}"><el-tag :type="row.status==='active'?'success':'info'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="last_heartbeat_at" :label="t('innovation_page.edge.cols.last_heartbeat')" width="170" />
                </el-table>

                <el-dialog v-model="showEdgeDialog" :title="t('innovation_page.edge.register_dialog.title')" width="500px">
                    <el-form :model="edgeForm" label-width="100px">
                        <el-form-item :label="t('innovation_page.edge.cols.name')"><el-input v-model="edgeForm.name" /></el-form-item>
                        <el-form-item :label="t('innovation_page.edge.cols.node_type')"><el-select v-model="edgeForm.node_type" style="width:100%"><el-option v-for="opt in nodeTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item :label="t('innovation_page.edge.cols.region')"><el-input v-model="edgeForm.region" :placeholder="t('innovation_page.edge.register_dialog.region_ph')" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showEdgeDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="handleRegisterEdge">{{ t('innovation_page.register') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { blockchain, mcp, serverless, edge, updateInnovationStatus } from '@/api/innovationAuth';

const { t } = useI18n();

const activeTab = ref('blockchain');
const mcpSubTab = ref('servers');

const protocolOptions = computed(() => [
    { label: 'SSE', value: 'sse' },
    { label: 'WebSocket', value: 'websocket' },
    { label: 'Stdio', value: 'stdio' },
]);

const frameworkOptions = computed(() => [
    { label: 'LangChain', value: 'langchain' },
    { label: 'AutoGPT', value: 'autogpt' },
    { label: 'CrewAI', value: 'crewai' },
    { label: 'Dify', value: 'dify' },
    { label: t('innovation_page.mcp.frameworks.custom'), value: 'custom' },
]);

const runtimeOptions = computed(() => [
    { label: 'Node.js', value: 'nodejs' },
    { label: 'Python', value: 'python' },
    { label: 'Go', value: 'go' },
    { label: 'Rust', value: 'rust' },
]);

const nodeTypeOptions = computed(() => [
    { label: 'Cloudflare', value: 'cloudflare' },
    { label: 'Akamai', value: 'akamai' },
    { label: 'Fastly', value: 'fastly' },
    { label: t('innovation_page.edge.node_types.custom'), value: 'custom' },
]);

// M3-14 Blockchain
const bcStats = ref({});
const bcLicenses = ref([]);
const bcLoading = ref(false);
const showChallenge = ref(false);
const challengeResult = ref(null);
const signature = ref('');
const challengeForm = reactive({ wallet_address: '' });

// M3-15 MCP
const mcpStats = ref({});
const mcpServers = ref([]);
const aiAgents = ref([]);
const mcpLoading = ref(false);
const showMcpDialog = ref(false);
const showAgentDialog = ref(false);
const mcpForm = reactive({ name: '', protocol: 'sse', endpoint: '' });
const agentForm = reactive({ name: '', framework: 'custom', monthly_token_quota: 1000000 });

// M3-16 Serverless
const slStats = ref({});
const slFunctions = ref([]);
const slLoading = ref(false);
const slToken = ref(null);
const showTokenDialog = ref(false);
const showSlDialog = ref(false);
const slForm = reactive({ name: '', runtime: 'nodejs', qps_limit: 10, monthly_invocation_limit: 100000 });

// M3-17 Edge
const edgeStats = ref({});
const edgeNodes = ref([]);
const edgeLoading = ref(false);
const showEdgeDialog = ref(false);
const edgeForm = reactive({ name: '', node_type: 'cloudflare', region: '' });

// M3-14
async function loadBlockchain() {
    bcLoading.value = true;
    try { bcStats.value = await blockchain.dashboard(); const r = await blockchain.list({ per_page: 20 }); bcLicenses.value = r.data || []; }
    catch (e) { console.error(e); } finally { bcLoading.value = false; }
}

async function generateChallenge() {
    try { challengeResult.value = await blockchain.createChallenge(challengeForm.wallet_address); }
    catch (e) { ElMessage.error(t('innovation_page.blockchain.messages.challenge_failed')); }
}

// M3-15
async function loadMcp() {
    mcpLoading.value = true;
    try {
        mcpStats.value = await mcp.dashboard();
        const s = await mcp.servers({ per_page: 50 }); mcpServers.value = s.data || [];
        const a = await mcp.agents({ per_page: 50 }); aiAgents.value = a.data || [];
    } catch (e) { console.error(e); } finally { mcpLoading.value = false; }
}

async function handleRegisterMcp() {
    try { await mcp.registerServer(mcpForm); ElMessage.success(t('innovation_page.messages.register_success')); showMcpDialog.value = false; loadMcp(); }
    catch (e) { ElMessage.error(t('innovation_page.messages.register_failed')); }
}

async function handleRegisterAgent() {
    try { await mcp.registerAgent(agentForm); ElMessage.success(t('innovation_page.messages.register_success')); showAgentDialog.value = false; loadMcp(); }
    catch (e) { ElMessage.error(t('innovation_page.messages.register_failed')); }
}

// M3-16
async function loadServerless() {
    slLoading.value = true;
    try { slStats.value = await serverless.dashboard(); const r = await serverless.functions({ per_page: 50 }); slFunctions.value = r.data || []; }
    catch (e) { console.error(e); } finally { slLoading.value = false; }
}

async function handleRegisterSl() {
    try { await serverless.register(slForm); ElMessage.success(t('innovation_page.messages.register_success')); showSlDialog.value = false; loadServerless(); }
    catch (e) { ElMessage.error(t('innovation_page.messages.register_failed')); }
}

async function generateSlToken(row) {
    try { slToken.value = await serverless.generateToken(row.id); showTokenDialog.value = true; }
    catch (e) { ElMessage.error(t('innovation_page.serverless.messages.generate_token_failed')); }
}

// M3-17
async function loadEdge() {
    edgeLoading.value = true;
    try { edgeStats.value = await edge.dashboard(); const r = await edge.nodes({ per_page: 50 }); edgeNodes.value = r.data || []; }
    catch (e) { console.error(e); } finally { edgeLoading.value = false; }
}

async function handleRegisterEdge() {
    try { await edge.registerNode(edgeForm); ElMessage.success(t('innovation_page.messages.register_success')); showEdgeDialog.value = false; loadEdge(); }
    catch (e) { ElMessage.error(t('innovation_page.messages.register_failed')); }
}

onMounted(() => { loadBlockchain(); loadMcp(); loadServerless(); loadEdge(); });
</script>

<style scoped>
.innovation-auth-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; }
</style>
