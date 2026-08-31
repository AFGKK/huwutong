<template>
    <div class="update-cdn-page">
        <div class="page-header">
            <h2>{{ t('update_cdn_page.title') }} <small class="text-muted">M2-69</small></h2>
            <div class="header-actions">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon> {{ t('updates_page.refresh') }}
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="tabLabels.dashboard" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ dash.totalPackages || 0 }}</div><div class="stat-label">{{ t('update_cdn_page.stats.total_packages') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-success">{{ dash.publishedPackages || 0 }}</div><div class="stat-label">{{ t('updates_page.stat_published') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ dash.monthlyDownloads || 0 }}</div><div class="stat-label">{{ t('update_cdn_page.stats.monthly_downloads') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">{{ formatBytes(dash.totalSize) }}</div><div class="stat-label">{{ t('update_cdn_page.stats.total_published_size') }}</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('update_cdn_page.sections.top_downloads') }}</span></template>
                            <el-table :data="dash.topPackages || []" size="small" stripe>
                                <el-table-column :label="t('update_cdn_page.columns.product_version')" min-width="160">
                                    <template #default="{ row }">{{ row.product }} v{{ row.version }}</template>
                                </el-table-column>
                                <el-table-column prop="downloads" :label="t('update_cdn_page.columns.downloads')" width="100" align="right" />
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('update_cdn_page.sections.daily_trend') }}</span></template>
                            <div v-if="dailyTrendLabels.length" class="trend-chart">
                                <div v-for="(count, idx) in dailyTrendValues" :key="idx" class="trend-bar-wrapper" :title="t('update_cdn_page.trend_tooltip', { date: dailyTrendLabels[idx], count })">
                                    <div class="trend-bar" :style="{ height: calcBar(count, dailyTrendValues) + '%' }"></div>
                                    <span class="trend-label">{{ dailyTrendLabels[idx].slice(5) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-center text-muted py-4">{{ t('messages.no_data') }}</div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.bandwidth" name="bandwidth">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ bw.monthly_gb || 0 }} GB</div><div class="stat-label">{{ t('update_cdn_page.stats.monthly_bandwidth') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-info">{{ bw.total_gb || 0 }} GB</div><div class="stat-label">{{ t('update_cdn_page.stats.total_bandwidth') }}</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-value" :class="bwLevelClass">{{ bwLevelLabel }}</div>
                                <div class="stat-label">{{ t('update_cdn_page.stats.bandwidth_level') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ bw.monthly_mb || 0 }} MB</div><div class="stat-label">{{ t('update_cdn_page.stats.monthly_bandwidth_mb') }}</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never">
                    <template #header><span>{{ t('update_cdn_page.sections.daily_bandwidth_7d') }}</span></template>
                    <el-table :data="bw.daily_bandwidth || []" size="small" stripe>
                        <el-table-column prop="date" :label="t('update_cdn_page.columns.date')" width="150" />
                        <el-table-column prop="bytes" :label="t('update_cdn_page.columns.bandwidth_bytes')" width="150" align="right">
                            <template #default="{ row }">{{ formatBytes(row.bytes) }}</template>
                        </el-table-column>
                        <el-table-column prop="downloads" :label="t('update_cdn_page.columns.downloads')" width="100" align="right" />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.logs" name="logs">
                <el-card shadow="never">
                    <el-table :data="logs" v-loading="loading" stripe border style="width:100%">
                        <el-table-column prop="created_at" :label="t('update_cdn_page.columns.time')" width="160">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('update_cdn_page.columns.package')" min-width="160">
                            <template #default="{ row }">{{ row.package?.product?.name }} v{{ row.package?.version }}</template>
                        </el-table-column>
                        <el-table-column prop="client_ip" :label="t('update_cdn_page.columns.ip')" width="140" />
                        <el-table-column prop="user_agent" :label="t('update_cdn_page.columns.user_agent')" min-width="200" show-overflow-tooltip />
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total" layout="total, sizes, prev, pager, next" @change="loadLogs" />
                    </div>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="tabLabels.cdn" name="cdn">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('update_cdn_page.sections.cdn_config') }}</span></template>
                            <div v-if="cdnConfig" class="config-list">
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.provider') }}</span><span>{{ cdnConfig.provider }}</span></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.status') }}</span><el-tag :type="cdnConfig.enabled ? 'success' : 'danger'" size="small">{{ cdnConfig.enabled ? t('update_cdn_page.status.enabled') : t('update_cdn_page.status.disabled') }}</el-tag></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.base_url') }}</span><code>{{ cdnConfig.base_url }}</code></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.cache_ttl') }}</span><span>{{ cdnConfig.cache_ttl }}s</span></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.signed_url_ttl') }}</span><span>{{ cdnConfig.signed_url_ttl }}s</span></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.cache_purge') }}</span><el-tag :type="cdnConfig.purge_enabled ? 'success' : 'info'" size="small">{{ cdnConfig.purge_enabled ? t('update_cdn_page.status.on') : t('update_cdn_page.status.off') }}</el-tag></div>
                                <div class="config-item"><span class="cfg-label">{{ t('update_cdn_page.config.resume') }}</span><el-tag :type="cdnConfig.resume_enabled ? 'success' : 'info'" size="small">{{ cdnConfig.resume_enabled ? t('update_cdn_page.status.on') : t('update_cdn_page.status.off') }}</el-tag></div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t('update_cdn_page.sections.manual_purge') }}</span></template>
                            <el-form label-position="top">
                                <el-form-item :label="t('update_cdn_page.purge.by_package_id')">
                                    <el-input-number v-model="purgePackageId" :min="1" style="width:200px" />
                                    <el-button class="ml-2" type="primary" size="small" @click="handlePurgePackage">{{ t('update_cdn_page.purge.purge_package') }}</el-button>
                                </el-form-item>
                                <el-form-item :label="t('update_cdn_page.purge.or_url')">
                                    <el-input v-model="purgeUrl" :placeholder="t('update_cdn_page.purge.url_ph')" />
                                    <el-button class="ml-2" type="primary" size="small" @click="handlePurgeUrl">{{ t('update_cdn_page.purge.purge_url') }}</el-button>
                                </el-form-item>
                            </el-form>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import { getCdnDashboard, getBandwidthStats, getDownloadLogs, purgeCdnCache, getCdnConfig } from '@/api/updateCdn';

