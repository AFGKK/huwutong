<template>
    <div class="handoff-page">
        <div class="page-header">
            <div class="header-left">
                <h2>客服转接中心</h2>
                <span class="header-subtitle">AI 转人工客服队列管理</span>
            </div>
            <div class="header-actions">
                <el-select v-model="agentStatus" style="width: 130px" @change="handleStatusChange">
                    <el-option label="🟢 在线" value="online" />
                    <el-option label="🟡 忙碌" value="busy" />
                    <el-option label="🟠 离开" value="away" />
                    <el-option label="🔴 离线" value="offline" />
                </el-select>
                <span class="agent-id">#{{ currentAgent }}</span>
            </div>
        </div>

        <!-- Queue Stats -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" style="color: #e6a23c">{{ queueStats.total_queued }}</div>
                        <div class="stat-lbl">排队中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" style="color: #f56c6c">{{ queueStats.urgent_queued }}</div>
                        <div class="stat-lbl">紧急队列</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" style="color: #409eff">{{ queueStats.avg_wait_formatted }}</div>
                        <div class="stat-lbl">平均等待</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-box">
                        <div class="stat-num" style="color: #67c23a">{{ queueStats.online_agents }}</div>
                        <div class="stat-lbl">在线客服</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- My Conversations -->
            <el-col :span="14">
                <el-card shadow="never" class="conversations-card">
                    <template #header>
                        <div class="card-header">
                            <span>我的对话 ({{ myConversations.length }})</span>
                            <el-button text size="small" @click="refreshAll">刷新</el-button>
                        </div>
                    </template>

                    <div v-if="loading" v-loading="loading" class="empty-state">加载中...</div>
                    <div v-else-if="myConversations.length === 0" class="empty-state">
                        <el-icon :size="40" color="#dcdfe6"><ChatDotSquare /></el-icon>
                        <p>暂无对话，等待客户转接...</p>
                    </div>

                    <div v-for="conv in myConversations" :key="conv.id"
                        class="conversation-item"
                        :class="{ active: activeConversation?.id === conv.id }"
                        @click="selectConversation(conv)">
                        <div class="conv-header">
                            <strong>{{ conv.customer?.user?.name || '未知客户' }}</strong>
                            <el-tag :type="conv.status === 'in_progress' ? 'success' : 'warning'" size="small">
                                {{ conv.status === 'in_progress' ? '进行中' : '待接受' }}
                            </el-tag>
                        </div>
                        <div class="conv-meta">
                            <span>{{ conv.reasonLabel }}</span>
                            <span>{{ formatTime(conv.created_at) }}</span>
                        </div>
                        <div class="conv-preview" v-if="conv.messages?.length">
                            {{ conv.messages[conv.messages.length - 1]?.content?.substring(0, 80) }}...
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- Chat Area -->
            <el-col :span="10">
                <el-card shadow="never" class="chat-card">
                    <template #header>
                        <div class="card-header" v-if="activeConversation">
                            <div>
                                <strong>{{ activeConversation.customer?.user?.name || '未知客户' }}</strong>
                                <el-tag size="small" class="ml-2">{{ activeConversation.reasonLabel }}</el-tag>
                            </div>
                            <div class="chat-actions">
                                <el-button size="small" @click="showTransferDialog = true">转交</el-button>
                                <el-button size="small" type="success" @click="handleClose" v-if="activeConversation.status === 'in_progress'">
                                    关闭
                                </el-button>
                                <el-button size="small" type="primary" @click="handleAccept" v-if="activeConversation.status === 'assigned' || activeConversation.status === 'queued'">
                                    接受
                                </el-button>
                            </div>
                        </div>
                        <div v-else>
                            <span>选择对话开始客服支持</span>
                        </div>
                    </template>

                    <div v-if="!activeConversation" class="empty-state">
                        <el-icon :size="40" color="#dcdfe6"><Message /></el-icon>
                        <p>从左侧选择一个对话</p>
                    </div>

                    <template v-else>
                        <div class="messages-container" ref="msgContainer">
                            <!-- Context notice -->
                            <div class="context-notice" v-if="activeConversation.conversation_context">
                                <el-collapse>
                                    <el-collapse-item title="💬 AI 对话上下文">
                                        <pre class="context-json">{{ JSON.stringify(activeConversation.conversation_context, null, 2) }}</pre>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>

                            <div v-for="msg in messages" :key="msg.id" class="message"
                                :class="'msg-' + msg.sender_type">
                                <div class="msg-sender">
                                    {{ msg.sender_type === 'agent' ? '客服' : msg.sender_type === 'customer' ? '客户' : '系统' }}
                                    <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div class="msg-bubble">{{ msg.content }}</div>
                            </div>
                        </div>

                        <div class="chat-input" v-if="activeConversation.status === 'in_progress'">
                            <el-input v-model="newMessage" type="textarea" :rows="2"
                                placeholder="输入回复内容..."
                                @keyup.enter.ctrl="handleSend" />
                            <el-button type="primary" @click="handleSend" :disabled="!newMessage.trim()" class="send-btn">
                                发送 (Ctrl+Enter)
                            </el-button>
                        </div>
                    </template>
                </el-card>
            </el-col>
        </el-row>

        <!-- Transfer Dialog -->
        <el-dialog v-model="showTransferDialog" title="转交对话" width="400px">
            <el-form>
                <el-form-item label="目标客服">
                    <el-select v-model="transferAgentId" filterable style="width: 100%">
                        <el-option v-for="a in onlineAgents" :key="a.id"
                            :label="a.name + ' (' + a.agent_status + ')'" :value="a.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="transferNote" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showTransferDialog = false">取消</el-button>
                <el-button type="primary" @click="handleTransfer">确认转交</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { ChatDotSquare, Message } from '@element-plus/icons-vue';
