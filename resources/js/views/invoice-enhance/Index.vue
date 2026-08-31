<template>
  <div class="invoice-enhance">
    <h2 class="mb-4">{{ t('invoice_enhance_page.title') }}</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.pending_reconciliations }}</div>
            <div class="stat-label">{{ t('invoice_enhance_page.stat_pending_reconciliations') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ stats.monthly_invoice_count }}</div>
            <div class="stat-label">{{ t('invoice_enhance_page.stat_monthly_invoice_count') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">¥{{ stats.monthly_invoice_total }}</div>
            <div class="stat-label">{{ t('invoice_enhance_page.stat_monthly_invoice_total') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">¥{{ stats.monthly_paid_total }}</div>
            <div class="stat-label">{{ t('invoice_enhance_page.stat_monthly_paid_total') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- 发票模板 -->
      <el-tab-pane :label="t('invoice_enhance_page.tab_templates')" name="templates">
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showTemplateDialog(null)">
            <el-icon><Plus /></el-icon> {{ t('invoice_enhance_page.btn_new_template') }}
          </el-button>
        </div>
        <el-table :data="templates" stripe v-loading="loading.templates" size="small">
          <el-table-column :label="t('invoice_enhance_page.col_template_name')" prop="name" min-width="160" />
          <el-table-column :label="t('invoice_enhance_page.col_code')" prop="code" width="120" />
          <el-table-column :label="t('invoice_enhance_page.col_default')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_default ? 'success' : 'info'" size="small">{{ row.is_default ? t('invoice_enhance_page.yes') : t('invoice_enhance_page.no') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('invoice_enhance_page.col_color_scheme')" prop="color_scheme" width="80" />
          <el-table-column :label="t('invoice_enhance_page.col_locale')" prop="locale" width="70" />
          <el-table-column :label="t('billing_page.col_status')" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('invoice_enhance_page.status_active') : t('invoice_enhance_page.status_inactive') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_created')" prop="created_at" width="160" />
          <el-table-column :label="t('billing_page.col_actions')" width="160" fixed="right">
            <template #default="{ row }">
              <el-button link size="small" type="primary" @click="showTemplateDialog(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm :title="t('invoice_enhance_page.confirm_delete')" @confirm="deleteTemplate(row.id)">
                <template #reference>
                  <el-button link size="small" type="danger">{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 账单对账 -->
      <el-tab-pane :label="t('invoice_enhance_page.tab_reconciliations')" name="reconciliations">
        <!-- 对账统计 -->
        <el-row :gutter="20" class="mb-3">
          <el-col :span="6">
            <el-statistic :title="t('invoice_enhance_page.stat_pending_recon')" :value="reconStats.total_count || 0" />
          </el-col>
          <el-col :span="6">
            <el-statistic :title="t('invoice_enhance_page.stat_unmatched')" :value="reconStats.unmatched_count || 0">
              <template #suffix>
                <el-tag v-if="reconStats.unmatched_count > 0" type="danger" size="small">{{ t('invoice_enhance_page.needs_action') }}</el-tag>
              </template>
            </el-statistic>
          </el-col>
          <el-col :span="6">
            <el-statistic :title="t('invoice_enhance_page.stat_amount_diff')" :value="reconStats.total_difference || 0" decimal-separator=".">
              <template #suffix>{{ t('invoice_enhance_page.currency_yuan') }}</template>
            </el-statistic>
          </el-col>
          <el-col :span="6">
            <el-button type="success" :loading="autoReconciling" @click="doAutoReconcile">
              {{ t('invoice_enhance_page.btn_auto_reconcile') }}
            </el-button>
          </el-col>
        </el-row>

        <el-table :data="reconciliations" stripe v-loading="loading.reconciliations" size="small">
          <el-table-column :label="t('billing_page.col_invoice_no')" width="160">
            <template #default="{ row }">{{ row.invoice?.invoice_no || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_customer')" prop="customer?.name" width="120" />
          <el-table-column :label="t('reconciliation_page.col_invoice_amount')" prop="invoice_amount" width="100">
            <template #default="{ row }">¥{{ row.invoice_amount }}</template>
          </el-table-column>
          <el-table-column :label="t('reconciliation_page.col_actual_amount')" prop="actual_amount" width="100">
            <template #default="{ row }">¥{{ row.actual_amount }}</template>
          </el-table-column>
          <el-table-column :label="t('reconciliation_page.col_difference')" prop="difference" width="100">
            <template #default="{ row }">
              <span :class="row.difference === 0 ? 'text-success' : 'text-danger'">
                {{ row.difference > 0 ? '+' : '' }}{{ row.difference }}
              </span>
            </template>
          </el-table-column>
          <el-table-column :label="t('reconciliation_page.col_payment_ref')" prop="payment_ref" width="140" show-overflow-tooltip />
          <el-table-column :label="t('billing_page.col_status')" width="90">
            <template #default="{ row }">
              <el-tag :type="reconStatusTag(row.status)" size="small">{{ reconStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_created')" prop="created_at" width="160" />
          <el-table-column :label="t('billing_page.col_actions')" width="160" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'unmatched' || row.status === 'pending'" link size="small" type="primary" @click="resolveRecon(row)">
                {{ t('reconciliation_page.resolve') }}
              </el-button>
              <el-tag v-else type="success" size="small">{{ row.status === 'matched' ? t('reconciliation_page.recon_st_matched') : t('invoice_enhance_page.status_resolved') }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <div class="flex justify-center mt-4" v-if="reconPagination.total > reconPagination.per_page">
          <el-pagination background layout="prev, pager, next"
            :total="reconPagination.total" :page-size="reconPagination.per_page"
            :current-page="reconPagination.current_page" @current-change="loadReconciliations" />
        </div>
      </el-tab-pane>

      <!-- 账单拆分 -->
      <el-tab-pane :label="t('invoice_enhance_page.tab_splits')" name="splits">
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showSplitDialog">
            <el-icon><Plus /></el-icon> {{ t('invoice_enhance_page.btn_split_invoice') }}
          </el-button>
        </div>
        <el-table :data="splits" stripe v-loading="loading.splits" size="small">
          <el-table-column :label="t('invoice_enhance_page.col_original_invoice_no')" width="160">
            <template #default="{ row }">{{ row.original_invoice?.invoice_no }}</template>
          </el-table-column>
          <el-table-column :label="t('invoice_enhance_page.col_original_amount')" width="100">
            <template #default="{ row }">¥{{ row.original_invoice?.amount }}</template>
          </el-table-column>
          <el-table-column :label="t('invoice_enhance_page.col_split_invoice_no')" width="160">
            <template #default="{ row }">{{ row.split_invoice?.invoice_no }}</template>
          </el-table-column>
          <el-table-column :label="t('invoice_enhance_page.col_split_amount')" width="100">
            <template #default="{ row }">
              <span class="text-warning">¥{{ row.amount }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('billing_page.col_reason')" prop="reason" min-width="180" show-overflow-tooltip />
          <el-table-column :label="t('billing_page.col_status')" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'completed' ? 'success' : 'info'" size="small">
                {{ splitStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('invoice_enhance_page.col_time')" prop="created_at" width="160" />
        </el-table>
        <div class="flex justify-center mt-4" v-if="splitPagination.total > splitPagination.per_page">
          <el-pagination background layout="prev, pager, next"
            :total="splitPagination.total" :page-size="splitPagination.per_page"
            :current-page="splitPagination.current_page" @current-change="loadSplits" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 模板编辑对话框 -->
    <el-dialog v-model="templateDialog.visible" :title="templateDialog.isEdit ? t('invoice_enhance_page.dialog_edit_template') : t('invoice_enhance_page.dialog_new_template')" width="680">
      <el-form :model="templateDialog.form" label-width="120" size="small">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('invoice_enhance_page.col_code')" prop="code">
              <el-input v-model="templateDialog.form.code" :placeholder="t('invoice_enhance_page.ph_unique_code')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('billing_page.col_name')" prop="name">
              <el-input v-model="templateDialog.form.name" :placeholder="t('invoice_enhance_page.ph_template_name')" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item :label="t('invoice_enhance_page.col_default')">
              <el-switch v-model="templateDialog.form.is_default" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('actions.enable')">
              <el-switch v-model="templateDialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('invoice_enhance_page.col_color_scheme')" prop="color_scheme">
              <el-select v-model="templateDialog.form.color_scheme" style="width:100%">
                <el-option v-for="opt in colorSchemeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('invoice_enhance_page.form_header')">
          <el-input v-model="headerText" type="textarea" :rows="3" :placeholder="t('invoice_enhance_page.ph_header_json')" />
        </el-form-item>
        <el-form-item :label="t('invoice_enhance_page.form_footer')">
          <el-input v-model="footerText" type="textarea" :rows="3" :placeholder="t('invoice_enhance_page.ph_footer_json')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="templateDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="templateDialog.saving" @click="saveTemplate">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 拆分对话框 -->
    <el-dialog v-model="splitDialog.visible" :title="t('invoice_enhance_page.dialog_split')" width="500">
      <el-form :model="splitDialog" label-width="120">
        <el-form-item :label="t('invoice_enhance_page.form_original_invoice_id')" prop="original_invoice_id">
          <el-input-number v-model="splitDialog.original_invoice_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('invoice_enhance_page.form_split_amount')" prop="amount">
          <el-input-number v-model="splitDialog.amount" :min="1" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('billing_page.col_reason')">
          <el-input v-model="splitDialog.reason" :placeholder="t('invoice_enhance_page.ph_split_reason')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="splitDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="splitDialog.saving" @click="doSplit">{{ t('invoice_enhance_page.btn_confirm_split') }}</el-button>
      </template>
    </el-dialog>

    <!-- 解决对账对话框 -->
    <el-dialog v-model="resolveDialog.visible" :title="t('invoice_enhance_page.dialog_resolve')" width="420">
      <p class="mb-3">{{ t('invoice_enhance_page.resolve_invoice_amount') }}: ¥{{ resolveDialog.invoiceAmount }} | {{ t('invoice_enhance_page.resolve_actual_amount') }}: ¥{{ resolveDialog.actualAmount }}</p>
      <p class="mb-3 text-danger" v-if="resolveDialog.difference !== 0">{{ t('invoice_enhance_page.resolve_difference') }}: ¥{{ resolveDialog.difference }}</p>
      <el-input v-model="resolveDialog.notes" type="textarea" :rows="3" :placeholder="t('invoice_enhance_page.ph_resolution_notes')" />
      <template #footer>
        <el-button @click="resolveDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="resolveDialog.saving" @click="doResolve">{{ t('invoice_enhance_page.btn_confirm_resolve') }}</el-button>
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
  getInvoiceTemplates, createInvoiceTemplate, updateInvoiceTemplate, deleteInvoiceTemplate,
  getReconciliations, createReconciliation, resolveReconciliation, getReconciliationStats, autoReconcile,
  getInvoiceSplits, splitInvoice, getInvoiceEnhanceStats,
} from '../../api/invoiceEnhance'

const { t } = useI18n()

const activeTab = ref('templates')
const loading = reactive({ templates: false, reconciliations: false, splits: false })
const autoReconciling = ref(false)
const templates = ref([])
const reconciliations = ref([])
const reconPagination = reactive({ total: 0, per_page: 20, current_page: 1 })
const splits = ref([])
const splitPagination = reactive({ total: 0, per_page: 20, current_page: 1 })
const stats = ref({})
const reconStats = ref({})

const templateDialog = reactive({
  visible: false, isEdit: false, saving: false,
  form: { code: '', name: '', is_default: false, is_active: true, color_scheme: 'blue', header: null, footer: null },
})

const splitDialog = reactive({
  visible: false, original_invoice_id: null, amount: 100, reason: '', saving: false,
})

const resolveDialog = reactive({
  visible: false, id: null, invoiceAmount: 0, actualAmount: 0, difference: 0, notes: '', saving: false,
})

const colorSchemeOptions = computed(() => [
  { label: t('invoice_enhance_page.color_blue'), value: 'blue' },
  { label: t('invoice_enhance_page.color_red'), value: 'red' },
  { label: t('invoice_enhance_page.color_green'), value: 'green' },
  { label: t('invoice_enhance_page.color_gray'), value: 'gray' },
])

const headerText = computed({
  get: () => templateDialog.form.header ? JSON.stringify(templateDialog.form.header, null, 2) : '',
  set: (val) => { try { templateDialog.form.header = val ? JSON.parse(val) : null } catch { /* ignore */ } },
})
const footerText = computed({
  get: () => templateDialog.form.footer ? JSON.stringify(templateDialog.form.footer, null, 2) : '',
  set: (val) => { try { templateDialog.form.footer = val ? JSON.parse(val) : null } catch { /* ignore */ } },
})

function reconStatusTag(s) {
  const map = { pending: 'warning', matched: 'success', unmatched: 'danger', resolved: 'info' }
  return map[s] || ''
}

function reconStatusLabel(s) {
  const map = {
    pending: t('reconciliation_page.recon_st_pending'),
    matched: t('reconciliation_page.recon_st_matched'),
    unmatched: t('reconciliation_page.recon_st_unmatched'),
    resolved: t('reconciliation_page.recon_st_resolved'),
  }
  return map[s] || s
}

function splitStatusLabel(s) {
  return s === 'completed' ? t('invoice_enhance_page.status_completed') : s
}

async function loadTemplates() {
  loading.templates = true
  try { const { data } = await getInvoiceTemplates(); templates.value = data || [] }
  catch (e) { ElMessage.error(t('invoice_enhance_page.messages.load_templates_failed')) }
  finally { loading.templates = false }
}

async function loadReconciliations(page = 1) {
  loading.reconciliations = true
  try {
    const { data } = await getReconciliations({ page })
    reconciliations.value = data.data || []
    Object.assign(reconPagination, data)
  } catch (e) { ElMessage.error(t('invoice_enhance_page.messages.load_reconciliations_failed')) }
  finally { loading.reconciliations = false }
}

async function loadSplits(page = 1) {
  loading.splits = true
  try {
    const { data } = await getInvoiceSplits({ page })
    splits.value = data.data || []
    Object.assign(splitPagination, data)
  } catch (e) { ElMessage.error(t('invoice_enhance_page.messages.load_splits_failed')) }
  finally { loading.splits = false }
}

function showTemplateDialog(template) {
  templateDialog.isEdit = !!template
  templateDialog.form = template
    ? { ...template }
    : { code: '', name: '', is_default: false, is_active: true, color_scheme: 'blue', header: null, footer: null }
  templateDialog.visible = true
}

async function saveTemplate() {
  templateDialog.saving = true
  try {
    if (templateDialog.isEdit) {
      await updateInvoiceTemplate(templateDialog.form.id, templateDialog.form)
      ElMessage.success(t('invoice_enhance_page.messages.updated'))
    } else {
      await createInvoiceTemplate(templateDialog.form)
      ElMessage.success(t('invoice_enhance_page.messages.created'))
    }
    templateDialog.visible = false
    loadTemplates()
  } catch (e) { ElMessage.error(t('invoice_enhance_page.messages.save_failed')) }
  finally { templateDialog.saving = false }
}

async function deleteTemplate(id) {
  try { await deleteInvoiceTemplate(id); ElMessage.success(t('invoice_enhance_page.messages.deleted')); loadTemplates() }
  catch (e) { ElMessage.error(t('invoice_enhance_page.messages.delete_failed')) }
}

async function doAutoReconcile() {
  autoReconciling.value = true
  try {
    const { data } = await autoReconcile()
    ElMessage.success(t('invoice_enhance_page.messages.auto_reconcile_done', { processed: data.processed, errors: data.errors }))
    loadReconciliations()
    loadReconStats()
  } catch (e) { ElMessage.error(t('invoice_enhance_page.messages.auto_reconcile_failed')) }
  finally { autoReconciling.value = false }
}

async function loadReconStats() {
  try { const { data } = await getReconciliationStats(); reconStats.value = data || {} }
  catch (e) { /* ignore */ }
}

function showSplitDialog() {
  splitDialog.original_invoice_id = null
  splitDialog.amount = 100
  splitDialog.reason = ''
  splitDialog.visible = true
}

async function doSplit() {
  splitDialog.saving = true
  try {
    const { data } = await splitInvoice({
      original_invoice_id: splitDialog.original_invoice_id,
      amount: splitDialog.amount,
      reason: splitDialog.reason,
    })
    ElMessage.success(t('invoice_enhance_page.messages.split_success', { invoiceNo: data.split_invoice?.invoice_no }))
    splitDialog.visible = false
    loadSplits()
  } catch (e) { ElMessage.error(e.response?.data?.error || t('invoice_enhance_page.messages.split_failed')) }
  finally { splitDialog.saving = false }
}

function resolveRecon(row) {
  resolveDialog.id = row.id
  resolveDialog.invoiceAmount = row.invoice_amount
  resolveDialog.actualAmount = row.actual_amount
  resolveDialog.difference = row.difference
  resolveDialog.notes = ''
  resolveDialog.visible = true
}

async function doResolve() {
  resolveDialog.saving = true
  try {
    await resolveReconciliation(resolveDialog.id, { resolution: 'manual', notes: resolveDialog.notes })
    ElMessage.success(t('invoice_enhance_page.messages.resolve_success'))
    resolveDialog.visible = false
    loadReconciliations()
    loadReconStats()
  } catch (e) { ElMessage.error(t('invoice_enhance_page.messages.resolve_failed')) }
  finally { resolveDialog.saving = false }
}

onMounted(async () => {
  const statsRes = await getInvoiceEnhanceStats().catch(() => ({ data: {} }))
  stats.value = statsRes.data || {}
  loadTemplates()
  loadReconciliations()
  loadSplits()
  loadReconStats()
})
</script>

<style scoped>
.invoice-enhance { min-height: 400px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-danger { color: #f56c6c !important; }
.text-warning { color: #e6a23c !important; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-4 { margin-top: 16px; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.justify-center { justify-content: center; }
</style>
