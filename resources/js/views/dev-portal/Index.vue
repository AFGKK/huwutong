<template>
    <div class="dev-portal-page">
        <div class="page-header">
            <div>
                <h2>{{ t('dev_portal_page.title') }}</h2>
                <p class="text-muted">{{ t('dev_portal_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('dev_portal_page.refresh') }}</el-button>
            </div>
        </div>

        <!-- 欢迎横幅 -->
        <el-card shadow="never" class="welcome-card mb-4">
            <div class="welcome-content">
                <div>
                    <h3>{{ t('dev_portal_page.welcome_title') }}</h3>
                    <p class="text-muted">{{ t('dev_portal_page.welcome_desc') }}</p>
                </div>
                <el-button type="primary" size="large" @click="scrollTo('#sdks')">{{ t('dev_portal_page.start_integration') }} →</el-button>
            </div>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-icon" style="background:#f1f5f9"><el-icon :size="24" color="#0f172a"><Key /></el-icon></div>
                    <div class="metric-info"><div class="metric-value">{{ stats.api_key_count }}</div><div class="metric-label">{{ t('dev_portal_page.stats.api_keys') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-icon" style="background:#f0f9eb"><el-icon :size="24" color="#67c23a"><Monitor /></el-icon></div>
                    <div class="metric-info"><div class="metric-value">{{ stats.playground_today }}</div><div class="metric-label">{{ t('dev_portal_page.stats.playground_today') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-icon" style="background:#fdf6ec"><el-icon :size="24" color="#e6a23c"><Document /></el-icon></div>
                    <div class="metric-info"><div class="metric-value">{{ stats.api_endpoints_count }}</div><div class="metric-label">{{ t('dev_portal_page.stats.api_endpoints') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-icon" style="background:#f5f0ff"><el-icon :size="24" color="#b37feb"><Coin /></el-icon></div>
                    <div class="metric-info"><div class="metric-value">{{ stats.total_sdks }}</div><div class="metric-label">{{ t('dev_portal_page.stats.sdk_languages') }}</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 快速开始步骤 -->
        <el-card shadow="hover" class="mb-4">
            <template #header><span><el-icon><TrendCharts /></el-icon> {{ t('dev_portal_page.quickstart.title') }} <small class="text-muted">{{ t('dev_portal_page.quickstart.subtitle') }}</small></span></template>
            <el-row :gutter="16">
                <el-col v-for="(step, i) in localizedQuickstartSteps" :key="i" :xs="24" :sm="12" :md="0" style="display:contents">
                    <div class="quickstart-step">
                        <div class="step-number">{{ step.step }}</div>
                        <div class="step-content">
                            <div class="step-title">{{ step.title }}</div>
                            <div class="step-desc">{{ step.description }}</div>
                            <el-button v-if="step.link.startsWith('http')" text size="small" @click="openLink(step.link)">{{ t('dev_portal_page.go') }} →</el-button>
                            <router-link v-else :to="step.link"><el-button text size="small">{{ t('dev_portal_page.go') }} →</el-button></router-link>
                        </div>
                    </div>
                </el-col>
            </el-row>
        </el-card>

        <!-- SDK 下载 -->
        <el-card shadow="hover" class="mb-4" id="sdks">
            <template #header><span><el-icon><Coin /></el-icon> {{ t('dev_portal_page.sdk.title') }} <small class="text-muted">{{ t('dev_portal_page.sdk.subtitle') }}</small></span></template>
            <el-row :gutter="16">
                <el-col v-for="(sdk, i) in localizedSdks" :key="i" :xs="24" :sm="12" :md="8" style="margin-bottom:12px">
                    <el-card shadow="hover" class="sdk-card" :body-style="{ padding: '16px' }">
                        <div class="sdk-header">
                            <span class="sdk-lang">{{ sdk.language.toUpperCase() }}</span>
                            <el-tag size="small" type="success">{{ sdk.latest_version }}</el-tag>
                        </div>
                        <div class="sdk-name">{{ sdk.name }}</div>
                        <div class="sdk-desc">{{ sdk.description }}</div>
                        <div class="sdk-install">
                            <code>{{ sdk.install_command }}</code>
                            <el-button size="small" text @click="copyText(sdk.install_command)"><el-icon><CopyDocument /></el-icon></el-button>
                        </div>
                        <div class="sdk-actions">
                            <el-button size="small" @click="openLink(sdk.docs_url)">{{ t('dev_portal_page.docs') }}</el-button>
                            <el-button size="small" @click="openLink(sdk.repo_url)">{{ t('dev_portal_page.github') }}</el-button>
                        </div>
                    </el-card>
                </el-col>
            </el-row>
        </el-card>

        <!-- 快速链接 -->
        <el-card shadow="hover">
            <template #header><span><el-icon><Link /></el-icon> {{ t('dev_portal_page.tools.title') }}</span></template>
            <el-row :gutter="16">
                <el-col v-for="(link, i) in localizedQuickLinks" :key="i" :xs="12" :sm="8" :md="6" style="margin-bottom:12px">
                    <router-link :to="link.path" style="text-decoration:none">
                        <el-card shadow="hover" class="link-card" :body-style="{ padding: '16px', textAlign: 'center' }">
                            <el-icon :size="28" color="#0f172a" style="margin-bottom:8px"><component :is="iconMap[link.icon] || 'Document'" /></el-icon>
                            <div class="link-title">{{ link.title }}</div>
                        </el-card>
                    </router-link>
                </el-col>
            </el-row>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Key, Monitor, Document, Coin, Link, CopyDocument, TrendCharts } from '@element-plus/icons-vue';
import devPortalApi from '@/api/devPortal';

const { t } = useI18n();

const loading = ref(false);
const sdks = ref([]);
const quickLinks = ref([]);
const quickstartSteps = ref([]);
const stats = reactive({ api_key_count: 0, playground_today: 0, api_endpoints_count: 0, total_sdks: 0, sdk_versions: [] });

const iconMap = { TrendCharts, Document, Monitor, WarningFilled: Document, Link, DataBoard: Monitor, EditPen: Document, Connection: Link };

const quickLinkPathKeys = {
    '/quickstart': 'quickstart',
    '/api-docs': 'api_docs',
    '/playground': 'playground',
    '/error-codes': 'error_codes',
    '/webhook-endpoints': 'webhook_guide',
    '/telemetry': 'sdk_telemetry',
    '/sandbox': 'sandbox',
    '/api-versions': 'api_versions',
};

const sdkLangKeys = ['php', 'python', 'javascript', 'java', 'go', 'dotnet'];

const localizedQuickLinks = computed(() =>
    quickLinks.value.map((link) => {
        const key = quickLinkPathKeys[link.path];
        return {
            ...link,
            title: key ? t(`dev_portal_page.quick_links.${key}.title`) : link.title,
        };
    }),
);

const localizedQuickstartSteps = computed(() =>
    quickstartSteps.value.map((step) => ({
        ...step,
        title: t(`dev_portal_page.quickstart_steps.step_${step.step}.title`),
        description: t(`dev_portal_page.quickstart_steps.step_${step.step}.description`),
    })),
);

const localizedSdks = computed(() =>
    sdks.value.map((sdk) => {
        const lang = sdk.language?.toLowerCase();
        const desc = sdkLangKeys.includes(lang)
            ? t(`dev_portal_page.sdks.${lang}.description`)
            : sdk.description;
        return { ...sdk, description: desc };
    }),
);

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        const r = await devPortalApi.dashboard();
        const data = r.data?.data || {};
        sdks.value = data.sdks || [];
        quickLinks.value = data.quick_links || [];
        quickstartSteps.value = data.quickstart_steps || [];
        Object.assign(stats, data.stats || {});
    } catch (e) { ElMessage.error(t('messages.load_failed')); }
    finally { loading.value = false; }
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => ElMessage.success(t('marketplace_uploader.copied')));
}

function openLink(url) {
    window.open(url, '_blank');
}

function scrollTo(id) {
    setTimeout(() => document.querySelector(id)?.scrollIntoView({ behavior: 'smooth' }), 100);
}
</script>

<style scoped>
.dev-portal-page { padding: 16px; max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: #909399; }

.welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; }
.welcome-card :deep(.el-card__body) { padding: 32px; }
.welcome-content { display: flex; justify-content: space-between; align-items: center; }
.welcome-content h3 { margin: 0 0 8px; font-size: 24px; color: #fff; }
.welcome-content p { margin: 0; color: rgba(255,255,255,0.8); }

.metric-card { display: flex; align-items: center; padding: 8px; }
.metric-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.metric-info { margin-left: 12px; }
.metric-value { font-size: 22px; font-weight: 700; line-height: 1.2; }
.metric-label { font-size: 13px; color: #909399; }

.quickstart-step { display: flex; gap: 16px; padding: 16px; margin-bottom: 8px; background: #fafafa; border-radius: 8px; align-items: flex-start; }
.step-number { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.step-content { flex: 1; }
.step-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.step-desc { font-size: 13px; color: #909399; margin-bottom: 4px; }

.sdk-card { height: 100%; }
.sdk-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.sdk-lang { font-weight: 700; font-size: 13px; color: #0f172a; letter-spacing: 1px; }
.sdk-name { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.sdk-desc { font-size: 12px; color: #909399; margin-bottom: 8px; }
.sdk-install { background: #f5f7fa; padding: 8px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.sdk-install code { font-size: 11px; word-break: break-all; }
.sdk-actions { display: flex; gap: 8px; }

.link-card { transition: transform .2s; cursor: pointer; }
.link-card:hover { transform: translateY(-2px); }
.link-title { font-size: 13px; color: #333; font-weight: 500; }
</style>
