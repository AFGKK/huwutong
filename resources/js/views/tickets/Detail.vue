<template>
    <div class="ticket-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/tickets' }">工单管理</el-breadcrumb-item>
                <el-breadcrumb-item>工单 #{{ ticket.id }}</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div class="detail-content">
            <el-row :gutter="16">
                <!-- 左侧：主体内容 -->
                <el-col :span="16">
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
                                <el-tag v-if="ticket.sla_breached" type="danger" size="small" class="ml-2">
                                    SLA 超时
                                </el-tag>
                            </div>
                        </div>

                        <h3 class="ticket-title">{{ ticket.subject || ticket.title }}</h3>

                        <div class="ticket-customer">
                            <el-icon><User /></el-icon>
                            <span>{{ ticket.customer?.name || ticket.user?.name || '未知' }}</span>
                            <span class="customer-email" v-if="ticket.customer?.email">{{ ticket.customer.email }}</span>
                        </div>

                        <el-divider />

                        <div class="ticket-description">{{ ticket.description }}</div>

                        <div class="ticket-time">
                            创建于 {{ ticket.created_at }}
                            <span v-if="ticket.customer?.user?.email" class="ml-4">邮箱: {{ ticket.customer?.user?.email }}</span>
                        </div>
                    </el-card>

                    <!-- 标签 -->
                    <el-card class="mb-4">
                        <template #header>
                            <span>标签</span>
                        </template>
                        <TagSelector
                            taggable-type="ticket"
                            :taggable-id="ticket.id"
                            :tags="ticket.tags || []"
                        />
                    </el-card>

                    <!-- 回复区域 -->
                    <el-card class="mb-4">
                        <template #header>
                            <div class="card-header">
                                <span>回复 ({{ replies.length }})</span>
                            </div>
                        </template>

                        <div v-if="replies.length" class="replies-list">
                            <div v-for="reply in replies" :key="reply.id" class="reply-item" :class="{ 'staff-reply': reply.is_admin }">
                                <div class="reply-header">
                                    <div class="reply-author">
                                        <el-avatar :size="28" :src="reply.user?.avatar_url || reply.admin?.avatar_url || reply.customer?.user?.avatar_url">
                                            <span class="avatar-initial">{{ (reply.user?.name || reply.admin?.name || reply.customer?.name || '?').charAt(0).toUpperCase() }}</span>
                                            <template #error>
                                                <span class="avatar-initial">{{ (reply.user?.name || reply.admin?.name || reply.customer?.name || '?').charAt(0).toUpperCase() }}</span>
                                            </template>
                                        </el-avatar>
                                        <span class="reply-name">
                                            {{ reply.user?.name || reply.admin?.name || reply.customer?.name || '客户' }}
                                            <el-tag v-if="reply.is_admin" size="small" type="primary" class="staff-tag">客服</el-tag>
                                            <el-tag v-else size="small" type="info" class="staff-tag">客户</el-tag>
                                        </span>
                                    </div>
                                    <span class="reply-time">{{ reply.created_at }}</span>
                                </div>
                                <div class="reply-body">{{ reply.content || reply.body || reply.message }}</div>
                            </div>
                        </div>
                        <el-empty v-else description="暂无回复" :image-size="60" />
                    </el-card>

                    <!-- 回复框 -->
                    <el-card v-if="ticket.status !== 'closed'" shadow="never">
                        <template #header>
                            <span>回复工单</span>
                        </template>
                        <el-input
                            v-model="replyBody"
                            type="textarea"
                            :rows="5"
                            placeholder="输入回复内容..."
                        />
                        <div class="reply-actions">
                            <el-button
                                type="primary"
                                @click="handleReply"
                                :loading="replying"
                                :disabled="!replyBody.trim()"
                            >
                                发送回复
                            </el-button>
                        </div>
                    </el-card>
                </el-col>

                <!-- 右侧：操作面板 -->
                <el-col :span="8">
                    <el-card class="mb-4">
                        <template #header>
                            <span>工单操作</span>
                        </template>
                        <div class="action-list">
                            <el-button
                                v-if="!ticket.assigned_to"
                                class="action-btn"
                                type="primary"
                                @click="handleAssign"
                                :icon="UserFilled"
                            >
                                分配处理人
                            </el-button>
                            <el-button
                                v-if="ticket.assigned_to"
                                class="action-btn"
                                type="primary"
                                plain
                                @click="handleAssign"
                                :icon="UserFilled"
                            >
                                重新分配 ({{ ticket.assigned_to.name }})
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'open'"
                                class="action-btn"
                                type="primary"
                                @click="handleQuickAction('assign', {})"
                                :icon="EditPen"
                            >
                                开始处理
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'in_progress'"
                                class="action-btn"
                                type="success"
                                @click="handleQuickAction('resolve')"
                                :icon="CircleCheck"
                            >
                                标记已解决
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'resolved'"
                                class="action-btn"
                                @click="handleQuickAction('reopen')"
                                :icon="Refresh"
                            >
                                重新打开
                            </el-button>
                            <el-button
                                v-if="ticket.status !== 'closed'"
                                class="action-btn"
                                type="info"
                                plain
                                @click="handleQuickAction('close')"
                                :icon="CircleClose"
                            >
                                关闭工单
                            </el-button>
                        </div>
                    </el-card>

                    <el-card>
                        <template #header>
                            <span>工单信息</span>
                        </template>
                        <el-descriptions :column="1" direction="vertical" border size="small">
                            <el-descriptions-item label="工单 ID">#{{ ticket.id }}</el-descriptions-item>
                            <el-descriptions-item label="客户">
                                {{ ticket.customer?.name || ticket.user?.name || '-' }}
                            </el-descriptions-item>
                            <el-descriptions-item label="创建时间">{{ ticket.created_at }}</el-descriptions-item>
                            <el-descriptions-item label="最后更新">{{ ticket.updated_at }}</el-descriptions-item>
                            <el-descriptions-item label="优先级">
                                <el-tag :type="priorityType(ticket.priority)" size="small">
                                    {{ priorityLabel(ticket.priority) }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="处理人">
                                {{ ticket.assigned_to?.name || '未分配' }}
                            </el-descriptions-item>
                            <el-descriptions-item label="SLA 截止">
                                <span v-if="ticket.sla_deadline">{{ ticket.sla_deadline }}</span>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item label="满意度">
                                <el-rate v-if="ticket.satisfaction_rating" :model-value="ticket.satisfaction_rating" :max="5" disabled size="small" />
                                <span v-else>未评价</span>
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <!-- 分配对话框 -->
        <el-dialog v-model="showAssignDialog" title="分配处理人" width="420px">
            <el-form label-position="top">
                <el-form-item label="选择客服人员">
                    <el-select v-model="assignUserId" placeholder="搜索选择" filterable style="width: 100%">
                        <el-option v-for="u in staffUsers" :key="u.id" :label="u.name" :value="u.id">
                            <span>{{ u.name }}</span>
                            <span class="text-muted" style="float: right;">{{ u.email }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">取消</el-button>
                <el-button type="primary" @click="confirmAssign" :loading="assigning">确认分配</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ticketApi from '@/api/ticket';
import TagSelector from '@/components/TagSelector.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    User, UserFilled, EditPen, CircleCheck, CircleClose, Refresh,
} from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const replying = ref(false);
const ticket = ref({});
const replies = ref([]);
const replyBody = ref('');

const showAssignDialog = ref(false);
const assigning = ref(false);
const assignUserId = ref(null);
const staffUsers = ref([]);

const STATUS_MAP = {
    open: { type: 'danger', label: '待处理' },
    replied: { type: 'warning', label: '已回复' },
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
        replies.value = res.data?.public_replies || res.data?.publicReplies || res.data?.replies || [];
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
        await ticketApi.reply(ticket.value.id, { content: replyBody.value });
        ElMessage.success('回复已发送');
        replyBody.value = '';
        await fetchDetail();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送回复失败');
    } finally {
        replying.value = false;
    }
}

