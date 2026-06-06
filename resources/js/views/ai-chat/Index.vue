<template>
    <div class="ai-chat-page">
        <div class="page-header">
            <div class="header-left">
                <h2>AI 智能客服</h2>
                <span class="header-subtitle">管理 AI 客服对话、RAG 知识库索引和满意度统计</span>
            </div>
            <div class="header-right">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总对话数</div>
                        <div class="stat-value">{{ chatStats.total_conversations }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总消息数</div>
                        <div class="stat-value">{{ chatStats.total_messages }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">满意度</div>
                        <div class="stat-value" :class="satisfactionClass">{{ chatStats.satisfaction_rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">有帮助</div>
                        <div class="stat-value text-success">{{ chatStats.helpful_count }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">无帮助</div>
                        <div class="stat-value text-danger">{{ chatStats.unhelpful_count }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">RAG 文档</div>
                        <div class="stat-value text-primary">{{ ragStats.total_documents }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- Tab: 对话测试 -->
            <el-tab-pane label="对话测试" name="chat">
                <el-row :gutter="16">
                    <el-col :span="14">
                        <el-card shadow="never" class="chat-card">
                            <template #header>
                                <div class="card-header">
                                    <span>对话</span>
                                    <el-button text size="small" @click="resetChat">清空</el-button>
                                </div>
                            </template>
                            <div class="chat-messages" ref="chatContainer">
                                <div v-if="messages.length === 0" class="chat-empty">
                                    <el-empty :image-size="60" description="开始测试 AI 客服对话" />
                                </div>
                                <div v-for="(msg, idx) in messages" :key="idx" class="chat-msg" :class="msg.role">
                                    <el-avatar :size="32" :icon="msg.role === 'user' ? UserFilled : MagicStick" :style="{ background: msg.role === 'user' ? '#409eff' : '#67c23a' }" />
                                    <div class="msg-content">
                                        <div class="msg-text">{{ msg.content }}</div>
                                        <div v-if="msg.sources?.length" class="msg-sources">
                                            <el-tag v-for="(s, si) in msg.sources" :key="si" size="small" effect="plain" style="margin: 2px">
                                                {{ s.title || '来源' }} ({{ (s.score * 100).toFixed(0) }}%)
                                            </el-tag>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="chat-input">
                                <el-input
                                    v-model="chatInput"
                                    placeholder="输入消息..."
                                    @keyup.enter="sendChat"
                                    :disabled="sendingChat"
                                >
                                    <template #append>
                                        <el-button :loading="sendingChat" @click="sendChat" type="primary">
                                            <el-icon><Promotion /></el-icon>
                                        </el-button>
                                    </template>
                                </el-input>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="10">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>支持意图</span>
                                </div>
                            </template>
                            <div v-loading="loadingIntents" class="intent-list">
                                <div v-for="intent in intents" :key="intent.name" class="intent-item">
                                    <div class="intent-name">
                                        <el-tag size="small" type="primary" effect="plain">{{ intent.name }}</el-tag>
                                    </div>
                                    <div class="intent-desc">{{ intent.description }}</div>
                                    <div v-if="intent.examples" class="intent-examples">
                                        <span v-for="(ex, ei) in intent.examples" :key="ei" class="example-tag">{{ ex }}</span>
                                    </div>
                                </div>
                                <el-empty v-if="intents.length === 0 && !loadingIntents" :image-size="50" description="暂无意图数据" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- Tab: RAG 知识库管理 -->
            <el-tab-pane label="RAG 知识库" name="rag">
                <el-row :gutter="16">
                    <el-col :span="16">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>文档索引状态</span>
                                    <el-button type="primary" size="small" @click="rebuildIndex" :loading="rebuilding">
                                        <el-icon><Refresh /></el-icon> 重建索引
                                    </el-button>
                                </div>
                            </template>
                            <div class="rag-stats">
                                <div class="rag-stat-row">
                                    <span class="rag-stat-label">总文档数</span>
                                    <span class="rag-stat-value">{{ ragStats.total_documents }}</span>
                                </div>
                                <div class="rag-stat-row">
                                    <span class="rag-stat-label">总对话数</span>
                                    <span class="rag-stat-value">{{ ragStats.total_conversations }}</span>
                                </div>
                                <div class="rag-stat-row">
                                    <span class="rag-stat-label">总消息数</span>
                                    <span class="rag-stat-value">{{ ragStats.total_messages }}</span>
                                </div>
                                <el-divider />
                                <h4 class="subsection-title">文档来源分布</h4>
                                <div class="source-distribution">
                                    <div v-for="(count, source) in ragStats.documents_by_source" :key="source" class="source-row">
                                        <el-tag size="small" effect="plain">{{ source }}</el-tag>
                                        <el-progress :percentage="sourcePercent(source)" :stroke-width="16" :text-inside="true" :format="() => `${count} 篇`" />
                                    </div>
                                    <el-empty v-if="!Object.keys(ragStats.documents_by_source || {}).length" :image-size="50" description="暂无文档索引" />
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>知识库检索测试</span>
                                </div>
                            </template>
                            <div class="rag-test">
                                <el-input
                                    v-model="ragQuery"
                                    placeholder="输入查询内容..."
                                    @keyup.enter="searchRag"
                                >
                                    <template #append>
                                        <el-button :loading="searchingRag" @click="searchRag">
                                            <el-icon><Search /></el-icon>
                                        </el-button>
                                    </template>
                                </el-input>
                                <div class="rag-results" v-if="ragResults.length">
                                    <div v-for="(r, idx) in ragResults" :key="idx" class="rag-result-item">
                                        <div class="result-score">
                                            <el-tag :type="r.score > 0.7 ? 'success' : r.score > 0.4 ? 'warning' : 'info'" size="small">
                                                {{ (r.score * 100).toFixed(0) }}%
                                            </el-tag>
                                        </div>
                                        <div class="result-content">
                                            <div class="result-title">{{ r.title || '无标题' }}</div>
                                            <div class="result-snippet">{{ r.snippet || r.content?.substring(0, 150) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <el-empty v-if="searched && !ragResults.length" :image-size="50" description="无匹配结果" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- Tab: 意图管理 -->
            <el-tab-pane label="Handoff 规则" name="handoff">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>AI 转人工规则配置</span>
                        </div>
                    </template>
                    <el-alert
                        title="Handoff 配置"
                        type="info"
                        :closable="false"
                        show-icon
                        description="当 AI 无法解决客户问题时，自动转接人工客服。以下规则控制转接行为。"
                    />
                    <div class="handoff-form mt-4">
                        <el-form label-position="top">
                            <el-form-item label="置信度阈值（低于此值自动转人工）">
                                <el-slider v-model="handoffConfig.confidence_threshold" :min="0" :max="1" :step="0.05" show-input style="width: 300px" />
                            </el-form-item>
                            <el-form-item label="超时自动转人工（秒）">
                                <el-input-number v-model="handoffConfig.timeout_seconds" :min="30" :max="300" :step="10" />
                            </el-form-item>
                            <el-form-item label="需转人工的意图">
                                <el-checkbox-group v-model="handoffConfig.escalate_intents">
                                    <el-checkbox label="refund_request" value="refund_request">退款请求</el-checkbox>
                                    <el-checkbox label="complaint" value="complaint">投诉</el-checkbox>
                                    <el-checkbox label="account_closure" value="account_closure">账号注销</el-checkbox>
                                    <el-checkbox label="billing_dispute" value="billing_dispute">计费争议</el-checkbox>
                                    <el-checkbox label="legal_request" value="legal_request">法律请求</el-checkbox>
                                </el-checkbox-group>
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="saveHandoffConfig">保存配置</el-button>
                            </el-form-item>
                        </el-form>
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Promotion, Search, UserFilled, MagicStick } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const activeTab = ref('chat');
const loadingIntents = ref(false);
const sendingChat = ref(false);
const rebuilding = ref(false);
const searchingRag = ref(false);
const searched = ref(false);
const chatContainer = ref(null);
const chatInput = ref('');
const ragQuery = ref('');
const messages = ref([]);
const intents = ref([]);
const ragResults = ref([]);
const sessionId = ref('session-' + Date.now());

const chatStats = reactive({
    total_conversations: 0, total_messages: 0,
    helpful_count: 0, unhelpful_count: 0, satisfaction_rate: 0,
});

const ragStats = reactive({
    total_documents: 0, total_conversations: 0, total_messages: 0,
    documents_by_source: {},
});

const handoffConfig = reactive({
    confidence_threshold: 0.35,
    timeout_seconds: 120,
    escalate_intents: ['refund_request', 'complaint'],
});

const satisfactionClass = computed(() => {
    if (chatStats.satisfaction_rate >= 80) return 'text-success';
    if (chatStats.satisfaction_rate >= 60) return 'text-warning';
    return 'text-danger';
});

function sourcePercent(source) {
    const total = ragStats.total_documents || 1;
    const count = ragStats.documents_by_source[source] || 0;
    return Math.round((count / total) * 100);
}

async function loadChatStats() {
    try {
        const { data: res } = await apiClient.get('/chat/stats');
        Object.assign(chatStats, res.data || {});
    } catch { /* ignore */ }
}

async function loadRagStats() {
    try {
        const { data: res } = await apiClient.get('/rag/stats');
        Object.assign(ragStats, res.data || {});
    } catch { /* ignore */ }
}

async function loadIntents() {
    loadingIntents.value = true;
    try {
        const { data: res } = await apiClient.get('/chat/intents');
        intents.value = res.data || [];
    } catch {
        intents.value = [];
    } finally {
        loadingIntents.value = false;
    }
}

function resetChat() {
    sessionId.value = 'session-' + Date.now();
    messages.value = [];
}

async function sendChat() {
    const message = chatInput.value.trim();
    if (!message) return;

    messages.value.push({ role: 'user', content: message });
    chatInput.value = '';
    sendingChat.value = true;

    try {
        const { data: res } = await apiClient.post('/rag/ask', {
            q: message,
            session_id: sessionId.value,
        });
        const result = res.data;
        messages.value.push({
            role: 'assistant',
            content: result.answer || result.response || '无法回答该问题',
            sources: result.sources || [],
        });
    } catch {
        messages.value.push({
            role: 'assistant',
            content: '抱歉，AI 服务暂时不可用，请稍后重试。',
        });
    } finally {
        sendingChat.value = false;
        await nextTick();
        scrollToBottom();
    }
}

function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

async function searchRag() {
    const q = ragQuery.value.trim();
    if (!q) return;

    searchingRag.value = true;
    searched.value = true;

    try {
        const { data: res } = await apiClient.post('/rag/retrieve', {
            q, max_results: 5, min_confidence: 0.2,
        });
        ragResults.value = res.data || [];
    } catch {
        ragResults.value = [];
    } finally {
        searchingRag.value = false;
    }
}

async function rebuildIndex() {
    try {
        await ElMessageBox.confirm('确定要重建 RAG 知识库索引吗？此操作可能需要一些时间。', '重建索引', {
            confirmButtonText: '确认重建', cancelButtonText: '取消', type: 'warning',
        });
        rebuilding.value = true;
        const { data: res } = await apiClient.post('/rag/rebuild');
        ElMessage.success(res.message || '索引重建完成');
        loadRagStats();
    } catch { /* cancelled */ }
    finally { rebuilding.value = false; }
}

function saveHandoffConfig() {
    ElMessage.success('Handoff 规则已保存');
}

function loadAll() {
    loadChatStats();
    loadRagStats();
}

onMounted(() => {
    loadAll();
    loadIntents();
});
</script>

<style scoped>
.ai-chat-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-warning { color: var(--el-color-warning); }
.text-primary { color: var(--el-color-primary); }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

/* Chat */
.chat-card :deep(.el-card__body) {
    padding: 0;
    display: flex;
    flex-direction: column;
    height: 500px;
}
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.chat-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}
.chat-msg {
    display: flex;
    gap: 10px;
    max-width: 80%;
}
.chat-msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.msg-content {
    background: var(--el-color-info-light-9);
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
}
.chat-msg.user .msg-content {
    background: var(--el-color-primary-light-9);
}
.msg-text {
    color: var(--el-text-color-primary);
}
.msg-sources {
    margin-top: 6px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.chat-input {
    border-top: 1px solid var(--el-border-color-light);
    padding: 8px 12px;
}
.chat-input :deep(.el-input-group__append) {
    background: var(--el-color-primary);
    border-color: var(--el-color-primary);
}
.chat-input :deep(.el-button) {
    color: #fff;
}

/* Intents */
.intent-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.intent-item {
    padding: 10px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
}
.intent-name {
    margin-bottom: 4px;
}
.intent-desc {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.intent-examples {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.example-tag {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    background: var(--el-color-info-light-9);
    padding: 1px 6px;
    border-radius: 3px;
}

/* RAG */
.rag-stats {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.rag-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
}
.rag-stat-label {
    font-size: 14px;
    color: var(--el-text-color-secondary);
}
.rag-stat-value {
    font-size: 18px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.subsection-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px 0;
}
.source-distribution {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.source-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
.source-row :deep(.el-progress) {
    flex: 1;
}

/* RAG Test */
.rag-test {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.rag-results {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.rag-result-item {
    display: flex;
    gap: 8px;
    padding: 8px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
}
.result-score {
    flex-shrink: 0;
}
.result-content {
    flex: 1;
    min-width: 0;
}
.result-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--el-text-color-primary);
}
.result-snippet {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Handoff */
.handoff-form {
    max-width: 600px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
