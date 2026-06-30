<template>
    <div class="live-chat-widget" :style="widgetStyle">
        <!-- Chat Button -->
        <div v-if="!isOpen" class="chat-button" @click="openChat">
            <el-badge :value="unreadCount" :hidden="unreadCount === 0">
                <div class="chat-icon">
                    <el-icon :size="24"><ChatDotRound /></el-icon>
                </div>
            </el-badge>
        </div>

        <!-- Chat Window -->
        <div v-else class="chat-window">
            <div class="chat-header">
                <div class="header-info">
                    <span class="header-title">🤖 互物通智能客服</span>
                    <span class="header-status">{{ online ? '在线' : '离线' }}</span>
                </div>
                <div class="header-actions">
                    <el-button text @click="minimizeChat" size="small">
                        <el-icon><Minus /></el-icon>
                    </el-button>
                    <el-button text @click="closeChat" size="small">
                        <el-icon><Close /></el-icon>
                    </el-button>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" ref="messagesRef">
                <div v-if="messages.length === 0" class="welcome-message">
                    <div class="welcome-avatar">🤖</div>
                    <p>您好！我是互物通智能客服助手，请问有什么可以帮您？</p>
                    <div class="suggestions">
                        <el-tag v-for="s in suggestions" :key="s" class="suggestion-tag"
                            @click="sendMessage(s)" effect="plain">
                            {{ s }}
                        </el-tag>
                    </div>
                </div>

                <div v-for="(msg, i) in messages" :key="i"
                    :class="['message', msg.role === 'user' ? 'user' : msg.role === 'agent' ? 'agent' : 'assistant']">
                    <div v-if="msg.role === 'assistant' || msg.role === 'agent'" class="msg-avatar">{{ msg.role === 'agent' ? '👤' : '🤖' }}</div>
                    <div class="msg-content">
                        <div class="msg-text" v-html="renderMarkdown(msg.content)"></div>
                        <div v-if="msg.sources && msg.sources.length > 0" class="msg-sources">
                            <el-tag v-for="(src, si) in msg.sources" :key="si" size="small" class="source-tag">
                                {{ src.title }}
                            </el-tag>
                        </div>
                    </div>
                    <div v-if="msg.role === 'user'" class="user-avatar">
                        <el-icon :size="18"><User /></el-icon>
                    </div>
                </div>

                <!-- Handoff notice (separate from messages) -->
                <div v-if="handoffNotice" class="handoff-notice">
                    <template v-if="handoffNotice.handoff_id">
                        ⏳ 正在为您转接人工客服，队列位置 #{{ handoffNotice.queue_position || '—' }}
                        <el-button size="small" type="primary" class="ml-2" @click="checkHandoffStatus(handoffNotice.handoff_id)">
                            查看状态
                        </el-button>
                    </template>
                    <template v-else>
                        ⏳ 已提交工单，客服将尽快联系您
                    </template>
                </div>

                <div v-if="isLoading" class="message assistant">
                    <div class="msg-avatar">🤖</div>
                    <div class="msg-content">
                        <div class="typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="chat-input">
                <el-input
                    v-model="inputMessage"
                    placeholder="输入您的问题..."
                    :disabled="isLoading"
                    @keyup.enter="handleSend"
                    clearable
                >
                    <template #append>
                        <el-button @click="handleSend" :disabled="!inputMessage.trim() || isLoading"
                            type="primary">
                            <el-icon><Promotion /></el-icon>
                        </el-button>
                    </template>
                </el-input>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import { ChatDotRound, Minus, Close, User, Promotion, Message } from '@element-plus/icons-vue';
import axios from 'axios';
import handoffApi from '@/api/handoff';
import liveChatApi from '@/api/liveChat';

const props = defineProps({
    apiBaseUrl: { type: String, default: '/api' },
});

const chatPosition = ref('right');
const chatBottom = ref(24);

const widgetStyle = computed(() => {
    const pos = chatPosition.value;
    const bottom = `${chatBottom.value}px`;
    if (pos === 'left') return { left: '24px', right: 'auto', bottom };
    return { right: '24px', left: 'auto', bottom };
});

const isOpen = ref(false);
const minimized = ref(false);
const online = ref(true);
const isLoading = ref(false);
const unreadCount = ref(0);
const inputMessage = ref('');
const messages = ref([]);
const conversationId = ref(null);
const handoffNotice = ref(null);
const messagesRef = ref(null);

