<template>
    <div class="qr-confirm-page">
        <div class="confirm-content">
            <div class="icon-area">
                <el-icon :size="64" color="#409eff"><Monitor /></el-icon>
            </div>
            <h2>确认扫码登录</h2>
            <p class="desc">检测到来自以下设备的登录请求</p>

            <el-descriptions :column="1" border size="small" class="session-info">
                <el-descriptions-item label="设备">{{ sessionInfo.device || '未知设备' }}</el-descriptions-item>
                <el-descriptions-item label="浏览器">{{ sessionInfo.browser || '未知' }}</el-descriptions-item>
                <el-descriptions-item label="操作系统">{{ sessionInfo.os || '未知' }}</el-descriptions-item>
                <el-descriptions-item label="IP 地址">{{ sessionInfo.ip || '未知' }}</el-descriptions-item>
                <el-descriptions-item label="位置">{{ sessionInfo.location || '未知' }}</el-descriptions-item>
                <el-descriptions-item label="时间">{{ sessionInfo.created_at || '-' }}</el-descriptions-item>
            </el-descriptions>

            <div class="actions">
                <el-button type="primary" size="large" :loading="confirming" @click="handleConfirm">确认登录</el-button>
                <el-button size="large" @click="handleCancel">取消</el-button>
            </div>

            <div v-if="confirmResult" class="result">
                <el-result v-if="confirmResult.success" icon="success" title="登录成功" sub-title="PC 端将自动跳转" />
                <el-result v-else icon="error" title="登录失败" :sub-title="confirmResult.message" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Monitor } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const route = useRoute();
const router = useRouter();

const confirming = ref(false);
const confirmResult = ref(null);
const sessionInfo = reactive({
    device: '', browser: '', os: '', ip: '', location: '', created_at: '',
});

async function loadSession() {
    const sessionId = route.query.session;
    if (!sessionId) {
        ElMessage.error('缺少会话 ID');
        return;
    }
    try {
        const { data: res } = await apiClient.get(`/auth/qrcode/session/${sessionId}`);
        if (res.data) {
            Object.assign(sessionInfo, res.data.metadata || {});
            if (!sessionInfo.created_at) sessionInfo.created_at = res.data.created_at;
        }
    } catch {
        ElMessage.error('会话已过期或无效');
    }
}

async function handleConfirm() {
    const sessionId = route.query.session;
    if (!sessionId) { ElMessage.error('缺少会话 ID'); return; }
    confirming.value = true;
    try {
        const { data: res } = await apiClient.post('/auth/qrcode/confirm', { session_id: sessionId });
        confirmResult.value = { success: true, message: res.message || '登录成功' };
        setTimeout(() => router.push('/portal/dashboard'), 2000);
    } catch (e) {
        confirmResult.value = { success: false, message: e.response?.data?.message || '确认失败' };
    } finally { confirming.value = false; }
}

function handleCancel() {
    ElMessage.info('已取消登录');
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