async function handleQuickAction(action) {
    const confirmMessages = {
        resolve: '确认将此工单标记为已解决？',
        close: '确认关闭此工单？',
        reopen: '确认重新打开此工单？',
    };

    try {
        if (confirmMessages[action]) {
            await ElMessageBox.confirm(confirmMessages[action], '确认操作', {
                confirmButtonText: '确认', cancelButtonText: '取消', type: 'info',
            });
        }
        await ticketApi[action](ticket.value.id);
        ElMessage.success('操作成功');
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '操作失败');
        }
    }
}

function handleAssign() {
    assignUserId.value = ticket.value.assigned_to?.id || null;
    showAssignDialog.value = true;
}

async function confirmAssign() {
    if (!assignUserId.value) {
        ElMessage.warning('请选择处理人');
        return;
    }
    assigning.value = true;
    try {
        await ticketApi.assign(ticket.value.id, assignUserId.value);
        ElMessage.success('分配成功');
        showAssignDialog.value = false;
        await fetchDetail();
    } catch {
        ElMessage.error('分配失败');
    } finally {
        assigning.value = false;
    }
}

onMounted(fetchDetail);
</script>

<style scoped>
.page-breadcrumb {
    margin-bottom: 16px;
}

.mb-4 { margin-bottom: 16px; }
.ml-2 { margin-left: 8px; }
.ml-4 { margin-left: 16px; }

.ticket-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.ticket-meta {
    display: flex;
    align-items: center;
}

.ticket-title {
    margin: 0 0 12px;
    font-size: 20px;
    color: #303133;
}

.ticket-customer {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #606266;
    margin-bottom: 8px;
}

.customer-email {
    font-size: 12px;
    color: #909399;
}

.ticket-description {
    font-size: 14px;
    color: #606266;
    line-height: 1.7;
    white-space: pre-wrap;
    margin-bottom: 12px;
}

.ticket-time {
    font-size: 12px;
    color: #909399;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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

.reply-item.staff-reply {
    background: #ecf5ff;
    border-color: #d9ecff;
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

.text-muted { color: #909399; }
</style>
