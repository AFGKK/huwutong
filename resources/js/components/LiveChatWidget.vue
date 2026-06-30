<template>
  <div class="live-chat-widget" v-if="visible">
    <!-- 浮动按钮 -->
    <div v-if="!opened" class="chat-fab" @click="openChat" :style="{ background: primaryColor }">
      <el-icon :size="24"><ChatDotSquare /></el-icon>
      <span v-if="unreadCount" class="unread-badge">{{ unreadCount }}</span>
    </div>

    <!-- 聊天窗口 -->
    <div v-else class="chat-window">
      <div class="chat-header" :style="{ background: primaryColor }">
        <span>{{ title }}</span>
        <div class="header-actions">
          <el-button circle size="small" text @click="minimize" style="color:#fff">
            <el-icon><Minus /></el-icon>
          </el-button>
          <el-button circle size="small" text @click="closeChat" style="color:#fff">
            <el-icon><Close /></el-icon>
          </el-button>
        </div>
      </div>

      <div class="chat-messages" ref="msgContainer">
        <div v-if="!conversationId" class="chat-greeting">
          <p>{{ greeting }}</p>
        </div>
        <div
          v-for="msg in messages"
          :key="msg.id"
          class="chat-msg"
          :class="msg.sender_type === 'user' ? 'msg-user' : 'msg-other'"
        >
          <div class="msg-content">{{ msg.content }}</div>
          <div class="msg-time">{{ formatTime(msg.sent_at) }}</div>
        </div>
        <div v-if="aiTyping" class="chat-msg msg-other">
          <div class="msg-content typing-indicator"><span>.</span><span>.</span><span>.</span></div>
        </div>
      </div>

      <div class="chat-input">
        <el-input
          v-model="inputText"
          :placeholder="placeholder"
          :disabled="loading"
          @keyup.enter="handleSend"
        >
          <template #append>
            <el-button @click="handleSend" :disabled="!inputText.trim() || loading">
              <el-icon><Promotion /></el-icon>
            </el-button>
          </template>
        </el-input>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import { ChatDotSquare, Minus, Close, Promotion } from '@element-plus/icons-vue';
import liveChatApi from '@/api/liveChat';

const props = defineProps({
  visible: { type: Boolean, default: true },
  title: { type: String, default: '在线客服' },
  primaryColor: { type: String, default: '#409EFF' },
  placeholder: { type: String, default: '请输入您的问题...' },
  greeting: { type: String, default: '您好！👋 请问有什么可以帮助您的？' },
});

const opened = ref(false);
const minimized = ref(false);
const conversationId = ref(null);
const messages = ref([]);
const inputText = ref('');
const loading = ref(false);
const aiTyping = ref(false);
const unreadCount = ref(0);
const msgContainer = ref(null);

async function openChat() {
  opened.value = true;
  unreadCount.value = 0;
  if (!conversationId.value) {
    try {
      const res = await liveChatApi.createConversation({ source: 'portal' });
      conversationId.value = res.data?.id;
    } catch { /* ignore */ }
  }
}

function minimize() {
  opened.value = false;
}

function closeChat() {
  opened.value = false;
  if (conversationId.value) {
    liveChatApi.closeConversation(conversationId.value).catch(() => {});
    conversationId.value = null;
    messages.value = [];
  }
}

async function handleSend() {
  const text = inputText.value.trim();
  if (!text || !conversationId.value) return;

  inputText.value = '';
  loading.value = true;

  // 立即显示用户消息
  messages.value.push({
    id: Date.now(),
    sender_type: 'user',
    content: text,
    sent_at: new Date().toISOString(),
  });

  aiTyping.value = true;
  scrollToBottom();

  try {
    const res = await liveChatApi.sendMessage(conversationId.value, text);
    aiTyping.value = false;
    if (res.data?.reply) {
      messages.value.push(res.data.reply);
    }
    if (res.data?.handoff) {
      messages.value.push({
        id: Date.now() + 1,
        sender_type: 'ai',
        content: '正在为您转接人工客服，请稍候...',
        sent_at: new Date().toISOString(),
      });
    }
  } catch {
    aiTyping.value = false;
    messages.value.push({
      id: Date.now() + 1,
      sender_type: 'ai',
      content: '消息发送失败，请稍后重试',
      sent_at: new Date().toISOString(),
    });
  } finally {
    loading.value = false;
    scrollToBottom();
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (msgContainer.value) {
      msgContainer.value.scrollTop = msgContainer.value.scrollHeight;
    }
  });
}

function formatTime(t) {
  if (!t) return '';
  return new Date(t).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.live-chat-widget { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.chat-fab {
  position: fixed; bottom: 24px; right: 24px; width: 56px; height: 56px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; cursor: pointer;
  color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,.15); z-index: 9999;
}
.unread-badge {
  position: absolute; top: -4px; right: -4px; background: #F56C6C;
  color: #fff; border-radius: 50%; width: 20px; height: 20px;
  display: flex; align-items: center; justify-content: center; font-size: 11px;
}
.chat-window {
  position: fixed; bottom: 24px; right: 24px; width: 360px; height: 520px;
  background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.15);
  display: flex; flex-direction: column; overflow: hidden; z-index: 9999;
}
.chat-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px; color: #fff; font-weight: 600;
}
.header-actions { display: flex; gap: 4px; }
.chat-messages { flex: 1; overflow-y: auto; padding: 16px; background: #f5f7fa; }
.chat-greeting { text-align: center; padding: 20px; color: #909399; }
.chat-msg { margin-bottom: 12px; max-width: 80%; }
.msg-user { margin-left: auto; }
.msg-other { margin-right: auto; }
.msg-content {
  padding: 8px 12px; border-radius: 8px; font-size: 14px; line-height: 1.5;
  word-break: break-word;
}
.msg-user .msg-content { background: #409EFF; color: #fff; border-radius: 8px 8px 0 8px; }
.msg-other .msg-content { background: #fff; color: #303133; border-radius: 8px 8px 8px 0; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
.msg-time { font-size: 11px; color: #C0C4CC; margin-top: 2px; }
.msg-user .msg-time { text-align: right; }
.chat-input { padding: 8px 12px; border-top: 1px solid #ebeef5; background: #fff; }
.typing-indicator { display: flex; gap: 4px; }
.typing-indicator span {
  animation: blink 1.4s infinite both;
  font-size: 24px; line-height: 1; color: #909399;
}
.typing-indicator span:nth-child(2) { animation-delay: .2s; }
.typing-indicator span:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,80%,100% { opacity: 0; } 40% { opacity: 1; } }
</style>
