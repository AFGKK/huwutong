<template>
    <div class="update-cdn-page">
        <div class="page-header">
            <h2>更新包 CDN 分发 <small class="text-muted">M2-69</small></h2>
            <div class="header-actions">
                <el-button @click="loadAll">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <!-- ═══════════ 概览 ═══════════ -->
            <el-tab-pane label="概览" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ dash.totalPackages || 0 }}</div><div class="stat-label">总更新包</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-success">{{ dash.publishedPackages || 0 }}</div><div class="stat-label">已发布</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ dash.monthlyDownloads || 0 }}</div><div class="stat-label">本月下载</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-danger">{{ formatBytes(dash.totalSize) }}</div><div class="stat-label">总发布大小</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>热门下载 Top 10</span></template>
                            <el-table :data="dash.topPackages || []" size="small" stripe>
                                <el-table-column label="产品/版本" min-width="160">
                                    <template #default="{ row }">{{ row.product }} v{{ row.version }}</template>
                                </el-table-column>
                                <el-table-column prop="downloads" label="下载次数" width="100" align="right" />
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>每日下载趋势（近 30 天）</span></template>
                            <div v-if="dailyTrendLabels.length" class="trend-chart">
                                <div v-for="(count, idx) in dailyTrendValues" :key="idx" class="trend-bar-wrapper" :title="`${dailyTrendLabels[idx]}: ${count} 次`">
                                    <div class="trend-bar" :style="{ height: calcBar(count, dailyTrendValues) + '%' }"></div>
                                    <span class="trend-label">{{ dailyTrendLabels[idx].slice(5) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-center text-muted py-4">暂无数据</div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ═══════════ 带宽监控 ═══════════ -->
            <el-tab-pane label="带宽监控" name="bandwidth">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-primary">{{ bw.monthly_gb || 0 }} GB</div><div class="stat-label">本月带宽</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value text-info">{{ bw.total_gb || 0 }} GB</div><div class="stat-label">累计带宽</div></div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-value" :class="bwLevelClass">{{ bw.level === 'normal' ? '正常' : bw.level === 'warning' ? '预警' : '超限' }}</div>
                                <div class="stat-label">带宽等级</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item"><div class="stat-value">{{ bw.monthly_mb || 0 }} MB</div><div class="stat-label">本月带宽(MB)</div></div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never">
                    <template #header><span>近 7 日每日带宽</span></template>
                    <el-table :data="bw.daily_bandwidth || []" size="small" stripe>
                        <el-table-column prop="date" label="日期" width="150" />
                        <el-table-column prop="bytes" label="带宽(Bytes)" width="150" align="right">
                            <template #default="{ row }">{{ formatBytes(row.bytes) }}</template>
                        </el-table-column>
                        <el-table-column prop="downloads" label="下载次数" width="100" align="right" />
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- ═══════════ 下载日志 ═══════════ -->
            <el-tab-pane label="下载日志" name="logs">
                <el-card shadow="never">
                    <el-table :data="logs" v-loading="loading" stripe border style="width:100%">
                        <el-table-column prop="created_at" label="时间" width="160">
                            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="包" min-width="160">
                            <template #default="{ row }">{{ row.package?.product?.name }} v{{ row.package?.version }}</template>
                        </el-table-column>
                        <el-table-column prop="client_ip" label="IP" width="140" />
                        <el-table-column prop="user_agent" label="User-Agent" min-width="200" show-overflow-tooltip />
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total" layout="total, sizes, prev, pager, next" @change="loadLogs" />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ═══════════ CDN 管理 ═══════════ -->
            <el-tab-pane label="CDN 管理" name="cdn">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>CDN 配置</span></template>
                            <div v-if="cdnConfig" class="config-list">
                                <div class="config-item"><span class="cfg-label">提供商</span><span>{{ cdnConfig.provider }}</span></div>
                                <div class="config-item"><span class="cfg-label">状态</span><el-tag :type="cdnConfig.enabled ? 'success' : 'danger'" size="small">{{ cdnConfig.enabled ? '已启用' : '已禁用' }}</el-tag></div>
                                <div class="config-item"><span class="cfg-label">基础 URL</span><code>{{ cdnConfig.base_url }}</code></div>
                                <div class="config-item"><span class="cfg-label">缓存 TTL</span><span>{{ cdnConfig.cache_ttl }}s</span></div>
                                <div class="config-item"><span class="cfg-label">签名 URL TTL</span><span>{{ cdnConfig.signed_url_ttl }}s</span></div>
                                <div class="config-item"><span class="cfg-label">缓存刷新</span><el-tag :type="cdnConfig.purge_enabled ? 'success' : 'info'" size="small">{{ cdnConfig.purge_enabled ? '已开启' : '已关闭' }}</el-tag></div>
                                <div class="config-item"><span class="cfg-label">断点续传</span><el-tag :type="cdnConfig.resume_enabled ? 'success' : 'info'" size="small">{{ cdnConfig.resume_enabled ? '已开启' : '已关闭' }}</el-tag></div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>手动刷新 CDN 缓存</span></template>
                            <el-form label-position="top">
                                <el-form-item label="按更新包 ID">
                                    <el-input-number v-model="purgePackageId" :min="1" style="width:200px" />
                                    <el-button class="ml-2" type="primary" size="small" @click="handlePurgePackage">刷新此包</el-button>
                                </el-form-item>
                                <el-form-item label="或输入 URL">
                                    <el-input v-model="purgeUrl" placeholder="https://cdn.huwutong.com/updates/..." />
                                    <el-button class="ml-2" type="primary" size="small" @click="handlePurgeUrl">刷新 URL</el-button>
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
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import { getCdnDashboard, getBandwidthStats, getDownloadLogs, purgeCdnCache, getCdnConfig } from '@/api/updateCdn';

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

const dailyTrendLabels = computed(() => Object.keys(dash.value.dailyTrend || {}));
const dailyTrendValues = computed(() => Object.values(dash.value.dailyTrend || {}));

const bwLevelClass = computed(() => {
    const map = { normal: 'text-success', warning: 'text-warning', critical: 'text-danger' };
    return map[bw.value.level] || '';
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
    if (!purgePackageId.value) { ElMessage.warning('请输入更新包 ID'); return; }
    try {
        const { data: res } = await purgeCdnCache({ package_id: purgePackageId.value });
        ElMessage.success(res.data?.success ? '缓存已刷新' : (res.data?.errors?.length ? `${res.data.purged?.length} 个成功, ${res.data.errors?.length} 个失败` : '操作完成'));
    } catch { /* */ }
}

async function handlePurgeUrl() {
    if (!purgeUrl.value) { ElMessage.warning('请输入 URL'); return; }
    try {
        const { data: res } = await purgeCdnCache({ url: purgeUrl.value });
        ElMessage.success(res.data?.success ? 'URL 缓存已刷新' : '刷新失败');
    } catch { /* */ }
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

function formatTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
