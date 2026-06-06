<template>
    <el-popover
        placement="bottom-end"
        :width="380"
        trigger="click"
        :visible="popoverVisible"
        @show="handleShow"
        @hide="popoverVisible = false"
    >
        <template #reference>
            <el-badge :value="unreadCount" :hidden="unreadCount === 0" class="notification-badge">
                <el-tooltip content="通知" placement="bottom">
                    <el-button
                        circle
                        :icon="Bell"
                        class="notification-btn"
                        @click="popoverVisible = !popoverVisible"
                    />
                </el-tooltip>
            </el-badge>
        </template>

        <div class="notification-popover">
            <div class="popover-header">
                <span class="popover-title">通知</span>
                <div class="popover-actions">
                    <el-button
                        v-if="unreadCount > 0"
                        text
                        size="small"
                        type="primary"
                        @click="handleMarkAllRead"
                    >
                        全部已读
                    </el-button>
                    <el-button
                        text
                        size="small"
                        type="primary"
                        @click="$router.push('/notifications'); popoverVisible = false"
                    >
                        查看全部
                    </el-button>
                </div>
            </div>

            <div class="popover-body" v-loading="loading">
                <div v-if="notifications.length === 0" class="empty-state">
                    <el-empty :image-size="50" description="暂无通知" />
                </div>
                <div
                    v-for="item in notifications"
                    :key="item.id"
                    class="notification-item"
                    :class="{ unread: !item.is_read }"
                    @click="handleClick(item)"
                >
                    <div class="notif-icon">
                        <el-tag
                            :type="typeTag(item.type)"
                            size="small"
                            effect="plain"
                            round
                        >
                            {{ typeIcon(item.type) }}
                        </el-tag>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">{{ item.title }}</div>
                        <div class="notif-text">{{ item.content }}</div>
                        <div class="notif-time">{{ timeAgo(item.created_at) }}</div>
                    </div>
                    <div class="notif-dot" v-if="!item.is_read">
                        <span class="dot" />
                    </div>
                </div>
            </div>
        </div>
    </el-popover>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { Bell } from '@element-plus/icons-vue';
import notificationApi from '@/api/notification';

const router = useRouter();
const popoverVisible = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

let pollTimer = null;

function typeTag(type) {
    const map = {
        expiry_warning: 'warning',
        status_change: 'primary',
        system: 'info',
        license_activation: 'success',
    };
    return map[type] || 'info';
}

function typeIcon(type) {
    const map = {
        expiry_warning: '⏰',
        status_change: '🔄',
        system: 'ℹ️',
        license_activation: '✅',
    };
    return map[type] || '📢';
}

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const now = Date.now();
    const date = new Date(dateStr).getTime();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return '刚刚';
    if (diff < 3600) return `${Math.floor(diff / 60)}分钟前`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}小时前`;
    if (diff < 2592000) return `${Math.floor(diff / 86400)}天前`;
    return new Date(dateStr).toLocaleDateString('zh-CN');
}

async function loadNotifications() {
    try {
        const { data: res } = await notificationApi.list({ per_page: 10 });
        if (res.success) {
            notifications.value = res.data?.data || [];
        }
    } catch {
        // ignore
    }
}

async function loadUnreadCount() {
    try {
        const { data: res } = await notificationApi.unreadCount();
        if (res.success) {
            unreadCount.value = res.data?.count || 0;
        }
    } catch {
        // ignore
    }
}

async function handleShow() {
    loading.value = true;
    await Promise.all([loadNotifications(), loadUnreadCount()]);
    loading.value = false;
}

async function handleMarkAllRead() {
    try {
        await notificationApi.markAllRead();
        unreadCount.value = 0;
        notifications.value.forEach(n => { n.is_read = true; n.read_at = new Date().toISOString(); });
    } catch {
        // ignore
    }
}

function handleClick(item) {
    if (!item.is_read) {
        notificationApi.markRead(item.id).catch(() => {});
        item.is_read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
}

// 定时轮询未读数
function startPolling() {
    loadUnreadCount();
    pollTimer = setInterval(loadUnreadCount, 30000); // 30秒
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<style scoped>
.notification-badge {
    margin-right: 8px;
}
.notification-btn {
    border: none;
    font-size: 18px;
}

.notification-popover {
    max-height: 480px;
    display: flex;
    flex-direction: column;
}

.popover-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0 12px;
    border-bottom: 1px solid var(--el-border-color-lighter);
}
.popover-title {
    font-weight: 600;
    font-size: 15px;
}
.popover-actions {
    display: flex;
    gap: 4px;
}

.popover-body {
    max-height: 380px;
    overflow-y: auto;
    margin: 0 -12px;
    padding: 0 12px;
}

.empty-state {
    padding: 20px 0;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.2s;
    border-bottom: 1px solid var(--el-border-color-extra-light);
}
.notification-item:hover {
    background: var(--el-color-primary-light-9);
}
.notification-item.unread {
    background: var(--el-color-info-light-9);
}

.notif-icon {
    flex-shrink: 0;
    margin-top: 2px;
}

.notif-content {
    flex: 1;
    min-width: 0;
}
.notif-title {
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    color: var(--el-text-color-primary);
}
.notif-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.notif-time {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}

.notif-dot {
    flex-shrink: 0;
    margin-top: 6px;
}
.dot {
    display: block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--el-color-primary);
}
</style>
