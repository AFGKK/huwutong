<template>
    <div class="innovation-auth-page">
        <h2>创新授权管理</h2>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ═══ M3-14 区块链 ═══ -->
            <el-tab-pane label="区块链/NFT License" name="blockchain">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ bcStats.total_nfts || 0 }}</div><div class="stat-label">总NFT数</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ bcStats.active_nfts || 0 }}</div><div class="stat-label">活跃NFT</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ (bcStats.enabled_chains || []).length }}</div><div class="stat-label">已启用链</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><el-button type="primary" size="small" @click="showChallenge = true">钱包验证</el-button></div></el-card></el-col>
                </el-row>
                <el-table :data="bcLicenses" v-loading="bcLoading" stripe>
                    <el-table-column prop="chain" label="链" width="90" />
                    <el-table-column prop="contract_address" label="合约地址" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="token_id" label="Token ID" width="120" />
                    <el-table-column prop="wallet_address" label="钱包地址" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="90">
                        <template #default="{row}"><el-tag :type="row.status==='active'?'success':'danger'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="绑定时间" width="170" />
                </el-table>

                <el-dialog v-model="showChallenge" title="钱包签名验证" width="450px">
                    <el-form :model="challengeForm" label-width="100px">
                        <el-form-item label="钱包地址"><el-input v-model="challengeForm.wallet_address" placeholder="0x..." /></el-form-item>
                    </el-form>
                    <div v-if="challengeResult" style="margin-top:12px">
                        <p><strong>消息:</strong></p>
                        <pre style="background:#f5f7fa;padding:8px;font-size:12px;border-radius:4px;">{{ challengeResult.message }}</pre>
                        <el-input v-model="signature" type="textarea" :rows="3" placeholder="粘贴签名" style="margin-top:8px" />
                        <el-button type="primary" size="small" style="margin-top:8px">验证签名</el-button>
                    </div>
                    <template #footer>
                        <el-button @click="showChallenge = false">关闭</el-button>
                        <el-button type="primary" @click="generateChallenge" :loading="bcLoading">生成签名消息</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- ═══ M3-15 MCP / AI Agent ═══ -->
            <el-tab-pane label="MCP / AI Agent" name="mcp">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ mcpStats.servers || 0 }}</div><div class="stat-label">MCP Servers</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ mcpStats.agents || 0 }}</div><div class="stat-label">AI Agents</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ mcpStats.activeServers || 0 }}</div><div class="stat-label">活跃Servers</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ mcpStats.totalTokens || 0 }}</div><div class="stat-label">总Token消耗</div></div></el-card></el-col>
                </el-row>
                <el-tabs v-model="mcpSubTab">
                    <el-tab-pane label="MCP Servers" name="servers">
                        <div class="toolbar"><el-button type="primary" @click="showMcpDialog = true">注册Server</el-button></div>
                        <el-table :data="mcpServers" v-loading="mcpLoading" stripe>
                            <el-table-column prop="name" label="名称" min-width="140" />
                            <el-table-column prop="protocol" label="协议" width="100" />
                            <el-table-column prop="endpoint" label="端点" min-width="200" show-overflow-tooltip />
                            <el-table-column prop="status" label="状态" width="90" />
                            <el-table-column prop="last_active_at" label="最后活跃" width="170" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane label="AI Agents" name="agents">
                        <div class="toolbar"><el-button type="primary" @click="showAgentDialog = true">注册Agent</el-button></div>
                        <el-table :data="aiAgents" v-loading="mcpLoading" stripe>
                            <el-table-column prop="name" label="名称" min-width="140" />
                            <el-table-column prop="framework" label="框架" width="120" />
                            <el-table-column label="Token配额" width="180">
                                <template #default="{row}">{{ row.tokens_used || 0 }} / {{ row.monthly_token_quota || 0 }}</template>
                            </el-table-column>
                            <el-table-column prop="status" label="状态" width="90" />
                        </el-table>
                    </el-tab-pane>
                </el-tabs>

                <el-dialog v-model="showMcpDialog" title="注册 MCP Server" width="500px">
                    <el-form :model="mcpForm" label-width="100px">
                        <el-form-item label="名称"><el-input v-model="mcpForm.name" /></el-form-item>
                        <el-form-item label="协议"><el-select v-model="mcpForm.protocol" style="width:100%"><el-option label="SSE" value="sse" /><el-option label="WebSocket" value="websocket" /><el-option label="Stdio" value="stdio" /></el-select></el-form-item>
                        <el-form-item label="端点"><el-input v-model="mcpForm.endpoint" placeholder="https://..." /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showMcpDialog = false">取消</el-button><el-button type="primary" @click="handleRegisterMcp">注册</el-button></template>
                </el-dialog>

                <el-dialog v-model="showAgentDialog" title="注册 AI Agent" width="500px">
                    <el-form :model="agentForm" label-width="120px">
                        <el-form-item label="名称"><el-input v-model="agentForm.name" /></el-form-item>
                        <el-form-item label="框架"><el-select v-model="agentForm.framework" style="width:100%"><el-option label="LangChain" value="langchain" /><el-option label="AutoGPT" value="autogpt" /><el-option label="CrewAI" value="crewai" /><el-option label="Dify" value="dify" /><el-option label="自定义" value="custom" /></el-select></el-form-item>
                        <el-form-item label="月Token配额"><el-input-number v-model="agentForm.monthly_token_quota" :min="0" :step="100000" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showAgentDialog = false">取消</el-button><el-button type="primary" @click="handleRegisterAgent">注册</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- ═══ M3-16 Serverless ═══ -->
            <el-tab-pane label="云函数授权" name="serverless">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ slStats.total || 0 }}</div><div class="stat-label">总函数数</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ slStats.active || 0 }}</div><div class="stat-label">活跃函数</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ slStats.totalInvocations || 0 }}</div><div class="stat-label">总调用量</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><el-button type="primary" size="small" @click="showSlDialog = true">注册函数</el-button></div></el-card></el-col>
                </el-row>
                <el-table :data="slFunctions" v-loading="slLoading" stripe>
                    <el-table-column prop="name" label="名称" min-width="140" />
                    <el-table-column prop="runtime" label="运行时" width="90" />
                    <el-table-column label="QPS限制" width="90"><template #default="{row}">{{ row.qps_limit }}/s</template></el-table-column>
                    <el-table-column label="调用量" width="160"><template #default="{row}">{{ row.invocations_used || 0 }} / {{ row.monthly_invocation_limit || 0 }}</template></el-table-column>
                    <el-table-column prop="status" label="状态" width="90" />
                    <el-table-column label="操作" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="success" @click="generateSlToken(row)">生成Token</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showTokenDialog" title="短时授权 Token" width="500px">
                    <el-alert v-if="slToken" :title="slToken.token" type="success" show-icon :closable="false" style="margin-bottom:12px" />
                    <p v-if="slToken">有效期: {{ slToken.expires_in }}秒 | 函数: {{ slToken.function_id }}</p>
                    <p v-else>生成中...</p>
                    <template #footer><el-button @click="showTokenDialog = false">关闭</el-button></template>
                </el-dialog>

                <el-dialog v-model="showSlDialog" title="注册云函数" width="500px">
                    <el-form :model="slForm" label-width="120px">
                        <el-form-item label="名称"><el-input v-model="slForm.name" /></el-form-item>
                        <el-form-item label="运行时"><el-select v-model="slForm.runtime" style="width:100%"><el-option label="Node.js" value="nodejs" /><el-option label="Python" value="python" /><el-option label="Go" value="go" /><el-option label="Rust" value="rust" /></el-select></el-form-item>
                        <el-form-item label="QPS限制"><el-input-number v-model="slForm.qps_limit" :min="1" :max="1000" style="width:100%" /></el-form-item>
                        <el-form-item label="月调用上限"><el-input-number v-model="slForm.monthly_invocation_limit" :min="0" :step="10000" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showSlDialog = false">取消</el-button><el-button type="primary" @click="handleRegisterSl">注册</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- ═══ M3-17 Edge ═══ -->
            <el-tab-pane label="边缘计算授权" name="edge">
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ edgeStats.nodes || 0 }}</div><div class="stat-label">总节点</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ edgeStats.activeNodes || 0 }}</div><div class="stat-label">活跃节点</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ edgeStats.healthyNodes || 0 }}</div><div class="stat-label">健康节点</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ edgeStats.totalTokens || 0 }}</div><div class="stat-label">AI Token消耗</div></div></el-card></el-col>
                </el-row>
                <div class="toolbar"><el-button type="primary" @click="showEdgeDialog = true">注册边缘节点</el-button></div>
                <el-table :data="edgeNodes" v-loading="edgeLoading" stripe>
                    <el-table-column prop="name" label="名称" min-width="140" />
                    <el-table-column prop="node_type" label="类型" width="100" />
                    <el-table-column prop="region" label="区域" width="120" />
                    <el-table-column prop="status" label="状态" width="90">
                        <template #default="{row}"><el-tag :type="row.status==='active'?'success':'info'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="last_heartbeat_at" label="最后心跳" width="170" />
                </el-table>

                <el-dialog v-model="showEdgeDialog" title="注册边缘节点" width="500px">
                    <el-form :model="edgeForm" label-width="100px">
                        <el-form-item label="名称"><el-input v-model="edgeForm.name" /></el-form-item>
                        <el-form-item label="类型"><el-select v-model="edgeForm.node_type" style="width:100%"><el-option label="Cloudflare" value="cloudflare" /><el-option label="Akamai" value="akamai" /><el-option label="Fastly" value="fastly" /><el-option label="自定义" value="custom" /></el-select></el-form-item>
                        <el-form-item label="区域"><el-input v-model="edgeForm.region" placeholder="例如: ap-east-1" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showEdgeDialog = false">取消</el-button><el-button type="primary" @click="handleRegisterEdge">注册</el-button></template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { blockchain, mcp, serverless, edge, updateInnovationStatus } from '@/api/innovationAuth';

