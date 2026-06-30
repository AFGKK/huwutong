<template>
  <div class="mcp-auth-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        MCP Server · AI Agent 授权
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.servers }}</div>
          <div class="stat-label">MCP Server 总数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.activeServers }}</div>
          <div class="stat-label">活跃 Server</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.agents }}</div>
          <div class="stat-label">AI Agent 总数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.totalTokens ? (stats.totalTokens / 1000).toFixed(0) + 'K' : 0 }}</div>
          <div class="stat-label">已用 Token</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- MCP Server -->
        <el-tab-pane label="MCP Server" name="servers">
          <div class="tab-toolbar">
            <el-button type="primary" style="margin-left:auto" @click="showCreateServer">
              <el-icon><Plus /></el-icon> 注册 Server
            </el-button>
          </div>
          <el-table :data="servers" stripe v-loading="serversLoading">
            <el-table-column label="名称" prop="name" min-width="140" />
            <el-table-column label="Server ID" width="200">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.server_id }}</span>
              </template>
            </el-table-column>
            <el-table-column label="协议" prop="protocol" width="80" />
            <el-table-column label="端点" prop="endpoint" min-width="200">
              <template #default="{ row }">{{ row.endpoint || '—' }}</template>
            </el-table-column>
            <el-table-column label="能力" width="180">
              <template #default="{ row }">
                <el-tag v-for="cap in (row.capabilities || [])" :key="cap" size="small" style="margin-right:4px">{{ cap }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="serverPagination.total > serverPagination.per_page">
            <el-pagination
              v-model:current-page="serverPagination.current_page"
              :page-size="serverPagination.per_page"
              :total="serverPagination.total"
              layout="prev, pager, next"
              @current-change="loadServers"
            />
          </div>
        </el-tab-pane>

        <!-- AI Agent -->
        <el-tab-pane label="AI Agent" name="agents">
          <div class="tab-toolbar">
            <el-button type="primary" style="margin-left:auto" @click="showCreateAgent">
              <el-icon><Plus /></el-icon> 注册 Agent
            </el-button>
          </div>
          <el-table :data="agents" stripe v-loading="agentsLoading">
            <el-table-column label="名称" prop="name" min-width="140" />
            <el-table-column label="Agent ID" width="200">
              <template #default="{ row }">
                <span class="font-mono text-sm">{{ row.agent_id }}</span>
              </template>
            </el-table-column>
            <el-table-column label="框架" prop="framework" width="100" />
            <el-table-column label="能力" width="180">
              <template #default="{ row }">
                <el-tag v-for="cap in (row.capabilities || [])" :key="cap" size="small" style="margin-right:4px">{{ cap }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Token 配额" width="100" align="center">
              <template #default="{ row }">{{ (row.monthly_token_quota / 1000).toFixed(0) }}K</template>
            </el-table-column>
            <el-table-column label="已用 Token" width="100" align="center">
              <template #default="{ row }">{{ row.tokens_used || 0 }}</template>
            </el-table-column>
            <el-table-column label="配额检查" width="80">
              <template #default="{ row }">
                <el-button size="small" type="warning" @click="handleCheckQuota(row)">检查</el-button>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="agentPagination.total > agentPagination.per_page">
            <el-pagination
              v-model:current-page="agentPagination.current_page"
              :page-size="agentPagination.per_page"
              :total="agentPagination.total"
              layout="prev, pager, next"
              @current-change="loadAgents"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建 Server 对话框 -->
    <el-dialog v-model="createServerVisible" title="注册 MCP Server" width="500px">
      <el-form ref="serverFormRef" :model="serverForm" :rules="serverRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="serverForm.name" placeholder="Server 名称" />
        </el-form-item>
        <el-form-item label="协议" prop="protocol">
          <el-select v-model="serverForm.protocol" style="width:100%">
            <el-option label="SSE" value="sse" />
            <el-option label="WebSocket" value="websocket" />
            <el-option label="STDIO" value="stdio" />
          </el-select>
        </el-form-item>
        <el-form-item label="端点" prop="endpoint">
          <el-input v-model="serverForm.endpoint" placeholder="https://..." />
        </el-form-item>
        <el-form-item label="能力" prop="capabilities">
          <el-checkbox-group v-model="serverForm.capabilities">
            <el-checkbox label="tools" value="tools" />
            <el-checkbox label="resources" value="resources" />
            <el-checkbox label="prompts" value="prompts" />
            <el-checkbox label="sampling" value="sampling" />
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createServerVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateServer" :loading="submitting">注册</el-button>
      </template>
    </el-dialog>

    <!-- 创建 Agent 对话框 -->
    <el-dialog v-model="createAgentVisible" title="注册 AI Agent" width="500px">
      <el-form ref="agentFormRef" :model="agentForm" :rules="agentRules" label-width="120px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="agentForm.name" placeholder="Agent 名称" />
        </el-form-item>
        <el-form-item label="框架" prop="framework">
          <el-select v-model="agentForm.framework" style="width:100%">
            <el-option label="LangChain" value="langchain" />
            <el-option label="AutoGPT" value="autogpt" />
            <el-option label="CrewAI" value="crewai" />
            <el-option label="Dify" value="dify" />
            <el-option label="自定义" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="月 Token 配额">
          <el-input-number v-model="agentForm.monthly_token_quota" :min="0" :max="999999999" :step="100000" style="width:100%" />
        </el-form-item>
        <el-form-item label="能力" prop="capabilities">
          <el-select v-model="agentForm.capabilities" multiple filterable style="width:100%" placeholder="选择能力">
            <el-option label="对话" value="chat" />
            <el-option label="代码生成" value="code_generation" />
            <el-option label="数据分析" value="data_analysis" />
            <el-option label="图像生成" value="image_generation" />
            <el-option label="文档处理" value="document_processing" />
            <el-option label="RAG 检索" value="rag_retrieval" />
            <el-option label="工具调用" value="tool_use" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createAgentVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateAgent" :loading="submitting">注册</el-button>
      </template>
    </el-dialog>

    <!-- 配额检查结果 -->
    <el-dialog v-model="quotaVisible" title="Token 配额检查" width="450px">
      <template v-if="quotaResult">
        <el-descriptions :column="1" border>
          <el-descriptions-item label="允许调用">
            <el-tag :type="quotaResult.allowed ? 'success' : 'danger'">{{ quotaResult.allowed ? '是' : '否' }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item v-if="quotaResult.reason" label="原因">{{ quotaResult.reason }}</el-descriptions-item>
          <el-descriptions-item label="已用 Token">{{ quotaResult.used }}</el-descriptions-item>
          <el-descriptions-item label="配额上限">{{ quotaResult.limit }}</el-descriptions-item>
          <el-descriptions-item label="剩余 Token" v-if="quotaResult.remaining !== undefined">
            <span :class="quotaResult.remaining < 10000 ? 'text-danger' : ''">{{ quotaResult.remaining }}</span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Connection, Refresh, Plus } from '@element-plus/icons-vue';
import mcpApi from '@/api/mcpAuth';

const loading = ref(false);
const submitting = ref(false);
const activeTab = ref('servers');

// 仪表盘
const stats = ref({ servers: 0, agents: 0, activeServers: 0, activeAgents: 0, totalTokens: 0 });

// MCP Servers
const servers = ref([]);
const serversLoading = ref(false);
const serverPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// AI Agents
const agents = ref([]);
const agentsLoading = ref(false);
const agentPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 创建 Server
const createServerVisible = ref(false);
const serverFormRef = ref(null);
const serverForm = reactive({ name: '', protocol: 'sse', endpoint: '', capabilities: ['tools'] });
const serverRules = { name: [{ required: true, message: '请输入名称' }] };

// 创建 Agent
const createAgentVisible = ref(false);
const agentFormRef = ref(null);
const agentForm = reactive({ name: '', framework: 'custom', monthly_token_quota: 1000000, capabilities: [] });
const agentRules = { name: [{ required: true, message: '请输入名称' }] };

// 配额检查
const quotaVisible = ref(false);
const quotaResult = ref(null);

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const res = await mcpApi.dashboard();
    stats.value = res.data;
  } finally {
    loading.value = false;
  }
  loadServers();
  loadAgents();
}

