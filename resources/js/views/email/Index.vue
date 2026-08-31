<template>
    <div class="email-page">
        <div class="page-header">
            <div>
                <h2>{{ t('email_page.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('email_page.subtitle') }}</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('email_page.tabs.dashboard')" name="dashboard">
                <EmailDashboard />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.tracking')" name="tracking">
                <EmailTracking />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.templates')" name="templates">
                <EmailTemplates />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.drip')" name="drip">
                <EmailDrip />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.smtp')" name="smtp">
                <CustomerSmtp />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.fallback')" name="fallback">
                <SmtpFallback />
            </el-tab-pane>
            <el-tab-pane :label="t('email_page.tabs.whitelabel')" name="whitelabel">
                <EmailWhitelabel />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, defineAsyncComponent } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();
const activeTab = ref(route.query.tab || 'dashboard');

const EmailDashboard = defineAsyncComponent(() => import('@/views/email-dashboard/Index.vue'));
const EmailTracking = defineAsyncComponent(() => import('@/views/email-tracking/Index.vue'));
const EmailTemplates = defineAsyncComponent(() => import('@/views/email-templates/Index.vue'));
const EmailDrip = defineAsyncComponent(() => import('@/views/email-drip/Index.vue'));
const CustomerSmtp = defineAsyncComponent(() => import('@/views/customer-smtp/Index.vue'));
const SmtpFallback = defineAsyncComponent(() => import('@/views/smtp-fallback/Index.vue'));
const EmailWhitelabel = defineAsyncComponent(() => import('@/views/email-whitelabel/Index.vue'));

function onTabChange(tab) {
    router.replace({ query: { tab } });
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
