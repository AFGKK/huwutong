<template>
    <div class="ticket-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/tickets' }">{{ t('tickets_page.title') }}</el-breadcrumb-item>
                <el-breadcrumb-item>{{ t('tickets_page.ticket_n', { id: ticket.id }) }}</el-breadcrumb-item>
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
                                    {{ t('tickets_page.st_sla_breached') }}
                                </el-tag>
                            </div>
                        </div>

                        <h3 class="ticket-title">{{ ticket.subject || ticket.title }}</h3>

                        <div class="ticket-customer">
                            <el-icon><User /></el-icon>
                            <span>{{ ticket.customer?.name || ticket.user?.name || t('tickets_page.unknown') }}</span>
                            <span class="customer-email" v-if="ticket.customer?.email">{{ ticket.customer.email }}</span>
                        </div>

                        <el-divider />

                        <div class="ticket-description">{{ ticket.description }}</div>

                        <div class="ticket-time">
                            {{ t('tickets_page.created_at', { time: ticket.created_at }) }}
                            <span v-if="ticket.customer?.user?.email" class="ml-4">{{ t('tickets_page.email_label') }} {{ ticket.customer?.user?.email }}</span>
                        </div>
                    </el-card>

                    <!-- 标签 -->
                    <el-card class="mb-4">
                        <template #header>
                            <span>{{ t('tickets_page.tags') }}</span>
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
                                <span>{{ t('tickets_page.replies_title', { n: replies.length }) }}</span>
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
                                            {{ reply.user?.name || reply.admin?.name || reply.customer?.name || t('tickets_page.role_customer') }}
                                            <el-tag v-if="reply.is_admin" size="small" type="primary" class="staff-tag">{{ t('tickets_page.role_staff') }}</el-tag>
                                            <el-tag v-else size="small" type="info" class="staff-tag">{{ t('tickets_page.role_customer') }}</el-tag>
                                        </span>
                                    </div>
                                    <span class="reply-time">{{ reply.created_at }}</span>
                                </div>
                                <div class="reply-body">{{ reply.content || reply.body || reply.message }}</div>
                            </div>
                        </div>
                        <el-empty v-else :description="t('tickets_page.no_replies')" :image-size="60" />
                    </el-card>

                    <!-- 回复框 -->
                    <el-card v-if="ticket.status !== 'closed'" shadow="never">
                        <template #header>
                            <span>{{ t('tickets_page.reply_title') }}</span>
                        </template>
                        <el-input
                            v-model="replyBody"
                            type="textarea"
                            :rows="5"
                            :placeholder="t('tickets_page.reply_ph')"
                        />
                        <div class="reply-actions">
                            <el-button
                                type="primary"
                                @click="handleReply"
                                :loading="replying"
                                :disabled="!replyBody.trim()"
                            >
                                {{ t('tickets_page.send_reply') }}
                            </el-button>
                        </div>
                    </el-card>
                </el-col>

                <!-- 右侧：操作面板 -->
                <el-col :span="8">
                    <el-card class="mb-4">
                        <template #header>
                            <span>{{ t('tickets_page.ticket_actions') }}</span>
                        </template>
                        <div class="action-list">
                            <el-button
                                v-if="!ticket.assigned_to"
                                class="action-btn"
                                type="primary"
                                @click="handleAssign"
                                :icon="UserFilled"
                            >
                                {{ t('tickets_page.assign_handler') }}
                            </el-button>
                            <el-button
                                v-if="ticket.assigned_to"
                                class="action-btn"
                                type="primary"
                                plain
                                @click="handleAssign"
                                :icon="UserFilled"
                            >
                                {{ t('tickets_page.reassign', { name: ticket.assigned_to.name }) }}
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'open'"
                                class="action-btn"
                                type="primary"
                                @click="handleQuickAction('assign', {})"
                                :icon="EditPen"
                            >
                                {{ t('tickets_page.start_processing') }}
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'in_progress'"
                                class="action-btn"
                                type="success"
                                @click="handleQuickAction('resolve')"
                                :icon="CircleCheck"
                            >
                                {{ t('tickets_page.mark_resolved') }}
                            </el-button>
                            <el-button
                                v-if="ticket.status === 'resolved'"
                                class="action-btn"
                                @click="handleQuickAction('reopen')"
                                :icon="Refresh"
                            >
                                {{ t('tickets_page.reopen') }}
                            </el-button>
                            <el-button
                                v-if="ticket.status !== 'closed'"
                                class="action-btn"
                                type="info"
                                plain
                                @click="handleQuickAction('close')"
                                :icon="CircleClose"
                            >
                                {{ t('tickets_page.close_ticket') }}
                            </el-button>
                        </div>
                    </el-card>

                    <el-card>
                        <template #header>
                            <span>{{ t('tickets_page.ticket_info') }}</span>
                        </template>
                        <el-descriptions :column="1" direction="vertical" border size="small">
                            <el-descriptions-item :label="t('tickets_page.ticket_id')">#{{ ticket.id }}</el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.col_customer')">
                                {{ ticket.customer?.name || ticket.user?.name || '-' }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.col_created')">{{ ticket.created_at }}</el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.last_updated')">{{ ticket.updated_at }}</el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.priority')">
                                <el-tag :type="priorityType(ticket.priority)" size="small">
                                    {{ priorityLabel(ticket.priority) }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.col_assignee')">
                                {{ ticket.assigned_to?.name || t('tickets_page.unassigned') }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.sla_deadline')">
                                <span v-if="ticket.sla_deadline">{{ ticket.sla_deadline }}</span>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('tickets_page.satisfaction')">
                                <el-rate v-if="ticket.satisfaction_rating" :model-value="ticket.satisfaction_rating" :max="5" disabled size="small" />
                                <span v-else>{{ t('tickets_page.not_rated') }}</span>
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-card>
                </el-col>
            </el-row>
        </div>

        <!-- 分配对话框 -->
        <el-dialog v-model="showAssignDialog" :title="t('tickets_page.assign_handler_dialog')" width="420px">
            <el-form label-position="top">
                <el-form-item :label="t('tickets_page.select_staff')">
                    <el-select v-model="assignUserId" :placeholder="t('tickets_page.search_select_ph')" filterable style="width: 100%">
                        <el-option v-for="u in staffUsers" :key="u.id" :label="u.name" :value="u.id">
                            <span>{{ u.name }}</span>
                            <span class="text-muted" style="float: right;">{{ u.email }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAssignDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="confirmAssign" :loading="assigning">{{ t('tickets_page.confirm_assign') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ticketApi from '@/api/ticket';
import TagSelector from '@/components/TagSelector.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    User, UserFilled, EditPen, CircleCheck, CircleClose, Refresh,
} from '@element-plus/icons-vue';

const { t } = useI18n();
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

const STATUS_MAP = computed(() => ({
    open: { type: 'danger', label: t('tickets_page.st_open') },
    replied: { type: 'warning', label: t('tickets_page.st_replied') },
    in_progress: { type: 'primary', label: t('tickets_page.st_in_progress') },
    resolved: { type: 'success', label: t('tickets_page.st_resolved') },
    closed: { type: 'info', label: t('tickets_page.st_closed') },
}));

const PRIORITY_MAP = computed(() => ({
    low: { type: 'info', label: t('tickets_page.pri_low') },
    normal: { type: '', label: t('tickets_page.pri_normal') },
    high: { type: 'warning', label: t('tickets_page.pri_high') },
    urgent: { type: 'danger', label: t('tickets_page.pri_urgent') },
}));

function statusType(s) { return STATUS_MAP.value[s]?.type || 'info'; }
function statusLabel(s) { return STATUS_MAP.value[s]?.label || s; }
function priorityType(p) { return PRIORITY_MAP.value[p]?.type || 'info'; }
function priorityLabel(p) { return PRIORITY_MAP.value[p]?.label || p; }

async function fetchDetail() {
    const id = route.params.id;
    if (!id) return;
    loading.value = true;
    try {
        const { data: res } = await ticketApi.show(id);
        ticket.value = res.data || {};
        replies.value = res.data?.public_replies || res.data?.publicReplies || res.data?.replies || [];
    } catch {
        ElMessage.error(t('tickets_page.detail_fail'));
    } finally {
        loading.value = false;
    }
}

async function handleReply() {
    if (!replyBody.value.trim()) return;
    replying.value = true;
    try {
        await ticketApi.reply(ticket.value.id, { content: replyBody.value });
        ElMessage.success(t('tickets_page.reply_sent'));
        replyBody.value = '';
        await fetchDetail();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('tickets_page.reply_fail'));
    } finally {
        replying.value = false;
    }
}

async function handleQuickAction(action) {
    const confirmMessages = {
        resolve: t('tickets_page.confirm_resolve'),
        close: t('tickets_page.confirm_close'),
        reopen: t('tickets_page.confirm_reopen'),
    };

    try {
        if (confirmMessages[action]) {
            await ElMessageBox.confirm(confirmMessages[action], t('tickets_page.confirm_action_title'), {
                confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info',
            });
        }
        await ticketApi[action](ticket.value.id);
        ElMessage.success(t('messages.success'));
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('messages.failed'));
        }
    }
}

function handleAssign() {
    assignUserId.value = ticket.value.assigned_to?.id || null;
    showAssignDialog.value = true;
}

async function confirmAssign() {
    if (!assignUserId.value) {
        ElMessage.warning(t('tickets_page.select_assignee_required'));
        return;
    }
    assigning.value = true;
    try {
        await ticketApi.assign(ticket.value.id, assignUserId.value);
        ElMessage.success(t('tickets_page.assign_ok'));
        showAssignDialog.value = false;
        await fetchDetail();
    } catch {
        ElMessage.error(t('tickets_page.assign_fail'));
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
    background: #f1f5f9;
    border-color: #e2e8f0;
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
