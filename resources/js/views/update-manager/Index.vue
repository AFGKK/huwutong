<template>
    <div class="update-page">
        <div class="page-header">
            <div>
                <h2>{{ t('update_manager_page.title') }}</h2>
                <p class="header-desc text-sm text-gray-500">{{ t('update_manager_page.subtitle') }}</p>
            </div>
        </div>
        <el-tabs v-model="activeTab" type="border-card" @tab-change="onTabChange">
            <el-tab-pane :label="t('update_manager_page.tabs.updates')" name="updates"><UpdatePackages /></el-tab-pane>
            <el-tab-pane :label="t('update_manager_page.tabs.signer')" name="signer"><UpdateSigner /></el-tab-pane>
            <el-tab-pane :label="t('update_manager_page.tabs.cdn')" name="cdn"><UpdateCdn /></el-tab-pane>
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
const activeTab = ref(route.query.tab || 'updates');
const UpdatePackages = defineAsyncComponent(() => import('@/views/updates/Index.vue'));
const UpdateSigner = defineAsyncComponent(() => import('@/views/update-signer/Index.vue'));
const UpdateCdn = defineAsyncComponent(() => import('@/views/update-cdn/Index.vue'));
function onTabChange(tab) { router.replace({ query: { tab } }); }
</script>
<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
