<template>
    <div class="postman-page">
        <div class="page-header">
            <div>
                <h2>{{ t('postman_page.title') }}</h2>
                <p class="text-muted">{{ t('postman_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('postman_page.refresh') }}</el-button>
            </div>
        </div>

        <!-- 统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('postman_page.stats.total_endpoints') }}</div><div class="metric-value">{{ stats.total_endpoints }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('postman_page.stats.filtered_endpoints') }}</div><div class="metric-value">{{ stats.filtered_endpoints }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('postman_page.stats.environments') }}</div><div class="metric-value">{{ stats.environments }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('postman_page.stats.examples') }}</div><div class="metric-value">{{ stats.examples }}</div></el-card></el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4">
            <!-- 下载卡片 -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#0f172a"><Download /></el-icon>
                    <h3>{{ t('postman_page.cards.download_collection.title') }}</h3>
                    <p class="text-muted">{{ t('postman_page.cards.download_collection.desc') }}</p>
                    <el-button type="primary" @click="downloadCollection" :loading="dlLoading">{{ t('postman_page.cards.download_collection.btn') }}</el-button>
                </el-card>
            </el-col>

            <!-- Run in Postman -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#ff6c37"><Connection /></el-icon>
                    <h3>{{ t('postman_page.cards.run_in_postman.title') }}</h3>
                    <p class="text-muted">{{ t('postman_page.cards.run_in_postman.desc') }}</p>
                    <el-button type="warning" @click="runInPostman" :loading="ripLoading">{{ t('postman_page.cards.run_in_postman.btn') }}</el-button>
                </el-card>
            </el-col>

            <!-- 环境配置 -->
            <el-col :xs="24" :md="8">
                <el-card shadow="hover" class="action-card">
                    <el-icon :size="40" color="#67c23a"><Setting /></el-icon>
                    <h3>{{ t('postman_page.cards.environments.title') }}</h3>
                    <p class="text-muted">{{ t('postman_page.cards.environments.desc') }}</p>
                    <div class="env-buttons">
                        <el-button v-for="env in environments" :key="env.key" size="small" @click="downloadEnv(env.key)" :loading="envLoading === env.key">
                            {{ env.name }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 使用说明 -->
        <el-card shadow="hover">
            <template #header><span><el-icon><Reading /></el-icon> {{ t('postman_page.guide.title') }}</span></template>
            <el-steps :active="3" align-center simple>
                <el-step
                    v-for="(step, i) in usageSteps"
                    :key="i"
                    :title="step.title"
                    :description="step.description"
                />
            </el-steps>
            <el-alert :title="t('postman_page.guide.tip')" type="info" show-icon :closable="false" style="margin-top:16px" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Download, Connection, Setting, Reading } from '@element-plus/icons-vue';
import postmanApi from '@/api/postman';

const { t } = useI18n();

const loading = ref(false);
const dlLoading = ref(false);
const ripLoading = ref(false);
const envLoading = ref('');
const stats = reactive({ total_endpoints: 0, filtered_endpoints: 0, environments: 0, examples: 0, include_groups: [] });
const environments = ref([]);

const usageSteps = computed(() => [
    { title: t('postman_page.guide.steps.download.title'), description: t('postman_page.guide.steps.download.desc') },
    { title: t('postman_page.guide.steps.import.title'), description: t('postman_page.guide.steps.import.desc') },
    { title: t('postman_page.guide.steps.environment.title'), description: t('postman_page.guide.steps.environment.desc') },
    { title: t('postman_page.guide.steps.test.title'), description: t('postman_page.guide.steps.test.desc') },
]);

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        const [s, e] = await Promise.all([postmanApi.stats(), postmanApi.environments()]);
        Object.assign(stats, s.data?.data || {});
        environments.value = e.data?.data || [];
    } finally { loading.value = false; }
}

async function downloadCollection() {
    dlLoading.value = true;
    try {
        const res = await postmanApi.downloadCollection();
        const json = JSON.stringify(res.data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'huwutong-api.postman_collection.json'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('postman_page.messages.collection_downloaded'));
    } catch { ElMessage.error(t('messages.failed')); }
    finally { dlLoading.value = false; }
}

async function runInPostman() {
    ripLoading.value = true;
    try {
        const res = await postmanApi.runInPostman();
        const data = res.data?.data;
        if (!data) { ElMessage.error(t('messages.load_failed')); return; }
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'huwutong-postman-import.json'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('postman_page.messages.import_pack_downloaded'));
    } catch { ElMessage.error(t('postman_page.messages.run_in_postman_failed')); }
    finally { ripLoading.value = false; }
}

async function downloadEnv(key) {
    envLoading.value = key;
    try {
        const res = await postmanApi.downloadEnvironment(key);
        const json = JSON.stringify(res.data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `huwutong-${key}.postman_environment.json`; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('postman_page.messages.env_downloaded', { key }));
    } catch { ElMessage.error(t('messages.failed')); }
    finally { envLoading.value = ''; }
}
</script>

<style scoped>
.postman-page { padding: 16px; max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.text-muted { color: #909399; }

.metric-card { padding: 12px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 22px; font-weight: 700; }

.action-card { text-align: center; padding: 24px 16px; height: 100%; }
.action-card h3 { margin: 12px 0 8px; font-size: 18px; }
.action-card p { margin: 0 0 16px; font-size: 13px; }

.env-buttons { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
</style>
