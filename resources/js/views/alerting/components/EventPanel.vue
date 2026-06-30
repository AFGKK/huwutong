<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 条告警事件</span>
      <div>
        <el-select v-model="filters.severity" placeholder="严重程度" clearable size="small" style="width:110px" @change="load" class="mr-2">
          <el-option label="提示" value="info" />
          <el-option label="警告" value="warning" />
          <el-option label="严重" value="critical" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable size="small" style="width:110px" @change="load" class="mr-2">
          <el-option label="触发中" value="firing" />
          <el-option label="已确认" value="acknowledged" />
          <el-option label="已解决" value="resolved" />
        </el-select>
        <el-button size="small" @click="load" type="primary">刷新</el-button>
      </div>
    </div>
    <el-table :data="events" stripe v-loading="loading" style="cursor:pointer" @row-click="emit('detail', $event)">
      <el-table-column label="标题" prop="title" min-width="200" show-overflow-tooltip />
      <el-table-column label="规则" prop="rule?.name" width="130" />
      <el-table-column label="类型" prop="event_type" width="100">
        <template #default="{ row }">{{ typeLabel(row.event_type) }}</template>
      </el-table-column>
      <el-table-column label="严重程度" prop="severity" width="80">
        <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
      </el-table-column>
      <el-table-column label="状态" prop="status" width="80">
        <template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template>
      </el-table-column>
      <el-table-column label="触发时间" prop="fired_at" width="160" />
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click.stop="emit('detail', row)">详情</el-button>
          <el-button v-if="row.status === 'firing'" size="small" type="warning" @click.stop="acknowledge(row)">确认</el-button>
          <el-button v-if="['firing','acknowledged'].includes(row.status)" size="small" type="success" @click.stop="resolve(row)">解决</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="flex justify-center mt-3" v-if="pagination.total > pagination.per_page">
      <el-pagination background layout="prev, pager, next" v-model:current-page="pagination.page"
        :page-size="pagination.per_page" :total="pagination.total" @current-change="load" />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getEvents, acknowledgeEvent, resolveEvent } from '../../../api/alerting'

const emit = defineEmits(['detail'])

const events = ref([])
const total = ref(0)
const loading = ref(false)

const filters = reactive({ severity: '', status: '' })
const pagination = reactive({ page: 1, per_page: 50, total: 0 })

function severityTag(s) { return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info' }
function severityLabel(s) { return { info: '提示', warning: '警告', critical: '严重' }[s] || s }
function statusTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info' }
function statusLabel(s) { return { firing: '触发中', acknowledged: '已确认', resolved: '已解决' }[s] || s }
function typeLabel(t) { const map = { license_expiry: '许可证到期', certificate_expiry: '证书到期', quota_exceeded: '配额超限', failed_payment: '支付失败', audit_anomaly: '审计异常', system_health: '系统健康', activation_burst: '激活暴增', heartbeat_missed: '心跳丢失', apm_slow: 'APM慢请求', sdk_deprecated: 'SDK版本过期', custom: '自定义' }; return map[t] || t }

async function acknowledge(event) {
  try {
    await acknowledgeEvent(event.id)
    ElMessage.success('已确认')
    await load()
  } catch (e) { ElMessage.error('操作失败') }
}

async function resolve(event) {
  try {
    await resolveEvent(event.id)
    ElMessage.success('已解决')
    await load()
  } catch (e) { ElMessage.error('操作失败') }
}

async function load() {
  loading.value = true
  try {
    const params = { page: pagination.page, per_page: pagination.per_page, ...filters }
    const { data } = await getEvents(params)
    const list = Array.isArray(data) ? data : data?.data || []
    events.value = list
    pagination.total = data?.total || list.length
    total.value = pagination.total
  } catch (e) { ElMessage.error('加载事件失败') } finally { loading.value = false }
}

onMounted(load)
</script>