async function loadServers() {
  serversLoading.value = true;
  try {
    const res = await mcpApi.listServers({ page: serverPagination.current_page });
    servers.value = res.data.data || [];
    Object.assign(serverPagination, res.data);
  } finally {
    serversLoading.value = false;
  }
}

async function loadAgents() {
  agentsLoading.value = true;
  try {
    const res = await mcpApi.listAgents({ page: agentPagination.current_page });
    agents.value = res.data.data || [];
    Object.assign(agentPagination, res.data);
  } finally {
    agentsLoading.value = false;
  }
}

function showCreateServer() {
  serverForm.name = '';
  serverForm.protocol = 'sse';
  serverForm.endpoint = '';
  serverForm.capabilities = ['tools'];
  createServerVisible.value = true;
}

async function handleCreateServer() {
  const valid = await serverFormRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await mcpApi.registerServer(serverForm);
    ElMessage.success('MCP Server 注册成功');
    createServerVisible.value = false;
    loadServers();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

function showCreateAgent() {
  agentForm.name = '';
  agentForm.framework = 'custom';
  agentForm.monthly_token_quota = 1000000;
  agentForm.capabilities = [];
  createAgentVisible.value = true;
}

async function handleCreateAgent() {
  const valid = await agentFormRef.value.validate().catch(() => false);
  if (!valid) return;
  submitting.value = true;
  try {
    await mcpApi.registerAgent(agentForm);
    ElMessage.success('AI Agent 注册成功');
    createAgentVisible.value = false;
    loadAgents();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

async function handleCheckQuota(agent) {
  try {
    const res = await mcpApi.checkAgentQuota(agent.id);
    quotaResult.value = res.data;
    quotaVisible.value = true;
  } catch {
    ElMessage.error('配额检查失败');
  }
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.mcp-auth-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-primary { color: #409EFF; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
.text-danger { color: #F56C6C; font-weight: 700; }
</style>
