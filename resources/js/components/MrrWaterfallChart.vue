<template>
  <div class="mrr-waterfall-chart">
    <div class="chart-controls">
      <el-radio-group v-model="months" size="small" @change="$emit('refresh', months)">
        <el-radio-button :value="6">{{ t('mrr.months_6') }}</el-radio-button>
        <el-radio-button :value="12">{{ t('mrr.months_12') }}</el-radio-button>
        <el-radio-button :value="24">{{ t('mrr.months_24') }}</el-radio-button>
      </el-radio-group>
    </div>
    <div ref="chartRef" class="chart-container" v-loading="loading"></div>
    <div v-if="!chartData.length && !loading" class="empty-data">
      <el-empty :description="t('mrr.empty')" />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'

const { t, locale } = useI18n()

const props = defineProps({
  chartData: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showControls: { type: Boolean, default: true },
})

defineEmits(['refresh'])

const chartRef = ref(null)
const months = ref(6)
let chartInstance = null

function numberLocale() {
  return locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
}

function fmt(v) {
  return Number(v || 0).toLocaleString(numberLocale(), { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function compactAxis(v) {
  if (locale.value?.startsWith('zh') && v >= 10000) {
    return `${(v / 10000).toFixed(0)}${t('mrr.wan')}`
  }
  if (!locale.value?.startsWith('zh') && Math.abs(v) >= 1000) {
    return new Intl.NumberFormat(numberLocale(), { notation: 'compact', maximumFractionDigits: 1 }).format(v)
  }
  return fmt(v)
}

function renderChart() {
  if (!chartRef.value || !props.chartData.length) return

  nextTick(() => {
    if (!chartInstance) {
      chartInstance = echarts.init(chartRef.value)
    }

    const monthLabels = props.chartData.map(d => d.month_label || d.month)
    const baseData = []
    const changeData = []

    props.chartData.forEach((d, idx) => {
      const net = d.net_change || 0
      baseData.push(d.starting_mrr)
      changeData.push(net)
      if (idx === props.chartData.length - 1) {
        changeData[idx] = d.ending_mrr
      }
    })

    const option = {
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: function (params) {
          const d = props.chartData[params[0]?.dataIndex]
          if (!d) return ''
          const lines = [`<b>${d.month_label}</b>`]
          lines.push(`${t('mrr.starting')}: ¥${fmt(d.starting_mrr)}`)
          lines.push(`<span style="color:#67c23a">● ${t('mrr.new')}: +¥${fmt(d.new)}</span>`)
          lines.push(`<span style="color:#0f172a">● ${t('mrr.expansion')}: +¥${fmt(d.expansion)}</span>`)
          lines.push(`<span style="color:#e6a23c">● ${t('mrr.contraction')}: -¥${fmt(d.contraction)}</span>`)
          lines.push(`<span style="color:#f56c6c">● ${t('mrr.churned')}: -¥${fmt(d.churned)}</span>`)
          lines.push(`<hr style="margin:4px 0">`)
          lines.push(`<b>${t('mrr.ending')}: ¥${fmt(d.ending_mrr)}</b>`)
          if (d.active_subscriptions) {
            lines.push(`${t('mrr.active_subs')}: ${d.active_subscriptions}`)
          }
          return lines.join('<br>')
        },
      },
      grid: { left: '8%', right: '6%', top: 40, bottom: 30, containLabel: true },
      xAxis: {
        type: 'category',
        data: monthLabels,
        axisLabel: { interval: 0, rotate: monthLabels.length > 8 ? 45 : 0 },
      },
      yAxis: {
        type: 'value',
        name: t('mrr.y_axis'),
        axisLabel: {
          formatter: (v) => compactAxis(v),
        },
      },
      series: [
        {
          name: t('mrr.starting'),
          type: 'bar',
          stack: 'waterfall',
          itemStyle: { color: 'transparent' },
          data: baseData,
          emphasis: { itemStyle: { color: 'transparent' } },
        },
        {
          name: t('mrr.new'),
          type: 'bar',
          stack: 'waterfall',
          data: props.chartData.map(d => d.net_change >= 0 ? d.net_change : 0),
          itemStyle: {
            color: '#67c23a',
            borderRadius: [2, 2, 0, 0],
          },
          label: {
            show: true,
            position: 'top',
            formatter: (p) => p.value > 0 ? `+¥${fmt(p.value)}` : '',
            fontSize: 11,
            color: '#67c23a',
          },
        },
        {
          name: t('mrr.churned'),
          type: 'bar',
          stack: 'waterfall',
          data: props.chartData.map(d => d.net_change < 0 ? d.net_change : 0),
          itemStyle: {
            color: '#f56c6c',
            borderRadius: [0, 0, 2, 2],
          },
          label: {
            show: true,
            position: 'bottom',
            formatter: (p) => p.value < 0 ? `¥${fmt(p.value)}` : '',
            fontSize: 11,
            color: '#f56c6c',
          },
        },
      ],
    }

    chartInstance.setOption(option, true)
    chartInstance.resize()
  })
}

function handleResize() {
  chartInstance?.resize()
}

watch(() => props.chartData, () => renderChart(), { deep: true })
watch(() => props.loading, () => {
  if (!props.loading) renderChart()
})
watch(locale, () => renderChart())

onMounted(() => {
  window.addEventListener('resize', handleResize)
  renderChart()
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  chartInstance?.dispose()
  chartInstance = null
})
</script>

<style scoped>
.mrr-waterfall-chart {
  position: relative;
  min-height: 380px;
}
.chart-controls {
  margin-bottom: 12px;
  text-align: right;
}
.chart-container {
  width: 100%;
  height: 360px;
}
.empty-data {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 300px;
}
</style>
