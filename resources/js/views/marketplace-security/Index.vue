<template>
    <div class="security-page">
        <div class="page-header">
            <div>
                <h2>{{ t('marketplace_security_page.title') }}</h2>
                <p class="text-muted">{{ t('marketplace_security_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="scanAllApps" :loading="scanningApps"><el-icon><Search /></el-icon> {{ t('marketplace_security_page.scan_apps') }}</el-button>
                <el-button @click="scanAllReviews" :loading="scanningReviews"><el-icon><Search /></el-icon> {{ t('marketplace_security_page.scan_reviews') }}</el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ stats.total_apps || 0 }}</div><div class="stat-label">{{ t('marketplace_security_page.stats.checked_apps') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value danger">{{ stats.flagged_apps || 0 }}</div><div class="stat-label">{{ t('marketplace_security_page.stats.flagged_apps') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value success">{{ stats.clean_apps || 0 }}</div><div class="stat-label">{{ t('marketplace_security_page.stats.clean_apps') }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-value warning">{{ stats.pending_reviews || 0 }}</div><div class="stat-label">{{ t('marketplace_security_page.stats.pending_reviews') }}</div></el-card></el-col>
        </el-row>

        <!-- 扫描结果 Tabs -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <el-tab-pane :label="t('marketplace_security_page.tabs.apps')" name="apps">
                    <div class="toolbar">
                        <span class="text-muted" v-if="appResults.length">{{ t('marketplace_security_page.toolbar.flagged_apps_count', { n: appResults.length }) }}</span>
                        <span class="text-muted" v-else>{{ t('marketplace_security_page.toolbar.no_flagged_apps') }}</span>
                    </div>
                    <el-table :data="appResults" v-loading="scanningApps" stripe>
                        <el-table-column :label="t('marketplace_security_page.columns.app')" min-width="200">
                            <template #default="{ row }">
                                <div class="app-cell">
                                    <span class="app-name">{{ row.app_name }}</span>
                                    <span class="text-muted small">{{ row.slug }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.violations')" width="80" prop="total" align="center">
                            <template #default="{ row }"><el-tag :type="row.total > 2 ? 'danger' : 'warning'" size="small">{{ row.total }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.severity')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="severityTag(row.max_severity)" size="small">{{ severityLabel(row.max_severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.details')" min-width="300">
                            <template #default="{ row }">
                                <div v-for="v in (row.violations || [])" :key="v.field" class="violation-item">
                                    <el-tag size="small" type="danger" style="margin-right:4px">{{ v.field_label }}</el-tag>
                                    <span class="text-muted small">{{ (v.words || []).slice(0,3).join(', ') }}{{ v.words?.length > 3 ? '...' : '' }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.actions')" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="$router.push('/app-marketplace/' + row.app_id)">{{ t('marketplace_security_page.view_app') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!appResults.length && !scanningApps" :description="t('marketplace_security_page.empty')" :image-size="50" />
                </el-tab-pane>

                <el-tab-pane :label="t('marketplace_security_page.tabs.reviews')" name="reviews">
                    <div class="toolbar">
                        <span class="text-muted" v-if="reviewResults.length">{{ t('marketplace_security_page.toolbar.flagged_reviews_count', { n: reviewResults.length }) }}</span>
                        <span class="text-muted" v-else>{{ t('marketplace_security_page.toolbar.no_flagged_reviews') }}</span>
                    </div>
                    <el-table :data="reviewResults" v-loading="scanningReviews" stripe>
                        <el-table-column :label="t('marketplace_security_page.columns.app')" min-width="160" prop="app_name" />
                        <el-table-column :label="t('marketplace_security_page.columns.user')" width="120" prop="user_name" />
                        <el-table-column :label="t('marketplace_security_page.columns.rating')" width="80">
                            <template #default="{ row }"><el-rate :model-value="row.rating" disabled size="small" /></template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.violations')" width="80" prop="total" align="center">
                            <template #default="{ row }"><el-tag :type="row.total > 2 ? 'danger' : 'warning'" size="small">{{ row.total }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.severity')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="severityTag(row.max_severity)" size="small">{{ severityLabel(row.max_severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('marketplace_security_page.columns.details')" min-width="250">
                            <template #default="{ row }">
                                <div v-for="v in (row.violations || [])" :key="v.field" class="violation-item">
                                    <span class="text-muted small">{{ (v.words || []).slice(0,3).join(', ') }}</span>
                                </div>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!reviewResults.length && !scanningReviews" :description="t('marketplace_security_page.empty')" :image-size="50" />
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import api from '@/api/marketplaceSecurity';

const { t } = useI18n();

const activeTab = ref('apps');
const stats = ref({});
const appResults = ref([]);
const reviewResults = ref([]);
const scanningApps = ref(false);
const scanningReviews = ref(false);

const severityLabels = computed(() => ({
    1: t('marketplace_security_page.severity.low'),
    2: t('marketplace_security_page.severity.medium'),
    3: t('marketplace_security_page.severity.high'),
}));

async function loadStats() {
    try { const { data: r } = await api.stats(); if (r.success) stats.value = r.data; } catch {}
}

async function scanAllApps() {
    scanningApps.value = true;
    try {
        const { data: r } = await api.scanAllApps();
        if (r.success) {
            appResults.value = r.data?.results || [];
            ElMessage.success(t('marketplace_security_page.messages.scan_apps_done', { n: r.data?.total_flagged || 0 }));
            loadStats();
        }
    } catch { ElMessage.error(t('marketplace_security_page.messages.scan_failed')); }
    finally { scanningApps.value = false; }
}

async function scanAllReviews() {
    scanningReviews.value = true;
    try {
        const { data: r } = await api.scanAllReviews();
        if (r.success) {
            reviewResults.value = r.data?.results || [];
            ElMessage.success(t('marketplace_security_page.messages.scan_reviews_done', { n: r.data?.total_flagged || 0 }));
        }
    } catch { ElMessage.error(t('marketplace_security_page.messages.scan_failed')); }
    finally { scanningReviews.value = false; }
}

function severityTag(s) { return { 1: 'info', 2: 'warning', 3: 'danger' }[s] || 'info'; }
function severityLabel(s) { return severityLabels.value[s] || t('marketplace_security_page.severity.unknown'); }

onMounted(loadStats);
</script>

<style scoped>
.security-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.text-muted { color: var(--el-text-color-secondary); font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.toolbar { display: flex; gap: 8px; margin-bottom: 16px; }
.stat-value { font-size: 24px; font-weight: 600; color: #303133; }
.stat-value.danger { color: #f56c6c; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.app-cell { display: flex; flex-direction: column; }
.app-name { font-weight: 500; }
.small { font-size: 12px; }
.violation-item { margin: 2px 0; }
</style>
