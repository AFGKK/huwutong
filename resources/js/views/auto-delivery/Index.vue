<template>
  <div class="auto-delivery-page">
    <div class="page-header">
      <h2>自动发货管理</h2>
      <p class="text-muted">支付成功后全自动创建 License、发送邮件、推送 Webhook</p>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_deliveries }}</div>
          <div class="stat-label">总发货</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-success">{{ stats.delivered }}</div>
          <div class="stat-label">已交付</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-warning">{{ stats.pending }}</div>
          <div class="stat-label">待处理</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-danger">{{ stats.failed }}</div>
          <div class="stat-label">失败</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.today_delivered }}</div>
          <div class="stat-label">今日交付</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.avg_delivery_time_ms }}ms</div>
          <div class="stat-label">平均耗时</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选 -->
    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="filters">
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部" clearable style="width:130px" @change="loadDeliveries">
            <el-option label="待处理" value="pending" />
            <el-option label="已交付" value="delivered" />
            <el-option label="失败" value="failed" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="filters.delivery_type" placeholder="全部" clearable style="width:150px" @change="loadDeliveries">
            <el-option label="License Key" value="license_key" />
            <el-option label="服务激活" value="service_activation" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="订单号" clearable style="width:180px" @input="onSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadDeliveries">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 发货列表 -->
    <el-table :data="deliveries" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column prop="order.order_no" label="订单号" width="160" />
      <el-table-column prop="orderItem.name" label="商品" min-width="160" show-overflow-tooltip />
      <el-table-column prop="delivery_type" label="交付类型" width="120">
        <template #default="{ row }">
          <el-tag :type="row.delivery_type === 'license_key' ? 'primary' : 'success'" size="small">
            {{ row.delivery_type === 'license_key' ? 'License Key' : '服务激活' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 'delivered' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">
            {{ row.status === 'delivered' ? '已交付' : row.status === 'failed' ? '失败' : '待处理' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Webhook" width="80" align="center">
        <template #default="{ row }">
          <el-icon v-if="row.webhook_pushed" color="#67c23a"><Check /></el-icon>
          <el-icon v-else color="#c0c4cc"><Close /></el-icon>
        </template>
      </el-table-column>
      <el-table-column label="邮件" width="80" align="center">
        <template #default="{ row }">
          <el-icon v-if="row.email_sent" color="#67c23a"><Check /></el-icon>
          <el-icon v-else color="#c0c4cc"><Close /></el-icon>
        </template>
      </el-table-column>
      <el-table-column label="API回调" width="80" align="center">
        <template #default="{ row }">
          <el-icon v-if="row.api_callback_sent" color="#67c23a"><Check /></el-icon>
          <el-icon v-else color="#c0c4cc"><Minus /></el-icon>
        </template>
      </el-table-column>
      <el-table-column prop="sent_at" label="发送时间" width="170" />
      <el-table-column prop="delivered_at" label="交付时间" width="170" />
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">详情</el-button>
          <el-button v-if="row.status === 'failed'" size="small" type="warning" :loading="row._retrying" @click="retry(row)">重试</el-button>
          <el-dropdown v-if="row.status === 'delivered'" @command="(cmd) => resend(row, cmd)" style="margin-left:4px">
            <el-button size="small">补发</el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="email">重发邮件</el-dropdown-item>
                <el-dropdown-item command="webhook">重推 Webhook</el-dropdown-item>
                <el-dropdown-item command="api_callback">重推 API 回调</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
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
    <el-drawer v-model="showDetailDrawer" :title="'发货 #' + (detail?.delivery?.id || '')" size="600px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="订单">{{ detail.delivery.order?.order_no }}</el-descriptions-item>
          <el-descriptions-item label="商品">{{ detail.delivery.orderItem?.name }}</el-descriptions-item>
          <el-descriptions-item label="交付类型">{{ detail.delivery.delivery_type }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.delivery.status === 'delivered' ? 'success' : 'danger'" size="small">{{ detail.delivery.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="Webhook">
            <el-icon v-if="detail.delivery.webhook_pushed" color="#67c23a"><Check /></el-icon>
            <span v-else>未推送</span>
          </el-descriptions-item>
          <el-descriptions-item label="邮件">
            <el-icon v-if="detail.delivery.email_sent" color="#67c23a"><Check /></el-icon>
            <span v-else>未发送</span>
          </el-descriptions-item>
          <el-descriptions-item label="API回调">
            <el-icon v-if="detail.delivery.api_callback_sent" color="#67c23a"><Check /></el-icon>
            <span v-else>未发送</span>
          </el-descriptions-item>
        </el-descriptions>

        <h4 style="margin:20px 0 12px">交付内容</h4>
        <pre class="code-pre">{{ formatJson(detail.delivery.content) }}</pre>

        <h4 style="margin:20px 0 12px">发货日志</h4>
        <el-timeline v-if="detail.logs?.length">
          <el-timeline-item v-for="log in detail.logs" :key="log.id" :timestamp="log.created_at">
            <div>
              <el-tag :type="log.status === 'sent' ? 'success' : 'danger'" size="small">{{ log.channel }}</el-tag>
              <span style="margin-left:8px">{{ log.status === 'sent' ? '发送成功' : '发送失败' }}</span>
            </div>
            <div v-if="log.error_message" style="color:#f56c6c;font-size:12px;margin-top:4px">{{ log.error_message }}</div>
          </el-timeline-item>
        </el-timeline>
        <el-empty v-else description="暂无日志" />
      </template>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Check, Close, Minus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import {
  getDeliveryStats, getDeliveries, getDeliveryDetail,
  executeDelivery, retryDelivery, resendDelivery, batchRetryDeliveries,
} from '@/api/autoDelivery'

const loading = ref(false)
const deliveries = ref([])
const pagination = reactive({ page: 1, per_page: 20, total: 0, last_page: 1 })
const stats = reactive({
  total_deliveries: 0, delivered: 0, pending: 0, failed: 0,
  today_delivered: 0, webhook_pushed: 0, email_sent: 0, api_callback_sent: 0,
  avg_delivery_time_ms: 0, channel_breakdown: {},
})
const filters = reactive({ status: '', delivery_type: '', search: '' })
const showDetailDrawer = ref(false)
const detail = ref(null)

let searchTimer = null

async function loadStats() {
  try {
    const res = await getDeliveryStats()
    Object.assign(stats, res.data || {})
  } catch (_) { /* ignore */ }
}

async function loadDeliveries(page = 1) {
  loading.value = true
  pagination.page = page
  try {
    const params = { ...filters, page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await getDeliveries(params)
    const data = res.data?.data || res.data || []
    deliveries.value = Array.isArray(data) ? data : []
    Object.assign(pagination, res.data?.pagination || res.meta || {})
  } catch (_) { deliveries.value = [] }
  finally { loading.value = false }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadDeliveries(), 300)
}

function onPageChange(p) { loadDeliveries(p) }

async function showDetail(row) {
  showDetailDrawer.value = true
  try {
    const res = await getDeliveryDetail(row.id)
    detail.value = res.data
  } catch (_) { detail.value = null }
}

async function retry(row) {
  row._retrying = true
  try {
    const res = await retryDelivery(row.id)
    ElMessage.success(res.message || '重试成功')
    await loadDeliveries(pagination.page)
  } catch (_) { /* ignore */ }
  finally { row._retrying = false }
}

async function resend(row, channel) {
  try {
    const res = await resendDelivery(row.id, channel)
    ElMessage.success(res.message || '补发成功')
  } catch (_) { /* ignore */ }
}

function formatJson(obj) {
  if (!obj) return '-'
  try { return typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2) }
  catch { return String(obj) }
}

onMounted(() => { loadStats(); loadDeliveries() })
</script>

<style scoped>
.auto-delivery-page { padding: 20px; }
.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.text-muted { color: #909399; font-size: 14px; }
.stat-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; color: #409eff; }
.stat-value.text-success { color: #67c23a; }
.stat-value.text-warning { color: #e6a23c; }
.stat-value.text-danger { color: #f56c6c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 13px; line-height: 1.5; overflow-x: auto; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
</style>
