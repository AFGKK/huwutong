<template>
    <div class="portal-ticket-detail" v-loading="loading">
        <el-page-header @back="$router.push('/portal/tickets')" :content="$t('portal.ticket_n', { id: ticket.id })" />

        <el-row :gutter="16" class="mt-4">
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
                        </div>
                        <div class="ticket-id">#{{ ticket.id }}</div>
                    </div>
                    <h3 class="ticket-title">{{ ticket.subject || ticket.title }}</h3>
                    <div class="ticket-description">{{ ticket.description }}</div>
                    <div class="ticket-footer">
                        <span class="ticket-author">{{ ticket.customer?.name || ticket.user?.name || $t('portal.you') }}</span>
                        <span class="ticket-time">{{ ticket.created_at }}</span>
                    </div>
                </el-card>

                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.replies_n', { n: replies.length }) }}</span>
                    </template>

                    <div v-if="replies.length" class="replies-list">
                        <div v-for="reply in replies" :key="reply.id" class="reply-item">
                            <div class="reply-header">
                                <div class="reply-author">
                                    <el-avatar :size="28" :src="reply.user?.avatar_url || reply.admin?.avatar_url">
                                        <span class="avatar-initial">{{ (reply.user?.name || reply.admin?.name || '?').charAt(0).toUpperCase() }}</span>
                                        <template #error>
                                            <span class="avatar-initial">{{ (reply.user?.name || reply.admin?.name || '?').charAt(0).toUpperCase() }}</span>
                                        </template>
                                    </el-avatar>
                                    <span class="reply-name">
                                        {{ reply.user?.name || reply.admin?.name || $t('portal.staff') }}
                                        <el-tag v-if="reply.is_admin" size="small" type="primary" class="staff-tag">{{ $t('portal.staff') }}</el-tag>
                                    </span>
                                </div>
                                <span class="reply-time">{{ reply.created_at }}</span>
                            </div>
                            <div class="reply-body">{{ reply.content || reply.body || reply.message }}</div>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('portal.no_replies')" :image-size="60" />
                </el-card>

                <el-card v-if="canReply" shadow="never">
                    <template #header>
                        <span>{{ $t('portal.add_reply') }}</span>
                    </template>
                    <el-input
                        v-model="replyBody"
                        type="textarea"
                        :rows="4"
                        :placeholder="$t('portal.reply_ph')"
                    />
                    <div class="reply-actions">
                        <el-button type="primary" @click="handleReply" :loading="replying" :disabled="!replyBody.trim()">
                            {{ $t('portal.send_reply') }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="8">
                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.ticket_actions') }}</span>
                    </template>
                    <div class="action-list">
                        <el-button
                            class="action-btn"
                            type="primary"
                            plain
                            :loading="contactingSupport"
                            @click="contactAftersale"
                        >
                            {{ $t('portal.contact_aftersale') }}
                        </el-button>
                        <el-button
                            v-if="ticket.status === 'resolved'"
                            class="action-btn"
                            @click="handleAction('reopen')"
                            :icon="Refresh"
                        >
                            {{ $t('portal.reopen_ticket') }}
                        </el-button>
                        <el-button
                            v-if="ticket.status === 'open' || ticket.status === 'in_progress'"
                            class="action-btn"
                            type="success"
                            @click="handleAction('resolve')"
                            :icon="CircleCheck"
                        >
                            {{ $t('portal.mark_resolved') }}
                        </el-button>
                        <el-button
                            v-if="ticket.status !== 'closed'"
                            class="action-btn"
                            type="info"
                            plain
                            @click="handleAction('close')"
                            :icon="CircleClose"
                        >
                            {{ $t('portal.close_ticket') }}
                        </el-button>
                    </div>
                </el-card>

                <el-card class="mb-4">
                    <template #header>
                        <span>{{ $t('portal.ticket_info') }}</span>
                    </template>
                    <el-descriptions :column="1" direction="vertical" border size="small">
                        <el-descriptions-item :label="$t('portal.created_at')">{{ ticket.created_at }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.updated_at')">{{ ticket.updated_at }}</el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.priority')">
                            <el-tag :type="priorityType(ticket.priority)" size="small">
                                {{ priorityLabel(ticket.priority) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="$t('portal.assignee')">{{ ticket.assigned_to?.name || $t('portal.unassigned') }}</el-descriptions-item>
                    </el-descriptions>
                </el-card>

                <el-card v-if="ticket.status === 'resolved' && !rated" shadow="never">
                    <template #header>
                        <span>{{ $t('portal.satisfaction') }}</span>
                    </template>
                    <p class="rate-hint">{{ $t('portal.rate_hint') }}</p>
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
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import ticketApi from '@/api/ticket';
import apiClient from '@/utils/request';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, CircleCheck, CircleClose } from '@element-plus/icons-vue';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const loading = ref(false);
const replying = ref(false);
const ticket = ref({});
const replies = ref([]);
const replyBody = ref('');
const satisfactionRating = ref(0);
const rated = ref(false);
const contactingSupport = ref(false);

const canReply = computed(() => {
    return ['open', 'replied', 'in_progress'].includes(ticket.value.status);
});

function statusType(s) {
    const map = { open: 'danger', replied: 'warning', in_progress: 'primary', resolved: 'success', closed: 'info' };
    return map[s] || 'info';
}
function statusLabel(s) {
    const map = {
        open: t('portal.ticket_open'),
        replied: t('portal.ticket_replied'),
        in_progress: t('portal.ticket_progress'),
        resolved: t('portal.ticket_resolved'),
        closed: t('portal.ticket_closed'),
    };
    return map[s] || s;
}
function priorityType(p) {
    const map = { low: 'info', medium: '', high: 'warning', urgent: 'danger' };
    return map[p] || 'info';
}
function priorityLabel(p) {
    const map = {
        low: t('portal.priority_low'),
        medium: t('portal.priority_medium'),
        high: t('portal.priority_high'),
        urgent: t('portal.priority_urgent'),
    };
    return map[p] || p;
}

async function fetchDetail() {
    const id = route.params.id;
    if (!id) return;
    loading.value = true;
    try {
        const { data: res } = await ticketApi.show(id);
        ticket.value = res.data || {};
        replies.value = res.data?.public_replies || res.data?.publicReplies || res.data?.replies || [];

        if (res.data?.satisfaction_rating) {
            rated.value = true;
            satisfactionRating.value = res.data.satisfaction_rating;
        }
    } catch {
        ElMessage.error(t('portal.ticket_detail_failed'));
    } finally {
        loading.value = false;
    }
}

async function contactAftersale() {
    if (!ticket.value?.id || contactingSupport.value) return;
    contactingSupport.value = true;
    try {
        const res = await apiClient.post('/user-chat/ticket-inquiry', { ticket_id: ticket.value.id });
        const conv = res.data?.data?.conversation;
        ElMessage.success(t('portal.ticket_inquiry_sent'));
        if (conv?.id) {
            router.push({ name: 'UserChat', query: { conv: String(conv.id) } });
        } else {
            router.push({ name: 'UserChat' });
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.ticket_inquiry_failed'));
    } finally {
        contactingSupport.value = false;
    }
}

async function handleReply() {
    if (!replyBody.value.trim()) return;
    replying.value = true;
    try {
        await ticketApi.reply(ticket.value.id, { content: replyBody.value });
        ElMessage.success(t('portal.reply_sent'));
        replyBody.value = '';
        await fetchDetail();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.reply_failed'));
    } finally {
        replying.value = false;
    }
}

async function handleAction(action) {
    const confirmMessages = {
        resolve: t('portal.confirm_resolve'),
        close: t('portal.confirm_close_ticket'),
        reopen: t('portal.confirm_reopen'),
    };
    try {
        await ElMessageBox.confirm(
            confirmMessages[action] || t('portal.confirm_action', { action }),
            t('portal.action_confirm_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );
        await ticketApi[action](ticket.value.id);
        ElMessage.success(t('portal.action_ok'));
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('messages.failed'));
        }
    }
}

async function handleSatisfaction(rating) {
    try {
        await ticketApi.satisfaction(ticket.value.id, rating);
        rated.value = true;
        ElMessage.success(t('portal.thanks_rating'));
    } catch {
        ElMessage.error(t('portal.rating_failed'));
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
