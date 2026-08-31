<template>
    <div class="account-binding-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t(`${P}.title`) }}</h2>
                <span class="header-subtitle">{{ t(`${P}.subtitle`) }}</span>
            </div>
        </div>

        <el-alert
            :title="t(`${P}.alert_title`)"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :description="t(`${P}.alert_desc`)"
        />

        <el-row :gutter="16">
            <el-col :span="16">
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.oauth_title`) }}</span>
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
                                    {{ t(`${P}.click_bind`, { name: provider.label }) }}
                                </div>
                                <div class="provider-desc bound" v-else>
                                    {{ t(`${P}.bound_as`, { name: getBoundProvider(provider.id).nickname || t(`${P}.unknown`) }) }}
                                    <span class="bound-time">
                                        {{ t(`${P}.bound_at`, { date: formatDate(getBoundProvider(provider.id).created_at) }) }}
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
                                    {{ t(`${P}.bind`) }}
                                </el-button>
                                <el-button
                                    v-else
                                    type="danger"
                                    plain
                                    size="small"
                                    @click="handleUnbind(getBoundProvider(provider.id))"
                                >
                                    {{ t(`${P}.unbind`) }}
                                </el-button>
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="8">
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.login_methods`) }}</span>
                        </div>
                    </template>

                    <div class="login-methods">
                        <div class="method-item">
                            <div class="method-left">
                                <el-icon :size="24" color="#67c23a"><Message /></el-icon>
                                <span>{{ t(`${P}.email_password`) }}</span>
                            </div>
                            <el-tag v-if="hasPassword" type="success" size="small" effect="dark">{{ t(`${P}.set`) }}</el-tag>
                            <el-button v-else text type="primary" size="small">{{ t(`${P}.set_now`) }}</el-button>
                        </div>
                        <el-divider style="margin: 8px 0;" />
                        <div class="method-item">
                            <div class="method-left">
                                <el-icon :size="24" color="#0f172a"><Iphone /></el-icon>
                                <span>{{ t(`${P}.phone`) }}</span>
                            </div>
                            <el-tag v-if="hasPhone" type="success" size="small" effect="dark">{{ t(`${P}.bound`) }}</el-tag>
                            <el-button v-else text type="primary" size="small">{{ t(`${P}.bind_phone`) }}</el-button>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.tips_title`) }}</span>
                        </div>
                    </template>

                    <div class="security-tips">
                        <div class="tip-item">
                            <el-icon color="#e6a23c"><WarningFilled /></el-icon>
                            <span>{{ t(`${P}.tip1`) }}</span>
                        </div>
                        <div class="tip-item">
                            <el-icon color="#0f172a"><InfoFilled /></el-icon>
                            <span>{{ t(`${P}.tip2`) }}</span>
                        </div>
                        <div class="tip-item">
                            <el-icon color="#f56c6c"><Remove /></el-icon>
                            <span>{{ t(`${P}.tip3`) }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Message, Iphone, WarningFilled, InfoFilled, Remove,
    ChromeFilled, ChatDotSquare,
} from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const { t, locale } = useI18n();
const P = 'account_binding_page';
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'));

const loading = ref(false);
const boundProviders = ref([]);
const hasPassword = ref(false);
const hasPhone = ref(false);

const supportedProviders = computed(() => [
    { id: 'wechat', label: t(`${P}.providers.wechat`), icon: ChatDotSquare, color: '#07c160' },
    { id: 'google', label: 'Google', icon: ChromeFilled, color: '#4285f4' },
    { id: 'github', label: 'GitHub', icon: ChromeFilled, color: '#24292f' },
    { id: 'qq', label: 'QQ', icon: ChatDotSquare, color: '#12b7f5' },
]);

function getBoundProvider(providerId) {
    return boundProviders.value.find(p => p.provider === providerId);
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(dateLocale.value, {
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
    const label = supportedProviders.value.find(p => p.id === providerId)?.label;
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_bind`, { name: label }),
            t(`${P}.bind_title`),
            {
                confirmButtonText: t(`${P}.go_auth`),
                cancelButtonText: t('actions.cancel'),
                type: 'info',
            }
        );
        const { data: res } = await apiClient.get(`/oauth/authorize-url/${providerId}`, {
            params: {
                intent: 'bind',
                return_to: '/build/account/binding',
            },
        });
        const url = res.data?.authorize_url;
        if (!url) {
            ElMessage.error(res.error?.message || res.message || t(`${P}.messages.no_url`));
            return;
        }
        window.location.href = url;
    } catch (e) {
        if (e === 'cancel' || e === 'close') return;
        ElMessage.error(e.response?.data?.error?.message || e.response?.data?.message || t(`${P}.messages.bind_failed`));
    }
}

async function handleUnbind(provider) {
    const providerInfo = supportedProviders.value.find(p => p.id === provider.provider);
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_unbind`, { name: providerInfo?.label || provider.provider }),
            t(`${P}.unbind_title`),
            {
                confirmButtonText: t(`${P}.confirm_unbind_btn`),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
            }
        );
        await apiClient.delete(`/oauth/unbind/${provider.id}`);
        ElMessage.success(t(`${P}.messages.unbound`));
        loadData();
    } catch (e) {
        if (e === 'cancel' || e === 'close') return;
        ElMessage.error(e.response?.data?.error?.message || e.response?.data?.message || t(`${P}.messages.unbind_failed`));
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
