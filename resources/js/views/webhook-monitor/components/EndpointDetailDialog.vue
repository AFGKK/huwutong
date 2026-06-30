<template>
  <el-dialog v-model="visible" title="端点监控详情" width="900px" :close-on-click-modal="false" top="5vh">
    <div v-loading="loading" v-if="detail">
      <!-- 端点基本信息 -->
      <el-descriptions :column="3" border size="small" class="mb-4">
        <el-descriptions-item label="名称">{{ detail.endpoint?.name }}</el-descriptions-item>
        <el-descriptions-item label="URL" :span="2">{{ detail.endpoint?.url }}</el-descriptions-item>
        <el-descriptions-item label="总事件">{{ detail.stats?.total_events }}</el-descriptions-item>
        <el-descriptions-item label="成功率">{{ detail.stats?.success_rate }}%</el-descriptions-item>
        <el-descriptions-item label="今日事件">{{ detail.stats?.today_events }}</el-descriptions-item>
      </el-descriptions>

      <!-- 延迟统计 -->
      <el-card class="mb-4" v-if="detail.latency?.samples > 0">
        <template #header><span>延迟统计（近7天）</span></template>
        <el-row :gutter="20">
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.avg_ms?.toFixed(1) || 0 }}ms</div><div class="mini-label">平均</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.min_ms || 0 }}ms</div><div class="mini-label">最小</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.max_ms || 0 }}ms</div><div class="mini-label">最大</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.samples || 0 }}</div><div class="mini-label">样本数</div></div></el-col>
        </el-row>
        <div ref="latencyTrendRef" style="height:200px;margin-top:12px" v-if="detail.latency_trend?.length > 0"></div>
      </el-card>

      <!-- 事件类型 & 状态码分布 -->
      <el-row :gutter="20" class="mb-4">
        <el-col :span="12">
          <el-card>
            <template #header><span>事件类型分布</span></template>
            <div ref="epEventTypeRef" style="height:200px"></div>
            <div v-if="(!detail.event_types || detail.event_types.length === 0)" class="text-center text-gray-400 py-2">暂无数据</div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card>
            <template #header><span>HTTP 状态码分布</span></template>
            <div ref="statusCodeRef" style="height:200px"></div>
            <div v-if="(!detail.status_codes || detail.status_codes.length === 0)" class="text-center text-gray-400 py-2">暂无数据</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 近7天趋势 -->
      <el-card class="mb-4">
        <template #header><span>近7天投递趋势</span></template>
        <div ref="epTrendRef" style="height:220px"></div>
        <div v-if="(!detail.trend || detail.trend.length === 0)" class="text-center text-gray-400 py-2">暂无趋势数据</div>
      </el-card>

      <!-- 最近投递记录 -->
      <el-card>
        <template #header><span>最近投递记录（最新50条）</span></template>
        <el-table :data="detail.recent_deliveries || []" stripe size="small" max-height="300">
          <el-table-column label="尝试" prop="attempt" width="60" />
          <el-table-column label="状态" prop="status" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="HTTP状态码" prop="response_code" width="100" />
          <el-table-column label="响应时间" prop="response_time_ms" width="100">
            <template #default="{ row }">{{ row.response_time_ms || '-' }}</template>
          </el-table-column>
          <el-table-column label="错误信息" prop="error_message" min-width="200" show-overflow-tooltip />
          <el-table-column label="投递时间" prop="delivered_at" width="160" />
        </el-table>
      </el-card>
    </div>
    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import * as echarts from 'echarts'
import { getWebhookMonitorEndpoint } from '../../../api/webhookMonitor'

const props = defineProps({
  visible: { type: Boolean, default: false },
  endpointId: { type: Number, default: null },
})
const emit = defineEmits(['update:visible'])
const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const loading = ref(false)
const detail = ref(null)
const latencyTrendRef = ref(null)
const epEventTypeRef = ref(null)
const statusCodeRef = ref(null)
const epTrendRef = ref(null)

async function load() {
  if (!props.endpointId) return
  loading.value = true
  try {
    const { data } = await getWebhookMonitorEndpoint(props.endpointId)
    detail.value = data
    await nextTick()
    renderDetailCharts()
  } catch { detail.value = null } finally { loading.value = false }
}

function renderDetailCharts() {
  if (!detail.value) return

  // 延迟趋势
  const ltEl = latencyTrendRef.value
  if (ltEl && detail.value.latency_trend?.length > 0) {
    echarts.init(ltEl).setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: 50, right: 20, bottom: 25, top: 10 },
      xAxis: { type: 'category', data: detail.value.latency_trend.map(t => t.date) },
      yAxis: { type: 'value', name: 'ms' },
      series: [{ type: 'line', smooth: true, data: detail.value.latency_trend.map(t => t.avg_ms), lineStyle: { color: '#409eff', width: 2 }, areaStyle: { color: 'rgba(64,158,255,0.1)' } }],
    })
  }

  // 事件类型
  const etEl = epEventTypeRef.value
  if (etEl && detail.value.event_types?.length > 0) {
    echarts.init(etEl).setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c}' },
      series: [{ type: 'pie', radius: ['30%', '55%'], data: detail.value.event_types.map(t => ({ name: t.event_type, value: t.total })) }],
    })
  }

  // 状态码
  const scEl = statusCodeRef.value
  if (scEl && detail.value.status_codes?.length > 0) {
    echarts.init(scEl).setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: 50, right: 20, bottom: 25, top: 10 },
      xAxis: { type: 'category', data: detail.value.status_codes.map(t => t.response_code || 'unknown') },
      yAxis: { type: 'value' },
      series: [{ type: 'bar', data: detail.value.status_codes.map(t => t.total), itemStyle: { color: '#67c23a' }, barMaxWidth: 30 }],
    })
  }

  // 趋势
  const trEl = epTrendRef.value
  if (trEl && detail.value.trend?.length > 0) {
    echarts.init(trEl).setOption({
      tooltip: { trigger: 'axis' },
      legend: { data: ['总量', '成功'], bottom: 0 },
      grid: { left: 50, right: 20, bottom: 30, top: 10 },
      xAxis: { type: 'category', data: detail.value.trend.map(t => t.date) },
      yAxis: { type: 'value', minInterval: 1 },
      series: [
        { name: '总量', type: 'bar', data: detail.value.trend.map(t => t.total), barMaxWidth: 20, itemStyle: { color: '#909399' } },
        { name: '成功', type: 'line', smooth: true, data: detail.value.trend.map(t => t.delivered), lineStyle: { color: '#67c23a', width: 2 } },
      ],
    })
  }
}

watch(() => props.visible, (v) => { if (v) load() })
watch(() => props.endpointId, (v) => { if (v && props.visible) load() })
</script>

<style scoped>
.mini-stat { text-align: center; padding: 6px 0; }
.mini-value { font-size: 22px; font-weight: 700; color: #409eff; }
.mini-label { font-size: 12px; color: #909399; margin-top: 2px; }
</style>
