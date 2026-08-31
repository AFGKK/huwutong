<template>
  <div class="compensation-panel">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-medium">{{ t(`${P}.title`) }}</h3>
      <el-button type="primary" :loading="generating" @click="autoGenerate" v-if="hasOpenBreaches">
        <el-icon><Plus /></el-icon> {{ t(`${P}.auto_generate`) }}
      </el-button>
    </div>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ compStats.total_count }}</div>
            <div class="stat-label">{{ t(`${P}.stats.total`) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">{{ compStats.pending_count }}</div>
            <div class="stat-label">{{ t(`${P}.stats.pending`) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ compStats.total_amount }}</div>
            <div class="stat-label">{{ t(`${P}.stats.amount`) }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">¥{{ compStats.total_amount }}</div>
            <div class="stat-label">{{ t(`${P}.stats.issued`) }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover" class="mb-4" v-if="compStats.monthly_trend?.length">
      <template #header>{{ t(`${P}.monthly_trend`) }}</template>
      <el-table :data="compStats.monthly_trend" stripe size="small">
        <el-table-column :label="t(`${P}.cols.month`)" prop="month" width="120" />
        <el-table-column :label="t(`${P}.cols.count`)" prop="cnt" width="120">
          <template #default="{ row }">
            <el-tag>{{ t(`${P}.times_n`, { n: row.cnt }) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.total`)" prop="total" min-width="120">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.total }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="hover" class="mb-4" v-if="compStats.by_type?.length">
      <template #header>{{ t(`${P}.type_dist`) }}</template>
      <el-table :data="compStats.by_type" stripe size="small">
        <el-table-column :label="t(`${P}.cols.type`)" prop="compensation_type" width="140">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.compensation_type)">{{ typeLabel(row.compensation_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.times`)" prop="cnt" width="100" />
        <el-table-column :label="t(`${P}.cols.amount`)" prop="total" min-width="120">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.total }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="hover" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item :label="t(`${P}.cols.status`)">
          <el-select v-model="filters.status" :placeholder="t(`${P}.all_status`)" clearable style="width:140px">
            <el-option v-for="s in allStatuses" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.severity`)">
          <el-select v-model="filters.severity" :placeholder="t(`${P}.all`)" clearable style="width:120px">
            <el-option :label="t(`${P}.severity.minor`)" value="minor" />
            <el-option :label="t(`${P}.severity.major`)" value="major" />
            <el-option :label="t(`${P}.severity.critical`)" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.type`)">
          <el-select v-model="filters.compensation_type" :placeholder="t(`${P}.all_types`)" clearable style="width:140px">
            <el-option :label="t(`${P}.types.credit`)" value="credit" />
            <el-option :label="t(`${P}.types.discount`)" value="discount" />
            <el-option :label="t(`${P}.types.extension`)" value="extension" />
            <el-option :label="t(`${P}.types.refund`)" value="refund" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">{{ t('actions.filter') }}</el-button>
          <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="hover">
      <el-table :data="compensations" stripe size="small" v-loading="loading">
        <el-table-column :label="t(`${P}.cols.contract`)" prop="contract.name" min-width="140" />
        <el-table-column :label="t(`${P}.cols.customer`)" prop="customer?.name" width="120">
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.severity`)" width="90">
          <template #default="{ row }">
            <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.type`)" width="100">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.compensation_type)" size="small">{{ typeLabel(row.compensation_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.amount`)" prop="amount" width="100">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.amount }}</span>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.status`)" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t(`${P}.cols.reason`)" prop="reason" min-width="200" show-overflow-tooltip />
        <el-table-column :label="t(`${P}.cols.created`)" prop="created_at" width="160" />
        <el-table-column :label="t(`${P}.cols.actions`)" width="200" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" type="primary" link size="small" @click="approve(row)">
              {{ t(`${P}.approve`) }}
            </el-button>
            <el-button v-if="row.status === 'approved'" type="success" link size="small" @click="issue(row)">
              {{ t(`${P}.issue`) }}
            </el-button>
            <el-button v-if="row.status === 'pending'" type="danger" link size="small" @click="showRejectDialog(row)">
              {{ t(`${P}.reject`) }}
            </el-button>
            <el-tag v-if="row.status === 'issued'" type="success" size="small">{{ t(`${P}.status.issued`) }}</el-tag>
            <el-tag v-if="row.status === 'rejected'" type="danger" size="small">{{ t(`${P}.status.rejected`) }}</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-4" v-if="pagination.total > pagination.per_page">
        <el-pagination
          background
          layout="prev, pager, next"
          :total="pagination.total"
          :page-size="pagination.per_page"
          :current-page="pagination.current_page"
          @current-change="onPageChange"
        />
      </div>
    </el-card>

    <el-dialog v-model="rejectDialog.visible" :title="t(`${P}.reject_title`)" width="420">
      <el-form :model="rejectDialog">
        <el-form-item :label="t(`${P}.reject_reason`)">
          <el-input v-model="rejectDialog.reason" type="textarea" :rows="3" :placeholder="t(`${P}.reject_ph`)" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="danger" :loading="rejecting" @click="doReject">{{ t(`${P}.confirm_reject`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getCompensations, getCompensationStats,
  autoGenerateCompensations, approveCompensation,
  issueCompensation, rejectCompensation,
} from '../../../api/sla'

const { t } = useI18n()
const P = 'sla_compensation_panel'

const loading = ref(false)
const generating = ref(false)
const rejecting = ref(false)
const compensations = ref([])
const compStats = ref({ total_count: 0, pending_count: 0, total_amount: 0, by_type: [], monthly_trend: [] })
const hasOpenBreaches = ref(false)
const pagination = reactive({ total: 0, per_page: 20, current_page: 1 })

const filters = reactive({
  status: '', severity: '', compensation_type: '',
})

const rejectDialog = reactive({
  visible: false,
  compensationId: null,
  reason: '',
})

const allStatuses = computed(() => [
  { value: 'pending', label: t(`${P}.status.pending`) },
  { value: 'approved', label: t(`${P}.status.approved`) },
  { value: 'issued', label: t(`${P}.status.issued`) },
  { value: 'rejected', label: t(`${P}.status.rejected`) },
])

function severityTag(s) {
  const map = { minor: 'info', major: 'warning', critical: 'danger' }
  return map[s] || 'info'
}

function severityLabel(s) {
  const key = `${P}.severity.${s}`
  const translated = t(key)
  return translated === key ? s : translated
}

function typeTag(type) {
  const map = { credit: 'success', discount: 'warning', extension: 'primary', refund: 'danger' }
  return map[type] || ''
}

function typeLabel(type) {
  const key = `${P}.types.${type}`
  const translated = t(key)
  return translated === key ? type : translated
}

function statusTag(s) {
  const map = { pending: 'warning', approved: 'primary', issued: 'success', rejected: 'danger' }
  return map[s] || ''
}

function statusLabel(s) {
  const key = `${P}.status.${s}`
  const translated = t(key)
  return translated === key ? s : translated
}

function resetFilters() {
  filters.status = ''
  filters.severity = ''
  filters.compensation_type = ''
  loadData()
}

function onPageChange(page) {
  pagination.current_page = page
  loadData()
}

async function loadData() {
  loading.value = true
  try {
    const params = { ...filters, page: pagination.current_page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const [compRes, statsRes] = await Promise.all([
      getCompensations(params),
      getCompensationStats(),
    ])
    compensations.value = compRes.data.data || []
    pagination.total = compRes.data.total || 0
    pagination.current_page = compRes.data.current_page || 1
    compStats.value = statsRes.data || compStats.value
    hasOpenBreaches.value = statsRes.data?.pending_count > 0
  } catch (e) {
    console.error('Failed to load compensations', e)
    ElMessage.error(t(`${P}.messages.load_failed`))
  } finally {
    loading.value = false
  }
}

async function autoGenerate() {
  generating.value = true
  try {
    const { data } = await autoGenerateCompensations()
    ElMessage.success(t(`${P}.messages.generated`, { n: data.generated }))
    loadData()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.generate_failed`))
  } finally {
    generating.value = false
  }
}

async function approve(comp) {
  try {
    await approveCompensation(comp.id)
    ElMessage.success(t(`${P}.messages.approved`))
    loadData()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.approve_failed`))
  }
}

async function issue(comp) {
  try {
    await issueCompensation(comp.id)
    ElMessage.success(t(`${P}.messages.issued`))
    loadData()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.issue_failed`))
  }
}

function showRejectDialog(comp) {
  rejectDialog.compensationId = comp.id
  rejectDialog.reason = ''
  rejectDialog.visible = true
}

async function doReject() {
  rejecting.value = true
  try {
    await rejectCompensation(rejectDialog.compensationId, rejectDialog.reason)
    ElMessage.success(t(`${P}.messages.rejected`))
    rejectDialog.visible = false
    loadData()
  } catch (e) {
    ElMessage.error(t(`${P}.messages.reject_failed`))
  } finally {
    rejecting.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.compensation-panel { min-height: 300px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.justify-center { justify-content: center; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
</style>
