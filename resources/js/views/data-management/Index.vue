<template>
    <div class="data-page">
        <div class="page-header">
            <div>
                <h2>{{ t('data_management_page.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('data_management_page.subtitle') }}</p>
            </div>
        </div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('data_management_page.tabs.consents')" name="consents"><LegalConsents /></el-tab-pane>
            <el-tab-pane :label="t('data_management_page.tabs.residency')" name="residency"><DataResidency /></el-tab-pane>
            <el-tab-pane :label="t('data_management_page.tabs.anonymization')" name="anonymization"><DataAnonymization /></el-tab-pane>
            <el-tab-pane :label="t('data_management_page.tabs.regional')" name="regional"><RegionalCompliance /></el-tab-pane>
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
const activeTab = ref(route.query.tab || 'consents');
const LegalConsents = defineAsyncComponent(() => import('@/views/legal-consent/Index.vue'));
const DataResidency = defineAsyncComponent(() => import('@/views/data-residency/Index.vue'));
const DataAnonymization = defineAsyncComponent(() => import('@/views/system/DataAnonymization.vue'));
const RegionalCompliance = defineAsyncComponent(() => import('@/views/regional-compliance/Index.vue'));
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