import handoffApi from '@/api/handoff';

const loading = ref(false);
const activeConversation = ref(null);
const newMessage = ref('');
const showTransferDialog = ref(false);
const transferAgentId = ref(null);
const transferNote = ref('');
const msgContainer = ref(null);

const myConversations = ref([]);
const messages = ref([]);
const onlineAgentsList = ref([]);
const agentStatus = ref('online');
const currentAgent = ref(null);

const queueStats = reactive({
    total_queued: 0, urgent_queued: 0,
    avg_wait_formatted: '—',
    online_agents: 0,
});

const onlineAgents = computed(() =>
    onlineAgentsList.value.filter(a => a.id !== currentAgent.value)
);

function formatTime(t) {
    if (!t) return '';
    return new Date(t).toLocaleString('zh-CN');
}

async function refreshAll() {
    loading.value = true;
    try {
        const [convRes, queueRes, agentsRes] = await Promise.all([
            handoffApi.myConversations(),
            handoffApi.getQueueStats(),
            handoffApi.onlineAgents(),
        ]);
        if (convRes.data.success) myConversations.value = convRes.data.data || [];
        if (queueRes.data.success) Object.assign(queueStats, queueRes.data.data || {});
        if (agentsRes.data.success) onlineAgentsList.value = agentsRes.data.data || [];
    } catch {
        ElMessage.error('获取数据失败');
    } finally {
        loading.value = false;
    }
}

async function selectConversation(conv) {
    activeConversation.value = conv;
    try {
        const { data: res } = await handoffApi.show(conv.id);
        if (res.success) {
            activeConversation.value = res.data;
            messages.value = res.data.messages || [];
        }
    } catch {
        messages.value = conv.messages || [];
    }
    await nextTick();
    scrollToBottom();
}

async function handleAccept() {
    if (!activeConversation.value) return;
    try {
        const { data: res } = await handoffApi.accept(activeConversation.value.id);
        if (res.success) {
            ElMessage.success('已接受转接');
            activeConversation.value = res.data;
            messages.value = res.data.messages || [];
            await refreshAll();
            await nextTick();
            scrollToBottom();
        }
    } catch {
        ElMessage.error('接受失败');
    }
}

async function handleSend() {
    if (!newMessage.value.trim() || !activeConversation.value) return;
    const content = newMessage.value.trim();
    newMessage.value = '';
    try {
        const { data: res } = await handoffApi.agentSend(activeConversation.value.id, content);
        if (res.success) {
            messages.value.push(res.data);
            await nextTick();
            scrollToBottom();
        }
    } catch {
        ElMessage.error('发送失败');
        newMessage.value = content;
    }
}

