<template>
    <div class="oem-dashboard">
        <h2>OEM 白标系统</h2>

        <!-- 概览卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ currentTierName }}</div>
                        <div class="stat-label">当前套餐</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.domains_verified }}/{{ stats.domains_remaining + stats.domains_verified }}</div>
                        <div class="stat-label">已认证域名</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value" :class="stats.has_logo ? 'success' : 'muted'">
                            {{ stats.has_logo ? '✓ 已设置' : '未设置' }}
                        </div>
                        <div class="stat-label">品牌 Logo</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-card">
                        <div class="stat-value" :class="stats.has_custom_domain ? 'success' : 'muted'">
                            {{ stats.has_custom_domain ? '✓ 已绑定' : '未绑定' }}
                        </div>
                        <div class="stat-label">自定义域名</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: 套餐管理 -->
            <el-tab-pane label="套餐管理" name="plan">
                <el-row :gutter="20">
                    <el-col :span="8" v-for="(tier, key) in tiers" :key="key">
                        <el-card
                            shadow="hover"
                            :class="{ 'tier-card-current': currentTier === key, 'tier-card': true }"
                        >
                            <div class="tier-header">
                                <h3>{{ tier.name_zh }}</h3>
                                <div class="tier-price">
                                    <span class="price">¥{{ tier.price_monthly }}</span>
                                    <span class="period">/月</span>
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
                                当前套餐
                            </el-button>
                            <el-button
                                v-else
                                type="primary"
                                style="width: 100%"
                                @click="handleUpgrade(key)"
                                :disabled="loading"
                            >
                                {{ compareTier(key) > 0 ? '升级到此套餐' : '降级到此套餐' }}
                            </el-button>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- Tab 2: 品牌配置 -->
            <el-tab-pane label="品牌配置" name="branding">
                <el-card shadow="never">
                    <template #header>
                        <span>品牌设置</span>
                        <el-button type="primary" size="small" style="float: right;" @click="openBrandingPage">
                            打开完整品牌配置
                        </el-button>
                    </template>
                    <el-form :model="brandingForm" label-width="120px">
                        <el-form-item label="品牌名称">
                            <el-input v-model="brandingForm.brand_name" placeholder="输入品牌名称" />
                        </el-form-item>
                        <el-form-item label="品牌标语">
                            <el-input v-model="brandingForm.brand_slogan" placeholder="输入品牌标语" />
                        </el-form-item>
                        <el-form-item label="Logo URL">
                            <el-input v-model="brandingForm.logo_url" placeholder="https://..." />
                            <div v-if="brandingForm.logo_url" class="logo-preview">
                                <img :src="brandingForm.logo_url" alt="logo" style="max-height: 60px; margin-top: 8px;" />
                            </div>
                        </el-form-item>
                        <el-form-item label="主色调">
                            <el-color-picker v-model="brandingForm.primary_color" />
                        </el-form-item>
                        <el-form-item label="辅助色">
                            <el-color-picker v-model="brandingForm.secondary_color" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 品牌化登录页 (M3-47) -->
            <el-tab-pane label="品牌登录页" name="login">
                <el-card shadow="never">
                    <template #header>
                        <span>自定义域名登录页配置</span>
                        <el-tag v-if="stats.has_custom_domain" type="success" size="small" style="margin-left: 12px;">
                            自定义域名已绑定
                        </el-tag>
                        <el-tag v-else type="info" size="small" style="margin-left: 12px;">
                            请先绑定自定义域名
                        </el-tag>
                    </template>
                    <el-alert
                        title="品牌化登录页会在您绑定的自定义域名下显示，客户访问时将看到您的品牌而非平台默认页面。"
                        type="info"
                        show-icon
                        :closable="false"
                        style="margin-bottom: 20px;"
                    />
                    <el-form :model="loginForm" label-width="140px">
                        <el-form-item label="登录页标题">
                            <el-input v-model="loginForm.login_page_title" placeholder="例如: 欢迎登录" />
                        </el-form-item>
                        <el-form-item label="登录页副标题">
                            <el-input v-model="loginForm.login_page_subtitle" placeholder="例如: 使用您的账号登录{brand_name}" />
                        </el-form-item>
                        <el-form-item label="背景图 URL">
                            <el-input v-model="loginForm.login_bg_image" placeholder="https://... 可选背景图片" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveLoginConfig" :loading="saving">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card shadow="never" style="margin-top: 16px;">
                    <template #header>
                        <span>登录页预览</span>
                    </template>
                    <div class="login-preview" :style="loginPreviewStyle">
                        <div class="preview-logo" v-if="brandingForm.logo_url">
                            <img :src="brandingForm.logo_url" alt="logo" style="max-height: 60px;" />
                        </div>
                        <h3>{{ loginForm.login_page_title || '欢迎登录' }}</h3>
                        <p style="color: #666;">{{ loginForm.login_page_subtitle || '' }}</p>
                        <div class="preview-form">
                            <el-input placeholder="账号" style="margin-bottom: 12px;" disabled />
                            <el-input placeholder="密码" type="password" disabled />
                            <el-button type="primary" style="width: 100%; margin-top: 12px;" disabled>登 录</el-button>
                        </div>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 4: 变更历史 -->
            <el-tab-pane label="变更历史" name="history">
                <el-table :data="history" stripe v-loading="loadingHistory">
                    <el-table-column prop="change_type" label="类型" width="120">
                        <template #default="{ row }">
                            <el-tag :type="changeTagType(row.change_type)" size="small">{{ row.change_type }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="from_tier" label="原套餐" width="120" />
                    <el-table-column prop="to_tier" label="新套餐" width="120" />
                    <el-table-column prop="price" label="价格" width="100">
                        <template #default="{ row }">¥{{ row.price }}</template>
                    </el-table-column>
                    <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="created_at" label="时间" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Check, Close } from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getOemDashboard, getOemTiers, subscribeOem, getOemHistory, saveBrandedLogin } from '@/api/oem';
