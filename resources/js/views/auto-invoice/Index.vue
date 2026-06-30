<template>
  <div class="auto-invoice-page">
    <div class="page-header">
      <h2>📄 自动开票管理</h2>
      <div class="header-actions">
        <el-button @click="loadAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">总发票数</div>
          <div class="stat-value">{{ stats.total_invoices }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">总开票金额</div>
          <div class="stat-value">¥{{ formatNum(stats.total_amount) }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">今日开票数</div>
          <div class="stat-value">{{ stats.today_invoices }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">今日开票金额</div>
          <div class="stat-value">¥{{ formatNum(stats.today_amount) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 发票抬头管理 -->
    <el-card shadow="hover" class="mb-6">
      <template #header>
        <div class="card-header">
          <span>🏢 企业发票抬头</span>
          <el-button size="small" type="primary" @click="showTitleDialog = true; titleForm = {}">新增抬头</el-button>
        </div>
      </template>
      <div v-if="titles.length === 0" class="empty-state">暂无发票抬头，请添加</div>
      <el-table v-else :data="titles" size="small" stripe>
        <el-table-column prop="title" label="抬头名称" min-width="160" />
        <el-table-column prop="tax_no" label="税号" width="140" />
        <el-table-column label="默认" width="60" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.is_default" type="success" size="small">默认</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="editTitle(row)">编辑</el-button>
            <el-button size="small" type="danger" @click="deleteTitle(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 筛选 -->
    <el-card shadow="hover" class="mb-6">
      <el-form :inline="true" :model="filters">
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="发票号/客户名" clearable style="width:180px" @input="onSearch" />
        </el-form-item>
        <el-form-item label="日期从">
          <el-date-picker v-model="filters.date_from" type="date" placeholder="开始" style="width:140px" @change="loadInvoices" />
        </el-form-item>
        <el-form-item label="日期至">
          <el-date-picker v-model="filters.date_to" type="date" placeholder="结束" style="width:140px" @change="loadInvoices" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadInvoices">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 发票列表 -->
    <el-table :data="invoices" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="invoice_no" label="发票号" width="200" />
      <el-table-column label="客户" min-width="140">
        <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
      </el-table-column>
      <el-table-column prop="subtotal" label="金额" width="120" align="right">
        <template #default="{ row }">¥{{ formatNum(row.amount) }}</template>
      </el-table-column>
      <el-table-column prop="tax_amount" label="税额" width="100" align="right">
        <template #default="{ row }">¥{{ formatNum(row.tax_amount) }}</template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="created_at" label="开票日期" width="170" />
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="showDetail(row)">详情</el-button>
          <el-button size="small" @click="preview(row)">预览</el-button>
          <el-button size="small" @click="resend(row)">重发</el-button>
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

    <!-- 发票详情抽屉 -->
    <el-drawer v-model="showDetailDrawer" :title="'发票 #' + (detail?.invoice_no || '')" size="500px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="发票号">{{ detail.invoice_no }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.status === 'paid' ? 'success' : 'warning'" size="small">{{ detail.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="金额">¥{{ formatNum(detail.amount) }}</el-descriptions-item>
          <el-descriptions-item label="税额">¥{{ formatNum(detail.tax_amount) }}</el-descriptions-item>
          <el-descriptions-item label="开票日期">{{ detail.created_at }}</el-descriptions-item>
          <el-descriptions-item label="备注">{{ detail.notes || '—' }}</el-descriptions-item>
        </el-descriptions>

        <h4 style="margin:20px 0 12px">明细</h4>
        <el-table :data="detail.line_items || []" size="small" stripe>
          <el-table-column prop="description" label="商品" min-width="120" />
          <el-table-column prop="quantity" label="数量" width="60" />
          <el-table-column prop="unit_price" label="单价" width="80" align="right">
            <template #default="{ row }">¥{{ formatNum(row.unit_price) }}</template>
          </el-table-column>
          <el-table-column prop="subtotal" label="小计" width="80" align="right">
            <template #default="{ row }">¥{{ formatNum(row.subtotal) }}</template>
          </el-table-column>
        </el-table>

        <div style="margin-top:16px;display:flex;gap:8px">
          <el-button @click="preview(detail)" :icon="View">预览发票</el-button>
          <el-button @click="resend(detail)" :icon="Message">重发邮件</el-button>
        </div>
      </template>
    </el-drawer>

    <!-- 发票抬头对话框 -->
    <el-dialog v-model="showTitleDialog" :title="editingTitle ? '编辑发票抬头' : '新增发票抬头'" width="600px">
      <el-form :model="titleForm" label-width="100px" size="small">
        <el-form-item label="抬头名称" required>
          <el-input v-model="titleForm.title" placeholder="企业全称" />
        </el-form-item>
        <el-form-item label="税号">
          <el-input v-model="titleForm.tax_no" placeholder="统一社会信用代码" />
        </el-form-item>
        <el-form-item label="地址">
          <el-input v-model="titleForm.address" placeholder="注册地址" />
        </el-form-item>
        <el-form-item label="电话">
          <el-input v-model="titleForm.phone" placeholder="注册电话" />
        </el-form-item>
        <el-form-item label="开户行">
          <el-input v-model="titleForm.bank_name" placeholder="开户银行名称" />
        </el-form-item>
        <el-form-item label="银行账号">
          <el-input v-model="titleForm.bank_account" placeholder="银行账号" />
        </el-form-item>
        <el-form-item label="设为默认">
          <el-switch v-model="titleForm.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTitleDialog = false">取消</el-button>
        <el-button type="primary" :loading="savingTitle" @click="saveTitle">{{ editingTitle ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Refresh, View, Message } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getInvoiceStats, getInvoices, getInvoiceDetail,
  previewInvoice, generateInvoice, resendInvoice,
  getInvoiceTitles, createInvoiceTitle, updateInvoiceTitle, deleteInvoiceTitle,
} from '@/api/autoInvoice'

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
const titleForm = reactive({ title: '', tax_no: '', address: '', phone: '', bank_name: '', bank_account: '', is_default: false })

let searchTimer = null

function formatNum(val) {
  if (val === null || val === undefined) return '0.00'
  return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
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
    ElMessage.success('发票邮件已重新发送')
  } catch { /* ignore */ }
}

async function saveTitle() {
  savingTitle.value = true
  try {
    if (editingTitle.value) {
      await updateInvoiceTitle(editingTitle.value, titleForm)
      ElMessage.success('发票抬头已更新')
    } else {
      await createInvoiceTitle(titleForm)
      ElMessage.success('发票抬头已创建')
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
    await ElMessageBox.confirm(`确定删除抬头「${row.title}」？`)
    await deleteInvoiceTitle(row.id)
    ElMessage.success('已删除')
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
