<template>
    <div>
        <el-dialog
            v-model="visible"
            :title="notification?.title || '系统通知'"
            width="480px"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            :show-close="notification?.payload?.require_ack"
        >
            <div class="critical-notif-body">
                <el-alert
                    v-if="notification?.type === 'app_suspended'"
                    title="应用已下架"
                    type="error"
                    :description="notification?.content"
                    show-icon
                    :closable="false"
                />
                <el-alert
                    v-else-if="notification?.type === 'app_force_update'"
                    title="强制更新提醒"
                    type="warning"
                    :description="notification?.content"
                    show-icon
                    :closable="false"
                />
                <div v-else class="notif-content-text">{{ notification?.content }}</div>

                <div v-if="notification?.payload" class="notif-payload mt-3">
                    <el-descriptions :column="1" border size="small" v-if="notification.payload.app_name">
                        <el-descriptions-item label="应用">{{ notification.payload.app_name }}</el-descriptions-item>
                        <el-descriptions-item v-if="notification.payload.target_version" label="目标版本">v{{ notification.payload.target_version }}</el-descriptions-item>
                    </el-descriptions>
                </div>
            </div>
            <template #footer>
                <el-button type="primary" @click="acknowledge">我知道了</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { ElMessage } from 'element-plus';
import request from '@/utils/request';

const visible = ref(false);
const notification = ref(null);
let pollTimer = null;

async function checkCriticalNotifications() {
    try {
        const { data: res } = await request.get('/notifications/unread-count');
        if (res?.data?.critical_count > 0 || res?.critical_count > 0) {
            // 有未读关键通知，获取第一条
            const { data: listRes } = await request.get('/notifications', {
                params: { per_page: 1, type: 'app_suspended,app_force_update', unread: true }
            });
            const items = listRes?.data?.data || listRes?.data || [];
            if (items.length > 0) {
                notification.value = items[0];
                visible.value = true;
            }
        }
    } catch { /* ignore */ }
}

async function acknowledge() {
    if (notification.value) {
        try {
            await request.post(`/notifications/${notification.value.id}/read`);
        } catch { /* ignore */ }
    }
    visible.value = false;
    notification.value = null;
}

onMounted(() => {
    checkCriticalNotifications();
    // 每 30 秒轮询一次关键通知
    pollTimer = setInterval(checkCriticalNotifications, 30000);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.critical-notif-body { padding: 8px 0; }
.notif-content-text { font-size: 14px; line-height: 1.6; color: #303133; padding: 12px 0; }
.mt-3 { margin-top: 12px; }
</style>
