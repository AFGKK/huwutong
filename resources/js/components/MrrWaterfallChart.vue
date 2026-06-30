<template>
  <div class="mrr-waterfall-chart">
    <div class="chart-controls">
      <el-radio-group v-model="months" size="small" @change="$emit('refresh', months)">
        <el-radio-button :value="6">近6月</el-radio-button>
        <el-radio-button :value="12">近12月</el-radio-button>
        <el-radio-button :value="24">近24月</el-radio-button>
      </el-radio-group>
    </div>
    <div ref="chartRef" class="chart-container" v-loading="loading"></div>
    <div v-if="!chartData.length && !loading" class="empty-data">
      <el-empty description="暂无MRR数据" />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import * as echarts from 'echarts';

const props = defineProps({
  chartData: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showControls: { type: Boolean, default: true },
});

defineEmits(['refresh']);

const chartRef = ref(null);
const months = ref(6);
let chartInstance = null;

function renderChart() {
  if (!chartRef.value || !props.chartData.length) return;

  nextTick(() => {
    if (!chartInstance) {
      chartInstance = echarts.init(chartRef.value);
    }

    const months = props.chartData.map(d => d.month_label || d.month);
    const starting = props.chartData.map(d => d.starting_mrr);
    const ending = props.chartData.map(d => d.ending_mrr);

    // 瀑布图：每个月的 MRR 构成
    // 需要 4 个系列: new(绿色), expansion(蓝色), contraction(橙色), churned(红色)
    // plus 起始 MRR 和 结束 MRR

    const newData = props.chartData.map(d => d.new || 0);
    const expansionData = props.chartData.map(d => d.expansion || 0);
    const contractionData = props.chartData.map(d => -(d.contraction || 0));
    const churnedData = props.chartData.map(d => -(d.churned || 0));

    // 起始 transparent bar + 净变化 bar
    // 使用自定义瀑布效果
    const baseData = [];
    const changeData = [];
    const positiveData = [];
    const negativeData = [];

    props.chartData.forEach((d, idx) => {
      const net = d.net_change || 0;
      baseData.push(d.starting_mrr);
      changeData.push(net);

      if (idx === props.chartData.length - 1) {
        // 最后一期只显示 ending
        changeData[idx] = d.ending_mrr;
      }
    });

    const option = {
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        formatter: function (params) {
          const d = props.chartData[params[0]?.dataIndex];
          if (!d) return '';
          const lines = [`<b>${d.month_label}</b>`];
          lines.push(`起始 MRR: ¥${fmt(d.starting_mrr)}`);
          lines.push(`<span style="color:#67c23a">● 新增: +¥${fmt(d.new)}</span>`);
          lines.push(`<span style="color:#409eff">● 扩展: +¥${fmt(d.expansion)}</span>`);
          lines.push(`<span style="color:#e6a23c">● 收缩: -¥${fmt(d.contraction)}</span>`);
          lines.push(`<span style="color:#f56c6c">● 流失: -¥${fmt(d.churned)}</span>`);
          lines.push(`<hr style="margin:4px 0">`);
          lines.push(`<b>结束 MRR: ¥${fmt(d.ending_mrr)}</b>`);
          if (d.active_subscriptions) {
            lines.push(`活跃订阅: ${d.active_subscriptions}`);
          }
          return lines.join('<br>');
        },
      },
      grid: { left: '8%', right: '6%', top: 40, bottom: 30, containLabel: true },
      xAxis: {
        type: 'category',
        data: months,
        axisLabel: { interval: 0, rotate: months.length > 8 ? 45 : 0 },
      },
      yAxis: {
        type: 'value',
        name: 'MRR (¥)',
        axisLabel: {
          formatter: (v) => v >= 10000 ? `${(v / 10000).toFixed(0)}万` : fmt(v),
        },
      },
      series: [
        {
          name: '起始 MRR',
          type: 'bar',
          stack: 'waterfall',
          itemStyle: { color: 'transparent' },
          data: baseData,
          emphasis: { itemStyle: { color: 'transparent' } },
        },
        {
          name: '新增',
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
          name: '流失',
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
    };

    chartInstance.setOption(option, true);
    chartInstance.resize();
  });
}

function fmt(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function handleResize() {
  chartInstance?.resize();
}

watch(() => props.chartData, () => renderChart(), { deep: true });
watch(() => props.loading, () => {
  if (!props.loading) renderChart();
});

onMounted(() => {
  window.addEventListener('resize', handleResize);
  renderChart();
});

onUnmounted(() => {
  window.removeEventListener('resize', handleResize);
  chartInstance?.dispose();
  chartInstance = null;
});
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
