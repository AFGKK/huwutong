<template>
  <div class="gdpr-page">
    <div class="page-header">
      <h2>{{ t('gdpr_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshStats" :loading="loading.stats">
          {{ t('gdpr_page.stats.refresh_btn') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_requests ?? 0 }}</div>
          <div class="stat-label">{{ t('gdpr_page.stats.total_requests') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card warning">
          <div class="stat-value">{{ stats.pending ?? 0 }}</div>
          <div class="stat-label">{{ t('gdpr_page.stats.pending') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card success">
          <div class="stat-value">{{ stats.completed ?? 0 }}</div>
          <div class="stat-label">{{ t('gdpr_page.stats.completed') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card info">
          <div class="stat-value">{{ stats.published_dpa ?? 0 }}</div>
          <div class="stat-label">{{ t('gdpr_page.stats.published_dpa') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 标签页 -->
    <el-tabs v-model="activeTab" class="main-tabs">
      <!-- Tab 1: DSR 请求管理 -->
      <el-tab-pane :label="t('gdpr_page.tabs.requests')" name="requests">
        <el-card shadow="never">
          <template #header>
            <div class="card-header">
              <span>{{ t('gdpr_page.compliance.requests_list_title') }}</span>
              <div class="header-actions">
                <el-select v-model="filters.status" :placeholder="t('gdpr_page.compliance.filter_status')" clearable @change="loadRequests" style="width:140px;margin-right:8px;">
                  <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-select v-model="filters.type" :placeholder="t('gdpr_page.compliance.filter_type')" clearable @change="loadRequests" style="width:140px;">
                  <el-option v-for="opt in typeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </div>
            </div>
          </template>

          <el-table :data="requests" v-loading="loading.requests" stripe>
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column :label="t('gdpr_page.requests.col_user')" min-width="140">
              <template #default="{ row }">
                <div>{{ row.user_name }}</div>
                <div class="text-muted">{{ row.user_email }}</div>
              </template>
            </el-table-column>
            <el-table-column :label="t('gdpr_page.requests.col_type')" width="120">
              <template #default="{ row }">
                <el-tag :type="typeTagType(row.type)" size="small">{{ row.type_label }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('gdpr_page.requests.col_status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">{{ row.status_label }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="reason" :label="t('gdpr_page.compliance.col_reason')" min-width="150" show-overflow-tooltip />
            <el-table-column :label="t('gdpr_page.compliance.col_processor')" width="120">
              <template #default="{ row }">
                {{ row.processor_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="t('gdpr_page.requests.col_submitted_at')" width="160" />
            <el-table-column :label="t('gdpr_page.requests.col_actions')" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
                <el-button
                  size="small"
                  type="primary"
                  :disabled="!['pending', 'failed'].includes(row.status)"
                  @click="handleProcess(row)"
                >{{ t('gdpr_page.requests.process') }}</el-button>
                <el-button
                  size="small"
                  type="success"
                  v-if="row.status === 'pending'"
                  @click="handleApprove(row)"
                >{{ t('actions.approve') }}</el-button>
                <el-button
                  size="small"
                  type="danger"
                  v-if="row.status === 'pending'"
                  @click="handleReject(row)"
                >{{ t('actions.reject') }}</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
            <el-pagination
              v-model:current-page="pagination.current_page"
              :page-size="pagination.per_page"
              :total="pagination.total"
              layout="prev, pager, next"
              @current-change="loadRequests"
            />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 2: DPA 管理 -->
      <el-tab-pane :label="t('gdpr_page.compliance.tab_dpa')" name="dpa">
        <el-card shadow="never">
          <template #header>
            <div class="card-header">
              <span>{{ t('gdpr_page.dpa.list_title') }}</span>
              <el-button type="primary" size="small" @click="showCreateDpa">
                <el-icon><Plus /></el-icon> {{ t('gdpr_page.dpa.new_btn') }}
              </el-button>
            </div>
          </template>

          <el-table :data="dpas" v-loading="loading.dpas" stripe>
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="title" :label="t('gdpr_page.dpa.col_title')" min-width="160" />
            <el-table-column prop="version" :label="t('gdpr_page.dpa.col_version')" width="80" />
            <el-table-column :label="t('gdpr_page.dpa.col_status')" width="100">
              <template #default="{ row }">
                <el-tag :type="dpaStatusTag(row.status)" size="small">
                  {{ dpaStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="jurisdiction" :label="t('gdpr_page.dpa.col_jurisdiction')" width="140" />
            <el-table-column :label="t('gdpr_page.dpa.col_signatures')" width="80">
              <template #default="{ row }">{{ row.signatures_count ?? 0 }}</template>
            </el-table-column>
            <el-table-column prop="effective_at" :label="t('gdpr_page.dpa.col_effective_at')" width="160" />
            <el-table-column :label="t('gdpr_page.dpa.col_actions')" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showDpaDetail(row)">{{ t('actions.view') }}</el-button>
                <el-button
                  size="small"
                  type="primary"
                  v-if="row.status === 'draft'"
                  @click="handlePublishDpa(row)"
                >{{ t('gdpr_page.dpa.publish') }}</el-button>
                <el-button
                  size="small"
                  type="warning"
                  v-if="row.status === 'draft'"
                  @click="showEditDpa(row)"
                >{{ t('actions.edit') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- DSR 请求详情对话框 -->
    <el-dialog v-model="detailVisible" :title="t('gdpr_page.compliance.detail_dialog_title')" width="600px">
      <template v-if="currentDetail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ currentDetail.id }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.requests.col_type')">{{ currentDetail.type_label }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.requests.col_status')">
            <el-tag :type="statusTagType(currentDetail.status)" size="small">{{ currentDetail.status_label }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.requests.col_user')">{{ currentDetail.user_name }} ({{ currentDetail.user_email }})</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.col_reason')" :span="2">{{ currentDetail.reason || t('gdpr_page.compliance.detail_reason_none') }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.col_processor')">{{ currentDetail.processor_name || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.detail_completed_at')">{{ currentDetail.completed_at || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.detail_file_size')">{{ formatFileSize(currentDetail.file_size) }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.detail_expires_at')">{{ currentDetail.expires_at || '-' }}</el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.detail_rejection_reason')" v-if="currentDetail.rejection_reason" :span="2">
            <span class="text-danger">{{ currentDetail.rejection_reason }}</span>
          </el-descriptions-item>
          <el-descriptions-item :label="t('gdpr_page.compliance.detail_created_at')" :span="2">{{ currentDetail.created_at }}</el-descriptions-item>
        </el-descriptions>
        <div style="margin-top:16px;" v-if="currentDetail.output_file && currentDetail.status === 'completed'">
          <el-button type="primary" @click="handleDownload(currentDetail)">
            <el-icon><Download /></el-icon> {{ t('gdpr_page.compliance.download_export') }}
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 新建/编辑 DPA 对话框 -->
    <el-dialog v-model="dpaFormVisible" :title="isEditingDpa ? t('gdpr_page.dpa.edit_dialog_title') : t('gdpr_page.dpa.dialog_title')" width="700px">
      <el-form :model="dpaForm" label-width="120px" :rules="dpaRules" ref="dpaFormRef">
        <el-form-item :label="t('gdpr_page.dpa.title_label')" prop="title">
          <el-input v-model="dpaForm.title" :placeholder="t('gdpr_page.dpa.title_ph')" />
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.version_label')" prop="version">
          <el-input v-model="dpaForm.version" :placeholder="t('gdpr_page.dpa.version_ph')" style="width:120px;" />
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.jurisdiction_label')" prop="jurisdiction">
          <el-input v-model="dpaForm.jurisdiction" :placeholder="t('gdpr_page.dpa.jurisdiction_ph')" />
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.content_label')" prop="content">
          <el-input
            v-model="dpaForm.content"
            type="textarea"
            :rows="8"
            :placeholder="t('gdpr_page.dpa.content_ph')"
          />
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.data_categories_label')">
          <el-select v-model="dpaForm.data_categories" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option v-for="opt in dataCategoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.processing_purposes_label')">
          <el-select v-model="dpaForm.processing_purposes" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option v-for="opt in processingPurposeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.security_measures_label')">
          <el-select v-model="dpaForm.security_measures" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option v-for="opt in securityMeasureOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('gdpr_page.dpa.expires_at_label')">
          <el-date-picker v-model="dpaForm.expires_at" type="date" :placeholder="t('gdpr_page.dpa.expires_at_ph')" style="width:100%;" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpaFormVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="loading.saveDpa" @click="saveDpa">
          {{ isEditingDpa ? t('actions.save') : t('actions.create') }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download } from '@element-plus/icons-vue'
import {
  getGdprRequests,
  getGdprStats,
  processGdprRequest,
  reviewGdprRequest,
  getDpaList,
  createDpa,
  updateDpa,
  publishDpa,
  downloadGdprExport,
} from '@/api/gdprCompliance'

const { t } = useI18n()

const activeTab = ref('requests')

const loading = reactive({
  stats: false,
  requests: false,
  dpas: false,
  saveDpa: false,
})

const stats = reactive({
  total_requests: 0,
  pending: 0,
  processing: 0,
  completed: 0,
  rejected: 0,
  failed: 0,
  published_dpa: 0,
})

const statusFilterOptions = computed(() => [
  { label: t('gdpr_page.requests.status_pending'), value: 'pending' },
  { label: t('gdpr_page.requests.status_processing'), value: 'processing' },
  { label: t('gdpr_page.requests.status_completed'), value: 'completed' },
  { label: t('gdpr_page.requests.status_rejected'), value: 'rejected' },
  { label: t('gdpr_page.requests.status_failed'), value: 'failed' },
])

const typeFilterOptions = computed(() => [
  { label: t('gdpr_page.requests.type_access'), value: 'access' },
  { label: t('gdpr_page.requests.type_export'), value: 'export' },
  { label: t('gdpr_page.requests.type_rectification'), value: 'rectification' },
  { label: t('gdpr_page.requests.type_erasure'), value: 'erasure' },
  { label: t('gdpr_page.requests.type_restrict'), value: 'restrict' },
  { label: t('gdpr_page.requests.type_portability'), value: 'portability' },
  { label: t('gdpr_page.requests.type_object'), value: 'object' },
])

const dataCategoryOptions = computed(() => [
  { label: t('gdpr_page.dpa.cat_account'), value: t('gdpr_page.dpa.cat_account_val') },
  { label: t('gdpr_page.dpa.cat_license'), value: t('gdpr_page.dpa.cat_license_val') },
  { label: t('gdpr_page.dpa.cat_payment'), value: t('gdpr_page.dpa.cat_payment_val') },
])

const processingPurposeOptions = computed(() => [
  { label: t('gdpr_page.dpa.purpose_license'), value: t('gdpr_page.dpa.purpose_license_val') },
  { label: t('gdpr_page.dpa.purpose_support'), value: t('gdpr_page.dpa.purpose_support_val') },
  { label: t('gdpr_page.dpa.purpose_audit'), value: t('gdpr_page.dpa.purpose_audit_val') },
])

const securityMeasureOptions = computed(() => [
  { label: t('gdpr_page.dpa.measure_tls'), value: t('gdpr_page.dpa.measure_tls_val') },
  { label: t('gdpr_page.dpa.measure_aes'), value: t('gdpr_page.dpa.measure_aes_val') },
  { label: t('gdpr_page.dpa.measure_rbac'), value: t('gdpr_page.dpa.measure_rbac_val') },
])

const dpaRules = computed(() => ({
  title: [{ required: true, message: t('gdpr_page.dpa.rule_title'), trigger: 'blur' }],
  version: [{ required: true, message: t('gdpr_page.dpa.rule_version'), trigger: 'blur' }],
  content: [{ required: true, message: t('gdpr_page.dpa.rule_content'), trigger: 'blur' }],
}))

// DSR 请求管理
const filters = reactive({
  status: '',
  type: '',
})

const requests = ref([])
const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
})

async function loadRequests() {
  loading.requests = true
  try {
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
    }
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    const res = await getGdprRequests(params)
    requests.value = res.data?.data ?? []
    if (res.data?.meta) {
      pagination.current_page = res.data.meta.current_page
      pagination.per_page = res.data.meta.per_page
      pagination.total = res.data.meta.total
    }
  } catch (e) {
    ElMessage.error(t('gdpr_page.compliance.load_requests_fail'))
  } finally {
    loading.requests = false
  }
}

async function refreshStats() {
  loading.stats = true
  try {
    const res = await getGdprStats()
    Object.assign(stats, res.data ?? {})
  } catch {
    ElMessage.error(t('gdpr_page.compliance.load_stats_fail'))
  } finally {
    loading.stats = false
  }
}

function typeTagType(type) {
  const map = { access: 'primary', export: 'success', rectification: 'warning', erasure: 'danger', restrict: 'info', portability: '', object: '' }
  return map[type] || ''
}

function statusTagType(status) {
  const map = { pending: 'warning', processing: 'primary', completed: 'success', approved: 'success', rejected: 'danger', failed: 'danger' }
  return map[status] || ''
}

function dpaStatusLabel(status) {
  if (status === 'draft') return t('gdpr_page.dpa.status_draft')
  if (status === 'published') return t('gdpr_page.dpa.status_published')
  return t('gdpr_page.dpa.status_archived')
}

// DSR 详情
const detailVisible = ref(false)
const currentDetail = ref(null)

function showDetail(row) {
  currentDetail.value = row
  detailVisible.value = true
}

async function handleProcess(row) {
  try {
    await ElMessageBox.confirm(
      t('gdpr_page.compliance.confirm_process', { type: row.type_label }),
      t('actions.confirm'),
    )
    await processGdprRequest(row.id)
    ElMessage.success(t('gdpr_page.requests.process_ok'))
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || t('gdpr_page.requests.process_fail'))
  }
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(
      t('gdpr_page.compliance.confirm_approve', { type: row.type_label }),
      t('actions.confirm'),
    )
    await reviewGdprRequest(row.id, { action: 'approve' })
    ElMessage.success(t('gdpr_page.requests.approve_ok'))
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || t('gdpr_page.requests.operation_fail'))
  }
}

async function handleReject(row) {
  try {
    const { value } = await ElMessageBox.prompt(
      t('gdpr_page.compliance.reject_prompt'),
      t('gdpr_page.compliance.reject_title'),
      {
        confirmButtonText: t('actions.reject'),
        cancelButtonText: t('actions.cancel'),
        inputType: 'textarea',
        inputValidator: (v) => !!v || t('gdpr_page.compliance.reject_validator'),
      },
    )
    await reviewGdprRequest(row.id, { action: 'reject', reason: value })
    ElMessage.success(t('gdpr_page.requests.reject_ok'))
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || t('gdpr_page.requests.operation_fail'))
  }
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(1)} ${units[i]}`
}

async function handleDownload(row) {
  try {
    const blob = await downloadGdprExport(row.id)
    const url = window.URL.createObjectURL(new Blob([blob]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `gdpr-export-${row.id}.json`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch {
    ElMessage.error(t('gdpr_page.compliance.download_fail'))
  }
}

// DPA 管理
const dpas = ref([])

async function loadDpas() {
  loading.dpas = true
  try {
    const res = await getDpaList({ per_page: 50 })
    dpas.value = res.data?.data ?? []
  } catch {
    ElMessage.error(t('gdpr_page.dpa.load_fail'))
  } finally {
    loading.dpas = false
  }
}

function dpaStatusTag(status) {
  return status === 'published' ? 'success' : status === 'draft' ? 'info' : 'warning'
}

// DPA 表单
const dpaFormVisible = ref(false)
const isEditingDpa = ref(false)
const editingDpaId = ref(null)
const dpaFormRef = ref(null)

const dpaForm = reactive({
  title: '',
  version: '',
  content: '',
  jurisdiction: '',
  data_categories: [],
  processing_purposes: [],
  security_measures: [],
  expires_at: null,
})

function resetDpaForm() {
  dpaForm.title = ''
  dpaForm.version = ''
  dpaForm.content = ''
  dpaForm.jurisdiction = ''
  dpaForm.data_categories = []
  dpaForm.processing_purposes = []
  dpaForm.security_measures = []
  dpaForm.expires_at = null
  isEditingDpa.value = false
  editingDpaId.value = null
}

function showCreateDpa() {
  resetDpaForm()
  dpaFormVisible.value = true
}

function showEditDpa(row) {
  resetDpaForm()
  isEditingDpa.value = true
  editingDpaId.value = row.id
  dpaForm.title = row.title
  dpaForm.version = row.version
  dpaForm.content = row.content || ''
  dpaForm.jurisdiction = row.jurisdiction || ''
  dpaForm.data_categories = row.data_categories || []
  dpaForm.processing_purposes = row.processing_purposes || []
  dpaForm.security_measures = row.security_measures || []
  dpaForm.expires_at = row.expires_at || null
  dpaFormVisible.value = true
}

async function saveDpa() {
  const valid = await dpaFormRef.value?.validate().catch(() => false)
  if (!valid) return

  loading.saveDpa = true
  try {
    const payload = { ...dpaForm }
    if (isEditingDpa.value) {
      await updateDpa(editingDpaId.value, payload)
      ElMessage.success(t('gdpr_page.dpa.updated_ok'))
    } else {
      await createDpa(payload)
      ElMessage.success(t('gdpr_page.dpa.created_ok'))
    }
    dpaFormVisible.value = false
    await loadDpas()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('gdpr_page.dpa.save_fail'))
  } finally {
    loading.saveDpa = false
  }
}

function showDpaDetail(row) {
  currentDetail.value = row
  detailVisible.value = true
}

async function handlePublishDpa(row) {
  try {
    await ElMessageBox.confirm(
      t('gdpr_page.dpa.publish_confirm_named', { title: row.title, version: row.version }),
      t('actions.confirm'),
    )
    await publishDpa(row.id)
    ElMessage.success(t('gdpr_page.dpa.publish_ok'))
    await loadDpas()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || t('gdpr_page.dpa.publish_fail'))
  }
}

onMounted(async () => {
  await Promise.all([loadRequests(), refreshStats(), loadDpas()])
})
</script>

<style scoped>
.gdpr-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  text-align: center;
}

.stat-card .stat-value {
  font-size: 32px;
  font-weight: 700;
  line-height: 1.2;
}

.stat-card .stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pagination-wrap {
  display: flex;
  justify-content: center;
  margin-top: 16px;
}

.text-muted {
  color: #909399;
  font-size: 12px;
}

.text-danger {
  color: #f56c6c;
}
</style>
