<template>
  <el-dialog v-model="visible" :title="t('endpoint_detail_dialog.title')" width="900px" :close-on-click-modal="false" top="5vh">
    <div v-loading="loading" v-if="detail">
      <el-descriptions :column="3" border size="small" class="mb-4">
        <el-descriptions-item :label="t('endpoint_detail_dialog.name')">{{ detail.endpoint?.name }}</el-descriptions-item>
        <el-descriptions-item label="URL" :span="2">{{ detail.endpoint?.url }}</el-descriptions-item>
        <el-descriptions-item :label="t('endpoint_detail_dialog.total_events')">{{ detail.stats?.total_events }}</el-descriptions-item>
        <el-descriptions-item :label="t('endpoint_detail_dialog.success_rate')">{{ detail.stats?.success_rate }}%</el-descriptions-item>
        <el-descriptions-item :label="t('endpoint_detail_dialog.today_events')">{{ detail.stats?.today_events }}</el-descriptions-item>
      </el-descriptions>

      <el-card class="mb-4" v-if="detail.latency?.samples > 0">
        <template #header><span>{{ t('endpoint_detail_dialog.latency_title') }}</span></template>
        <el-row :gutter="20">
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.avg_ms?.toFixed(1) || 0 }}ms</div><div class="mini-label">{{ t('endpoint_detail_dialog.avg') }}</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.min_ms || 0 }}ms</div><div class="mini-label">{{ t('endpoint_detail_dialog.min') }}</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.max_ms || 0 }}ms</div><div class="mini-label">{{ t('endpoint_detail_dialog.max') }}</div></div></el-col>
          <el-col :span="6"><div class="mini-stat"><div class="mini-value">{{ detail.latency?.samples || 0 }}</div><div class="mini-label">{{ t('endpoint_detail_dialog.samples') }}</div></div></el-col>
        </el-row>
        <div ref="latencyTrendRef" style="height:200px;margin-top:12px" v-if="detail.latency_trend?.length > 0"></div>
      </el-card>

      <el-row :gutter="20" class="mb-4">
        <el-col :span="12">
          <el-card>
            <template #header><span>{{ t('endpoint_detail_dialog.event_types') }}</span></template>
            <div ref="epEventTypeRef" style="height:200px"></div>
            <div v-if="(!detail.event_types || detail.event_types.length === 0)" class="text-center text-gray-400 py-2">{{ t('messages.no_data') }}</div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card>
            <template #header><span>{{ t('endpoint_detail_dialog.status_codes') }}</span></template>
            <div ref="statusCodeRef" style="height:200px"></div>
            <div v-if="(!detail.status_codes || detail.status_codes.length === 0)" class="text-center text-gray-400 py-2">{{ t('messages.no_data') }}</div>
          </el-card>
        </el-col>
      </el-row>

      <el-card class="mb-4">
        <template #header><span>{{ t('endpoint_detail_dialog.trend_title') }}</span></template>
        <div ref="epTrendRef" style="height:220px"></div>
        <div v-if="(!detail.trend || detail.trend.length === 0)" class="text-center text-gray-400 py-2">{{ t('endpoint_detail_dialog.no_trend') }}</div>
      </el-card>

      <el-card>
        <template #header><span>{{ t('endpoint_detail_dialog.recent') }}</span></template>
        <el-table :data="detail.recent_deliveries || []" stripe size="small" max-height="300">
          <el-table-column :label="t('endpoint_detail_dialog.cols.attempt')" prop="attempt" width="60" />
          <el-table-column :label="t('endpoint_detail_dialog.cols.status')" prop="status" width="80">
            <template #default="{ row }">
              <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('endpoint_detail_dialog.cols.http')" prop="response_code" width="100" />
          <el-table-column :label="t('endpoint_detail_dialog.cols.response_time')" prop="response_time_ms" width="100">
            <template #default="{ row }">{{ row.response_time_ms || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('endpoint_detail_dialog.cols.error')" prop="error_message" min-width="200" show-overflow-tooltip />
          <el-table-column :label="t('endpoint_detail_dialog.cols.delivered_at')" prop="delivered_at" width="160" />
        </el-table>
      </el-card>
    </div>
    <template #footer>
      <el-button @click="visible = false">{{ t('actions.close') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import * as echarts from 'echarts'
import { getWebhookMonitorEndpoint } from '../../../api/webhookMonitor'

const { t } = useI18n()

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

  const ltEl = latencyTrendRef.value
  if (ltEl && detail.value.latency_trend?.length > 0) {
    echarts.init(ltEl).setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: 50, right: 20, bottom: 25, top: 10 },
      xAxis: { type: 'category', data: detail.value.latency_trend.map(t => t.date) },
      yAxis: { type: 'value', name: 'ms' },
      series: [{ type: 'line', smooth: true, data: detail.value.latency_trend.map(t => t.avg_ms), lineStyle: { color: '#0f172a', width: 2 }, areaStyle: { color: 'rgba(15,23,42,0.1)' } }],
    })
  }

  const etEl = epEventTypeRef.value
  if (etEl && detail.value.event_types?.length > 0) {
    echarts.init(etEl).setOption({
      tooltip: { trigger: 'item', formatter: '{b}: {c}' },
      series: [{ type: 'pie', radius: ['30%', '55%'], data: detail.value.event_types.map(item => ({ name: item.event_type, value: item.total })) }],
    })
  }

  const scEl = statusCodeRef.value
  if (scEl && detail.value.status_codes?.length > 0) {
    echarts.init(scEl).setOption({
      tooltip: { trigger: 'axis' },
      grid: { left: 50, right: 20, bottom: 25, top: 10 },
      xAxis: { type: 'category', data: detail.value.status_codes.map(item => item.response_code || 'unknown') },
      yAxis: { type: 'value' },
      series: [{ type: 'bar', data: detail.value.status_codes.map(item => item.total), itemStyle: { color: '#67c23a' }, barMaxWidth: 30 }],
    })
  }

  const trEl = epTrendRef.value
  if (trEl && detail.value.trend?.length > 0) {
    const totalLabel = t('endpoint_detail_dialog.chart_total')
    const successLabel = t('endpoint_detail_dialog.chart_success')
    echarts.init(trEl).setOption({
      tooltip: { trigger: 'axis' },
      legend: { data: [totalLabel, successLabel], bottom: 0 },
      grid: { left: 50, right: 20, bottom: 30, top: 10 },
      xAxis: { type: 'category', data: detail.value.trend.map(item => item.date) },
      yAxis: { type: 'value', minInterval: 1 },
      series: [
        { name: totalLabel, type: 'bar', data: detail.value.trend.map(item => item.total), barMaxWidth: 20, itemStyle: { color: '#909399' } },
        { name: successLabel, type: 'line', smooth: true, data: detail.value.trend.map(item => item.delivered), lineStyle: { color: '#67c23a', width: 2 } },
      ],
    })
  }
}

watch(() => props.visible, (v) => { if (v) load() })
watch(() => props.endpointId, (v) => { if (v && props.visible) load() })
</script>

<style scoped>
.mini-stat { text-align: center; padding: 6px 0; }
.mini-value { font-size: 22px; font-weight: 700; color: #0f172a; }
.mini-label { font-size: 12px; color: #909399; margin-top: 2px; }
</style>
