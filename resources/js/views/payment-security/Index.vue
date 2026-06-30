<template>
  <div class="payment-security-page">
    <h2>支付安全管理</h2>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-value">{{ stats.total_checks ?? 0 }}</div>
          <div class="stat-label">总检测次数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-value text-success">{{ stats.passed ?? 0 }}</div>
          <div class="stat-label">通过</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-value text-danger">{{ stats.failed ?? 0 }}</div>
          <div class="stat-label">失败</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-value text-warning">{{ stats.critical_today ?? 0 }}</div>
          <div class="stat-label">今日严重</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 过滤器 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item label="检测类型">
          <el-select v-model="filters.check_type" clearable placeholder="全部">
            <el-option v-for="t in checkTypes" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="风险等级">
          <el-select v-model="filters.risk_level" clearable placeholder="全部">
            <el-option label="Low" value="low" />
            <el-option label="Medium" value="medium" />
            <el-option label="High" value="high" />
            <el-option label="Critical" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchLogs">搜索</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 日志表格 -->
    <el-card shadow="never">
      <el-table :data="logs" v-loading="logLoading" stripe size="small">
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column prop="check_type" label="检测类型" width="140" />
        <el-table-column label="结果" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.passed ? 'success' : 'danger'" size="small">
              {{ row.passed ? '✓' : '✗' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="风险等级" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="levelTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ip_address" label="IP 地址" width="140" />
        <el-table-column label="详情" min-width="200">
          <template #default="{ row }">
            <el-tooltip :content="JSON.stringify(row.details)" placement="top">
              <el-button text size="small">查看</el-button>
            </el-tooltip>
          </template>
        </el-table-column>
      </el-table>
      <div class="mt-3 flex justify-end">
        <el-pagination
          v-model:current-page="page"
          :page-size="perPage"
          :total="total"
          layout="prev, pager, next"
          small
          @current-change="fetchLogs"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/paymentSecurity'

const stats = ref({})
const logs = ref([])
const logLoading = ref(false)
const filters = ref({ check_type: '', risk_level: '' })
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

const checkTypes = ['duplicate_payment', 'amount_tamper', 'signature_verify', 'refund_abuse', 'ip_check']

function levelTag(level) {
  const map = { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }
  return map[level] ?? 'info'
}

async function fetchStats() {
  try {
    const res = await api.stats()
    stats.value = res.data?.data ?? {}
  } catch (e) { console.error(e) }
}

async function fetchLogs() {
  logLoading.value = true
  try {
    const params = { ...filters.value, page: page.value, per_page: perPage.value }
    const res = await api.logs(params)
    logs.value = res.data?.data?.data ?? res.data?.data ?? []
    total.value = res.data?.data?.total ?? 0
  } catch (e) { console.error(e) }
  finally { logLoading.value = false }
}

onMounted(() => {
  fetchStats()
  fetchLogs()
})
</script>

<style scoped>
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
</style>
