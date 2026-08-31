<template>
    <div class="oem-dashboard">
        <h2>{{ t('oem_page.title') }}</h2>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ currentTierName }}</div>
                        <div class="stat-label">{{ t('oem_page.stats.current_plan') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.domains_verified }}/{{ stats.domains_remaining + stats.domains_verified }}</div>
                        <div class="stat-label">{{ t('oem_page.stats.domains_verified') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value" :class="stats.has_logo ? 'success' : 'muted'">
                            {{ stats.has_logo ? t('oem_page.stats.logo_set') : t('oem_page.stats.logo_unset') }}
                        </div>
                        <div class="stat-label">{{ t('oem_page.stats.brand_logo') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value" :class="stats.has_custom_domain ? 'success' : 'muted'">
                            {{ stats.has_custom_domain ? t('oem_page.stats.domain_bound') : t('oem_page.stats.domain_unbound') }}
                        </div>
                        <div class="stat-label">{{ t('oem_page.stats.custom_domain') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: 套餐管理 -->
            <el-tab-pane :label="t('oem_page.tabs.plan')" name="plan">
                <el-row :gutter="20">
                    <el-col :span="8" v-for="(tier, key) in tiers" :key="key">
                        <el-card
                            shadow="hover"
                            :class="{ 'tier-card-current': currentTier === key, 'tier-card': true }"
                        >
                            <div class="tier-header">
                                <h3>{{ tierName(tier) }}</h3>
                                <div class="tier-price">
                                    <span class="price">¥{{ tier.price_monthly }}</span>
                                    <span class="period">{{ t('oem_page.plan.period_suffix') }}</span>
                                </div>
                            </div>
                            <el-divider />
                            <div class="tier-features">
                                <div v-for="(enabled, feat) in tier.features" :key="feat" class="feature-item">
                                    <el-icon :color="enabled ? '#67c23a' : '#c0c4cc'" style="margin-right: 6px;">
                                        <Check v-if="enabled" />
                                        <Close v-else />
                                    </el-icon>
                                    <span :class="{ 'feature-disabled': !enabled }">{{ featureLabel(feat) }}</span>
                                </div>
                            </div>
                            <el-divider />
                            <el-button
                                v-if="currentTier === key"
                                type="success"
                                disabled
                                style="width: 100%"
                            >
                                {{ t('oem_page.plan.current_plan') }}
                            </el-button>
                            <el-button
                                v-else
                                type="primary"
                                style="width: 100%"
                                @click="handleUpgrade(key)"
                                :disabled="loading"
                            >
                                {{ compareTier(key) > 0 ? t('oem_page.plan.upgrade_to') : t('oem_page.plan.downgrade_to') }}
                            </el-button>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- Tab 2: 品牌配置 -->
            <el-tab-pane :label="t('oem_page.tabs.branding')" name="branding">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('oem_page.branding.title') }}</span>
                        <el-button type="primary" size="small" style="float: right;" @click="openBrandingPage">
                            {{ t('oem_page.branding.open_full') }}
                        </el-button>
                    </template>
                    <el-form :model="brandingForm" label-width="120px">
                        <el-form-item :label="t('oem_page.branding.brand_name')">
                            <el-input v-model="brandingForm.brand_name" :placeholder="t('oem_page.branding.brand_name_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('oem_page.branding.brand_slogan')">
                            <el-input v-model="brandingForm.brand_slogan" :placeholder="t('oem_page.branding.brand_slogan_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('oem_page.branding.logo_url')">
                            <el-input v-model="brandingForm.logo_url" placeholder="https://..." />
                            <div v-if="brandingForm.logo_url" class="logo-preview">
                                <img :src="brandingForm.logo_url" alt="logo" style="max-height: 60px; margin-top: 8px;" />
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('oem_page.branding.primary_color')">
                            <el-color-picker v-model="brandingForm.primary_color" />
                        </el-form-item>
                        <el-form-item :label="t('oem_page.branding.secondary_color')">
                            <el-color-picker v-model="brandingForm.secondary_color" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 品牌化登录页 (M3-47) -->
            <el-tab-pane :label="t('oem_page.tabs.login')" name="login">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('oem_page.login.config_title') }}</span>
                        <el-tag v-if="stats.has_custom_domain" type="success" size="small" style="margin-left: 12px;">
                            {{ t('oem_page.login.domain_bound') }}
                        </el-tag>
                        <el-tag v-else type="info" size="small" style="margin-left: 12px;">
                            {{ t('oem_page.login.domain_required') }}
                        </el-tag>
                    </template>
                    <el-alert
                        :title="t('oem_page.login.alert')"
                        type="info"
                        show-icon
                        :closable="false"
                        style="margin-bottom: 20px;"
                    />
                    <el-form :model="loginForm" label-width="140px">
                        <el-form-item :label="t('oem_page.login.page_title')">
                            <el-input v-model="loginForm.login_page_title" :placeholder="t('oem_page.login.page_title_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('oem_page.login.page_subtitle')">
                            <el-input v-model="loginForm.login_page_subtitle" :placeholder="t('oem_page.login.page_subtitle_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('oem_page.login.bg_image')">
                            <el-input v-model="loginForm.login_bg_image" :placeholder="t('oem_page.login.bg_image_ph')" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveLoginConfig" :loading="saving">{{ t('oem_page.login.save_config') }}</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never" style="margin-top: 16px;">
                    <template #header>
                        <span>{{ t('oem_page.login.preview_title') }}</span>
                    </template>
                    <div class="login-preview" :style="loginPreviewStyle">
                        <div class="preview-logo" v-if="brandingForm.logo_url">
                            <img :src="brandingForm.logo_url" alt="logo" style="max-height: 60px;" />
                        </div>
                        <h3>{{ loginForm.login_page_title || t('oem_page.login.default_title') }}</h3>
                        <p style="color: #666;">{{ loginForm.login_page_subtitle || '' }}</p>
                        <div class="preview-form">
                            <el-input :placeholder="t('oem_page.login.account_ph')" style="margin-bottom: 12px;" disabled />
                            <el-input :placeholder="t('oem_page.login.password_ph')" type="password" disabled />
                            <el-button type="primary" style="width: 100%; margin-top: 12px;" disabled>{{ t('oem_page.login.login_btn') }}</el-button>
                        </div>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 4: 变更历史 -->
            <el-tab-pane :label="t('oem_page.tabs.history')" name="history">
                <el-table :data="history" stripe v-loading="loadingHistory">
                    <el-table-column prop="change_type" :label="t('oem_page.history.col_type')" width="120">
                        <template #default="{ row }">
                            <el-tag :type="changeTagType(row.change_type)" size="small">{{ changeTypeLabel(row.change_type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="from_tier" :label="t('oem_page.history.col_from_tier')" width="120" />
                    <el-table-column prop="to_tier" :label="t('oem_page.history.col_to_tier')" width="120" />
                    <el-table-column prop="price" :label="t('oem_page.history.col_price')" width="100">
                        <template #default="{ row }">¥{{ row.price }}</template>
                    </el-table-column>
                    <el-table-column prop="reason" :label="t('oem_page.history.col_reason')" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="created_at" :label="t('oem_page.history.col_time')" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Check, Close } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getOemDashboard, getOemTiers, subscribeOem, getOemHistory, saveBrandedLogin } from '@/api/oem';
import { useRouter } from 'vue-router';

const { t, locale } = useI18n();
const router = useRouter();

const activeTab = ref('plan');
const loading = ref(false);
const saving = ref(false);
const loadingHistory = ref(false);

const currentTier = ref('basic');
const tiers = ref({});
const stats = ref({});
const history = ref([]);
const brandingData = ref(null);

const brandingForm = reactive({
    brand_name: '',
    brand_slogan: '',
    logo_url: '',
    primary_color: '#0f172a',
    secondary_color: '#67c23a',
});

const loginForm = reactive({
    login_page_title: '',
    login_page_subtitle: '',
    login_bg_image: '',
});

const isZh = computed(() => locale.value.startsWith('zh'));

const featureLabels = computed(() => ({
    custom_logo: t('oem_page.features.custom_logo'),
    brand_colors: t('oem_page.features.brand_colors'),
    brand_name_customization: t('oem_page.features.brand_name_customization'),
    custom_favicon: t('oem_page.features.custom_favicon'),
    custom_domain: t('oem_page.features.custom_domain'),
    ssl_auto: t('oem_page.features.ssl_auto'),
    branded_login: t('oem_page.features.branded_login'),
    custom_email_domain: t('oem_page.features.custom_email_domain'),
    remove_branding: t('oem_page.features.remove_branding'),
    api_whitelabel: t('oem_page.features.api_whitelabel'),
    custom_css: t('oem_page.features.custom_css'),
    custom_html: t('oem_page.features.custom_html'),
    multi_locale_branding: t('oem_page.features.multi_locale_branding'),
    priority_support: t('oem_page.features.priority_support'),
    max_domains: t('oem_page.features.max_domains'),
    max_themes: t('oem_page.features.max_themes'),
}));

const changeTypeLabels = computed(() => ({
    activate: t('oem_page.change_types.activate'),
    upgrade: t('oem_page.change_types.upgrade'),
    downgrade: t('oem_page.change_types.downgrade'),
    renew: t('oem_page.change_types.renew'),
    cancel: t('oem_page.change_types.cancel'),
    reactivate: t('oem_page.change_types.reactivate'),
}));

const upgradeConfirmOptions = computed(() => ({
    confirmButtonText: t('actions.confirm'),
    cancelButtonText: t('actions.cancel'),
    type: 'warning',
}));

function tierName(tier) {
    if (!tier) return '';
    if (isZh.value) return tier.name_zh || tier.name || '';
    return tier.name_en || tier.name_zh || tier.name || '';
}

const currentTierName = computed(() => {
    const config = tiers.value[currentTier.value];
    return tierName(config) || currentTier.value;
});

const loginPreviewStyle = computed(() => {
    const bg = loginForm.login_bg_image
        ? `url(${loginForm.login_bg_image}) center/cover no-repeat`
        : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
    return {
        background: bg,
        padding: '30px',
        borderRadius: '8px',
        color: '#fff',
        textAlign: 'center',
        minHeight: '320px',
    };
});

const featureLabel = (key) => featureLabels.value[key] || key;

const changeTypeLabel = (type) => changeTypeLabels.value[type] || type;

const changeTagType = (type) => {
    const map = {
        activate: 'success',
        upgrade: 'primary',
        downgrade: 'warning',
        renew: 'info',
        cancel: 'danger',
        reactivate: 'success',
    };
    return map[type] || 'info';
};

const compareTier = (key) => {
    const order = ['basic', 'business', 'enterprise'];
    return order.indexOf(key) - order.indexOf(currentTier.value);
};

async function loadDashboard() {
    try {
        const res = await getOemDashboard();
        currentTier.value = res.subscription?.tier || 'basic';
        stats.value = res.stats || {};
        tiers.value = res.available_tiers || {};
        brandingData.value = res.subscription || null;

        // 加载品牌配置
        if (res.stats?.has_branding) {
            // branding config will be loaded from full branding page
        }
    } catch (e) {
        console.error('Failed to load OEM dashboard', e);
    }
}

async function loadHistory() {
    loadingHistory.value = true;
    try {
        const res = await getOemHistory();
        history.value = res || [];
    } catch (e) {
        console.error('Failed to load OEM history', e);
    } finally {
        loadingHistory.value = false;
    }
}

async function handleUpgrade(tier) {
    const isUpgrade = compareTier(tier) > 0;
    const tierLabel = tierName(tiers.value[tier]);
    try {
        await ElMessageBox.confirm(
            isUpgrade
                ? t('oem_page.plan.upgrade_confirm', { tier: tierLabel })
                : t('oem_page.plan.downgrade_confirm', { tier: tierLabel }),
            isUpgrade ? t('oem_page.plan.upgrade_confirm_title') : t('oem_page.plan.downgrade_confirm_title'),
            upgradeConfirmOptions.value,
        );
        loading.value = true;
        await subscribeOem({ tier });
        ElMessage.success(isUpgrade ? t('oem_page.plan.upgrade_success') : t('oem_page.plan.downgrade_success'));
        await loadDashboard();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(isUpgrade ? t('oem_page.plan.upgrade_failed') : t('oem_page.plan.downgrade_failed'));
        }
    } finally {
        loading.value = false;
    }
}

async function saveLoginConfig() {
    saving.value = true;
    try {
        await saveBrandedLogin(loginForm);
        ElMessage.success(t('oem_page.login.saved'));
    } catch (e) {
        ElMessage.error(t('messages.failed'));
    } finally {
        saving.value = false;
    }
}

function openBrandingPage() {
    router.push('/admin/portal-branding');
}

onMounted(() => {
    loadDashboard();
    loadHistory();
});
</script>

<style scoped>
.oem-dashboard {
    padding: 20px;
}
.stats-row {
    margin-bottom: 20px;
}
.stat-card {
    text-align: center;
    padding: 10px 0;
}
.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}
.stat-value.success {
    color: #67c23a;
}
.stat-value.muted {
    color: #909399;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 6px;
}
.tier-card {
    transition: all 0.3s;
}
.tier-card-current {
    border: 2px solid #0f172a;
}
.tier-header {
    text-align: center;
    padding: 10px 0;
}
.tier-price {
    margin-top: 12px;
}
.price {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
}
.period {
    color: #909399;
    font-size: 14px;
}
.tier-features {
    padding: 0 10px;
}
.feature-item {
    display: flex;
    align-items: center;
    padding: 6px 0;
    font-size: 14px;
}
.feature-disabled {
    color: #c0c4cc;
}
.logo-preview img {
    border-radius: 4px;
    border: 1px solid #eee;
}
.login-preview {
    position: relative;
}
.preview-form {
    max-width: 300px;
    margin: 20px auto 0;
}
.preview-form :deep(.el-input__wrapper) {
    background: rgba(255,255,255,0.9);
}
</style>
