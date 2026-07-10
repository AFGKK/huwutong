<template>
    <div class="email-page">
        <div class="page-header">
            <div>
                <h2>邮件管理</h2>
                <p class="header-desc text-sm text-gray-500">邮件投递、模板、追踪、SMTP 配置、营销、白标</p>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane label="📊 投递面板" name="dashboard">
                <EmailDashboard />
            </el-tab-pane>
            <el-tab-pane label="📋 追踪" name="tracking">
                <EmailTracking />
            </el-tab-pane>
            <el-tab-pane label="📝 模板" name="templates">
                <EmailTemplates />
            </el-tab-pane>
            <el-tab-pane label="📧 营销" name="drip">
                <EmailDrip />
            </el-tab-pane>
            <el-tab-pane label="⚙️ SMTP" name="smtp">
                <CustomerSmtp />
            </el-tab-pane>
            <el-tab-pane label="🔄 降级" name="fallback">
                <SmtpFallback />
            </el-tab-pane>
            <el-tab-pane label="🏷️ 白标" name="whitelabel">
                <EmailWhitelabel />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const activeTab = ref(route.query.tab || 'dashboard');

const EmailDashboard = () => import('@/views/email-dashboard/Index.vue');
const EmailTracking = () => import('@/views/email-tracking/Index.vue');
const EmailTemplates = () => import('@/views/email-templates/Index.vue');
const EmailDrip = () => import('@/views/email-drip/Index.vue');
const CustomerSmtp = () => import('@/views/customer-smtp/Index.vue');
const SmtpFallback = () => import('@/views/smtp-fallback/Index.vue');
const EmailWhitelabel = () => import('@/views/email-whitelabel/Index.vue');

function onTabChange(tab) {
    router.replace({ query: { tab } });
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