const { t, locale } = useI18n();

const activeTab = ref('dashboard');
const loading = ref(false);
const dash = ref({});
const bw = ref({});
const logs = ref([]);
const cdnConfig = ref(null);
const page = ref(1);
const perPage = ref(25);
const total = ref(0);
const purgePackageId = ref(null);
const purgeUrl = ref('');

const tabLabels = computed(() => ({
    dashboard: t('update_cdn_page.tabs.dashboard'),
    bandwidth: t('update_cdn_page.tabs.bandwidth'),
    logs: t('update_cdn_page.tabs.logs'),
    cdn: t('update_cdn_page.tabs.cdn'),
}));

const dailyTrendLabels = computed(() => Object.keys(dash.value.dailyTrend || {}));
const dailyTrendValues = computed(() => Object.values(dash.value.dailyTrend || {}));

const bwLevelClass = computed(() => {
    const map = { normal: 'text-success', warning: 'text-warning', critical: 'text-danger' };
    return map[bw.value.level] || '';
});

const bwLevelLabel = computed(() => {
    const level = bw.value.level;
    if (level && ['normal', 'warning', 'critical'].includes(level)) {
        return t(`update_cdn_page.bandwidth_levels.${level}`);
    }
    return '—';
});

async function loadDashboard() {
    try {
        const { data: res } = await getCdnDashboard();
        dash.value = res.data || {};
    } catch { dash.value = {}; }
}

async function loadBandwidth() {
    try {
        const { data: res } = await getBandwidthStats();
        bw.value = res.data || {};
    } catch { bw.value = {}; }
}

async function loadLogs() {
    loading.value = true;
    try {
        const { data: res } = await getDownloadLogs({ page: page.value, per_page: perPage.value });
        logs.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch { logs.value = []; }
    finally { loading.value = false; }
}

async function loadConfig() {
    try {
        const { data: res } = await getCdnConfig();
        cdnConfig.value = res.data || null;
    } catch { cdnConfig.value = null; }
}

async function handlePurgePackage() {
    if (!purgePackageId.value) {
        ElMessage.warning(t('update_cdn_page.messages.enter_package_id'));
        return;
    }
    try {
        const { data: res } = await purgeCdnCache({ package_id: purgePackageId.value });
        if (res.data?.success) {
            ElMessage.success(t('update_cdn_page.messages.cache_purged'));
        } else if (res.data?.errors?.length) {
            ElMessage.success(t('update_cdn_page.messages.purge_partial', {
                success: res.data.purged?.length || 0,
                failed: res.data.errors.length,
            }));
        } else {
            ElMessage.success(t('messages.success'));
        }
    } catch { /* */ }
}

async function handlePurgeUrl() {
    if (!purgeUrl.value) {
        ElMessage.warning(t('update_cdn_page.messages.enter_url'));
        return;
    }
    try {
        const { data: res } = await purgeCdnCache({ url: purgeUrl.value });
        ElMessage.success(res.data?.success ? t('update_cdn_page.messages.url_cache_purged') : t('update_cdn_page.messages.purge_failed'));
    } catch { /* */ }
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

function formatTime(dateStr) {
    if (!dateStr) return '—';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function calcBar(count, values) {
    if (!values?.length || !count) return 0;
    const max = Math.max(...values);
    return max ? (count / max) * 100 : 0;
}

function loadAll() {
    loadDashboard();
    loadBandwidth();
    loadLogs();
    loadConfig();
}

onMounted(loadAll);
</script>

<style scoped>
.update-cdn-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: var(--el-text-color-secondary); }
.mb-4 { margin-bottom: 16px; }
.py-4 { padding: 24px 0; }
.text-center { text-align: center; }
.ml-2 { margin-left: 8px; }
.stat-item { text-align: center; padding: 12px 0; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.text-primary { color: var(--el-color-primary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }
.text-info { color: var(--el-color-info); }

.config-list { display: flex; flex-direction: column; gap: 8px; }
.config-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--el-border-color-light); }
.config-item:last-child { border-bottom: none; }
.cfg-label { font-weight: 600; color: var(--el-text-color-secondary); font-size: 13px; }

.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 16px; }

.trend-chart { display: flex; align-items: flex-end; gap: 3px; height: 140px; padding: 8px 0; }
.trend-bar-wrapper { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; justify-content: flex-end; }
.trend-bar { width: 100%; max-width: 20px; background: var(--el-color-primary); border-radius: 2px; min-height: 2px; }
.trend-label { font-size: 9px; color: var(--el-text-color-secondary); margin-top: 4px; }
</style>
