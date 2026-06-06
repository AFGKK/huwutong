<template>
    <div class="portal-ticket-detail" v-loading="loading">
        <el-page-header @back="$router.push('/portal/tickets')" :content="`工单 #${ticket.id}`" />

        <el-row :gutter="16" class="mt-4">
            <!-- 左侧：工单内容和回复 -->
            <el-col :span="16">
                <!-- 工单主体 -->
                <el-card class="mb-4">
                    <div class="ticket-header">
                        <div class="ticket-meta">
                            <el-tag :type="statusType(ticket.status)" size="small" effect="dark">
                                {{ statusLabel(ticket.status) }}
                            </el-tag>
                            <el-tag :type="priorityType(ticket.priority)" size="small" class="ml-2">
                                {{ priorityLabel(ticket.priority) }}
                            </el-tag>
                            <el-tag v-if="ticket.category" size="small" effect="plain" class="ml-2">
                                {{ ticket.category.name }}
                            </el-tag>
                        </div>
                        <div class="ticket-id">#{{ ticket.id }}</div>
                    </div>
                    <h3 class="ticket-title">{{ ticket.title }}</h3>
                    <div class="ticket-description">{{ ticket.description }}</div>
                    <div class="ticket-footer">
                        <span class="ticket-author">{{ ticket.customer?.name || ticket.user?.name || '您' }}</span>
                        <span class="ticket-time">{{ ticket.created_at }}</span>
                    </div>
                </el-card>

                <!-- 回复列表 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>回复 ({{ replies.length }})</span>
                    </template>

                    <div v-if="replies.length" class="replies-list">
                        <div v-for="reply in replies" :key="reply.id" class="reply-item">
                            <div class="reply-header">
                                <div class="reply-author">
                                    <el-avatar :size="28" :icon="reply.is_admin ? 'UserFilled' : 'User'" />
                                    <span class="reply-name">
                                        {{ reply.user?.name || reply.admin?.name || '客服' }}
                                        <el-tag v-if="reply.is_admin" size="small" type="primary" class="staff-tag">客服</el-tag>
                                    </span>
                                </div>
                                <span class="reply-time">{{ reply.created_at }}</span>
                            </div>
                            <div class="reply-body">{{ reply.body || reply.message }}</div>
                        </div>
                    </div>
                    <el-empty v-else description="暂无回复，请耐心等待" :image-size="60" />
                </el-card>

                <!-- 回复框 -->
                <el-card v-if="canReply" shadow="never">
                    <template #header>
                        <span>添加回复</span>
                    </template>
                    <el-input
                        v-model="replyBody"
                        type="textarea"
                        :rows="4"
                        placeholder="输入您的回复内容..."
                    />
                    <div class="reply-actions">
                        <el-button type="primary" @click="handleReply" :loading="replying" :disabled="!replyBody.trim()">
                            发送回复
                        </el-button>
                    </div>
                </el-card>
            </el-col>

            <!-- 右侧：操作面板 -->
            <el-col :span="8">
                <!-- 状态操作 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>工单操作</span>
                    </template>
                    <div class="action-list">
                        <el-button
                            v-if="ticket.status === 'resolved'"
                            class="action-btn"
                            @click="handleAction('reopen')"
                            :icon="Refresh"
                        >
                            重新打开
                        </el-button>
                        <el-button
                            v-if="ticket.status === 'open' || ticket.status === 'in_progress'"
                            class="action-btn"
                            type="success"
                            @click="handleAction('resolve')"
                            :icon="CircleCheck"
                        >
                            标记已解决
                        </el-button>
                        <el-button
                            v-if="ticket.status !== 'closed'"
                            class="action-btn"
                            type="info"
                            plain
                            @click="handleAction('close')"
                            :icon="CircleClose"
                        >
                            关闭工单
                        </el-button>
                    </div>
                </el-card>

                <!-- 工单信息 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>工单信息</span>
                    </template>
                    <el-descriptions :column="1" direction="vertical" border size="small">
                        <el-descriptions-item label="创建时间">{{ ticket.created_at }}</el-descriptions-item>
                        <el-descriptions-item label="最后更新">{{ ticket.updated_at }}</el-descriptions-item>
                        <el-descriptions-item label="优先级">
                            <el-tag :type="priorityType(ticket.priority)" size="small">
                                {{ priorityLabel(ticket.priority) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="处理人">{{ ticket.assigned_to?.name || '未分配' }}</el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <!-- 满意度评价 -->
                <el-card v-if="ticket.status === 'resolved' && !rated" shadow="never">
                    <template #header>
                        <span>满意度评价</span>
                    </template>
                    <p class="rate-hint">请对我们的服务进行评价</p>
                    <div class="rate-stars">
                        <el-rate v-model="satisfactionRating" :max="5" @change="handleSatisfaction" />
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ticketApi from '@/api/ticket';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, CircleCheck, CircleClose } from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const replying = ref(false);
const ticket = ref({});
const replies = ref([]);
const replyBody = ref('');
const satisfactionRating = ref(0);
const rated = ref(false);

const canReply = computed(() => {
    return ['open', 'in_progress'].includes(ticket.value.status);
});

const STATUS_MAP = {
    open: { type: 'danger', label: '待处理' },
    in_progress: { type: 'primary', label: '处理中' },
    resolved: { type: 'success', label: '已解决' },
    closed: { type: 'info', label: '已关闭' },
};

const PRIORITY_MAP = {
    low: { type: 'info', label: '低' },
    normal: { type: '', label: '普通' },
    high: { type: 'warning', label: '高' },
    urgent: { type: 'danger', label: '紧急' },
};

function statusType(s) { return STATUS_MAP[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP[s]?.label || s; }
function priorityType(p) { return PRIORITY_MAP[p]?.type || 'info'; }
function priorityLabel(p) { return PRIORITY_MAP[p]?.label || p; }

async function fetchDetail() {
    const id = route.params.id;
    if (!id) return;
    loading.value = true;
    try {
        const { data: res } = await ticketApi.show(id);
        ticket.value = res.data || {};
        replies.value = res.data?.replies || [];

        // Check if already rated
        if (res.data?.satisfaction_rating) {
            rated.value = true;
            satisfactionRating.value = res.data.satisfaction_rating;
        }
    } catch {
        ElMessage.error('获取工单详情失败');
    } finally {
        loading.value = false;
    }
}

async function handleReply() {
    if (!replyBody.value.trim()) return;
    replying.value = true;
    try {
        await ticketApi.reply(ticket.value.id, { body: replyBody.value });
        ElMessage.success('回复已发送');
        replyBody.value = '';
        await fetchDetail();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送回复失败');
    } finally {
        replying.value = false;
    }
}

async function handleAction(action) {
    const confirmMessages = {
        resolve: '确认将此工单标记为已解决？',
        close: '确认关闭此工单？关闭后如需继续沟通可重新打开。',
        reopen: '确认重新打开此工单？',
    };
    try {
        await ElMessageBox.confirm(
            confirmMessages[action] || `确认执行 ${action} 操作？`,
            '操作确认',
            { confirmButtonText: '确认', cancelButtonText: '取消', type: 'info' }
        );
        await ticketApi[action](ticket.value.id);
        ElMessage.success('操作成功');
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '操作失败');
        }
    }
}

