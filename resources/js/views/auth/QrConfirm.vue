<template>
    <div class="qr-confirm-page">
        <div class="confirm-content">
            <div class="icon-area">
                <el-icon :size="64" color="#0f172a"><Monitor /></el-icon>
            </div>
            <h2>{{ $t('qr_confirm.title') }}</h2>
            <p class="desc">{{ $t('qr_confirm.desc') }}</p>

            <el-descriptions :column="1" border size="small" class="session-info">
                <el-descriptions-item :label="$t('qr_confirm.device')">{{ sessionInfo.device || $t('qr_confirm.unknown_device') }}</el-descriptions-item>
                <el-descriptions-item :label="$t('qr_confirm.browser')">{{ sessionInfo.browser || $t('qr_confirm.unknown') }}</el-descriptions-item>
                <el-descriptions-item :label="$t('qr_confirm.os')">{{ sessionInfo.os || $t('qr_confirm.unknown') }}</el-descriptions-item>
                <el-descriptions-item :label="$t('qr_confirm.ip')">{{ sessionInfo.ip || $t('qr_confirm.unknown') }}</el-descriptions-item>
                <el-descriptions-item :label="$t('qr_confirm.location')">{{ sessionInfo.location || $t('qr_confirm.unknown') }}</el-descriptions-item>
                <el-descriptions-item :label="$t('qr_confirm.time')">{{ sessionInfo.created_at || '-' }}</el-descriptions-item>
            </el-descriptions>

            <div class="actions">
                <el-button type="primary" size="large" :loading="confirming" @click="handleConfirm">{{ $t('qr_confirm.confirm') }}</el-button>
                <el-button size="large" @click="handleCancel">{{ $t('qr_confirm.cancel') }}</el-button>
            </div>

            <div v-if="confirmResult" class="result">
                <el-result v-if="confirmResult.success" icon="success" :title="$t('qr_confirm.success')" :sub-title="$t('qr_confirm.success_sub')" />
                <el-result v-else icon="error" :title="$t('qr_confirm.fail')" :sub-title="confirmResult.message" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Monitor } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const confirming = ref(false);
const confirmResult = ref(null);
const sessionInfo = reactive({
    device: '', browser: '', os: '', ip: '', location: '', created_at: '',
});

async function loadSession() {
    const sessionId = route.query.session;
    if (!sessionId) {
        ElMessage.error(t('qr_confirm.missing_session'));
        return;
    }
    try {
        const { data: res } = await apiClient.get(`/auth/qrcode/session/${sessionId}`);
        if (res.data) {
            Object.assign(sessionInfo, res.data.metadata || {});
            if (!sessionInfo.created_at) sessionInfo.created_at = res.data.created_at;
        }
    } catch {
        ElMessage.error(t('qr_confirm.expired'));
    }
}

async function handleConfirm() {
    const sessionId = route.query.session;
    if (!sessionId) { ElMessage.error(t('qr_confirm.missing_session')); return; }
    confirming.value = true;
    try {
        const { data: res } = await apiClient.post('/auth/qrcode/confirm', { session_id: sessionId });
        confirmResult.value = { success: true, message: res.message || t('qr_confirm.success') };
        setTimeout(() => router.push('/portal/dashboard'), 2000);
    } catch (e) {
        confirmResult.value = { success: false, message: e.response?.data?.message || t('qr_confirm.confirm_fail') };
    } finally { confirming.value = false; }
}

function handleCancel() {
    ElMessage.info(t('qr_confirm.cancelled'));
    router.push('/portal/dashboard');
}

onMounted(() => { loadSession(); });
</script>

<style scoped>
.qr-confirm-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f5f7fa; }
.confirm-content { width: 420px; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); text-align: center; }
.icon-area { margin-bottom: 16px; }
h2 { margin: 0 0 8px; font-size: 20px; }
.desc { color: #909399; margin-bottom: 24px; }
.session-info { text-align: left; margin-bottom: 24px; }
.actions { margin-bottom: 16px; }
.actions .el-button { min-width: 140px; }
.result { margin-top: 16px; }
</style>
