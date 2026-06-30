<template>
  <div class="invoice-enhance">
    <h2 class="mb-4">发票与账单管理增强</h2>

    <!-- 概览统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.pending_reconciliations }}</div>
            <div class="stat-label">待处理对账</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ stats.monthly_invoice_count }}</div>
            <div class="stat-label">本月发票数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">¥{{ stats.monthly_invoice_total }}</div>
            <div class="stat-label">本月发票金额</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">¥{{ stats.monthly_paid_total }}</div>
            <div class="stat-label">本月已收金额</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- 发票模板 -->
      <el-tab-pane label="发票模板" name="templates">
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showTemplateDialog(null)">
            <el-icon><Plus /></el-icon> 新建模板
          </el-button>
        </div>
        <el-table :data="templates" stripe v-loading="loading.templates" size="small">
          <el-table-column label="模板名称" prop="name" min-width="160" />
          <el-table-column label="编码" prop="code" width="120" />
          <el-table-column label="默认" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_default ? 'success' : 'info'" size="small">{{ row.is_default ? '是' : '否' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="配色" prop="color_scheme" width="80" />
          <el-table-column label="语言" prop="locale" width="70" />
          <el-table-column label="状态" width="70">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" prop="created_at" width="160" />
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button link size="small" type="primary" @click="showTemplateDialog(row)">编辑</el-button>
              <el-popconfirm title="确认删除？" @confirm="deleteTemplate(row.id)">
                <template #reference>
                  <el-button link size="small" type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 账单对账 -->
      <el-tab-pane label="账单对账" name="reconciliations">
        <!-- 对账统计 -->
        <el-row :gutter="20" class="mb-3">
          <el-col :span="6">
            <el-statistic title="待对账" :value="reconStats.total_count || 0" />
          </el-col>
          <el-col :span="6">
            <el-statistic title="不匹配" :value="reconStats.unmatched_count || 0">
              <template #suffix>
                <el-tag v-if="reconStats.unmatched_count > 0" type="danger" size="small">需处理</el-tag>
              </template>
            </el-statistic>
          </el-col>
          <el-col :span="6">
            <el-statistic title="金额差异" :value="reconStats.total_difference || 0" decimal-separator=".">
              <template #suffix>元</template>
            </el-statistic>
          </el-col>
          <el-col :span="6">
            <el-button type="success" :loading="autoReconciling" @click="doAutoReconcile">
              自动对账
            </el-button>
          </el-col>
        </el-row>

        <el-table :data="reconciliations" stripe v-loading="loading.reconciliations" size="small">
          <el-table-column label="发票号" width="160">
            <template #default="{ row }">{{ row.invoice?.invoice_no || '-' }}</template>
          </el-table-column>
          <el-table-column label="客户" prop="customer?.name" width="120" />
          <el-table-column label="发票金额" prop="invoice_amount" width="100">
            <template #default="{ row }">¥{{ row.invoice_amount }}</template>
          </el-table-column>
          <el-table-column label="实际金额" prop="actual_amount" width="100">
            <template #default="{ row }">¥{{ row.actual_amount }}</template>
          </el-table-column>
          <el-table-column label="差异" prop="difference" width="100">
            <template #default="{ row }">
              <span :class="row.difference === 0 ? 'text-success' : 'text-danger'">
                {{ row.difference > 0 ? '+' : '' }}{{ row.difference }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="支付参考" prop="payment_ref" width="140" show-overflow-tooltip />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="reconStatusTag(row.status)" size="small">{{ reconStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="创建时间" prop="created_at" width="160" />
          <el-table-column label="操作" width="160" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'unmatched' || row.status === 'pending'" link size="small" type="primary" @click="resolveRecon(row)">
                解决
              </el-button>
              <el-tag v-else type="success" size="small">{{ row.status === 'matched' ? '已匹配' : '已解决' }}</el-tag>
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
      <el-tab-pane label="账单拆分" name="splits">
        <div class="flex justify-between items-center mb-3">
          <el-button type="primary" @click="showSplitDialog">
            <el-icon><Plus /></el-icon> 拆分账单
          </el-button>
        </div>
        <el-table :data="splits" stripe v-loading="loading.splits" size="small">
          <el-table-column label="原发票号" width="160">
            <template #default="{ row }">{{ row.original_invoice?.invoice_no }}</template>
          </el-table-column>
          <el-table-column label="原金额" width="100">
            <template #default="{ row }">¥{{ row.original_invoice?.amount }}</template>
          </el-table-column>
          <el-table-column label="拆分发票号" width="160">
            <template #default="{ row }">{{ row.split_invoice?.invoice_no }}</template>
          </el-table-column>
          <el-table-column label="拆分金额" width="100">
            <template #default="{ row }">
              <span class="text-warning">¥{{ row.amount }}</span>
            </template>
          </el-table-column>
          <el-table-column label="原因" prop="reason" min-width="180" show-overflow-tooltip />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'completed' ? 'success' : 'info'" size="small">
                {{ row.status === 'completed' ? '已完成' : row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="时间" prop="created_at" width="160" />
        </el-table>
        <div class="flex justify-center mt-4" v-if="splitPagination.total > splitPagination.per_page">
          <el-pagination background layout="prev, pager, next"
            :total="splitPagination.total" :page-size="splitPagination.per_page"
            :current-page="splitPagination.current_page" @current-change="loadSplits" />
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- 模板编辑对话框 -->
    <el-dialog v-model="templateDialog.visible" :title="templateDialog.isEdit ? '编辑模板' : '新建模板'" width="680">
      <el-form :model="templateDialog.form" label-width="120" size="small">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="编码" prop="code">
              <el-input v-model="templateDialog.form.code" placeholder="唯一编码" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="名称" prop="name">
              <el-input v-model="templateDialog.form.name" placeholder="模板名称" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="8">
            <el-form-item label="默认">
              <el-switch v-model="templateDialog.form.is_default" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="启用">
              <el-switch v-model="templateDialog.form.is_active" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="配色" prop="color_scheme">
              <el-select v-model="templateDialog.form.color_scheme" style="width:100%">
                <el-option label="蓝色" value="blue" />
                <el-option label="红色" value="red" />
                <el-option label="绿色" value="green" />
                <el-option label="灰色" value="gray" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="页眉">
          <el-input v-model="headerText" type="textarea" :rows="3" placeholder="JSON 格式的页眉配置" />
        </el-form-item>
        <el-form-item label="页脚">
          <el-input v-model="footerText" type="textarea" :rows="3" placeholder="JSON 格式的页脚配置" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="templateDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="templateDialog.saving" @click="saveTemplate">保存</el-button>
      </template>
    </el-dialog>

    <!-- 拆分对话框 -->
    <el-dialog v-model="splitDialog.visible" title="拆分账单" width="500">
      <el-form :model="splitDialog" label-width="120">
        <el-form-item label="原始发票ID" prop="original_invoice_id">
          <el-input-number v-model="splitDialog.original_invoice_id" :min="1" style="width:100%" />
        </el-form-item>
        <el-form-item label="拆分金额" prop="amount">
          <el-input-number v-model="splitDialog.amount" :min="1" :precision="2" style="width:100%" />
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="splitDialog.reason" placeholder="拆分原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="splitDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="splitDialog.saving" @click="doSplit">确认拆分</el-button>
      </template>
    </el-dialog>

    <!-- 解决对账对话框 -->
    <el-dialog v-model="resolveDialog.visible" title="解决对账" width="420">
      <p class="mb-3">发票金额: ¥{{ resolveDialog.invoiceAmount }} | 实际金额: ¥{{ resolveDialog.actualAmount }}</p>
      <p class="mb-3 text-danger" v-if="resolveDialog.difference !== 0">差异: ¥{{ resolveDialog.difference }}</p>
      <el-input v-model="resolveDialog.notes" type="textarea" :rows="3" placeholder="处理说明" />
      <template #footer>
        <el-button @click="resolveDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="resolveDialog.saving" @click="doResolve">确认解决</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getInvoiceTemplates, createInvoiceTemplate, updateInvoiceTemplate, deleteInvoiceTemplate,
  getReconciliations, createReconciliation, resolveReconciliation, getReconciliationStats, autoReconcile,
  getInvoiceSplits, splitInvoice, getInvoiceEnhanceStats,
} from '../../api/invoiceEnhance'

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
  const map = { pending: '待对账', matched: '已匹配', unmatched: '不匹配', resolved: '已解决' }
  return map[s] || s
}

async function loadTemplates() {
  loading.templates = true
  try { const { data } = await getInvoiceTemplates(); templates.value = data || [] }
  catch (e) { ElMessage.error('加载模板失败') }
  finally { loading.templates = false }
}

async function loadReconciliations(page = 1) {
  loading.reconciliations = true
  try {
    const { data } = await getReconciliations({ page })
    reconciliations.value = data.data || []
    Object.assign(reconPagination, data)
  } catch (e) { ElMessage.error('加载对账记录失败') }
  finally { loading.reconciliations = false }
}

async function loadSplits(page = 1) {
  loading.splits = true
  try {
    const { data } = await getInvoiceSplits({ page })
    splits.value = data.data || []
    Object.assign(splitPagination, data)
  } catch (e) { ElMessage.error('加载拆分记录失败') }
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
      ElMessage.success('已更新')
    } else {
      await createInvoiceTemplate(templateDialog.form)
      ElMessage.success('已创建')
    }
    templateDialog.visible = false
    loadTemplates()
  } catch (e) { ElMessage.error('保存失败') }
  finally { templateDialog.saving = false }
}

async function deleteTemplate(id) {
  try { await deleteInvoiceTemplate(id); ElMessage.success('已删除'); loadTemplates() }
  catch (e) { ElMessage.error('删除失败') }
}

async function doAutoReconcile() {
  autoReconciling.value = true
  try {
    const { data } = await autoReconcile()
    ElMessage.success(`对账完成：${data.processed} 条成功，${data.errors} 条失败`)
    loadReconciliations()
    loadReconStats()
  } catch (e) { ElMessage.error('自动对账失败') }
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
    ElMessage.success(`拆分成功：新发票 #${data.split_invoice?.invoice_no}`)
    splitDialog.visible = false
    loadSplits()
  } catch (e) { ElMessage.error(e.response?.data?.error || '拆分失败') }
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
    ElMessage.success('对账已解决')
    resolveDialog.visible = false
    loadReconciliations()
    loadReconStats()
  } catch (e) { ElMessage.error('解决失败') }
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
