<template>
  <div ref="chartRef" class="health-trend-chart" v-loading="loading"></div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t, locale } = useI18n()

const props = defineProps({
  points: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const chartRef = ref(null)
let chartInstance = null

function renderChart() {
  if (!chartRef.value || !props.points.length) return

  nextTick(() => {
    if (!chartInstance) chartInstance = echarts.init(chartRef.value)

    const dates = props.points.map(p => p.date)
    const scores = props.points.map(p => p.avg_score)

    chartInstance.setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: 50, right: 20, top: 30, bottom: 30 },
      xAxis: { type: 'category', data: dates, axisLabel: { rotate: dates.length > 10 ? 45 : 0 } },
      yAxis: { type: 'value', name: t('csm_health.avg_score'), min: 0, max: 100 },
      series: [{
        name: t('csm_health.avg_health'),
        type: 'line',
        smooth: true,
        data: scores,
        areaStyle: { opacity: 0.15 },
        itemStyle: { color: '#0f172a' },
      }],
    }, true)
    chartInstance.resize()
  })
}

function handleResize() { chartInstance?.resize() }

watch(() => props.points, renderChart, { deep: true })
watch(() => props.loading, () => { if (!props.loading) renderChart() })
watch(locale, renderChart)

onMounted(() => {
  window.addEventListener('resize', handleResize)
  renderChart()
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  chartInstance?.dispose()
})
</script>

<style scoped>
.health-trend-chart { width: 100%; height: 280px; }
</style>
