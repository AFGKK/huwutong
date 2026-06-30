<template>
    <div class="app-marketplace-page">
        <div class="page-header">
            <div>
                <h2>应用市场</h2>
                <p class="text-muted">浏览和安装第三方开发者提供的插件与应用</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">已上架应用</div><div class="stat-value success">{{ stats.published_apps || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">总安装数</div><div class="stat-value primary">{{ stats.total_installations || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">已安装</div><div class="stat-value">{{ stats.installed_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">分类</div><div class="stat-value">{{ stats.category_count || categories.length || 0 }}</div></el-card>
            </el-col>
        </el-row>

        <!-- 排行榜 Tab -->
        <el-card shadow="hover" class="mb-4">
            <el-tabs v-model="rankingTab" @tab-change="loadRankings">
                <el-tab-pane label="🔥 热门下载" name="downloads">
                    <div class="ranking-list">
                        <div v-for="(app, idx) in rankings" :key="app.id" class="ranking-item" @click="$router.push('/app-marketplace/' + app.id)">
                            <span class="ranking-num" :class="{ 'top': idx < 3 }">{{ idx + 1 }}</span>
                            <el-avatar :size="28" :src="app.icon_url" icon="Grid" />
                            <div class="ranking-info">
                                <span class="ranking-name">{{ app.name }}</span>
                                <span class="ranking-meta">{{ (app.install_count || 0) + ' 次安装' }}</span>
                            </div>
                            <el-rate v-if="app.review_count > 0" :model-value="Math.round(app.avg_rating || 0)" disabled size="small" />
                        </div>
                        <el-empty v-if="!rankings.length" description="暂无数据" :image-size="40" />
                    </div>
                </el-tab-pane>
                <el-tab-pane label="⭐ 评分最高" name="rating" />
                <el-tab-pane label="🆕 最新上架" name="newest" />
            </el-tabs>
        </el-card>

        <!-- 下载趋势 -->
        <el-card shadow="hover" class="mb-4">
            <div class="section-header">
                <span class="section-title">📈 下载趋势</span>
                <el-radio-group v-model="trendRange" size="small" @change="loadTrend">
                    <el-radio-button value="7days">7天</el-radio-button>
                    <el-radio-button value="30days">30天</el-radio-button>
                </el-radio-group>
            </div>
            <div ref="trendChartRef" style="width:100%;height:260px"></div>
            <el-empty v-if="!trendLoading && !trendData?.labels?.length" description="暂无下载数据" :image-size="40" />
        </el-card>

        <!-- 搜索与筛选 -->
        <el-card shadow="hover" class="mb-4">
            <el-form :inline="true" @submit.prevent="loadApps">
                <el-form-item>
                    <el-input v-model="filters.search" placeholder="搜索应用名称..." clearable style="width:220px" @clear="loadApps" @keyup.enter="loadApps" />
                </el-form-item>
                <el-form-item>
                    <el-select v-model="filters.category" placeholder="全部分类" clearable style="width:140px" @change="loadApps">
                        <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :icon="Search">搜索</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 应用列表 -->
        <el-card shadow="hover">
            <el-table :data="apps" stripe v-loading="appsLoading">
                <el-table-column label="应用" min-width="180">
                    <template #default="{ row }">
                        <div class="app-info">
                            <el-avatar :size="40" :src="row.logo_url" icon="Grid" />
                            <div class="app-meta">
                                <div class="app-name">{{ row.name }}</div>
                                <div class="text-muted small">{{ row.slug }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="开发者" width="150">
                    <template #default="{ row }">{{ row.developer?.display_name || row.developer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="分类" width="100">
                    <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column label="版本" width="80" prop="current_version" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ row.status === 'published' ? '已上架' : row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="安装" width="70" prop="install_count" align="center" />
                <el-table-column label="评分" width="150">
                    <template #default="{ row }">
                        <el-rate v-if="row.review_count > 0" :model-value="Math.round(row.avg_rating || 0)" disabled size="small" />
                        <span v-else class="text-muted" style="font-size:12px">暂无评分</span>
                    </template>
                </el-table-column>
                <el-table-column label="上架时间" width="160">
                    <template #default="{ row }">{{ fmtDate(row.published_at || row.updated_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="$router.push('/app-marketplace/' + row.id)">详情</el-button>
                        <el-button
                            size="small"
                            :type="row.installed ? 'default' : 'primary'"
                            :loading="installingId === row.id"
                            @click="toggleInstall(row)"
                        >
                            {{ row.installed ? '已安装' : '安装' }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!apps.length && !appsLoading" description="暂无应用" :image-size="60" />

            <!-- 分页 -->
            <div class="pagination-wrap" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="loadApps"
                    @size-change="loadApps"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Search, Grid } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import api from '@/api/openPlatform';

const loading = ref(false);
const appsLoading = ref(false);
const apps = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const installingId = ref(null);

// ─── 排行榜 ───
const rankingTab = ref('downloads');
const rankings = ref([]);
const rankingsLoading = ref(false);

async function loadRankings() {
    rankingsLoading.value = true;
    try {
        const { data: res } = await api.rankings({ type: rankingTab.value, limit: 10 });
        if (res.success) rankings.value = res.data?.apps || [];
    } catch { rankings.value = []; }
    finally { rankingsLoading.value = false; }
}

// ─── 下载趋势 ───
const trendChartRef = ref(null);
const trendRange = ref('7days');
const trendLoading = ref(false);
const trendData = ref({ labels: [], downloads: [] });
let trendChartInstance = null;

async function loadTrend() {
    trendLoading.value = true;
    try {
        const params = { days: trendRange.value === '7days' ? 7 : 30 };
        const { data: res } = await api.downloadTrend(params);
        const d = res.data || res;
        trendData.value = { labels: d.labels || [], downloads: d.datasets?.[0]?.data || d.downloads || [] };
        nextTick(() => renderTrendChart());
    } catch { trendData.value = { labels: [], downloads: [] }; }
    finally { trendLoading.value = false; }
}

function renderTrendChart() {
    if (!trendChartRef.value || !trendData.value.labels?.length) return;
    if (trendChartInstance) trendChartInstance.dispose();
    trendChartInstance = echarts.init(trendChartRef.value);
    trendChartInstance.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: trendData.value.labels, axisLabel: { rotate: 30 } },
        yAxis: { type: 'value', minInterval: 1 },
        series: [{
            type: 'bar', data: trendData.value.downloads,
            itemStyle: { color: '#409eff', borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 40,
        }],
    });
}

const stats = reactive({
    published_apps: 0,
    total_installations: 0,
    installed_count: 0,
    category_count: 0,
});

const categories = [
    { value: 'automation', label: '自动化' },
    { value: 'analytics', label: '数据分析' },
    { value: 'notification', label: '通知' },
    { value: 'integration', label: '集成' },
    { value: 'security', label: '安全' },
    { value: 'other', label: '其他' },
];

const filters = reactive({
    search: '',
    category: '',
});

const CATEGORY_MAP = {};
categories.forEach(c => { CATEGORY_MAP[c.value] = c.label; });

function categoryLabel(val) { return CATEGORY_MAP[val] || val || '-'; }

function fmtDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

async function loadApps() {
    appsLoading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-published_at',
            status: 'published',
        };
        if (filters.search) params.search = filters.search;
        if (filters.category) params.category = filters.category;

        const { data: res } = await api.marketplace(params);
        const items = res.data?.data || res.data || [];
        apps.value = items.map(a => ({ ...a, installed: a.installed || false }));
        total.value = res.meta?.total || res.data?.total || apps.value.length;
    } catch {
        apps.value = [];
    } finally {
        appsLoading.value = false;
    }
}

