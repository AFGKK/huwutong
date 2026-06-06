<template>
    <div class="llm-page">
        <div class="page-header">
            <div class="header-left">
                <h2>大模型统一适配层</h2>
                <span class="header-subtitle">DeepSeek / OpenAI / Anthropic Claude / 通义千问 / 文心一言 / 智谱 GLM</span>
            </div>
        </div>

        <el-alert title="已对接 DeepSeek-V3 / DeepSeek-R1，支持一键切换 Provider、Token 用量统计、流式输出、自动降级" type="success" show-icon :closable="false" class="alert-bar" />

        <el-tabs v-model="activeTab" class="tabs-container">
            <!-- Provider 管理 -->
            <el-tab-pane label="Provider 管理" name="providers">
                <el-table :data="providers" v-loading="loadingProviders" stripe>
                    <el-table-column label="排序" width="60" prop="sort_order" align="center" />
                    <el-table-column label="名称" min-width="120" prop="name" />
                    <el-table-column label="驱动" width="110" prop="driver" />
                    <el-table-column label="默认模型" width="150" prop="default_model" />
                    <el-table-column label="API Base" min-width="200" prop="api_base" />
                    <el-table-column label="API Key" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.api_key_set ? 'success' : 'danger'" size="small" effect="plain">
                                {{ row.api_key_set ? '已配置' : '未配置' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? '启用' : '停用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="备用" width="60" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_fallback" type="warning" size="small">备用</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openEditDialog(row)">编辑</el-button>
                            <el-button text size="small" type="success" :loading="testingId === row.id" @click="handleTest(row)">测试</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 编辑 Provider 对话框 -->
                <el-dialog v-model="editDialogVisible" :title="'编辑 ' + editForm.name" width="560px">
                    <el-form :model="editForm" label-position="top" size="small">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item label="名称">
                                    <el-input v-model="editForm.name" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="默认模型">
                                    <el-input v-model="editForm.default_model" placeholder="如 deepseek-chat" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item label="API Base URL">
                            <el-input v-model="editForm.api_base" placeholder="https://api.deepseek.com" />
                        </el-form-item>
                        <el-form-item label="API Key">
                            <el-input v-model="editForm.api_key" type="password" show-password placeholder="留空不修改" />
                        </el-form-item>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item label="温度 (temperature)">
                                    <el-input-number v-model="editForm.config.temperature" :min="0" :max="2" :step="0.1" :precision="1" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="最大 Token">
                                    <el-input-number v-model="editForm.config.max_tokens" :min="100" :max="128000" :step="1000" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="4">
                                <el-form-item label="启用">
                                    <el-switch v-model="editForm.is_active" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="4">
                                <el-form-item label="备用">
                                    <el-switch v-model="editForm.is_fallback" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                    <template #footer>
                        <el-button @click="editDialogVisible = false">取消</el-button>
                        <el-button type="primary" :loading="savingProvider" @click="handleSaveProvider">保存</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- AI 聊天测试 -->
            <el-tab-pane label="AI 聊天测试" name="chat">
                <div class="chat-container">
                    <div class="chat-messages" ref="chatRef">
                        <div v-if="chatMessages.length === 0" class="chat-empty">
                            <el-icon :size="48" color="#c0c4cc"><ChatLineSquare /></el-icon>
                            <p>输入内容开始与 AI 对话测试</p>
                        </div>
                        <div v-for="(msg, i) in chatMessages" :key="i" :class="['chat-msg', msg.role]">
                            <div class="msg-role">{{ msg.role === 'user' ? '你' : 'AI' }}</div>
                            <div class="msg-content" v-html="renderMarkdown(msg.content)" />
                            <div v-if="msg.usage" class="msg-usage">
                                Token: {{ msg.usage.total_tokens }} | {{ msg.duration_ms }}ms
                            </div>
                        </div>
                        <div v-if="streaming" class="chat-msg assistant">
                            <div class="msg-role">AI</div>
                            <div class="msg-content">{{ streamContent }}<span class="cursor-blink">▍</span></div>
                        </div>
                    </div>
                    <div class="chat-input-bar">
                        <el-select v-model="chatProvider" size="small" style="width: 130px; margin-right: 8px;">
                            <el-option v-for="p in activeProviders" :key="p.slug" :label="p.name" :value="p.slug" />
                        </el-select>
                        <el-select v-model="chatModel" size="small" style="width: 150px; margin-right: 8px;">
                            <el-option v-for="m in availableModels" :key="m.id" :label="m.name" :value="m.id" />
                        </el-select>
                        <el-input v-model="chatInput" size="small" placeholder="输入消息，按 Enter 发送" @keyup.enter="sendChat" :disabled="streaming" clearable />
                        <el-button type="primary" size="small" :loading="streaming" @click="sendChat" style="margin-left: 8px;">
                            发送
                        </el-button>
                        <el-button size="small" @click="clearChat" style="margin-left: 4px;">清空</el-button>
                    </div>
                </div>
            </el-tab-pane>

            <!-- 自动降级管理 -->
            <el-tab-pane label="自动降级管理" name="fallback">
                <div class="fallback-panel">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header>
                                    <div class="card-header">
                                        <span>降级状态</span>
                                        <el-tag v-if="fallbackStatus?.fallback_active" type="warning">降级中</el-tag>
                                        <el-tag v-else type="success">正常</el-tag>
                                    </div>
                                </template>
                                <el-descriptions :column="1" border v-if="fallbackStatus">
                                    <el-descriptions-item label="降级策略">
                                        <el-tag size="small">{{ fallbackStatus.fallback_strategy || '无' }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="活跃降级 Provider">
                                        {{ fallbackStatus.fallback_provider || '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="连续失败次数（当前）">
                                        <el-tag :type="(fallbackStatus.consecutive_failures || 0) > 3 ? 'danger' : 'info'" size="small">
                                            {{ fallbackStatus.consecutive_failures || 0 }}
                                        </el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item label="降级触发时间">
                                        {{ fallbackStatus.fallback_triggered_at || '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="总计降级次数">
                                        {{ fallbackStatus.total_fallbacks || 0 }}
                                    </el-descriptions-item>
                                    <el-descriptions-item label="上次降级原因">
                                        {{ fallbackStatus.last_fallback_reason || '-' }}
                                    </el-descriptions-item>
                                </el-descriptions>
                                <div v-else class="empty-data">
                                    <el-skeleton :rows="4" animated />
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header><span>Provider 健康度</span></template>
                                <el-table :data="fallbackStatus?.provider_health || []" size="small" v-if="(fallbackStatus?.provider_health?.length || 0) > 0">
                                    <el-table-column label="Provider" prop="provider_name" />
                                    <el-table-column label="健康状态" width="100">
                                        <template #default="{ row }">
                                            <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">
                                                {{ row.healthy ? '健康' : '异常' }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="最近成功" width="160" prop="last_success_at">
                                        <template #default="{ row }">{{ row.last_success_at || '-' }}</template>
                                    </el-table-column>
                                    <el-table-column label="失败率" width="100">
                                        <template #default="{ row }">
                                            {{ row.failure_rate ? (row.failure_rate * 100).toFixed(1) + '%' : '0%' }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-else-if="!loadingFallback" description="暂无 Provider 健康数据" />
                                <el-skeleton v-else :rows="3" animated />
                            </el-card>
                        </el-col>
                    </el-row>
                    <el-card shadow="never" class="section-card">
                        <template #header><span>操作</span></template>
                        <div class="action-bar">
                            <el-button type="warning" @click="handleResetFallback" :loading="resettingFallback">
                                <el-icon style="margin-right:4px"><Refresh /></el-icon>重置降级状态
                            </el-button>
                            <el-button @click="loadFallbackStatus" :loading="loadingFallback" style="margin-left: 8px;">
                                刷新状态
                            </el-button>
                            <span class="tip-text">重置后系统将重新尝试使用主 Provider</span>
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- Token 统计 -->
            <el-tab-pane label="Token 用量统计" name="stats">
                <el-row :gutter="16" class="stats-row">
                    <el-col :span="6" v-for="s in tokenStatCards" :key="s.label">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                            <div class="stat-label">{{ s.label }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" class="section-card">
                    <template #header><span>按模型统计</span></template>
                    <el-table :data="byModel" v-loading="loadingStats" stripe size="small">
                        <el-table-column label="模型" prop="model" />
                        <el-table-column label="Token 数" prop="tokens" align="right">
                            <template #default="{ row }">{{ formatNumber(row.tokens) }}</template>
                        </el-table-column>
                        <el-table-column label="费用 (USD)" prop="cost" align="right" width="140">
                            <template #default="{ row }">${{ row.cost.toFixed(6) }}</template>
                        </el-table-column>
                        <el-table-column label="请求数" prop="requests" align="right" width="100" />
                    </el-table>
                </el-card>

                <el-card shadow="never" class="section-card">
                    <template #header><span>每日趋势（近 30 天）</span></template>
                    <div class="daily-chart" v-if="byDay.length > 0">
                        <div class="bar-item" v-for="d in dailyChartData" :key="d.date">
                            <div class="bar-label">{{ d.label }}</div>
                            <div class="bar-track">
                                <div class="bar-fill" :style="{ width: d.pct + '%' }" />
                            </div>
                            <div class="bar-value">{{ formatNumber(d.tokens) }}</div>
                        </div>
                    </div>
                    <el-empty v-else description="暂无数据" />
                </el-card>
            </el-tab-pane>

            <!-- 日志 -->
            <el-tab-pane label="请求日志" name="logs">
                <div class="filter-bar">
                    <el-select v-model="logFilter.errorsOnly" size="small" style="width: 130px;" @change="loadLogs">
                        <el-option label="全部" :value="false" />
                        <el-option label="仅错误" :value="true" />
                    </el-select>
                    <el-button size="small" @click="loadLogs" style="margin-left: 8px;">刷新</el-button>
                </div>
                <el-table :data="logList" v-loading="loadingLogs" stripe size="small" max-height="500">
                    <el-table-column label="Provider" width="100" prop="provider_name" />
                    <el-table-column label="模型" width="120" prop="model" />
                    <el-table-column label="功能" width="80" prop="function" />
                    <el-table-column label="Token" width="100" align="right">
                        <template #default="{ row }">{{ row.total_tokens }}</template>
                    </el-table-column>
                    <el-table-column label="费用 $" width="100" align="right" prop="cost_usd" />
                    <el-table-column label="耗时" width="80" align="right" prop="duration_ms">
                        <template #default="{ row }">{{ row.duration_ms }}ms</template>
                    </el-table-column>
                    <el-table-column label="状态" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                                {{ row.success ? '成功' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="错误" min-width="160" prop="error_message" />
                    <el-table-column label="时间" width="170" prop="created_at" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { ChatLineSquare } from '@element-plus/icons-vue';
import llmApi from '@/api/llm';
import llmFallbackApi from '@/api/llmFallback';
import { Refresh } from '@element-plus/icons-vue';

const activeTab = ref('providers');
const loadingProviders = ref(false);
const savingProvider = ref(false);
const testingId = ref(null);

const providers = ref([]);
const editDialogVisible = ref(false);
const editForm = reactive({
    id: null, name: '', api_base: '', api_key: '',
    default_model: '', is_active: true, is_fallback: false,
    config: { temperature: 0.7, max_tokens: 4096 },
});

// Chat
const chatInput = ref('');
const chatMessages = ref([]);
const chatProvider = ref('deepseek');
const chatModel = ref('deepseek-chat');
const streaming = ref(false);
const streamContent = ref('');
const chatRef = ref(null);
let abortController = null;

const activeProviders = computed(() => providers.value.filter(p => p.is_active));
const availableModels = computed(() => {
    const p = providers.value.find(p => p.slug === chatProvider.value);
    return p?.models || [];
});

    // Stats
const loadingStats = ref(false);
const tokenStats = reactive({
    total_tokens: 0, total_cost_usd: 0, total_requests: 0,
    total_prompt_tokens: 0, total_completion_tokens: 0,
});
const byModel = ref([]);
const byDay = ref([]);

const tokenStatCards = computed(() => [
    { label: '总 Token', value: formatNumber(tokenStats.total_tokens), color: '#409EFF' },
    { label: '总费用 (USD)', value: '$' + (tokenStats.total_cost_usd || 0).toFixed(6), color: '#F56C6C' },
    { label: '总请求数', value: formatNumber(tokenStats.total_requests), color: '#67C23A' },
    { label: 'Prompt/Completion', value: formatNumber(tokenStats.total_prompt_tokens) + ' / ' + formatNumber(tokenStats.total_completion_tokens), color: '#E6A23C' },
]);

const dailyChartData = computed(() => {
    if (byDay.value.length === 0) return [];
    const maxTokens = Math.max(...byDay.value.map(d => d.tokens || 0));
    return byDay.value.map(d => ({
        date: d.date,
        label: d.date?.slice(5) || '',
        tokens: d.tokens || 0,
        pct: maxTokens > 0 ? ((d.tokens || 0) / maxTokens) * 100 : 0,
    }));
});

// Logs
const logList = ref([]);
const loadingLogs = ref(false);
const logFilter = reactive({ errorsOnly: false });

function formatNumber(n) {
    if (!n) return '0';
    return n.toLocaleString();
}

function renderMarkdown(text) {
    if (!text) return '';
    const escaped = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return escaped
        .replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

async function loadProviders() {
    loadingProviders.value = true;
    try {
        const { data: res } = await llmApi.providers();
        if (res.success) providers.value = res.data || [];
    } finally {
        loadingProviders.value = false;
    }
}

function openEditDialog(provider) {
    Object.assign(editForm, {
        id: provider.id,
        name: provider.name,
        api_base: provider.api_base || '',
        api_key: '',
        default_model: provider.default_model || '',
        is_active: provider.is_active,
        is_fallback: provider.is_fallback,
        config: {
            temperature: provider.config?.temperature ?? 0.7,
            max_tokens: provider.config?.max_tokens ?? 4096,
        },
    });
    editDialogVisible.value = true;
}

async function handleSaveProvider() {
    savingProvider.value = true;
    try {
        const data = {
            name: editForm.name,
            api_base: editForm.api_base,
            default_model: editForm.default_model,
            is_active: editForm.is_active,
            is_fallback: editForm.is_fallback,
            config: {
                temperature: editForm.config.temperature,
                max_tokens: editForm.config.max_tokens,
            },
        };
        if (editForm.api_key) data.api_key = editForm.api_key;

        const { data: res } = await llmApi.updateProvider(editForm.id, data);
        if (res.success) {
            ElMessage.success('配置已保存');
            editDialogVisible.value = false;
            await loadProviders();
        }
    } catch {
        ElMessage.error('保存失败');
    } finally {
        savingProvider.value = false;
    }
}

async function handleTest(provider) {
    testingId.value = provider.id;
    try {
        const { data: res } = await llmApi.testConnection(provider.id);
        if (res.success) {
            ElMessage.success(res.data?.message || '连接成功');
        } else {
            ElMessage.warning(res.message || '连接异常');
        }
    } catch {
        ElMessage.error('测试失败');
    } finally {
        testingId.value = null;
    }
}

async function sendChat() {
    const text = chatInput.value.trim();
    if (!text || streaming.value) return;

    chatMessages.value.push({ role: 'user', content: text });
    chatInput.value = '';

    streaming.value = true;
    streamContent.value = '';
    const msgIndex = chatMessages.value.length;

    chatMessages.value.push({ role: 'assistant', content: '', usage: null });

    try {
        const { data: res } = await llmApi.chat([
            { role: 'system', content: '你是一个智能助手，请用中文回答。' },
            ...chatMessages.value.slice(0, msgIndex).map(m => ({ role: m.role, content: m.content })),
        ], {
            provider: chatProvider.value,
            model: chatModel.value,
        });

        if (res.success) {
            chatMessages.value[msgIndex].content = res.data?.content || '';
            chatMessages.value[msgIndex].usage = res.data?.usage || null;
        } else {
            chatMessages.value[msgIndex].content = '错误: ' + (res.message || '请求失败');
        }
    } catch (e) {
        chatMessages.value[msgIndex].content = '请求异常: ' + (e.message || '未知错误');
    } finally {
        streaming.value = false;
        scrollToBottom();
    }
}

function clearChat() {
    chatMessages.value = [];
    streamContent.value = '';
}

function scrollToBottom() {
    nextTick(() => {
        if (chatRef.value) {
            chatRef.value.scrollTop = chatRef.value.scrollHeight;
        }
    });
}

async function loadStats() {
    loadingStats.value = true;
    try {
        const { data: res } = await llmApi.tokenStats(30);
        if (res.success) {
            Object.assign(tokenStats, res.data);
            byModel.value = res.data?.by_model || [];
            byDay.value = res.data?.by_day || [];
        }
    } finally {
        loadingStats.value = false;
    }
}

async function loadLogs() {
    loadingLogs.value = true;
    try {
        const params = { per_page: 50 };
        if (logFilter.errorsOnly) params.errors_only = true;
        const { data: res } = await llmApi.logs(params);
        if (res.success) logList.value = res.data?.data || [];
    } finally {
        loadingLogs.value = false;
    }
}

// ── Fallback 管理 ──
const fallbackStatus = ref(null);
const loadingFallback = ref(false);
const resettingFallback = ref(false);

async function loadFallbackStatus() {
    loadingFallback.value = true;
    try {
        const { data: res } = await llmFallbackApi.status();
        if (res.success) fallbackStatus.value = res.data;
    } catch {
        // ignore
    } finally {
        loadingFallback.value = false;
    }
}

async function handleResetFallback() {
    resettingFallback.value = true;
    try {
        const { data: res } = await llmFallbackApi.reset();
        if (res.success) {
            ElMessage.success('降级状态已重置');
            await loadFallbackStatus();
        } else {
            ElMessage.warning(res.message || '重置失败');
        }
    } catch {
        ElMessage.error('重置请求失败');
    } finally {
        resettingFallback.value = false;
    }
}

onMounted(() => {
    loadProviders();
    loadStats();
    loadLogs();
    loadFallbackStatus();
});
</script>

<style scoped>
.llm-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.alert-bar { margin-bottom: 16px; }
.tabs-container { }

/* Chat */
.chat-container {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;
    overflow: hidden;
}
.chat-messages {
    height: 420px;
    overflow-y: auto;
    padding: 16px;
    background: var(--el-bg-color-page);
}
.chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--el-text-color-placeholder);
}
.chat-empty p { margin-top: 12px; }
.chat-msg {
    margin-bottom: 16px;
    max-width: 80%;
}
.chat-msg.user {
    margin-left: auto;
    text-align: right;
}
.chat-msg.assistant {
    margin-right: auto;
}
.msg-role {
    font-size: 12px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
}
.msg-content {
    background: var(--el-bg-color);
    border-radius: 8px;
    padding: 10px 14px;
    line-height: 1.6;
    font-size: 14px;
    text-align: left;
    white-space: pre-wrap;
    word-break: break-word;
}
.chat-msg.user .msg-content {
    background: var(--el-color-primary-light-9);
}
.msg-usage {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}
.cursor-blink {
    animation: blink 1s step-end infinite;
}
@keyframes blink {
    50% { opacity: 0; }
}
.chat-input-bar {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-top: 1px solid var(--el-border-color-lighter);
    background: var(--el-bg-color);
}
.chat-input-bar .el-input { flex: 1; }

/* Stats */
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 20px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.section-card { margin-bottom: 16px; }

/* Logs */
.filter-bar {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
/* 每日趋势图 */
.daily-chart {
    max-height: 300px;
    overflow-y: auto;
}
.bar-item {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
    font-size: 12px;
}
.bar-label {
    width: 50px;
    color: var(--el-text-color-secondary);
    text-align: right;
    margin-right: 8px;
    flex-shrink: 0;
}
.bar-track {
    flex: 1;
    height: 16px;
    background: var(--el-fill-color-light);
    border-radius: 8px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #409EFF, #79bbff);
    border-radius: 8px;
    transition: width 0.3s ease;
}
.bar-value {
    width: 80px;
    text-align: right;
    margin-left: 8px;
    color: var(--el-text-color-regular);
    flex-shrink: 0;
}
:deep(.el-card__body) { padding: 16px; }

/* Fallback panel */
.fallback-panel { }
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.action-bar {
    display: flex;
    align-items: center;
    gap: 8px;
}
.tip-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 8px;
}
.empty-data {
    padding: 20px 0;
}
</style>
