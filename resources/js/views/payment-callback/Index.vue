<template>
  <div class="callback-page">
    <div class="page-header">
      <h2>支付回调管理</h2>
      <p class="text-muted">统一处理支付成功/失败/退款回调，自动更新订单状态并触发发货</p>
    </div>

    <!-- 统计 -->
    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">总计</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-success">{{ stats.completed }}</div><div class="stat-label">已完成</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-danger">{{ stats.failed }}</div><div class="stat-label">失败</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-warning">{{ stats.pending }}</div><div class="stat-label">待处理</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.today }}</div><div class="stat-label">今日</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.duplicate }}</div><div class="stat-label">重复</div></el-card>
      </el-col>
    </el-row>

    <!-- 筛选 -->
    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="filters">
        <el-form-item label="网关">
          <el-select v-model="filters.gateway" placeholder="全部" clearable style="width:130px" @change="loadCallbacks">
            <el-option label="Stripe" value="stripe" />
            <el-option label="支付宝" value="alipay" />
            <el-option label="微信" value="wechat" />
            <el-option label="PayPal" value="paypal" />
            <el-option label="Mock" value="mock" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width:130px" @change="loadCallbacks">
            <el-option label="已完成" value="completed" />
            <el-option label="失败" value="failed" />
            <el-option label="待处理" value="received" />
            <el-option label="重复" value="duplicate" />
          </el-select>
        </el-form-item>
        <el-form-item label="事件">
          <el-select v-model="filters.event_type" placeholder="全部" clearable style="width:150px" @change="loadCallbacks">
            <el-option label="支付成功" value="payment_success" />
            <el-option label="支付失败" value="payment_failed" />
            <el-option label="退款" value="refund" />
            <el-option label="拒付" value="chargeback" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="订单号/交易号" clearable style="width:200px" @input="onSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadCallbacks">查询</el-button>
          <el-button :disabled="!hasFailed" type="danger" :loading="batchLoading" @click="batchRetry">批量重试失败</el-button>
          <el-button type="warning" @click="showSimulate = true">模拟回调</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 回调列表 -->
    <el-table :data="callbacks" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="created_at" label="时间" width="170" />
      <el-table-column prop="gateway" label="网关" width="80">
        <template #default="{ row }"><el-tag size="small">{{ row.gateway }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="event_type" label="事件" width="120">
        <template #default="{ row }">
          <el-tag :type="eventTypeTag(row.event_type)" size="small">{{ eventLabel(row.event_type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="order" label="订单" width="140">
        <template #default="{ row }">{{ row.order?.order_no || '-' }}</template>
      </el-table-column>
      <el-table-column prop="transaction_id" label="交易号" width="180" show-overflow-tooltip />
      <el-table-column prop="amount" label="金额" width="100">
        <template #default="{ row }">{{ row.amount ? formatMoney(row.amount) : '-' }}</template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="120" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">详情</el-button>
          <el-button v-if="row.status === 'failed'" size="small" type="warning" :loading="row._retrying" @click="retry(row)">重试</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-if="pagination.total > pagination.per_page"
      background layout="prev,pager,next,total"
      :total="pagination.total" :page-size="pagination.per_page"
      :current-page="pagination.page" @current-change="onPageChange"
      style="margin-top:16px;justify-content:center"
    />

    <!-- 详情抽屉 -->
    <el-drawer v-model="showDetailDrawer" :title="'回调 #' + (detail?.id || '')" size="600px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="网关">{{ detail.gateway }}</el-descriptions-item>
          <el-descriptions-item label="事件">{{ eventLabel(detail.event_type) }}</el-descriptions-item>
          <el-descriptions-item label="订单号">{{ detail.order?.order_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="交易号">{{ detail.transaction_id }}</el-descriptions-item>
          <el-descriptions-item label="金额">{{ detail.amount ? formatMoney(detail.amount) : '-' }}</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
        </el-descriptions>
        <div v-if="detail.error_message" class="error-msg">{{ detail.error_message }}</div>
        <h4 style="margin:16px 0 8px">原始回调数据</h4>
        <pre class="code-pre">{{ formatJson(detail.raw_payload) }}</pre>
      </template>
    </el-drawer>

    <!-- 模拟回调对话框 -->
    <el-dialog v-model="showSimulate" title="模拟支付回调" width="500px">
      <el-form :model="simForm" label-width="100px">
        <el-form-item label="网关">
          <el-select v-model="simForm.gateway" style="width:100%">
            <el-option label="Mock" value="mock" />
            <el-option label="Stripe" value="stripe" />
            <el-option label="支付宝" value="alipay" />
          </el-select>
        </el-form-item>
        <el-form-item label="事件类型">
          <el-select v-model="simForm.event_type" style="width:100%">
            <el-option label="支付成功" value="payment_success" />
            <el-option label="支付失败" value="payment_failed" />
            <el-option label="退款" value="refund" />
            <el-option label="拒付" value="chargeback" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单 ID">
          <el-input-number v-model="simForm.order_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="金额">
          <el-input-number v-model="simForm.amount" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSimulate = false">取消</el-button>
        <el-button type="primary" :loading="simLoading" @click="doSimulate">发送模拟回调</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  getCallbackStats, getCallbacks,
  retryCallback, batchRetryCallbacks, simulateCallback,
} from '@/api/paymentCallback'

const loading = ref(false)
const batchLoading = ref(false)
const callbacks = ref([])
const pagination = reactive({ page: 1, per_page: 20, total: 0, last_page: 1 })
const stats = reactive({ total: 0, completed: 0, failed: 0, pending: 0, today: 0, duplicate: 0 })
const filters = reactive({ gateway: '', status: '', event_type: '', search: '' })
const showDetailDrawer = ref(false)
const detail = ref(null)
const showSimulate = ref(false)
const simLoading = ref(false)
const simForm = reactive({ gateway: 'mock', event_type: 'payment_success', order_id: 1, amount: 99 })

const hasFailed = computed(() => stats.failed > 0)

let searchTimer = null

function eventLabel(type) {
  const map = { payment_success: '支付成功', payment_failed: '支付失败', refund: '退款', chargeback: '拒付' }
  return map[type] || type
}
function eventTypeTag(type) {
  const map = { payment_success: 'success', payment_failed: 'danger', refund: 'warning', chargeback: 'info' }
  return map[type] || ''
}
function statusLabel(s) {
  const map = { completed: '已完成', failed: '失败', received: '待处理', processing: '处理中', duplicate: '重复' }
  return map[s] || s
}
function statusTag(s) {
  const map = { completed: 'success', failed: 'danger', received: 'warning', processing: 'primary', duplicate: 'info' }
  return map[s] || ''
}
function formatMoney(v) { return '¥' + Number(v).toFixed(2) }

async function loadStats() {
  try { const res = await getCallbackStats(); Object.assign(stats, res.data || {}) } catch (_) { /* ignore */ }
}

async function loadCallbacks(page = 1) {
  loading.value = true; pagination.page = page
  try {
    const params = { ...filters, page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await getCallbacks(params)
    const items = res.data?.data || res.data || []
    callbacks.value = Array.isArray(items) ? items : []
    Object.assign(pagination, res.data?.pagination || res.meta || {})
  } catch (_) { callbacks.value = [] }
  finally { loading.value = false }
}

function onSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadCallbacks(), 300) }
function onPageChange(p) { loadCallbacks(p) }

function showDetail(row) {
  showDetailDrawer.value = true
  detail.value = row
}

async function retry(row) {
  row._retrying = true
  try {
    const res = await retryCallback(row.id)
    ElMessage.success(res.message || '重试成功')
    await loadCallbacks(pagination.page)
  } catch (_) { /* ignore */ }
  finally { row._retrying = false }
}

async function batchRetry() {
  batchLoading.value = true
  try {
    const res = await batchRetryCallbacks({ ids: [] })
    ElMessage.success('批量重试完成')
    await loadCallbacks()
    await loadStats()
  } catch (_) { /* ignore */ }
  finally { batchLoading.value = false }
}

async function doSimulate() {
  simLoading.value = true
  try {
    const res = await simulateCallback(simForm)
    ElMessage.success(res.message || '模拟回调成功')
    showSimulate.value = false
    await loadCallbacks()
    await loadStats()
  } catch (_) { /* ignore */ }
  finally { simLoading.value = false }
}

function formatJson(obj) {
  if (!obj) return '-'
  try { return typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2) }
  catch { return String(obj) }
}

onMounted(() => { loadStats(); loadCallbacks() })
</script>

<style scoped>
.callback-page { padding: 20px; }
.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.text-muted { color: #909399; font-size: 14px; }
.stat-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; color: #409eff; }
.stat-value.text-success { color: #67c23a; }
.stat-value.text-danger { color: #f56c6c; }
.stat-value.text-warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 13px; line-height: 1.5; overflow-x: auto; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
.error-msg { margin-top: 12px; padding: 8px 12px; background: #fef0f0; color: #f56c6c; border-radius: 4px; font-size: 13px; }
</style>