async function loadStats() {
    try {
        const { data: res } = await api.stats();
        const s = res.data || {};
        stats.published_apps = s.published_apps || s.published || 0;
        stats.total_installations = s.total_installations || s.installations || 0;
        stats.installed_count = s.installed_count || 0;
    } catch { /* ignore */ }
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadApps(), loadStats(), loadRankings(), loadTrend()]);
    loading.value = false;
}


async function toggleInstall(app) {
    installingId.value = app.id;
    try {
        if (app.installed) {
            await ElMessageBox.confirm(`确定要卸载「${app.name}」吗？`, '确认卸载');
            await api.uninstallApp(app.id);
            app.installed = false;
            ElMessage.success('已卸载');
        } else {
            await api.installApp(app.id);
            app.installed = true;
            ElMessage.success('安装成功');
        }
        loadStats();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '操作失败');
        }
    } finally {
        installingId.value = null;
    }
}

onMounted(() => {
    refreshAll();
});
</script>

<style scoped>
.app-marketplace-page { padding: 20px; }
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-value.success { color: #67c23a; }
.stat-value.primary { color: #409eff; }
.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
.app-info { display: flex; align-items: center; gap: 10px; }
.app-meta { line-height: 1.4; }
.app-name { font-weight: 500; }
.small { font-size: 12px; }
.app-description { white-space: pre-wrap; line-height: 1.6; color: #606266; font-size: 14px; }
.ranking-list { display: flex; flex-wrap: wrap; gap: 0; }
.ranking-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; width: 50%; cursor: pointer; border-radius: 6px; transition: background 0.2s; }
.ranking-item:hover { background: var(--el-fill-color-light); }
.ranking-num { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px; font-weight: 600; color: #909399; background: #f0f0f0; flex-shrink: 0; }
.ranking-num.top { color: #fff; background: #e6a23c; }
.ranking-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ranking-name { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ranking-meta { font-size: 12px; color: #909399; }
</style>
