<template>
  <div class="heatmap-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><MapLocation /></el-icon>{{ t('heatmap_page.title') }}</h2>
      <div class="header-actions">
        <el-select v-model="selectedLayers" multiple :placeholder="t('heatmap_page.select_layers_ph')" style="width:280px;margin-right:8px">
          <el-option v-for="opt in layerSourceOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-select v-model="days" style="width:120px;margin-right:8px">
          <el-option v-for="opt in daysOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
        </el-select>
        <el-button type="primary" @click="loadData" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('heatmap_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.activated_countries }}</div>
          <div class="stat-label">{{ t('heatmap_page.stats.countries') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-active">
          <div class="stat-value">{{ stats.total_geo_points }}</div>
          <div class="stat-label">{{ t('heatmap_page.stats.geo_points') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.recent_30d_events }}</div>
          <div class="stat-label">{{ t('heatmap_page.stats.events_30d') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ activeLayerCount }}</div>
          <div class="stat-label">{{ t('heatmap_page.stats.active_layers') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 地图 + 国家排名 -->
    <el-row :gutter="16">
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <span>{{ t('heatmap_page.sections.geo_heatmap') }}</span>
            <el-tag v-if="mapLayerName" size="small" style="margin-left:8px">{{ mapLayerName }}</el-tag>
          </template>
          <div ref="mapRef" style="width:100%;height:520px"></div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header>
            <span>{{ t('heatmap_page.sections.country_ranking') }}</span>
            <el-tag size="small" style="margin-left:8px">{{ topCountries.length }}</el-tag>
          </template>
          <div class="country-list" v-if="topCountries.length">
            <div v-for="(c, i) in topCountries" :key="c.country_code" class="country-item" @click="drillDown(c.country_code)">
              <span class="rank-badge">{{ i + 1 }}</span>
              <span class="country-flag">{{ getFlagEmoji(c.country_code) }}</span>
              <span class="country-name">{{ c.country_name || c.country_code }}</span>
              <el-progress :percentage="getPercent(c)" :stroke-width="8" :status="getBarStatus(i)" />
              <span class="country-count">
                <template v-if="'total' in c">{{ formatNum(c.total) }}</template>
                <template v-else-if="'invoice_count' in c">{{ formatNum(c.invoice_count) }}</template>
              </span>
            </div>
          </div>
          <el-empty v-else :description="t('messages.no_data')" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 图层管理 -->
    <el-card shadow="hover" class="mt-4">
      <template #header>
        <span>{{ t('heatmap_page.sections.layer_management') }}</span>
        <el-button size="small" type="primary" style="float:right" @click="showLayerDialog = true">
          <el-icon><Plus /></el-icon> {{ t('heatmap_page.buttons.new_layer') }}
        </el-button>
      </template>
      <el-table :data="layers" stripe v-loading="layersLoading">
        <el-table-column prop="name" :label="t('heatmap_page.cols.name')" width="180" />
        <el-table-column prop="slug" :label="t('heatmap_page.cols.slug')" width="160" />
        <el-table-column :label="t('heatmap_page.cols.data_source')" width="140">
          <template #default="{ row }">
            <el-tag>{{ layerSourceLabel(row.data_source) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('heatmap_page.cols.type')" width="140">
          <template #default="{ row }">
            <el-tag type="info">{{ layerTypeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('heatmap_page.cols.status')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
              {{ row.is_active ? t('heatmap_page.status.enabled') : t('heatmap_page.status.disabled') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('heatmap_page.cols.actions')" width="140">
          <template #default="{ row }">
            <el-button size="small" @click="editLayer(row)">{{ t('actions.edit') }}</el-button>
            <el-button size="small" type="danger" @click="deleteLayer(row)">{{ t('actions.delete') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 图层编辑对话框 -->
    <el-dialog v-model="showLayerDialog" :title="editingLayer ? t('heatmap_page.dialog.edit_layer') : t('heatmap_page.dialog.new_layer')" width="520px">
      <el-form :model="layerForm" label-width="110px">
        <el-form-item :label="t('heatmap_page.cols.name')" required>
          <el-input v-model="layerForm.name" :placeholder="t('heatmap_page.dialog.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('heatmap_page.dialog.slug_label')" required>
          <el-input v-model="layerForm.slug" :placeholder="t('heatmap_page.dialog.slug_ph')" />
        </el-form-item>
        <el-form-item :label="t('heatmap_page.cols.data_source')" required>
          <el-select v-model="layerForm.data_source" style="width:100%">
            <el-option v-for="opt in layerSourceOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('heatmap_page.dialog.layer_type')">
          <el-select v-model="layerForm.type" style="width:100%">
            <el-option v-for="opt in layerTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showLayerDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveLayer" :loading="savingLayer">
          {{ editingLayer ? t('actions.save') : t('actions.create') }}
        </el-button>
      </template>
    </el-dialog>

    <!-- 国家钻取对话框 -->
    <el-dialog v-model="showDrillDialog" :title="t('heatmap_page.dialog.country_detail', { code: drillCountry })" width="700px">
      <el-row :gutter="16" v-if="drillData">
        <el-col :span="12">
          <el-card shadow="hover">
            <template #header><span>{{ t('heatmap_page.drill.event_distribution') }}</span></template>
            <div ref="drillEventChartRef" style="height:200px"></div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="hover">
            <template #header><span>{{ t('heatmap_page.drill.daily_trend') }}</span></template>
            <div ref="drillTrendChartRef" style="height:200px"></div>
          </el-card>
        </el-col>
        <el-col :span="24" class="mt-4">
          <el-card shadow="hover">
            <template #header><span>{{ t('heatmap_page.drill.city_distribution') }}</span></template>
            <el-table :data="drillData.cities" stripe size="small">
              <el-table-column prop="city" :label="t('heatmap_page.cols.city')" />
              <el-table-column prop="cnt" :label="t('heatmap_page.cols.event_count')" width="100" sortable />
            </el-table>
          </el-card>
        </el-col>
      </el-row>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { MapLocation, Refresh, Plus } from '@element-plus/icons-vue'
import * as echarts from 'echarts'
import heatmapApi from '../../api/heatmap'

const { t, locale } = useI18n()

// ── 状态 ──
const loading = ref(false)
const days = ref(30)
const selectedLayers = ref(['license_activations'])
const stats = ref({ activated_countries: 0, total_geo_points: 0, recent_30d_events: 0 })
const mapRef = ref(null)
const mapChart = ref(null)
const lastMapPoints = ref([])

const topCountries = ref([])
const activeLayers = ref([])
const layers = ref([])
const layersLoading = ref(false)
const showLayerDialog = ref(false)
const editingLayer = ref(null)
const layerForm = ref({
    name: '', slug: '', data_source: 'license_activations', type: 'heatmap_scatter',
})
const savingLayer = ref(false)

// 钻取
const showDrillDialog = ref(false)
const drillCountry = ref('')
const drillData = ref(null)
const drillEventChartRef = ref(null)
const drillTrendChartRef = ref(null)

const layerSourceKeys = ['license_activations', 'product_usage', 'api_calls', 'revenue']
const layerTypeKeys = ['heatmap_scatter', 'country_choropleth', 'region_bubble']

// ── 计算 ──
const layerSourceLabels = computed(() => ({
    license_activations: t('heatmap_page.sources.license_activations'),
    product_usage: t('heatmap_page.sources.product_usage'),
    api_calls: t('heatmap_page.sources.api_calls'),
    revenue: t('heatmap_page.sources.revenue'),
}))

const layerSourceOptions = computed(() =>
    layerSourceKeys.map((value) => ({ value, label: layerSourceLabels.value[value] }))
)

const layerTypeLabels = computed(() => ({
    heatmap_scatter: t('heatmap_page.layer_types.heatmap_scatter'),
    country_choropleth: t('heatmap_page.layer_types.country_choropleth'),
    region_bubble: t('heatmap_page.layer_types.region_bubble'),
}))

const layerTypeOptions = computed(() =>
    layerTypeKeys.map((value) => ({ value, label: layerTypeLabels.value[value] }))
)

const daysOptions = computed(() => [
    { label: t('heatmap_page.days.d7'), value: 7 },
    { label: t('heatmap_page.days.d30'), value: 30 },
    { label: t('heatmap_page.days.d90'), value: 90 },
    { label: t('heatmap_page.days.d365'), value: 365 },
])

const activeLayerCount = computed(() => selectedLayers.value.length)
const mapLayerName = computed(() =>
    selectedLayers.value.map((l) => layerSourceLabels.value[l] || l).join(' + ')
)

// ── 方法 ──
function formatNum(n) {
    if (!n && n !== 0) return '0'
    return Number(n).toLocaleString()
}

function getPercent(c) {
    const maxVal = Math.max(...topCountries.value.map(x => x.cnt || x.total || x.invoice_count || 0), 1)
    return Math.round(((c.cnt || c.total || c.invoice_count || 0) / maxVal) * 100)
}

function getBarStatus(i) {
    if (i === 0) return 'success'
    if (i < 3) return 'warning'
    return ''
}

function getFlagEmoji(code) {
    if (!code || code.length !== 2) return ''
    const base = 0x1F1E6
    const a = code.toUpperCase().charCodeAt(0) - 65
    const b = code.toUpperCase().charCodeAt(1) - 65
    if (a < 0 || a > 25 || b < 0 || b > 25) return ''
    return String.fromCodePoint(base + a, base + b)
}

function layerSourceLabel(source) {
    return layerSourceLabels.value[source] || source
}

function layerTypeLabel(type) {
    return layerTypeLabels.value[type] || type
}

async function loadDashboard() {
    try {
        const res = await heatmapApi.getDashboard()
        stats.value = res.data || {}
    } catch (e) {
        console.error('Failed to load heatmap dashboard', e)
    }
}

async function loadData() {
    loading.value = true
    try {
        const params = { days: days.value, layers: selectedLayers.value.join(',') }
        const res = await heatmapApi.getData(params)
        const data = res.data || {}

        // 找第一个有数据的图层展示
        let firstLayerData = null
        for (const layer of selectedLayers.value) {
            if (data[layer]) {
                firstLayerData = data[layer]
                break
            }
        }

        if (firstLayerData) {
            topCountries.value = firstLayerData.countries || []
            renderMap(firstLayerData.points || [])
        } else {
            topCountries.value = []
            renderMap([])
        }
    } catch (e) {
        console.error('Failed to load heatmap data', e)
        ElMessage.error(t('heatmap_page.messages.load_data_failed'))
    } finally {
        loading.value = false
    }
}

async function loadLayers() {
    layersLoading.value = true
    try {
        const res = await heatmapApi.getLayers()
        layers.value = res.data || []
    } catch (e) {
        console.error('Failed to load layers', e)
    } finally {
        layersLoading.value = false
    }
}

function saveLayer() {
    savingLayer.value = true
    const apiCall = editingLayer.value
        ? heatmapApi.updateLayer(editingLayer.value.id, layerForm.value)
        : heatmapApi.createLayer(layerForm.value)

    apiCall.then(() => {
        ElMessage.success(editingLayer.value ? t('heatmap_page.messages.layer_updated') : t('heatmap_page.messages.layer_created'))
        showLayerDialog.value = false
        editingLayer.value = null
        loadLayers()
    }).catch(e => {
        ElMessage.error(t('messages.failed') + ': ' + (e.response?.data?.message || e.message))
    }).finally(() => {
        savingLayer.value = false
    })
}

function editLayer(row) {
    editingLayer.value = row
    layerForm.value = {
        name: row.name,
        slug: row.slug,
        data_source: row.data_source,
        type: row.type || 'heatmap_scatter',
    }
    showLayerDialog.value = true
}

function deleteLayer(row) {
    ElMessageBox.confirm(
        t('heatmap_page.prompts.delete_layer', { name: row.name }),
        t('actions.confirm'),
        { type: 'warning' },
    ).then(() => {
        heatmapApi.deleteLayer(row.id).then(() => {
            ElMessage.success(t('heatmap_page.messages.deleted'))
            loadLayers()
        })
    }).catch(() => {})
}

async function drillDown(countryCode) {
    drillCountry.value = countryCode
    showDrillDialog.value = true
    try {
        const res = await heatmapApi.getCountryDetail(countryCode, { days: days.value })
        drillData.value = res.data
        await nextTick()
        renderDrillCharts()
    } catch (e) {
        console.error('Failed to load country detail', e)
    }
}

// ── ECharts 地图渲染 ──
function renderMap(points) {
    if (!mapRef.value) return
    lastMapPoints.value = points || []
    if (!mapChart.value) {
        mapChart.value = echarts.init(mapRef.value)
    }

    const scatterData = lastMapPoints.value.map(p => ({
        value: [parseFloat(p.longitude), parseFloat(p.latitude), parseInt(p.intensity) || 1],
        name: p.city || p.country_name || '',
    }))

    const option = {
        tooltip: {
            trigger: 'item',
            formatter: params => {
                if (params.seriesType === 'scatter') {
                    return `${params.name}<br/>${t('heatmap_page.charts.longitude')}: ${params.value[0]}<br/>${t('heatmap_page.charts.latitude')}: ${params.value[1]}<br/>${t('heatmap_page.charts.intensity')}: ${params.value[2]}`
                }
                return params.name
            },
        },
        visualMap: {
            min: 0,
            max: Math.max(...scatterData.map(d => d.value[2]), 1),
            inRange: {
                color: ['#313695', '#4575b4', '#74add1', '#abd9e9', '#fee090', '#fdae61', '#f46d43', '#d73027'],
            },
            text: [t('heatmap_page.charts.high'), t('heatmap_page.charts.low')],
            calculable: true,
            bottom: 20,
        },
        geo: {
            map: 'world',
            roam: true,
            label: { show: false },
            itemStyle: {
                areaColor: '#e8e8e8',
                borderColor: '#bbb',
                borderWidth: 0.5,
            },
            emphasis: {
                label: { show: true, color: '#333' },
                itemStyle: { areaColor: '#ffd666' },
            },
        },
        series: [{
            name: t('heatmap_page.charts.heat_distribution'),
            type: 'scatter',
            coordinateSystem: 'geo',
            data: scatterData,
            symbolSize: val => Math.max(4, Math.min(30, (val[2] || 1) * 2)),
            encode: { value: 2 },
            label: { show: false },
            emphasis: {
                scale: 1.5,
                label: { show: true, formatter: p => p.name, position: 'top' },
            },
        }],
    }

    mapChart.value.setOption(option, true)
}

function renderDrillCharts() {
    if (!drillData.value) return

    // 事件类型分布（饼图）
    if (drillEventChartRef.value) {
        const ec = echarts.init(drillEventChartRef.value)
        ec.setOption({
            tooltip: { trigger: 'item' },
            series: [{
                type: 'pie',
                radius: ['30%', '60%'],
                data: (drillData.value.events || []).map(e => ({
                    name: e.event_type,
                    value: e.cnt,
                })),
                label: { show: true, formatter: '{b}: {c}' },
            }],
        })
    }

    // 每日趋势（折线图）
    if (drillTrendChartRef.value) {
        const tc = echarts.init(drillTrendChartRef.value)
        const trend = drillData.value.daily_trend || []
        tc.setOption({
            tooltip: { trigger: 'axis' },
            xAxis: {
                type: 'category',
                data: trend.map(d => d.date),
                axisLabel: { rotate: 45 },
            },
            yAxis: { type: 'value' },
            series: [{
                type: 'line',
                data: trend.map(d => d.cnt),
                smooth: true,
                areaStyle: { opacity: 0.15 },
            }],
        })
    }
}

// ── 侦听图层/天数变化 ──
watch([selectedLayers, days], () => {
    loadData()
})

watch(locale, () => {
    if (mapChart.value) {
        renderMap(lastMapPoints.value)
    }
})

// ── 生命周期 ──
onMounted(() => {
    loadDashboard()
    loadLayers()
    loadData()

    // 注册世界地图（DataV.GeoAtlas 世界地图JSON）
    fetch('https://geo.datav.aliyun.com/areas_v3/bound/100000_full.json')
        .then(r => r.json())
        .then(worldJson => {
            echarts.registerMap('world', worldJson)
            loadData()
        })
        .catch(() => {
            // 如果地图加载失败，仍然显示数据
            console.warn('Failed to load world map, showing scatter without base map')
            loadData()
        })

    window.addEventListener('resize', handleResize)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
    mapChart.value?.dispose()
})

function handleResize() {
    mapChart.value?.resize()
}
</script>

<style scoped>
.heatmap-page {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0;
    font-size: 22px;
}

.header-actions {
    display: flex;
    align-items: center;
}

.mb-4 {
    margin-bottom: 16px;
}

.mt-4 {
    margin-top: 16px;
}

.stat-card {
    text-align: center;
}

.stat-card .stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}

.stat-card .stat-label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
}

.stat-active .stat-value {
    color: #0f172a;
}

.country-list {
    max-height: 460px;
    overflow-y: auto;
}

.country-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.country-item:hover {
    background: #f5f7fa;
}

.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e6e8eb;
    font-size: 11px;
    font-weight: 600;
    margin-right: 8px;
    flex-shrink: 0;
}

.country-flag {
    font-size: 18px;
    margin-right: 8px;
    flex-shrink: 0;
}

.country-name {
    flex: 1;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.el-progress {
    flex: 2;
    margin: 0 12px;
    min-width: 80px;
}

.country-count {
    font-size: 12px;
    color: #909399;
    white-space: nowrap;
    min-width: 40px;
    text-align: right;
}
</style>