const suggestions = [
    '如何激活 License？',
    'License 已过期怎么办？',
    '设备数量超出限制',
    '转人工客服',
];

function renderMarkdown(text) {
    if (!text) return '';
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/`(.*?)`/g, '<code>$1</code>')
        .replace(/\n/g, '<br>');
}

async function handleSend() {
    const msg = inputMessage.value.trim();
    if (!msg || isLoading.value) return;

    inputMessage.value = '';
    messages.value.push({ role: 'user', content: msg });
    isLoading.value = true;
    scrollToBottom();

    try {
        if (!conversationId.value) {
            const res = await liveChatApi.createConversation({ source: 'widget' });
            conversationId.value = res.data?.id || res.data?.data?.id;
        }

        const res = await liveChatApi.sendMessage(conversationId.value, msg);
        const data = res.data?.data || res.data;

        if (data?.reply) {
            messages.value.push({
                role: 'assistant',
                content: data.reply.content || data.reply,
                sources: data.reply.sources || [],
            });
        }

        if (data?.handoff) {
            handoffNotice.value = {
                handoff_id: data.handoff.id || data.handoff,
                queue_position: data.handoff.queue_position || null,
            };
            messages.value.push({
                role: 'assistant',
                content: '⏳ 正在为您转接人工客服，请稍候...',
            });
        }
    } catch {
        messages.value.push({
            role: 'assistant',
            content: '抱歉，我现在无法连接到服务器。请稍后再试。',
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
}

async function sendMessage(text) {
    inputMessage.value = text;
    await handleSend();
}

// ─── Handoff 状态查询 ───
const handoffPolling = ref(null);
const activeHandoffId = ref(null);
let handoffEchoChannel = null;

function checkHandoffStatus(handoffId) {
    activeHandoffId.value = handoffId;
    if (subscribeHandoffEcho(handoffId)) return;
    pollHandoffStatus(handoffId);
}

function subscribeHandoffEcho(handoffId) {
    if (typeof window.Echo === 'undefined') return false;
    unsubscribeHandoffEcho();

    handoffEchoChannel = window.Echo.private(`handoff.${handoffId}`);
    handoffEchoChannel.listen('.handoff.message', (payload) => {
        if (payload.sender_type === 'customer') return;
        const exists = messages.value.some(m => m.msg_id === `echo_${payload.handoff_id}_${payload.timestamp}`);
        if (exists) return;

        if (payload.sender_type === 'system' && payload.message?.includes('已接入')) {
            messages.value.push({ role: 'agent', content: payload.message, sender_type: 'system', msg_id: `echo_sys_${Date.now()}` });
        } else {
            messages.value.push({ role: 'agent', msg_id: `echo_${payload.handoff_id}_${payload.timestamp}`, content: payload.message, sender_type: payload.sender_type });
        }

        if (['resolved', 'closed'].includes(payload.status)) {
            messages.value.push({ role: 'agent', content: '本次客服对话已结束', sender_type: 'system', msg_id: `echo_close_${Date.now()}` });
            unsubscribeHandoffEcho();
        }
        scrollToBottom();
    });
    return true;
}

function unsubscribeHandoffEcho() {
    if (handoffEchoChannel) {
        handoffEchoChannel.stopListening('.handoff.message');
        handoffEchoChannel.unsubscribe();
        handoffEchoChannel = null;
    }
}

async function pollHandoffStatus(handoffId) {
    try {
        const { data: res } = await handoffApi.status(handoffId);
        if (res.success) {
            const handoff = res.data;
            if (handoff.messages?.length > 0) {
                for (const msg of handoff.messages) {
                    const exists = messages.value.some(m => m.msg_id === msg.id);
                    if (!exists && msg.sender_type !== 'customer') {
                        messages.value.push({ role: 'agent', msg_id: msg.id, content: msg.content, sender_type: msg.sender_type });
                    }
                }
            }
            if (handoff.status === 'in_progress' && handoff.assignee) {
                messages.value.push({ role: 'agent', content: `客服 ${handoff.assignee.name} 已接入您的对话`, sender_type: 'system', msg_id: 'system_' + Date.now() });
            }
            if (['resolved', 'closed'].includes(handoff.status)) {
                messages.value.push({ role: 'agent', content: '本次客服对话已结束', sender_type: 'system', msg_id: 'system_close_' + Date.now() });
                stopHandoffPolling();
                return;
            }
            scrollToBottom();
        }
    } catch { /* ignore */ }
}

function startHandoffPolling(handoffId) {
    activeHandoffId.value = handoffId;
    stopHandoffPolling();
    if (!subscribeHandoffEcho(handoffId)) {
        handoffPolling.value = setInterval(() => pollHandoffStatus(handoffId), 5000);
    }
}

function stopHandoffPolling() {
    if (handoffPolling.value) {
        clearInterval(handoffPolling.value);
        handoffPolling.value = null;
    }
    unsubscribeHandoffEcho();
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesRef.value) {
            messagesRef.value.scrollTop = messagesRef.value.scrollHeight;
        }
    });
}

