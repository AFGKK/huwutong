<template>
    <div class="app-marketplace-page">
        <div class="page-header">
            <div>
                <h2>{{ t('app_marketplace_page.title') }}</h2>
                <p class="text-muted">{{ t('app_marketplace_page.subtitle') }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('actions.refresh') }}</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">{{ t('app_marketplace_page.stats.published') }}</div><div class="stat-value success">{{ stats.published_apps || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">{{ t('app_marketplace_page.stats.total_installs') }}</div><div class="stat-value primary">{{ stats.total_installations || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">{{ t('app_marketplace_page.stats.installed') }}</div><div class="stat-value">{{ stats.installed_count || 0 }}</div></el-card>
            </el-col>
            <el-col :xs="12" :sm="8" :md="6">
                <el-card shadow="hover"><div class="stat-label">{{ t('app_marketplace_page.stats.categories') }}</div><div class="stat-value">{{ stats.category_count || categories.length || 0 }}</div></el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="mb-4">
            <el-tabs v-model="rankingTab" @tab-change="loadRankings">
                <el-tab-pane :label="t('app_marketplace_page.tabs.downloads')" name="downloads">
                    <div class="ranking-list">
                        <div v-for="(app, idx) in rankings" :key="app.id" class="ranking-item" @click="$router.push('/app-marketplace/' + app.id)">
                            <span class="ranking-num" :class="{ 'top': idx < 3 }">{{ idx + 1 }}</span>
                            <el-avatar :size="28" :src="app.icon_url" icon="Grid" />
                            <div class="ranking-info">
                                <span class="ranking-name">{{ app.name }}</span>
                                <span class="ranking-meta">{{ t('app_marketplace_page.installs_n', { n: app.install_count || 0 }) }}</span>
                            </div>
                            <el-rate v-if="app.review_count > 0" :model-value="Math.round(app.avg_rating || 0)" disabled size="small" />
                        </div>
                        <el-empty v-if="!rankings.length" :description="t('messages.no_data')" :image-size="40" />
                    </div>
                </el-tab-pane>
                <el-tab-pane :label="t('app_marketplace_page.tabs.rating')" name="rating" />
                <el-tab-pane :label="t('app_marketplace_page.tabs.newest')" name="newest" />
            </el-tabs>
        </el-card>

        <el-card shadow="hover" class="mb-4">
            <div class="section-header">
                <span class="section-title">{{ t('app_marketplace_page.trend_title') }}</span>
                <el-radio-group v-model="trendRange" size="small" @change="loadTrend">
                    <el-radio-button value="7days">{{ t('app_marketplace_page.days_n', { n: 7 }) }}</el-radio-button>
                    <el-radio-button value="30days">{{ t('app_marketplace_page.days_n', { n: 30 }) }}</el-radio-button>
                </el-radio-group>
            </div>
            <div ref="trendChartRef" style="width:100%;height:260px"></div>
            <el-empty v-if="!trendLoading && !trendData?.labels?.length" :description="t('app_marketplace_page.empty_trend')" :image-size="40" />
        </el-card>

        <el-card shadow="hover" class="mb-4">
            <el-form :inline="true" @submit.prevent="loadApps">
                <el-form-item>
                    <el-input v-model="filters.search" :placeholder="t('app_marketplace_page.search_ph')" clearable style="width:220px" @clear="loadApps" @keyup.enter="loadApps" />
                </el-form-item>
                <el-form-item>
                    <el-select v-model="filters.category" :placeholder="t('app_marketplace_page.all_categories')" clearable style="width:140px" @change="loadApps">
                        <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :icon="Search">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="hover">
            <el-table :data="apps" stripe v-loading="appsLoading">
                <el-table-column :label="t('app_marketplace_page.cols.app')" min-width="180">
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
                <el-table-column :label="t('app_marketplace_page.cols.developer')" width="150">
                    <template #default="{ row }">{{ row.developer?.display_name || row.developer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('app_marketplace_page.cols.category')" width="100">
                    <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column :label="t('app_marketplace_page.cols.version')" width="80" prop="current_version" />
                <el-table-column :label="t('app_marketplace_page.cols.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                            {{ row.status === 'published' ? t('app_marketplace_page.published') : row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('app_marketplace_page.cols.installs')" width="70" prop="install_count" align="center" />
                <el-table-column :label="t('app_marketplace_page.cols.rating')" width="150">
                    <template #default="{ row }">
                        <el-rate v-if="row.review_count > 0" :model-value="Math.round(row.avg_rating || 0)" disabled size="small" />
                        <span v-else class="text-muted" style="font-size:12px">{{ t('app_marketplace_page.no_rating') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('app_marketplace_page.cols.published_at')" width="160">
                    <template #default="{ row }">{{ fmtDate(row.published_at || row.updated_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('app_marketplace_page.cols.actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="$router.push('/app-marketplace/' + row.id)">{{ t('actions.view_details') }}</el-button>
                        <el-button
                            size="small"
                            :type="row.installed ? 'default' : 'primary'"
                            :loading="installingId === row.id"
                            @click="toggleInstall(row)"
                        >
                            {{ row.installed ? t('app_marketplace_page.installed') : t('app_marketplace_page.install') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!apps.length && !appsLoading" :description="t('app_marketplace_page.empty_apps')" :image-size="60" />

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
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Search } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import api from '@/api/openPlatform'

const { t, locale } = useI18n()

const loading = ref(false)
const appsLoading = ref(false)
const apps = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const installingId = ref(null)

const rankingTab = ref('downloads')
const rankings = ref([])
const rankingsLoading = ref(false)

async function loadRankings() {
    rankingsLoading.value = true
    try {
        const { data: res } = await api.rankings({ type: rankingTab.value, limit: 10 })
        if (res.success) rankings.value = res.data?.apps || []
    } catch { rankings.value = [] }
    finally { rankingsLoading.value = false }
}

const trendChartRef = ref(null)
const trendRange = ref('7days')
const trendLoading = ref(false)
const trendData = ref({ labels: [], downloads: [] })
let trendChartInstance = null

async function loadTrend() {
    trendLoading.value = true
    try {
        const params = { days: trendRange.value === '7days' ? 7 : 30 }
        const { data: res } = await api.downloadTrend(params)
        const d = res.data || res
        trendData.value = { labels: d.labels || [], downloads: d.datasets?.[0]?.data || d.downloads || [] }
        nextTick(() => renderTrendChart())
    } catch { trendData.value = { labels: [], downloads: [] } }
    finally { trendLoading.value = false }
}

function renderTrendChart() {
    if (!trendChartRef.value || !trendData.value.labels?.length) return
    if (trendChartInstance) trendChartInstance.dispose()
    trendChartInstance = echarts.init(trendChartRef.value)
    trendChartInstance.setOption({
        tooltip: { trigger: 'axis' },
        grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
        xAxis: { type: 'category', data: trendData.value.labels, axisLabel: { rotate: 30 } },
        yAxis: { type: 'value', minInterval: 1 },
        series: [{
            type: 'bar', data: trendData.value.downloads,
            itemStyle: { color: '#0f172a', borderRadius: [4, 4, 0, 0] },
            barMaxWidth: 40,
        }],
    })
}

const stats = reactive({
    published_apps: 0,
    total_installations: 0,
    installed_count: 0,
    category_count: 0,
})

const categories = computed(() => [
    { value: 'automation', label: t('app_marketplace_page.categories.automation') },
    { value: 'analytics', label: t('app_marketplace_page.categories.analytics') },
    { value: 'notification', label: t('app_marketplace_page.categories.notification') },
    { value: 'integration', label: t('app_marketplace_page.categories.integration') },
    { value: 'security', label: t('app_marketplace_page.categories.security') },
    { value: 'other', label: t('app_marketplace_page.categories.other') },
])

const filters = reactive({
    search: '',
    category: '',
})

function categoryLabel(val) {
    const found = categories.value.find(c => c.value === val)
    return found?.label || val || '-'
}

function fmtDate(dateStr) {
    if (!dateStr) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
    })
}

async function loadApps() {
    appsLoading.value = true
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-published_at',
            status: 'published',
        }
        if (filters.search) params.search = filters.search
        if (filters.category) params.category = filters.category

        const { data: res } = await api.marketplace(params)
        const items = res.data?.data || res.data || []
        apps.value = items.map(a => ({ ...a, installed: a.installed || false }))
        total.value = res.meta?.total || res.data?.total || apps.value.length
    } catch {
        apps.value = []
    } finally {
        appsLoading.value = false
    }
}

async function loadStats() {
    try {
        const { data: res } = await api.stats()
        const s = res.data || {}
        stats.published_apps = s.published_apps || s.published || 0
        stats.total_installations = s.total_installations || s.installations || 0
        stats.installed_count = s.installed_count || 0
    } catch { /* ignore */ }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([loadApps(), loadStats(), loadRankings(), loadTrend()])
    loading.value = false
}

async function toggleInstall(app) {
    installingId.value = app.id
    try {
        if (app.installed) {
            await ElMessageBox.confirm(
                t('app_marketplace_page.uninstall_confirm', { name: app.name }),
                t('app_marketplace_page.uninstall_title')
            )
            await api.uninstallApp(app.id)
            app.installed = false
            ElMessage.success(t('app_marketplace_page.messages.uninstalled'))
        } else {
            await api.installApp(app.id)
            app.installed = true
            ElMessage.success(t('app_marketplace_page.messages.installed'))
        }
        loadStats()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || t('messages.failed'))
        }
    } finally {
        installingId.value = null
    }
}

onMounted(() => {
    refreshAll()
})
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
.stat-value.primary { color: #0f172a; }
.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
.app-info { display: flex; align-items: center; gap: 10px; }
.app-meta { line-height: 1.4; }
.app-name { font-weight: 500; }
.small { font-size: 12px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-title { font-weight: 600; }
.ranking-list { display: flex; flex-wrap: wrap; gap: 0; }
.ranking-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; width: 50%; cursor: pointer; border-radius: 6px; transition: background 0.2s; }
.ranking-item:hover { background: var(--el-fill-color-light); }
.ranking-num { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px; font-weight: 600; color: #909399; background: #f0f0f0; flex-shrink: 0; }
.ranking-num.top { color: #fff; background: #e6a23c; }
.ranking-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.ranking-name { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ranking-meta { font-size: 12px; color: #909399; }
</style>
