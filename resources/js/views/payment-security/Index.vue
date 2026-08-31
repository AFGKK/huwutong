<template>
  <div class="payment-security-page">
    <h2>{{ t('payment_security_page.title') }}</h2>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col v-for="item in statItems" :key="item.key" :span="6">
        <el-card shadow="never">
          <div class="stat-value" :class="item.valueClass">{{ stats[item.key] ?? 0 }}</div>
          <div class="stat-label">{{ item.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 过滤器 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item :label="t('payment_security_page.filter_check_type')">
          <el-select v-model="filters.check_type" clearable :placeholder="t('payment_security_page.filter_all')">
            <el-option v-for="opt in checkTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('payment_security_page.filter_risk_level')">
          <el-select v-model="filters.risk_level" clearable :placeholder="t('payment_security_page.filter_all')">
            <el-option v-for="opt in riskLevelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchLogs">{{ t('actions.search') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 日志表格 -->
    <el-card shadow="never">
      <el-table :data="logs" v-loading="logLoading" stripe size="small">
        <el-table-column prop="created_at" :label="t('payment_security_page.cols.time')" width="160" />
        <el-table-column :label="t('payment_security_page.cols.check_type')" width="140">
          <template #default="{ row }">{{ checkTypeLabel(row.check_type) }}</template>
        </el-table-column>
        <el-table-column :label="t('payment_security_page.cols.result')" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.passed ? 'success' : 'danger'" size="small">
              {{ row.passed ? t('payment_security_page.result.passed') : t('payment_security_page.result.failed') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('payment_security_page.cols.risk_level')" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="levelTag(row.risk_level)" size="small">{{ riskLevelLabel(row.risk_level) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="ip_address" :label="t('payment_security_page.cols.ip_address')" width="140" />
        <el-table-column :label="t('payment_security_page.cols.details')" min-width="200">
          <template #default="{ row }">
            <el-tooltip :content="JSON.stringify(row.details)" placement="top">
              <el-button text size="small">{{ t('actions.view') }}</el-button>
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
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api/paymentSecurity'

const { t } = useI18n()

const stats = ref({})
const logs = ref([])
const logLoading = ref(false)
const filters = ref({ check_type: '', risk_level: '' })
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

const checkTypeKeys = ['duplicate_payment', 'amount_tamper', 'signature_verify', 'refund_abuse', 'ip_check']
const riskLevelKeys = ['low', 'medium', 'high', 'critical']

const statItems = computed(() => [
  { key: 'total_checks', label: t('payment_security_page.stats.total_checks'), valueClass: '' },
  { key: 'passed', label: t('payment_security_page.stats.passed'), valueClass: 'text-success' },
  { key: 'failed', label: t('payment_security_page.stats.failed'), valueClass: 'text-danger' },
  { key: 'critical_today', label: t('payment_security_page.stats.critical_today'), valueClass: 'text-warning' },
])

const checkTypeOptions = computed(() =>
  checkTypeKeys.map((value) => ({
    value,
    label: t(`payment_security_page.check_types.${value}`),
  }))
)

const riskLevelOptions = computed(() =>
  riskLevelKeys.map((value) => ({
    value,
    label: t(`payment_security_page.risk_levels.${value}`),
  }))
)

function checkTypeLabel(type) {
  const key = `payment_security_page.check_types.${type}`
  return t(key) !== key ? t(key) : type
}

function riskLevelLabel(level) {
  const key = `payment_security_page.risk_levels.${level}`
  return t(key) !== key ? t(key) : level
}

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
