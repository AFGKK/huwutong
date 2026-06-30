<template>
    <div class="account-binding-page">
        <div class="page-header">
            <div class="header-left">
                <h2>账号绑定管理</h2>
                <span class="header-subtitle">管理第三方登录绑定和账号登录方式</span>
            </div>
        </div>

        <el-alert
            title="账号安全提示"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="绑定多个登录方式可以提高账号安全性，并在一种方式不可用时使用其他方式登录。"
        />

        <el-row :gutter="16">
            <!-- 第三方 OAuth 绑定 -->
            <el-col :span="16">
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>第三方账号绑定</span>
                        </div>
                    </template>

                    <div v-loading="loading" class="provider-list">
                        <div
                            v-for="provider in supportedProviders"
                            :key="provider.id"
                            class="provider-card"
                        >
                            <div class="provider-icon">
                                <el-avatar :size="48" :icon="provider.icon" :style="{ background: provider.color }" />
                            </div>
                            <div class="provider-info">
                                <div class="provider-name">{{ provider.label }}</div>
                                <div class="provider-desc" v-if="!getBoundProvider(provider.id)">
                                    点击绑定 {{ provider.label }} 账号
                                </div>
                                <div class="provider-desc bound" v-else>
                                    已绑定：{{ getBoundProvider(provider.id).nickname || '未知' }}
                                    <span class="bound-time">
                                        绑定于 {{ formatDate(getBoundProvider(provider.id).created_at) }}
                                    </span>
                                </div>
                            </div>
                            <div class="provider-actions">
                                <el-button
                                    v-if="!getBoundProvider(provider.id)"
                                    type="primary"
                                    plain
                                    size="small"
                                    @click="handleBind(provider.id)"
                                >
                                    绑定
                                </el-button>
                                <el-button
                                    v-else
                                    type="danger"
                                    plain
                                    size="small"
                                    @click="handleUnbind(getBoundProvider(provider.id))"
                                >
                                    解绑
                                </el-button>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- 账号登录方式状态 -->
            <el-col :span="8">
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>登录方式</span>
                        </div>
                    </template>

                    <div class="login-methods">
                        <div class="method-item">
                            <div class="method-left">
                                <el-icon :size="24" color="#67c23a"><Message /></el-icon>
                                <span>邮箱密码</span>
                            </div>
                            <el-tag v-if="hasPassword" type="success" size="small" effect="dark">已设置</el-tag>
                            <el-button v-else text type="primary" size="small">立即设置</el-button>
                        </div>
                        <el-divider style="margin: 8px 0;" />
                        <div class="method-item">
                            <div class="method-left">
                                <el-icon :size="24" color="#409eff"><Iphone /></el-icon>
                                <span>手机号</span>
                            </div>
                            <el-tag v-if="hasPhone" type="success" size="small" effect="dark">已绑定</el-tag>
                            <el-button v-else text type="primary" size="small">绑定手机</el-button>
                        </div>
                    </div>
                </el-card>

                <!-- 安全建议 -->
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>安全建议</span>
                        </div>
                    </template>

                    <div class="security-tips">
                        <div class="tip-item">
                            <el-icon color="#e6a23c"><WarningFilled /></el-icon>
                            <span>建议绑定至少 2 种登录方式</span>
                        </div>
                        <div class="tip-item">
                            <el-icon color="#409eff"><InfoFilled /></el-icon>
                            <span>定期检查绑定账号的安全性</span>
                        </div>
                        <div class="tip-item">
                            <el-icon color="#f56c6c"><Remove /></el-icon>
                            <span>解绑前请确保有其他可用登录方式</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Message, Iphone, WarningFilled, InfoFilled, Remove,
    ChromeFilled, Apple, ChatDotSquare,
} from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loading = ref(false);
const boundProviders = ref([]);
const hasPassword = ref(false);
const hasPhone = ref(false);

const supportedProviders = [
    { id: 'wechat', label: '微信', icon: ChatDotSquare, color: '#07c160' },
    { id: 'google', label: 'Google', icon: ChromeFilled, color: '#4285f4' },
    { id: 'github', label: 'GitHub', icon: ChromeFilled, color: '#24292f' },
    { id: 'apple', label: 'Apple', icon: Apple, color: '#000000' },
    { id: 'alipay', label: '支付宝', icon: Apple, color: '#1677ff' },
    { id: 'qq', label: 'QQ', icon: ChatDotSquare, color: '#12b7f5' },
];

function getBoundProvider(providerId) {
    return boundProviders.value.find(p => p.provider === providerId);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

async function loadData() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/oauth/providers');
        boundProviders.value = res.data?.oauth_providers || [];
        hasPassword.value = res.data?.has_password || false;
        hasPhone.value = res.data?.has_phone || false;
    } catch {
        boundProviders.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleBind(providerId) {
    try {
        await ElMessageBox.confirm(
            `即将跳转到 ${supportedProviders.find(p => p.id === providerId)?.label} 授权页面进行绑定。`,
            '确认绑定',
            {
                confirmButtonText: '前往授权',
                cancelButtonText: '取消',
                type: 'info',
            }
        );
        // 实际项目中这里会跳转到 OAuth 授权 URL
        ElMessage.info(`跳转到 ${supportedProviders.find(p => p.id === providerId)?.label} 授权...`);
    } catch {
        // cancelled
    }
}

async function handleUnbind(provider) {
    const providerInfo = supportedProviders.find(p => p.id === provider.provider);
    try {
        await ElMessageBox.confirm(
            `确定要解除 ${providerInfo?.label || provider.provider} 的绑定吗？解绑后该方式将无法登录。`,
            '确认解绑',
            {
                confirmButtonText: '确定解绑',
                cancelButtonText: '取消',
                type: 'warning',
            }
        );
        await apiClient.delete(`/oauth/unbind/${provider.id}`);
        ElMessage.success('解绑成功');
        loadData();
    } catch {
        // cancelled
    }
}

onMounted(() => {
    loadData();
});
</script>

<style scoped>
.account-binding-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.provider-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.provider-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    transition: all 0.2s;
}
.provider-card:hover {
    border-color: var(--el-color-primary-light-5);
    background: var(--el-color-info-light-9);
}

.provider-info {
    flex: 1;
    min-width: 0;
}
.provider-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    margin-bottom: 2px;
}
.provider-desc {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.provider-desc.bound {
    color: var(--el-color-success);
}
.bound-time {
    display: block;
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 2px;
}

.login-methods {
    display: flex;
    flex-direction: column;
}
.method-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
}
.method-left {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--el-text-color-primary);
}

.security-tips {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.tip-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--el-text-color-regular);
}

:deep(.el-card__body) { padding: 16px; }
</style>