import { useRouter } from 'vue-router';

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
    primary_color: '#409eff',
    secondary_color: '#67c23a',
});

const loginForm = reactive({
    login_page_title: '',
    login_page_subtitle: '',
    login_bg_image: '',
});

const currentTierName = computed(() => {
    const config = tiers.value[currentTier.value];
    return config?.name_zh || currentTier.value;
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

const featureLabel = (key) => {
    const labels = {
        custom_logo: '自定义 Logo',
        brand_colors: '品牌色定制',
        brand_name_customization: '品牌名称',
        custom_favicon: '自定义 Favicon',
        custom_domain: '自定义域名',
        ssl_auto: '自动 SSL 证书',
        branded_login: '品牌化登录页',
        custom_email_domain: '自定义发信域名',
        remove_branding: '移除平台品牌',
        api_whitelabel: 'API 白标',
        custom_css: '自定义 CSS',
        custom_html: '自定义 HTML',
        multi_locale_branding: '多语言品牌',
        priority_support: '优先技术支持',
        max_domains: '域名数量',
        max_themes: '主题数量',
    };
    return labels[key] || key;
};

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
    const action = compareTier(tier) > 0 ? '升级' : '降级';
    try {
        await ElMessageBox.confirm(
            `确定${action}到「${tiers.value[tier]?.name_zh}」套餐？`,
            `${action}确认`,
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        loading.value = true;
        await subscribeOem({ tier });
        ElMessage.success(`${action}成功`);
        await loadDashboard();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(`${action}失败`);
        }
    } finally {
        loading.value = false;
    }
}

async function saveLoginConfig() {
    saving.value = true;
    try {
        await saveBrandedLogin(loginForm);
        ElMessage.success('登录页配置已保存');
    } catch (e) {
        ElMessage.error('保存失败');
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
    color: #409eff;
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
    border: 2px solid #409eff;
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
    color: #409eff;
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