function openChat() {
    isOpen.value = true;
    unreadCount.value = 0;
}

function minimizeChat() {
    isOpen.value = false;
}

function closeChat() {
    isOpen.value = false;
    stopHandoffPolling();
    unreadCount.value = 0;
    if (conversationId.value) {
        liveChatApi.closeConversation(conversationId.value).catch(() => {});
        conversationId.value = null;
        messages.value = [];
        handoffNotice.value = null;
    }
}

onMounted(async () => {
    try {
        const res = await axios.get('/api/settings/public');
        if (res.data?.success) {
            const data = res.data.data || {};
            chatPosition.value = data.chat_widget_position || 'right';
            chatBottom.value = parseInt(data.chat_widget_bottom) || 24;
        }
    } catch {}
});

onUnmounted(() => {
    stopHandoffPolling();
});
</script>

<style scoped>
.live-chat-widget {
    position: fixed;
    bottom: 24px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.chat-button {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #409eff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(64, 158, 255, 0.4);
    transition: transform 0.2s;
}

.chat-button:hover {
    transform: scale(1.1);
}

.chat-icon {
    color: white;
}

.chat-window {
    width: 440px;
    height: 640px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-header {
    background: linear-gradient(135deg, #409eff, #337ecc);
    color: white;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.header-title {
    font-weight: 600;
    font-size: 14px;
}

.header-status {
    font-size: 11px;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 10px;
}

.header-actions {
    display: flex;
    gap: 4px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #f5f7fa;
}

.welcome-message {
    text-align: center;
    padding: 24px 16px;
}

.welcome-avatar {
    font-size: 48px;
    margin-bottom: 12px;
}

.welcome-message p {
    color: #606266;
    font-size: 14px;
    margin-bottom: 16px;
}

.suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
}

.suggestion-tag {
    cursor: pointer;
    font-size: 12px;
}

.message {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.message.user {
    justify-content: flex-end;
}

.msg-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #409eff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.msg-content {
    max-width: 75%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.6;
}

.message.assistant .msg-content {
    background: white;
    color: #303133;
    border: 1px solid #e4e7ed;
}

.message.user .msg-content {
    background: #409eff;
    color: white;
}

.msg-text :deep(code) {
    background: rgba(0, 0, 0, 0.06);
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 12px;
}

.msg-text :deep(br) {
    display: block;
    margin: 4px 0;
}

.msg-sources {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.source-tag {
    font-size: 10px;
}

.escalated-notice {
    margin-top: 8px;
    font-size: 12px;
    color: #e6a23c;
    background: #fdf6ec;
    padding: 4px 8px;
    border-radius: 4px;
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 4px 0;
}

.typing-indicator span {
    width: 6px;
    height: 6px;
    background: #909399;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

.handoff-notice {
    text-align: center;
    padding: 12px;
    background: #fff3e0;
    border-radius: 8px;
    margin-bottom: 12px;
    font-size: 13px;
    color: #e65100;
}

.handoff-notice .ml-2 {
    margin-left: 8px;
}

.message.agent .msg-content {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #c8e6c9;
}

.msg-label {
    font-size: 10px;
    color: #999;
    margin-top: 4px;
}

@keyframes typing {
    0%, 80%, 100% { opacity: 0; }
    40% { opacity: 1; }
}

@keyframes typing {
    0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-4px); }
}

.chat-input {
    padding: 12px;
    border-top: 1px solid #e4e7ed;
    background: white;
}

.chat-input :deep(.el-input-group__append) {
    padding: 0;
}
</style>
