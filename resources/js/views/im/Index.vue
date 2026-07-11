<template>
    <div class="im-center">
        <div class="page-header">
            <h2>💬 IM 即时通讯中心</h2>
        </div>

        <el-tabs v-model="activeTab" type="border-card" class="im-tabs">
            <el-tab-pane label="🤖 AI 对话" name="ai-chat">
                <div class="tab-content">
                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">总对话数</div>
                                    <div class="stat-value">{{ aiStats.total_conversations }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">总消息数</div>
                                    <div class="stat-value">{{ aiStats.total_messages }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">满意度</div>
                                    <div class="stat-value" :class="satisfactionClass">{{ aiStats.satisfaction_rate }}%</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">有帮助</div>
                                    <div class="stat-value" style="color:#67c23a">{{ aiStats.helpful_count }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">无帮助</div>
                                    <div class="stat-value" style="color:#f56c6c">{{ aiStats.unhelpful_count }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">RAG 文档</div>
                                    <div class="stat-value" style="color:#409eff">{{ ragStats.total_documents }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 子标签页 -->
                    <el-tabs v-model="aiSubTab" class="ai-sub-tabs">
                        <!-- 对话测试 -->
                        <el-tab-pane label="🗨️ 对话测试" name="chat">
                            <el-row :gutter="16">
                                <el-col :span="14">
                                    <el-card shadow="never" class="chat-card">
                                        <template #header>
                                            <div class="card-header">
                                                <span>对话</span>
                                                <el-button text size="small" @click="resetAiChat">清空</el-button>
                                            </div>
                                        </template>
                                        <div class="chat-messages" ref="chatContainer">
                                            <div v-if="aiMessages.length === 0" class="chat-empty">
                                                <el-empty :image-size="60" description="开始测试 AI 客服对话" />
                                            </div>
                                            <div v-for="(msg, idx) in aiMessages" :key="idx" class="chat-msg" :class="msg.role">
                                                <el-avatar :size="32" :icon="msg.role === 'user' ? UserFilled : MagicStick" :style="{ background: msg.role === 'user' ? '#409eff' : '#67c23a' }" />
                                                <div class="msg-content">
                                                    <div class="msg-text">{{ msg.content }}</div>
                                                    <div v-if="msg.sources?.length" class="msg-sources">
                                                        <el-tag v-for="(s, si) in msg.sources" :key="si" size="small" effect="plain" style="margin:2px">
                                                            {{ s.title || '来源' }} ({{ (s.score * 100).toFixed(0) }}%)
                                                        </el-tag>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="chat-input">
                                            <el-input v-model="aiChatInput" placeholder="输入消息..." @keyup.enter="sendAiChat" :disabled="sendingAiChat">
                                                <template #append>
                                                    <el-button :loading="sendingAiChat" @click="sendAiChat" type="primary">
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
                                            <div class="card-header"><span>支持意图</span></div>
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

                        <!-- RAG 知识库 -->
                        <el-tab-pane label="📚 RAG 知识库" name="rag">
                            <el-row :gutter="16">
                                <el-col :span="16">
                                    <el-card shadow="never">
                                        <template #header>
                                            <div class="card-header">
                                                <span>文档索引状态</span>
                                                <el-button type="primary" size="small" @click="rebuildRagIndex" :loading="rebuildingRag">
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
                                                    <el-progress :percentage="ragSourcePercent(source)" :stroke-width="16" :text-inside="true" :format="() => `${count} 篇`" />
                                                </div>
                                                <el-empty v-if="!Object.keys(ragStats.documents_by_source || {}).length" :image-size="50" description="暂无文档索引" />
                                            </div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="8">
                                    <el-card shadow="never">
                                        <template #header>
                                            <div class="card-header"><span>知识库检索测试</span></div>
                                        </template>
                                        <div class="rag-test">
                                            <el-input v-model="ragQuery" placeholder="输入查询内容..." @keyup.enter="searchRag">
                                                <template #append>
                                                    <el-button :loading="searchingRag" @click="searchRag"><el-icon><Search /></el-icon></el-button>
                                                </template>
                                            </el-input>
                                            <div class="rag-results" v-if="ragResults.length">
                                                <div v-for="(r, idx) in ragResults" :key="idx" class="rag-result-item">
                                                    <div class="result-score">
                                                        <el-tag :type="r.score > 0.7 ? 'success' : r.score > 0.4 ? 'warning' : 'info'" size="small">{{ (r.score * 100).toFixed(0) }}%</el-tag>
                                                    </div>
                                                    <div class="result-content">
                                                        <div class="result-title">{{ r.title || '无标题' }}</div>
                                                        <div class="result-snippet">{{ r.snippet || r.content?.substring(0, 150) }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <el-empty v-if="ragSearched && !ragResults.length" :image-size="50" description="无匹配结果" />
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </el-tab-pane>

                        <!-- Handoff 规则 -->
                        <el-tab-pane label="⚙️ Handoff 规则" name="handoff">
                            <el-card shadow="never">
                                <template #header>
                                    <div class="card-header"><span>AI 转人工规则配置</span></div>
                                </template>
                                <el-alert title="Handoff 配置" type="info" :closable="false" show-icon description="当 AI 无法解决客户问题时，自动转接人工客服。以下规则控制转接行为。" />
                                <div class="handoff-form mt-4">
                                    <el-form label-position="top">
                                        <el-form-item label="置信度阈值（低于此值自动转人工）">
                                            <el-slider v-model="handoffConfig.confidence_threshold" :min="0" :max="1" :step="0.05" show-input style="width:300px" />
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
            </el-tab-pane>

            <el-tab-pane label="🔗 渠道集成" name="integration">
                <div class="tab-content">
                    <el-row :gutter="20">
                        <el-col :span="6">
                            <el-card shadow="never" class="integration-card" @click="$router.push('/im-integration')">
                                <div class="integration-icon">📢</div>
                                <div class="integration-name">IM 通知集成</div>
                                <div class="integration-desc">Slack / 钉钉 / 企微 / 飞书</div>
                                <el-tag size="small" type="success">已配置</el-tag>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="integration-card" @click="$router.push('/teams-notifier')">
                                <div class="integration-icon">💼</div>
                                <div class="integration-name">Teams 通知</div>
                                <div class="integration-desc">Microsoft Teams</div>
                                <el-tag size="small" type="info">查看详情</el-tag>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="integration-card">
                                <div class="integration-icon">🌐</div>
                                <div class="integration-name">WebSocket</div>
                                <div class="integration-desc">Laravel Reverb + Echo</div>
                                <el-tag size="small" type="success">运行中</el-tag>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="integration-card" @click="$router.push('/tickets')">
                                <div class="integration-icon">🎫</div>
                                <div class="integration-name">工单系统</div>
                                <div class="integration-desc">售后/支持工单</div>
                                <el-tag size="small" type="info">查看工单</el-tag>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <el-tab-pane label="⚙️ 配置" name="settings">
                <div class="tab-content">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><span>AI 与转接配置</span></template>
                                <el-form label-width="200px">
                                    <el-form-item label="AI 自动回复">
                                        <el-switch v-model="config.aiAutoReply" />
                                        <span class="config-hint">启用后 AI 优先回复用户消息</span>
                                    </el-form-item>
                                    <el-form-item label="自动转人工阈值">
                                        <el-select v-model="config.autoHandoffLevel" style="width: 200px;">
                                            <el-option label="3 轮对话后" :value="3" />
                                            <el-option label="5 轮对话后" :value="5" />
                                            <el-option label="10 轮对话后" :value="10" />
                                        </el-select>
                                        <span class="config-hint">AI 无法解决时自动转人工</span>
                                    </el-form-item>
                                    <el-form-item label="人工客服在线状态">
                                        <el-tag type="success">在线</el-tag>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><span>聊天窗口设置</span></template>
                                <el-form label-width="160px">
                                    <el-form-item label="窗口宽度 (px)">
                                        <el-input-number v-model="chatWidget.width" :min="320" :max="600" :step="10" />
                                    </el-form-item>
                                    <el-form-item label="窗口高度 (px)">
                                        <el-input-number v-model="chatWidget.height" :min="400" :max="800" :step="10" />
                                    </el-form-item>
                                    <el-form-item label="浮动按钮位置">
                                        <el-radio-group v-model="chatWidget.position">
                                            <el-radio value="right">右下角</el-radio>
                                            <el-radio value="left">左下角</el-radio>
                                        </el-radio-group>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" @click="saveChatWidgetConfig" :loading="savingChatWidget">保存设置</el-button>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                            <el-card shadow="never" style="margin-top:16px">
                                <template #header><span>语音识别配置</span></template>
                                <el-form label-width="180px" size="small">
                                    <el-form-item label="语音识别服务商">
                                        <el-select v-model="asrConfig.provider" style="width:100%">
                                            <el-option label="模拟模式（测试用）" value="mock" />
                                            <el-option label="OpenAI Whisper" value="openai" />
                                            <el-option label="阿里云语音识别" value="aliyun" />
                                            <el-option label="腾讯云语音识别" value="tencent" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item v-if="asrConfig.provider === 'openai'" label="OpenAI API Key">
                                        <el-input v-model="asrConfig.openai_key" type="password" placeholder="sk-..." />
                                    </el-form-item>
                                    <template v-if="asrConfig.provider === 'aliyun'">
                                        <el-form-item label="AppKey">
                                            <el-input v-model="asrConfig.aliyun_app_key" placeholder="请输入 AppKey" />
                                        </el-form-item>
                                        <el-form-item label="AccessKey ID">
                                            <el-input v-model="asrConfig.aliyun_access_key" placeholder="请输入 AccessKey ID" />
                                        </el-form-item>
                                        <el-form-item label="AccessKey Secret">
                                            <el-input v-model="asrConfig.aliyun_access_secret" type="password" placeholder="请输入 Secret" />
                                        </el-form-item>
                                    </template>
                                    <template v-if="asrConfig.provider === 'tencent'">
                                        <el-form-item label="SecretId">
                                            <el-input v-model="asrConfig.tencent_secret_id" placeholder="请输入 SecretId" />
                                        </el-form-item>
                                        <el-form-item label="SecretKey">
                                            <el-input v-model="asrConfig.tencent_secret_key" type="password" placeholder="请输入 SecretKey" />
                                        </el-form-item>
                                    </template>
                                    <el-form-item>
                                        <el-button type="primary" @click="saveAsrConfig" :loading="savingAsr">保存配置</el-button>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                            <div style="margin-top:12px">
                                <el-button @click="$router.push('/settings')">系统设置 →</el-button>
                            </div>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <el-tab-pane label="💬 在线客服" name="liveChat">
                <LiveChatAdmin />
            </el-tab-pane>

            <el-tab-pane label="👨‍💼 客服工作台" name="agentWorkspace">
                <AgentWorkspace />
            </el-tab-pane>

            <el-tab-pane label="❓ FAQ 管理" name="faq">
                <div class="tab-content">
                    <div class="tab-summary">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-500">管理 IM 客服中的常见问题列表</p>
                            <div class="flex gap-2">
                                <el-button type="success" @click="openFaqAddDialog">
                                    ➕ 新增 FAQ
                                </el-button>
                                <el-button type="primary" @click="openFaqAdmin">
                                    打开 FAQ 管理页面 →
                                </el-button>
                            </div>
                        </div>
                        <div class="mt-2 border rounded-lg overflow-hidden" style="height:560px">
                            <iframe ref="faqIframe" src="/admin/chat-faqs" class="w-full h-full border-0"></iframe>
                        </div>
                    </div>
                </div>

                <!-- 新增 FAQ 对话框 -->
                <el-dialog v-model="faqDialog.visible" title="新增 FAQ" width="500px" :close-on-click-modal="false">
                    <el-form :model="faqDialog.form" label-position="top" @submit.prevent="submitFaq">
                        <el-form-item label="问题" required>
                            <el-input v-model="faqDialog.form.question" placeholder="请输入常见问题" maxlength="200" />
                        </el-form-item>
                        <el-form-item label="答案">
                            <el-input v-model="faqDialog.form.answer" type="textarea" :rows="3" placeholder="请输入答案" maxlength="500" />
                        </el-form-item>
                        <el-form-item label="图标（Emoji）">
                            <el-input v-model="faqDialog.form.icon" placeholder="💬" maxlength="10" />
                        </el-form-item>
                        <el-form-item>
                            <el-switch v-model="faqDialog.form.is_active" active-text="启用" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="faqDialog.visible = false">取消</el-button>
                        <el-button type="primary" @click="submitFaq" :loading="faqDialog.submitting">保存</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Promotion, Search, Refresh, UserFilled, MagicStick } from '@element-plus/icons-vue';
import apiClient from '@/utils/request';
import AgentWorkspace from './AgentWorkspace.vue';
import LiveChatAdmin from '../live-chat/Index.vue';

const route = useRoute();
const activeTab = ref('ai-chat');
const faqIframe = ref(null);

const faqDialog = reactive({
    visible: false,
    submitting: false,
    form: {
        question: '',
        answer: '',
        icon: '💬',
        is_active: true,
    },
});

function openFaqAdmin() {
    window.open('/admin/chat-faqs', '_blank');
}

function openFaqAddDialog() {
    faqDialog.form = { question: '', answer: '', icon: '💬', is_active: true };
    faqDialog.visible = true;
}

async function submitFaq() {
    if (!faqDialog.form.question.trim()) {
        ElMessage.warning('请输入问题');
        return;
    }
    faqDialog.submitting = true;
    try {
        const { data } = await apiClient.post('/chat-faqs', faqDialog.form);
        if (data.success) {
            ElMessage.success('FAQ 已添加');
            faqDialog.visible = false;
            // Refresh iframe
            if (faqIframe.value) {
                faqIframe.value.src = '/admin/chat-faqs?' + Date.now();
            }
        } else {
            ElMessage.error(data.errors?.question?.[0] || data.errors?.answer?.[0] || '添加失败');
        }
    } catch {
        ElMessage.error('网络错误');
    } finally {
        faqDialog.submitting = false;
    }
}

const aiStats = reactive({ total_conversations: 0, total_messages: 0, satisfaction_rate: 0, helpful_count: 0, unhelpful_count: 0 });
const ragStats = reactive({ total_documents: 0, total_conversations: 0, total_messages: 0, documents_by_source: {} });
const handoffConfig = reactive({ confidence_threshold: 0.35, timeout_seconds: 120, escalate_intents: ['refund_request', 'complaint'] });

const satisfactionClass = computed(() => {
    if (aiStats.satisfaction_rate >= 80) return 'text-success';
    if (aiStats.satisfaction_rate >= 60) return 'text-warning';
    return 'text-danger';
});

// AI 子标签
const aiSubTab = ref('chat');
const aiChatInput = ref('');
const aiMessages = ref([]);
const sendingAiChat = ref(false);
const sessionId = ref('session-' + Date.now());
const intents = ref([]);
const loadingIntents = ref(false);
const chatContainer = ref(null);
const rebuildingRag = ref(false);
const ragQuery = ref('');
const ragResults = ref([]);
const searchingRag = ref(false);
const ragSearched = ref(false);

const config = reactive({
    aiAutoReply: true,
    autoHandoffLevel: 5,
});

const chatWidget = reactive({
    width: 440,
    height: 640,
    position: 'right',
});
const savingChatWidget = ref(false);
const asrConfig = reactive({
    provider: 'mock',
    openai_key: '',
    aliyun_app_key: '',
    aliyun_access_key: '',
    aliyun_access_secret: '',
    tencent_secret_id: '',
    tencent_secret_key: '',
});
const savingAsr = ref(false);

async function saveChatWidgetConfig() {
    savingChatWidget.value = true;
    try {
        await apiClient.post('/settings', {
            settings: [
                { key: 'chat_widget_width', value: String(chatWidget.width) },
                { key: 'chat_widget_height', value: String(chatWidget.height) },
                { key: 'chat_widget_position', value: chatWidget.position },
            ],
        });
        ElMessage.success('聊天窗口设置已保存');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        savingChatWidget.value = false;
    }
}

async function loadChatWidgetConfig() {
    try {
        const res = await apiClient.get('/settings/public');
        const data = res.data?.data || {};
        chatWidget.width = parseInt(data.chat_widget_width) || 440;
        chatWidget.height = parseInt(data.chat_widget_height) || 640;
        chatWidget.position = data.chat_widget_position || 'right';
    } catch { /* ignore */ }
}

async function saveAsrConfig() {
    savingAsr.value = true;
    try {
        const settings = [
            { key: 'asr_provider', value: asrConfig.provider },
        ];
        if (asrConfig.provider === 'openai') settings.push({ key: 'asr_openai_key', value: asrConfig.openai_key });
        if (asrConfig.provider === 'aliyun') {
            settings.push({ key: 'asr_aliyun_app_key', value: asrConfig.aliyun_app_key });
            settings.push({ key: 'asr_aliyun_access_key', value: asrConfig.aliyun_access_key });
            settings.push({ key: 'asr_aliyun_access_secret', value: asrConfig.aliyun_access_secret });
        }
        if (asrConfig.provider === 'tencent') {
            settings.push({ key: 'asr_tencent_secret_id', value: asrConfig.tencent_secret_id });
            settings.push({ key: 'asr_tencent_secret_key', value: asrConfig.tencent_secret_key });
        }
        await apiClient.post('/settings', { settings });
        ElMessage.success('语音识别配置已保存');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        savingAsr.value = false;
    }
}

async function loadAsrConfig() {
    try {
        const res = await apiClient.get('/settings/all');
        const items = res.data?.data || [];
        const map = {};
        items.forEach(item => { map[item.key] = item.value; });
        asrConfig.provider = map.asr_provider || 'mock';
        asrConfig.openai_key = map.asr_openai_key || '';
        asrConfig.aliyun_app_key = map.asr_aliyun_app_key || '';
        asrConfig.aliyun_access_key = map.asr_aliyun_access_key || '';
        asrConfig.aliyun_access_secret = map.asr_aliyun_access_secret || '';
        asrConfig.tencent_secret_id = map.asr_tencent_secret_id || '';
        asrConfig.tencent_secret_key = map.asr_tencent_secret_key || '';
    } catch { /* ignore */ }
}

async function loadStats() {
    try {
        const [chatRes, ragRes, intentRes] = await Promise.allSettled([
            apiClient.get('/chat/stats'),
            apiClient.get('/rag/stats'),
            apiClient.get('/chat/intents'),
        ]);
        if (chatRes.status === 'fulfilled' && chatRes.value.data?.success) {
            Object.assign(aiStats, chatRes.value.data.data || {});
        }
        if (ragRes.status === 'fulfilled') {
            Object.assign(ragStats, ragRes.value.data?.data || {});
        }
        if (intentRes.status === 'fulfilled') {
            intents.value = intentRes.value.data?.data || [];
        }
    } catch { /* ignore */ }
}

// ── AI 对话测试 ──
function resetAiChat() {
    sessionId.value = 'session-' + Date.now();
    aiMessages.value = [];
}

async function sendAiChat() {
    const message = aiChatInput.value.trim();
    if (!message) return;
    aiMessages.value.push({ role: 'user', content: message });
    aiChatInput.value = '';
    sendingAiChat.value = true;
    try {
        const res = await apiClient.post('/chat/send', {
            session_id: sessionId.value,
            message,
            save_conversation: false,
        });
        const data = res.data?.data || res.data || {};
        aiMessages.value.push({
            role: 'assistant',
            content: data.answer || data.reply || data.content || '未收到回复',
            sources: data.sources || [],
        });
        await nextTick();
        if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    } catch {
        aiMessages.value.push({ role: 'assistant', content: '抱歉，AI 回复失败，请稍后重试。' });
    } finally {
        sendingAiChat.value = false;
    }
}

// ── RAG 知识库 ──
function ragSourcePercent(source) {
    const total = ragStats.total_documents || 1;
    const count = ragStats.documents_by_source[source] || 0;
    return Math.round((count / total) * 100);
}

async function rebuildRagIndex() {
    rebuildingRag.value = true;
    try {
        await apiClient.post('/rag/rebuild');
        ElMessage.success('索引重建已触发');
        const ragRes = await apiClient.get('/rag/stats');
        Object.assign(ragStats, ragRes.data?.data || {});
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '重建失败');
    } finally {
        rebuildingRag.value = false;
    }
}

async function searchRag() {
    const q = ragQuery.value.trim();
    if (!q) return;
    searchingRag.value = true;
    ragSearched.value = true;
    try {
        const res = await apiClient.post('/rag/retrieve', { query: q });
        ragResults.value = res.data?.data || [];
    } catch {
        ragResults.value = [];
    } finally {
        searchingRag.value = false;
    }
}

// ── Handoff 配置 ──
async function loadHandoffConfig() {
    try {
        const { data: res } = await apiClient.get('/chat/handoff-config');
        Object.assign(handoffConfig, res.data || {});
    } catch { /* use defaults */ }
}

async function saveHandoffConfig() {
    try {
        await apiClient.post('/chat/handoff-config', handoffConfig);
        ElMessage.success('Handoff 配置已保存');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    }
}

onMounted(() => {
    if (route.query.tab) {
        activeTab.value = String(route.query.tab);
    }
    loadStats();
    loadChatWidgetConfig();
    loadAsrConfig();
    loadHandoffConfig();
});
</script>

<style scoped>
.im-center { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.im-tabs { min-height: 400px; }
.tab-content { padding: 10px 0; }
.tab-actions { margin-top: 20px; text-align: center; }
.stat-item { text-align: center; padding: 10px; }
.stat-value { font-size: 32px; font-weight: bold; color: #409EFF; }
.stat-label { font-size: 14px; color: #909399; margin-top: 8px; }
.integration-card { cursor: pointer; text-align: center; padding: 10px; transition: all .3s; }
.integration-card:hover { border-color: #409EFF; transform: translateY(-2px); }
.integration-icon { font-size: 36px; margin-bottom: 8px; }
.integration-name { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
.integration-desc { font-size: 12px; color: #909399; margin-bottom: 10px; }
.config-hint { font-size: 12px; color: #909399; margin-left: 10px; }

/* AI 子标签页 */
.ai-sub-tabs { margin-top: 16px; }
.ai-sub-tabs :deep(.el-tabs__item) { font-size: 13px; }
.chat-card .chat-messages { height: 360px; overflow-y: auto; padding: 10px; }
.chat-card .chat-msg { display: flex; gap: 10px; margin-bottom: 16px; }
.chat-card .chat-msg.assistant { flex-direction: row; }
.chat-card .chat-msg.user { flex-direction: row-reverse; }
.chat-card .msg-content { max-width: 70%; }
.chat-card .msg-text { padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.5; }
.chat-card .chat-msg.user .msg-text { background: #409eff; color: #fff; border-bottom-right-radius: 4px; }
.chat-card .chat-msg.assistant .msg-text { background: #f0f0f0; color: #303133; border-bottom-left-radius: 4px; }
.chat-card .msg-sources { margin-top: 4px; }
.chat-card .chat-empty { text-align: center; padding: 80px 0; }
.chat-card .chat-input { border-top: 1px solid #ebeef5; padding-top: 10px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.intent-list { max-height: 400px; overflow-y: auto; }
.intent-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.intent-item:last-child { border-bottom: none; }
.intent-name { margin-bottom: 4px; }
.intent-desc { font-size: 12px; color: #909399; margin: 4px 0; }
.intent-examples { display: flex; flex-wrap: wrap; gap: 4px; }
.example-tag { font-size: 11px; background: #f5f7fa; padding: 2px 8px; border-radius: 10px; color: #606266; }
.rag-stats { padding: 10px 0; }
.rag-stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f7fa; }
.rag-stat-label { font-size: 13px; color: #606266; }
.rag-stat-value { font-size: 14px; font-weight: 600; color: #303133; }
.subsection-title { font-size: 14px; font-weight: 600; margin: 12px 0 8px; }
.source-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.source-row .el-progress { flex: 1; }
.rag-test { min-height: 200px; }
.rag-results { margin-top: 12px; }
.rag-result-item { display: flex; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.result-score { flex-shrink: 0; }
.result-title { font-size: 13px; font-weight: 500; margin-bottom: 2px; }
.result-snippet { font-size: 12px; color: #909399; line-height: 1.4; }
.handoff-form { max-width: 500px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
</style>