async function handleSatisfaction(rating) {
    try {
        await ticketApi.satisfaction(ticket.value.id, rating);
        rated.value = true;
        ElMessage.success('感谢您的评价！');
    } catch {
        ElMessage.error('提交评价失败');
    }
}

onMounted(fetchDetail);
</script>

<style scoped>
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.ticket-meta {
    display: flex;
    align-items: center;
}

.ticket-id {
    font-family: monospace;
    color: #909399;
    font-size: 14px;
}

.ticket-title {
    margin: 0 0 12px;
    font-size: 18px;
    color: #303133;
}

.ticket-description {
    font-size: 14px;
    color: #606266;
    line-height: 1.7;
    white-space: pre-wrap;
    margin-bottom: 16px;
}

.ticket-footer {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #909399;
}

.replies-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.reply-item {
    padding: 12px;
    background: #fafafa;
    border-radius: 8px;
    border: 1px solid #f0f0f0;
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.reply-author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-name {
    font-size: 14px;
    font-weight: 500;
    color: #303133;
    display: flex;
    align-items: center;
    gap: 6px;
}

.staff-tag {
    font-size: 10px;
}

.reply-time {
    font-size: 12px;
    color: #909399;
}

.reply-body {
    font-size: 14px;
    color: #606266;
    line-height: 1.6;
    white-space: pre-wrap;
}

.reply-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

.action-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-btn {
    width: 100%;
}

.rate-hint {
    font-size: 14px;
    color: #909399;
    margin: 0 0 12px;
}

.rate-stars {
    text-align: center;
}
</style>