const activeTab = ref('blockchain');
const mcpSubTab = ref('servers');

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
    catch (e) { ElMessage.error('生成挑战失败'); }
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
    try { await mcp.registerServer(mcpForm); ElMessage.success('注册成功'); showMcpDialog.value = false; loadMcp(); }
    catch (e) { ElMessage.error('注册失败'); }
}

async function handleRegisterAgent() {
    try { await mcp.registerAgent(agentForm); ElMessage.success('注册成功'); showAgentDialog.value = false; loadMcp(); }
    catch (e) { ElMessage.error('注册失败'); }
}

// M3-16
async function loadServerless() {
    slLoading.value = true;
    try { slStats.value = await serverless.dashboard(); const r = await serverless.functions({ per_page: 50 }); slFunctions.value = r.data || []; }
    catch (e) { console.error(e); } finally { slLoading.value = false; }
}

async function handleRegisterSl() {
    try { await serverless.register(slForm); ElMessage.success('注册成功'); showSlDialog.value = false; loadServerless(); }
    catch (e) { ElMessage.error('注册失败'); }
}

async function generateSlToken(row) {
    try { slToken.value = await serverless.generateToken(row.id); showTokenDialog.value = true; }
    catch (e) { ElMessage.error('生成Token失败'); }
}

// M3-17
async function loadEdge() {
    edgeLoading.value = true;
    try { edgeStats.value = await edge.dashboard(); const r = await edge.nodes({ per_page: 50 }); edgeNodes.value = r.data || []; }
    catch (e) { console.error(e); } finally { edgeLoading.value = false; }
}

async function handleRegisterEdge() {
    try { await edge.registerNode(edgeForm); ElMessage.success('注册成功'); showEdgeDialog.value = false; loadEdge(); }
    catch (e) { ElMessage.error('注册失败'); }
}

onMounted(() => { loadBlockchain(); loadMcp(); loadServerless(); loadEdge(); });
</script>

<style scoped>
.innovation-auth-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; }
</style>