async function handleClose() {
    if (!activeConversation.value) return;
    try {
        await ElMessageBox.confirm('确认关闭此对话？', '确认', { type: 'info' });
        const { data: res } = await handoffApi.close(activeConversation.value.id, '客服关闭对话');
        if (res.success) {
            ElMessage.success('对话已关闭');
            activeConversation.value = null;
            messages.value = [];
            await refreshAll();
        }
    } catch { /* cancelled */ }
}

async function handleTransfer() {
    if (!transferAgentId.value || !activeConversation.value) {
        ElMessage.warning('请选择目标客服');
        return;
    }
    try {
        const { data: res } = await handoffApi.transfer(
            activeConversation.value.id,
            transferAgentId.value,
            transferNote.value
        );
        if (res.success) {
            ElMessage.success('已转交');
            showTransferDialog.value = false;
            activeConversation.value = null;
            messages.value = [];
            await refreshAll();
        }
    } catch {
        ElMessage.error('转交失败');
    }
}

async function handleStatusChange(val) {
    try {
        await handoffApi.updateStatus(val);
        ElMessage.success(`状态已更新为: ${val === 'online' ? '在线' : val === 'busy' ? '忙碌' : val === 'away' ? '离开' : '离线'}`);
    } catch {
        ElMessage.error('更新状态失败');
    }
}

function scrollToBottom() {
    if (msgContainer.value) {
        msgContainer.value.scrollTop = msgContainer.value.scrollHeight;
    }
}

onMounted(() => {
    refreshAll();

    // 每30秒自动刷新
    setInterval(refreshAll, 30000);
});
</script>

<style scoped>
.handoff-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0 0 4px; }
.header-subtitle { font-size: 14px; color: #909399; }
.header-actions { display: flex; gap: 12px; align-items: center; }
.agent-id { font-size: 12px; color: #909399; }

.stat-box { text-align: center; padding: 8px; }
.stat-num { font-size: 28px; font-weight: 700; }
.stat-lbl { font-size: 13px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }

.card-header { display: flex; justify-content: space-between; align-items: center; }

.conversations-card { height: calc(100vh - 280px); overflow-y: auto; }
.conversation-item { padding: 12px 16px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.2s; }
.conversation-item:hover { background: #f5f7fa; }
.conversation-item.active { background: #ecf5ff; border-left: 3px solid #409eff; }
.conv-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.conv-meta { display: flex; gap: 12px; font-size: 12px; color: #909399; margin-bottom: 4px; }
.conv-preview { font-size: 13px; color: #606266; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.chat-card { height: calc(100vh - 280px); display: flex; flex-direction: column; }
.chat-card :deep(.el-card__body) { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-actions { display: flex; gap: 8px; }

.messages-container { flex: 1; overflow-y: auto; padding: 8px 0; }
.context-notice { margin-bottom: 12px; }
.context-json { font-size: 11px; max-height: 120px; overflow-y: auto; background: #f5f7fa; padding: 8px; border-radius: 4px; }

.message { margin-bottom: 12px; }
.msg-sender { font-size: 12px; color: #909399; margin-bottom: 2px; }
.msg-time { margin-left: 8px; }
.msg-bubble { display: inline-block; max-width: 85%; padding: 8px 14px; border-radius: 8px; font-size: 14px; line-height: 1.5; white-space: pre-wrap; }

.msg-agent .msg-bubble { background: #ecf5ff; color: #303133; border-bottom-left-radius: 2px; }
.msg-customer .msg-bubble { background: #f0f9eb; color: #303133; border-bottom-right-radius: 2px; }
.msg-system .msg-bubble { background: #fdf6ec; color: #606266; font-size: 13px; border-radius: 4px; width: 100%; }

.chat-input { display: flex; gap: 8px; padding-top: 12px; border-top: 1px solid #f0f0f0; }
.chat-input .el-textarea { flex: 1; }
.send-btn { align-self: flex-end; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 200px; color: #909399; gap: 12px; }
</style>
