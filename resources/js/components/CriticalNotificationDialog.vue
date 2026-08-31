<template>
    <div>
        <el-dialog
            v-model="visible"
            :title="notification?.title || t('critical_notif.title')"
            width="480px"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            :show-close="notification?.payload?.require_ack"
        >
            <div class="critical-notif-body">
                <el-alert
                    v-if="notification?.type === 'app_suspended'"
                    :title="t('critical_notif.app_suspended')"
                    type="error"
                    :description="notification?.content"
                    show-icon
                    :closable="false"
                />
                <el-alert
                    v-else-if="notification?.type === 'app_force_update'"
                    :title="t('critical_notif.force_update')"
                    type="warning"
                    :description="notification?.content"
                    show-icon
                    :closable="false"
                />
                <div v-else class="notif-content-text">{{ notification?.content }}</div>

                <div v-if="notification?.payload" class="notif-payload mt-3">
                    <el-descriptions :column="1" border size="small" v-if="notification.payload.app_name">
                        <el-descriptions-item :label="t('critical_notif.app')">{{ notification.payload.app_name }}</el-descriptions-item>
                        <el-descriptions-item v-if="notification.payload.target_version" :label="t('critical_notif.target_version')">v{{ notification.payload.target_version }}</el-descriptions-item>
                    </el-descriptions>
                </div>
            </div>
            <template #footer>
                <el-button type="primary" @click="acknowledge">{{ t('critical_notif.got_it') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import request from '@/utils/request';

const { t } = useI18n();
const visible = ref(false);
const notification = ref(null);
let pollTimer = null;

async function checkCriticalNotifications() {
    try {
        const { data: res } = await request.get('/notifications/unread-count', { silentAuth: true });
        if (res?.data?.critical_count > 0 || res?.critical_count > 0) {
            const { data: listRes } = await request.get('/notifications', {
                params: { per_page: 1, type: 'app_suspended,app_force_update', unread: true },
                silentAuth: true,
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
            await request.post(`/notifications/${notification.value.id}/read`, null, { silentAuth: true });
        } catch { /* ignore */ }
    }
    visible.value = false;
    notification.value = null;
}

onMounted(() => {
    checkCriticalNotifications();
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
