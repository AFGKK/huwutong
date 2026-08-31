<template>
  <div class="auto-invoice-page">
    <div class="page-header">
      <h2>{{ t('auto_invoice_page.title') }}</h2>
      <div class="header-actions">
        <el-button @click="loadAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('auto_invoice_page.refresh') }}
        </el-button>
      </div>
    </div>

    <el-row :gutter="16" class="mb-6">
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t('auto_invoice_page.stats.total') }}</div>
          <div class="stat-value">{{ stats.total_invoices }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t('auto_invoice_page.stats.total_amount') }}</div>
          <div class="stat-value">¥{{ formatNum(stats.total_amount) }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t('auto_invoice_page.stats.today') }}</div>
          <div class="stat-value">{{ stats.today_invoices }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t('auto_invoice_page.stats.today_amount') }}</div>
          <div class="stat-value">¥{{ formatNum(stats.today_amount) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover" class="mb-6">
      <template #header>
        <div class="card-header">
          <span>{{ t('auto_invoice_page.titles_section') }}</span>
          <el-button size="small" type="primary" @click="showTitleDialog = true; editingTitle = false; Object.assign(titleForm, emptyTitle())">{{ t('auto_invoice_page.add_title') }}</el-button>
        </div>
      </template>
      <div v-if="titles.length === 0" class="empty-state">{{ t('auto_invoice_page.empty_titles') }}</div>
      <el-table v-else :data="titles" size="small" stripe>
        <el-table-column prop="title" :label="t('auto_invoice_page.cols.title_name')" min-width="160" />
        <el-table-column prop="tax_no" :label="t('auto_invoice_page.cols.tax_no')" width="140" />
        <el-table-column :label="t('auto_invoice_page.cols.default')" width="60" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_default" type="success" size="small">{{ t('auto_invoice_page.default') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('auto_invoice_page.cols.actions')" width="160" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="editTitle(row)">{{ t('actions.edit') }}</el-button>
            <el-button size="small" type="danger" @click="deleteTitle(row)">{{ t('actions.delete') }}</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-card shadow="hover" class="mb-6">
      <el-form :inline="true" :model="filters">
        <el-form-item :label="t('actions.search')">
          <el-input v-model="filters.search" :placeholder="t('auto_invoice_page.search_ph')" clearable style="width:180px" @input="onSearch" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.date_from')">
          <el-date-picker v-model="filters.date_from" type="date" :placeholder="t('auto_invoice_page.start')" style="width:140px" @change="loadInvoices" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.date_to')">
          <el-date-picker v-model="filters.date_to" type="date" :placeholder="t('auto_invoice_page.end')" style="width:140px" @change="loadInvoices" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadInvoices()">{{ t('auto_invoice_page.query') }}</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-table :data="invoices" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="invoice_no" :label="t('auto_invoice_page.cols.invoice_no')" width="200" />
      <el-table-column :label="t('auto_invoice_page.cols.customer')" min-width="140">
        <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
      </el-table-column>
      <el-table-column prop="subtotal" :label="t('auto_invoice_page.cols.amount')" width="120" align="right">
        <template #default="{ row }">¥{{ formatNum(row.amount) }}</template>
      </el-table-column>
      <el-table-column prop="tax_amount" :label="t('auto_invoice_page.cols.tax')" width="100" align="right">
        <template #default="{ row }">¥{{ formatNum(row.tax_amount) }}</template>
      </el-table-column>
      <el-table-column prop="status" :label="t('auto_invoice_page.cols.status')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" :label="t('auto_invoice_page.cols.issued_at')" width="170" />
      <el-table-column :label="t('auto_invoice_page.cols.actions')" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
          <el-button size="small" @click="preview(row)">{{ t('auto_invoice_page.preview') }}</el-button>
          <el-button size="small" @click="resend(row)">{{ t('auto_invoice_page.resend') }}</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-pagination
      v-if="pagination.total > pagination.per_page"
      background layout="prev,pager,next,total"
      :total="pagination.total" :page-size="pagination.per_page"
      :current-page="pagination.current_page"
      @current-change="onPageChange"
      style="margin-top:16px;justify-content:center"
    />

    <el-drawer v-model="showDetailDrawer" :title="t('auto_invoice_page.detail_title', { no: detail?.invoice_no || '' })" size="500px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('auto_invoice_page.cols.invoice_no')">{{ detail.invoice_no }}</el-descriptions-item>
          <el-descriptions-item :label="t('auto_invoice_page.cols.status')">
            <el-tag :type="detail.status === 'paid' ? 'success' : 'warning'" size="small">{{ detail.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('auto_invoice_page.cols.amount')">¥{{ formatNum(detail.amount) }}</el-descriptions-item>
          <el-descriptions-item :label="t('auto_invoice_page.cols.tax')">¥{{ formatNum(detail.tax_amount) }}</el-descriptions-item>
          <el-descriptions-item :label="t('auto_invoice_page.cols.issued_at')">{{ detail.created_at }}</el-descriptions-item>
          <el-descriptions-item :label="t('auto_invoice_page.cols.notes')">{{ detail.notes || '—' }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin:20px 0 12px">{{ t('auto_invoice_page.line_items') }}</h4>
        <el-table :data="detail.line_items || []" size="small" stripe>
          <el-table-column prop="description" :label="t('auto_invoice_page.cols.item')" min-width="120" />
          <el-table-column prop="quantity" :label="t('auto_invoice_page.cols.qty')" width="60" />
          <el-table-column prop="unit_price" :label="t('auto_invoice_page.cols.unit_price')" width="80" align="right">
            <template #default="{ row }">¥{{ formatNum(row.unit_price) }}</template>
          </el-table-column>
          <el-table-column prop="subtotal" :label="t('auto_invoice_page.cols.subtotal')" width="80" align="right">
            <template #default="{ row }">¥{{ formatNum(row.subtotal) }}</template>
          </el-table-column>
        </el-table>

        <div style="margin-top:16px;display:flex;gap:8px">
          <el-button @click="preview(detail)" :icon="View">{{ t('auto_invoice_page.preview_invoice') }}</el-button>
          <el-button @click="resend(detail)" :icon="Message">{{ t('auto_invoice_page.resend_email') }}</el-button>
        </div>
      </template>
    </el-drawer>

    <el-dialog v-model="showTitleDialog" :title="editingTitle ? t('auto_invoice_page.edit_title') : t('auto_invoice_page.new_title')" width="600px">
      <el-form :model="titleForm" label-width="100px" size="small">
        <el-form-item :label="t('auto_invoice_page.form.title')" required>
          <el-input v-model="titleForm.title" :placeholder="t('auto_invoice_page.form.title_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.tax_no')">
          <el-input v-model="titleForm.tax_no" :placeholder="t('auto_invoice_page.form.tax_no_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.address')">
          <el-input v-model="titleForm.address" :placeholder="t('auto_invoice_page.form.address_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.phone')">
          <el-input v-model="titleForm.phone" :placeholder="t('auto_invoice_page.form.phone_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.bank_name')">
          <el-input v-model="titleForm.bank_name" :placeholder="t('auto_invoice_page.form.bank_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.bank_account')">
          <el-input v-model="titleForm.bank_account" :placeholder="t('auto_invoice_page.form.bank_account_ph')" />
        </el-form-item>
        <el-form-item :label="t('auto_invoice_page.form.is_default')">
          <el-switch v-model="titleForm.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTitleDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingTitle" @click="saveTitle">{{ editingTitle ? t('actions.save') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh, View, Message } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getInvoiceStats, getInvoices, getInvoiceDetail,
  previewInvoice, resendInvoice,
  getInvoiceTitles, createInvoiceTitle, updateInvoiceTitle, deleteInvoiceTitle,
} from '@/api/autoInvoice'

const { t, locale } = useI18n()

const loading = ref(false)
const invoices = ref([])
const titles = ref([])
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const stats = reactive({ total_invoices: 0, total_amount: 0, today_invoices: 0, today_amount: 0 })
const filters = reactive({ search: '', date_from: '', date_to: '' })
const showDetailDrawer = ref(false)
const detail = ref(null)
const showTitleDialog = ref(false)
const editingTitle = ref(false)
const savingTitle = ref(false)

function emptyTitle() {
  return { title: '', tax_no: '', address: '', phone: '', bank_name: '', bank_account: '', is_default: false }
}
const titleForm = reactive(emptyTitle())

let searchTimer = null

function formatNum(val) {
  if (val === null || val === undefined) return '0.00'
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return Number(val).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadStats() {
  try {
    const res = await getInvoiceStats()
    Object.assign(stats, res.data || {})
  } catch { /* ignore */ }
}

async function loadInvoices(page = 1) {
  loading.value = true
  pagination.current_page = page
  try {
    const params = { ...filters, page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await getInvoices(params)
    const data = res.data?.data || res.data || []
    invoices.value = Array.isArray(data) ? data : []
    Object.assign(pagination, res.data || res.meta || {})
  } catch { invoices.value = [] }
  finally { loading.value = false }
}

async function loadTitles() {
  try {
    const res = await getInvoiceTitles()
    titles.value = res.data?.data || res.data || []
  } catch { /* ignore */ }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadInvoices(), 300)
}

function onPageChange(p) { loadInvoices(p) }

async function showDetail(row) {
  showDetailDrawer.value = true
  detail.value = null
  try {
    const res = await getInvoiceDetail(row.id)
    detail.value = res.data
  } catch { /* ignore */ }
}

async function preview(row) {
  try {
    const res = await previewInvoice(row.id)
    const win = window.open('', '_blank')
    if (win) {
      win.document.write(res.data || res)
      win.document.close()
    }
  } catch { /* ignore */ }
}

async function resend(row) {
  try {
    await resendInvoice(row.id)
    ElMessage.success(t('auto_invoice_page.messages.resent'))
  } catch { /* ignore */ }
}

async function saveTitle() {
  savingTitle.value = true
  try {
    if (editingTitle.value) {
      await updateInvoiceTitle(editingTitle.value, titleForm)
      ElMessage.success(t('auto_invoice_page.messages.title_updated'))
    } else {
      await createInvoiceTitle(titleForm)
      ElMessage.success(t('auto_invoice_page.messages.title_created'))
    }
    showTitleDialog.value = false
    loadTitles()
  } catch { /* ignore */ }
  finally { savingTitle.value = false }
}

function editTitle(row) {
  editingTitle.value = row.id
  Object.assign(titleForm, row)
  showTitleDialog.value = true
}

async function deleteTitle(row) {
  try {
    await ElMessageBox.confirm(t('auto_invoice_page.messages.delete_confirm', { title: row.title }), t('actions.confirm'))
    await deleteInvoiceTitle(row.id)
    ElMessage.success(t('auto_invoice_page.messages.deleted'))
    loadTitles()
  } catch { /* ignore */ }
}

function loadAll() {
  loadStats()
  loadInvoices()
}

onMounted(() => {
  loadAll()
  loadTitles()
})
</script>

<style scoped>
.auto-invoice-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.mb-6 { margin-bottom: 24px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: #303133; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.empty-state { padding: 24px; text-align: center; color: #909399; }
</style>
