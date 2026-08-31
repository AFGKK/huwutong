<template>
  <div class="callback-page">
    <div class="page-header">
      <h2>{{ t('payment_callback_page.title') }}</h2>
      <p class="text-muted">{{ t('payment_callback_page.subtitle') }}</p>
    </div>

    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">{{ t('payment_callback_page.stats.total') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-success">{{ stats.completed }}</div><div class="stat-label">{{ t('payment_callback_page.statuses.completed') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-danger">{{ stats.failed }}</div><div class="stat-label">{{ t('payment_callback_page.statuses.failed') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value text-warning">{{ stats.pending }}</div><div class="stat-label">{{ t('payment_callback_page.statuses.pending') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.today }}</div><div class="stat-label">{{ t('payment_callback_page.stats.today') }}</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ stats.duplicate }}</div><div class="stat-label">{{ t('payment_callback_page.statuses.duplicate') }}</div></el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="filters">
        <el-form-item :label="t('payment_callback_page.cols.gateway')">
          <el-select v-model="filters.gateway" :placeholder="t('payment_callback_page.all')" clearable style="width:130px" @change="loadCallbacks">
            <el-option label="Stripe" value="stripe" />
            <el-option :label="t('payment_callback_page.gateways.alipay')" value="alipay" />
            <el-option :label="t('payment_callback_page.gateways.wechat')" value="wechat" />
            <el-option label="PayPal" value="paypal" />
            <el-option label="Mock" value="mock" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('payment_callback_page.cols.status')">
          <el-select v-model="filters.status" :placeholder="t('payment_callback_page.all')" clearable style="width:130px" @change="loadCallbacks">
            <el-option :label="t('payment_callback_page.statuses.completed')" value="completed" />
            <el-option :label="t('payment_callback_page.statuses.failed')" value="failed" />
            <el-option :label="t('payment_callback_page.statuses.pending')" value="received" />
            <el-option :label="t('payment_callback_page.statuses.duplicate')" value="duplicate" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('payment_callback_page.cols.event')">
          <el-select v-model="filters.event_type" :placeholder="t('payment_callback_page.all')" clearable style="width:150px" @change="loadCallbacks">
            <el-option :label="t('payment_callback_page.events.payment_success')" value="payment_success" />
            <el-option :label="t('payment_callback_page.events.payment_failed')" value="payment_failed" />
            <el-option :label="t('payment_callback_page.events.refund')" value="refund" />
            <el-option :label="t('payment_callback_page.events.chargeback')" value="chargeback" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('actions.search')">
          <el-input v-model="filters.search" :placeholder="t('payment_callback_page.search_ph')" clearable style="width:200px" @input="onSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadCallbacks()">{{ t('payment_callback_page.query') }}</el-button>
          <el-button :disabled="!hasFailed" type="danger" :loading="batchLoading" @click="batchRetry">{{ t('payment_callback_page.batch_retry') }}</el-button>
          <el-button type="warning" @click="showSimulate = true">{{ t('payment_callback_page.simulate') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-table :data="callbacks" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="created_at" :label="t('payment_callback_page.cols.time')" width="170" />
      <el-table-column prop="gateway" :label="t('payment_callback_page.cols.gateway')" width="80">
        <template #default="{ row }"><el-tag size="small">{{ row.gateway }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="event_type" :label="t('payment_callback_page.cols.event')" width="120">
        <template #default="{ row }">
          <el-tag :type="eventTypeTag(row.event_type)" size="small">{{ eventLabel(row.event_type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="order" :label="t('payment_callback_page.cols.order')" width="140">
        <template #default="{ row }">{{ row.order?.order_no || '-' }}</template>
      </el-table-column>
      <el-table-column prop="transaction_id" :label="t('payment_callback_page.cols.txn')" width="180" show-overflow-tooltip />
      <el-table-column prop="amount" :label="t('payment_callback_page.cols.amount')" width="100">
        <template #default="{ row }">{{ row.amount ? formatMoney(row.amount) : '-' }}</template>
      </el-table-column>
      <el-table-column prop="status" :label="t('payment_callback_page.cols.status')" width="100">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('payment_callback_page.cols.actions')" width="120" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
          <el-button v-if="row.status === 'failed'" size="small" type="warning" :loading="row._retrying" @click="retry(row)">{{ t('actions.retry') }}</el-button>
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

    <el-drawer v-model="showDetailDrawer" :title="t('payment_callback_page.detail_title', { id: detail?.id || '' })" size="600px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('payment_callback_page.cols.gateway')">{{ detail.gateway }}</el-descriptions-item>
          <el-descriptions-item :label="t('payment_callback_page.cols.event')">{{ eventLabel(detail.event_type) }}</el-descriptions-item>
          <el-descriptions-item :label="t('payment_callback_page.order_no')">{{ detail.order?.order_no || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('payment_callback_page.cols.txn')">{{ detail.transaction_id }}</el-descriptions-item>
          <el-descriptions-item :label="t('payment_callback_page.cols.amount')">{{ detail.amount ? formatMoney(detail.amount) : '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('payment_callback_page.cols.status')"><el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag></el-descriptions-item>
        </el-descriptions>
        <div v-if="detail.error_message" class="error-msg">{{ detail.error_message }}</div>
        <h4 style="margin:16px 0 8px">{{ t('payment_callback_page.raw_payload') }}</h4>
        <pre class="code-pre">{{ formatJson(detail.raw_payload) }}</pre>
      </template>
    </el-drawer>

    <el-dialog v-model="showSimulate" :title="t('payment_callback_page.simulate_title')" width="500px">
      <el-form :model="simForm" label-width="100px">
        <el-form-item :label="t('payment_callback_page.cols.gateway')">
          <el-select v-model="simForm.gateway" style="width:100%">
            <el-option label="Mock" value="mock" />
            <el-option label="Stripe" value="stripe" />
            <el-option :label="t('payment_callback_page.gateways.alipay')" value="alipay" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('payment_callback_page.event_type')">
          <el-select v-model="simForm.event_type" style="width:100%">
            <el-option :label="t('payment_callback_page.events.payment_success')" value="payment_success" />
            <el-option :label="t('payment_callback_page.events.payment_failed')" value="payment_failed" />
            <el-option :label="t('payment_callback_page.events.refund')" value="refund" />
            <el-option :label="t('payment_callback_page.events.chargeback')" value="chargeback" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('payment_callback_page.order_id')">
          <el-input-number v-model="simForm.order_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('payment_callback_page.cols.amount')">
          <el-input-number v-model="simForm.amount" :min="0" :precision="2" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showSimulate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="simLoading" @click="doSimulate">{{ t('payment_callback_page.send_simulate') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
  getCallbackStats, getCallbacks,
  retryCallback, batchRetryCallbacks, simulateCallback,
} from '@/api/paymentCallback'

const { t } = useI18n()

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
  const key = { payment_success: 'payment_success', payment_failed: 'payment_failed', refund: 'refund', chargeback: 'chargeback' }[type]
  return key ? t(`payment_callback_page.events.${key}`) : type
}
function eventTypeTag(type) {
  const map = { payment_success: 'success', payment_failed: 'danger', refund: 'warning', chargeback: 'info' }
  return map[type] || ''
}
function statusLabel(s) {
  const key = { completed: 'completed', failed: 'failed', received: 'pending', processing: 'processing', duplicate: 'duplicate' }[s]
  return key ? t(`payment_callback_page.statuses.${key}`) : s
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
    ElMessage.success(res.message || t('payment_callback_page.messages.retry_ok'))
    await loadCallbacks(pagination.page)
  } catch (_) { /* ignore */ }
  finally { row._retrying = false }
}

async function batchRetry() {
  batchLoading.value = true
  try {
    await batchRetryCallbacks({ ids: [] })
    ElMessage.success(t('payment_callback_page.messages.batch_ok'))
    await loadCallbacks()
    await loadStats()
  } catch (_) { /* ignore */ }
  finally { batchLoading.value = false }
}

async function doSimulate() {
  simLoading.value = true
  try {
    const res = await simulateCallback(simForm)
    ElMessage.success(res.message || t('payment_callback_page.messages.simulate_ok'))
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
.stat-value { font-size: 24px; font-weight: 700; color: #0f172a; }
.stat-value.text-success { color: #67c23a; }
.stat-value.text-danger { color: #f56c6c; }
.stat-value.text-warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 13px; line-height: 1.5; overflow-x: auto; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
.error-msg { margin-top: 12px; padding: 8px 12px; background: #fef0f0; color: #f56c6c; border-radius: 4px; font-size: 13px; }
</style>
