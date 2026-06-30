<template>
  <div class="tax-compliance-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><List /></el-icon>全球税收合规</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value warning">{{ dashboard.overdue_documents }}</div>
          <div class="stat-label">逾期文档</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.pending_documents }}</div>
          <div class="stat-label">待处理文档</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value success">{{ dashboard.filed_reports }}</div>
          <div class="stat-label">已申报报告</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value primary">¥{{ formatMoney(dashboard.quarter_liability) }}</div>
          <div class="stat-label">本季度负债</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.active_rules }}</div>
          <div class="stat-label">活跃规则</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.covered_countries }}</div>
          <div class="stat-label">覆盖国家</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.upcoming_due }}</div>
          <div class="stat-label">即将到期(30天)</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value info">{{ dashboard.pending_reports }}</div>
          <div class="stat-label">待申报报告</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 税务报告 -->
        <el-tab-pane label="税务申报报告" name="reports">
          <div class="tab-toolbar">
            <el-select v-model="reportFilter.country" placeholder="国家" clearable style="width:100px">
              <el-option label="全部" value="" />
              <el-option v-for="c in countries" :key="c" :label="c" :value="c" />
            </el-select>
            <el-select v-model="reportFilter.status" placeholder="状态" clearable style="width:120px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option label="草稿" value="draft" />
              <el-option label="待提交" value="final" />
              <el-option label="已申报" value="filed" />
            </el-select>
            <el-select v-model="reportFilter.report_type" placeholder="报告类型" clearable style="width:140px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in reportTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showGenerateDialog = true">
              <el-icon><Plus /></el-icon> 生成报告
            </el-button>
          </div>
          <el-table :data="reports" stripe v-loading="reportsLoading">
            <el-table-column label="报告类型" width="120">
              <template #default="{ row }">{{ reportTypes[row.report_type] || row.report_type }}</template>
            </el-table-column>
            <el-table-column label="国家" width="70">
              <template #default="{ row }">{{ row.country }}</template>
            </el-table-column>
            <el-table-column label="周期" width="100">
              <template #default="{ row }">{{ row.period }}</template>
            </el-table-column>
            <el-table-column label="销售收入" width="120" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_sales) }}</template>
            </el-table-column>
            <el-table-column label="税款" width="100" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_tax_payable) }}</template>
            </el-table-column>
            <el-table-column label="减免/反向" width="100">
              <template #default="{ row }">{{ formatMoney(row.total_exempt_sales + row.total_reverse_charge) }}</template>
            </el-table-column>
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.status === 'filed' ? 'success' : row.status === 'final' ? 'warning' : 'info'" size="small">
                  {{ row.status === 'filed' ? '已申报' : row.status === 'final' ? '待提交' : '草稿' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status !== 'filed'" type="primary" link size="small" @click="handleFileReport(row)">申报</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 合规文档 -->
        <el-tab-pane label="合规文档与税局通信" name="documents">
          <div class="tab-toolbar">
            <el-input v-model="docSearch" placeholder="搜索标题/编号" clearable style="width:200px" @clear="loadDocuments" @keyup.enter="loadDocuments" />
            <el-select v-model="docFilter.status" placeholder="状态" clearable style="width:120px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in docStatuses" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="docFilter.document_type" placeholder="文档类型" clearable style="width:150px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in docTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showDocDialog = true">
              <el-icon><Plus /></el-icon> 新建文档
            </el-button>
          </div>
          <el-table :data="documents" stripe v-loading="docLoading">
            <el-table-column label="文档类型" width="110">
              <template #default="{ row }">{{ docTypes[row.document_type] || row.document_type }}</template>
            </el-table-column>
            <el-table-column label="国家" width="60">{{ row.country }}</el-table-column>
            <el-table-column label="标题" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ row.title }}</template>
            </el-table-column>
            <el-table-column label="编号" width="120">{{ row.reference_number || '-' }}</el-table-column>
            <el-table-column label="日期" width="100">
              <template #default="{ row }">{{ row.document_date }}</template>
            </el-table-column>
            <el-table-column label="到期日" width="100">
              <template #default="{ row }">{{ row.due_date || '-' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'overdue' ? 'danger' : row.status === 'completed' ? 'success' : row.status === 'archived' ? 'info' : 'warning'" size="small">
                  {{ docStatuses[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" link size="small" @click="editDoc(row)">编辑</el-button>
                <el-popconfirm title="确定删除?" @confirm="handleDeleteDoc(row)">
                  <template #reference>
                    <el-button type="danger" link size="small">删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 税务规则 -->
        <el-tab-pane label="特殊税务规则" name="rules">
          <div class="tab-toolbar">
            <el-select v-model="ruleFilter.rule_type" placeholder="规则类型" clearable style="width:140px">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in ruleTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="ruleFilter.is_active" placeholder="状态" clearable style="width:100px;margin-left:8px">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="true" />
              <el-option label="停用" value="false" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showRuleDialog = true">
              <el-icon><Plus /></el-icon> 新建规则
            </el-button>
          </div>
          <el-table :data="rules" stripe v-loading="rulesLoading">
            <el-table-column label="规则名称" min-width="160">
              <template #default="{ row }">{{ row.name }}</template>
            </el-table-column>
            <el-table-column label="类型" width="100">
              <template #default="{ row }">{{ ruleTypes[row.rule_type] || row.rule_type }}</template>
            </el-table-column>
            <el-table-column label="国家" width="60">
              <template #default="{ row }">{{ row.country || '全局' }}</template>
            </el-table-column>
            <el-table-column label="条件" width="120">
              <template #default="{ row }">{{ row.condition_type ? row.condition_type + '=' + row.condition_value : '无条件' }}</template>
            </el-table-column>
            <el-table-column label="动作" width="100">
              <template #default="{ row }">{{ ruleActions[row.action] || row.action }}</template>
            </el-table-column>
            <el-table-column label="调整值" width="80">
              <template #default="{ row }">{{ row.rate_modifier !== null ? (row.rate_modifier * 100).toFixed(1) + '%' : '-' }}</template>
            </el-table-column>
            <el-table-column label="活跃" width="60">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" size="small" @change="v => toggleRuleActive(row, v)" />
              </template>
            </el-table-column>
            <el-table-column label="描述" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">{{ row.description || '-' }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-popconfirm title="确定删除?" @confirm="handleDeleteRule(row)">
                  <template #reference>
                    <el-button type="danger" link size="small">删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 生成报告对话框 -->
    <el-dialog v-model="showGenerateDialog" title="生成税务报告" width="400px">
      <el-form :model="genForm" label-width="80px">
        <el-form-item label="国家" required>
          <el-input v-model="genForm.country" placeholder="2位国家代码" style="width:120px" maxlength="2" @input="v => genForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item label="周期" required>
          <el-date-picker v-model="genForm.period" type="month" placeholder="选择月份" format="YYYY-MM" value-format="YYYY-MM" />
        </el-form-item>
        <el-form-item label="报告类型">
          <el-select v-model="genForm.report_type" style="width:100%">
            <el-option v-for="(l, k) in reportTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showGenerateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleGenerate" :loading="generating">生成</el-button>
      </template>
    </el-dialog>

    <!-- 文档编辑对话框 -->
    <el-dialog v-model="showDocDialog" :title="editingDocId ? '编辑文档' : '新建文档'" width="500px">
      <el-form :model="docForm" label-width="100px">
        <el-form-item label="文档类型" required>
          <el-select v-model="docForm.document_type" style="width:100%">
            <el-option v-for="(l, k) in docTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="国家" required>
          <el-input v-model="docForm.country" placeholder="2位国家代码" maxlength="2" style="width:100px" @input="v => docForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item label="标题" required>
          <el-input v-model="docForm.title" />
        </el-form-item>
        <el-form-item label="编号">
          <el-input v-model="docForm.reference_number" />
        </el-form-item>
        <el-form-item label="文档日期" required>
          <el-date-picker v-model="docForm.document_date" type="date" placeholder="选择日期" style="width:100%" />
        </el-form-item>
        <el-form-item label="到期日">
          <el-date-picker v-model="docForm.due_date" type="date" placeholder="选填" style="width:100%" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="docForm.status" style="width:100%">
            <el-option v-for="(l, k) in docStatuses" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="docForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDocDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveDoc" :loading="docSaving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 规则编辑对话框 -->
    <el-dialog v-model="showRuleDialog" title="新建税务规则" width="500px">
      <el-form :model="ruleForm" label-width="100px">
        <el-form-item label="规则名称" required>
          <el-input v-model="ruleForm.name" />
        </el-form-item>
        <el-form-item label="规则类型" required>
          <el-select v-model="ruleForm.rule_type" style="width:100%">
            <el-option v-for="(l, k) in ruleTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="国家">
          <el-input v-model="ruleForm.country" placeholder="留空=全局" maxlength="2" style="width:120px" @input="v => ruleForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item label="条件类型">
          <el-input v-model="ruleForm.condition_type" placeholder="e.g. amount_range" />
        </el-form-item>
        <el-form-item label="条件值">
          <el-input v-model="ruleForm.condition_value" />
        </el-form-item>
        <el-form-item label="动作" required>
          <el-select v-model="ruleForm.action" style="width:100%">
            <el-option v-for="(l, k) in ruleActions" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="调整值">
          <el-input-number v-model="ruleForm.rate_modifier" :min="0" :max="1" :step="0.01" style="width:100%" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="ruleForm.description" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRuleDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="ruleSaving">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { List, Refresh, Plus } from '@element-plus/icons-vue'
import api from '../../api/taxCompliance'

const loading = ref(false)
const activeTab = ref('reports')

// Dashboard
const dashboard = reactive({
    overdue_documents: 0, pending_documents: 0, upcoming_due: 0,
    pending_reports: 0, filed_reports: 0,
    active_rules: 0, covered_countries: 0, quarter_liability: 0,
})

// Constants
const reportTypes = { vat_return: 'VAT申报', gst_return: 'GST申报', sales_tax: '销售税申报', cross_border: '跨境交易', liability_summary: '负债汇总' }
const docTypes = { tax_return: '纳税申报表', filing_receipt: '申报回执', correspondence: '税局通信', certificate: '税务证明', audit_letter: '审计函件' }
const docStatuses = { pending: '待处理', completed: '已完成', overdue: '逾期', archived: '已归档' }
const ruleTypes = { reduced_rate: '减免税率', exemption: '免税规则', threshold: '阈值规则', special_zone: '特殊区域' }
const ruleActions = { apply_rate: '应用税率', exempt: '免税', reduce_rate: '减免', reverse_charge: '反向征收' }
const countries = ref([])
const reports = ref([])
const reportsLoading = ref(false)
const reportFilter = reactive({ country: '', status: '', report_type: '' })

// Documents
const documents = ref([])
const docLoading = ref(false)
const docSearch = ref('')
const docFilter = reactive({ status: '', document_type: '' })
const showDocDialog = ref(false)
const docForm = reactive({ document_type: 'correspondence', country: '', title: '', reference_number: '', document_date: '', due_date: '', status: 'pending', notes: '' })
const docSaving = ref(false)
const editingDocId = ref(null)

// Reports generation
const showGenerateDialog = ref(false)
const genForm = reactive({ country: 'CN', period: '', report_type: 'vat_return' })
const generating = ref(false)

// Rules
const rules = ref([])
const rulesLoading = ref(false)
const ruleFilter = reactive({ rule_type: '', is_active: '' })
const showRuleDialog = ref(false)
const ruleForm = reactive({ name: '', rule_type: 'reduced_rate', country: '', condition_type: '', condition_value: '', action: 'apply_rate', rate_modifier: null, description: '' })
const ruleSaving = ref(false)

function formatMoney(v) {
    v = parseFloat(v)
    if (isNaN(v)) return '0.00'
    return v.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        const d = res.data || {}
        Object.assign(dashboard, d)
        countries.value = (d.latest_reports || []).map(r => r.country).filter(Boolean)
        countries.value = [...new Set(countries.value)]
    } catch (e) { console.error(e) }
}

async function loadReports() {
    reportsLoading.value = true
    try {
        const params = {}
        if (reportFilter.country) params.country = reportFilter.country
        if (reportFilter.status) params.status = reportFilter.status
        if (reportFilter.report_type) params.report_type = reportFilter.report_type
        const res = await api.reports(params)
        reports.value = res.data?.data || res.data || []
    } catch (e) { console.error(e) }
    finally { reportsLoading.value = false }
}

async function loadDocuments() {
    docLoading.value = true
    try {
        const params = { ...docFilter }
        if (docSearch.value) params.search = docSearch.value
        const res = await api.documents(params)
        documents.value = res.data?.data || res.data || []
    } catch (e) { console.error(e) }
    finally { docLoading.value = false }
}

async function loadRules() {
    rulesLoading.value = true
    try {
        const params = {}
        if (ruleFilter.rule_type) params.rule_type = ruleFilter.rule_type
        if (ruleFilter.is_active !== '') params.is_active = ruleFilter.is_active
        const res = await api.rules(params)
        rules.value = res.data?.data || res.data || []
    } catch (e) { console.error(e) }
    finally { rulesLoading.value = false }
}

function refreshAll() {
    loading.value = true
    Promise.all([loadDashboard(), loadReports(), loadDocuments(), loadRules()])
        .finally(() => { loading.value = false })
}

// Report generation
async function handleGenerate() {
    if (!genForm.country || !genForm.period) {
        ElMessage.warning('请填写国家和周期')
        return
    }
    generating.value = true
    try {
        await api.generateReport(genForm)
        ElMessage.success('报告已生成')
        showGenerateDialog.value = false
        loadReports()
    } catch (e) { ElMessage.error('生成失败: ' + (e.response?.data?.message || e.message)) }
    finally { generating.value = false }
}

async function handleFileReport(row) {
    try {
        await api.fileReport(row.id)
        ElMessage.success('已标记为已申报')
        loadReports()
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

// Document CRUD
function editDoc(row) {
    editingDocId.value = row.id
    Object.assign(docForm, {
        document_type: row.document_type,
        country: row.country,
        title: row.title,
        reference_number: row.reference_number || '',
        document_date: row.document_date,
        due_date: row.due_date,
        status: row.status,
        notes: row.notes || '',
    })
    showDocDialog.value = true
}

async function handleSaveDoc() {
    docSaving.value = true
    try {
        if (editingDocId.value) {
            await api.updateDocument(editingDocId.value, docForm)
            ElMessage.success('文档已更新')
        } else {
            await api.storeDocument(docForm)
            ElMessage.success('文档已创建')
        }
        showDocDialog.value = false
        editingDocId.value = null
        Object.assign(docForm, { document_type: 'correspondence', country: '', title: '', reference_number: '', document_date: '', due_date: '', status: 'pending', notes: '' })
        loadDocuments()
        loadDashboard()
    } catch (e) { ElMessage.error('保存失败: ' + (e.response?.data?.message || e.message)) }
    finally { docSaving.value = false }
}

async function handleDeleteDoc(row) {
    try {
        await api.deleteDocument(row.id)
        ElMessage.success('已删除')
        loadDocuments()
    } catch (e) { ElMessage.error('删除失败') }
}

// Rule CRUD
async function handleSaveRule() {
    ruleSaving.value = true
    try {
        await api.storeRule(ruleForm)
        ElMessage.success('规则已创建')
        showRuleDialog.value = false
        Object.assign(ruleForm, { name: '', rule_type: 'reduced_rate', country: '', condition_type: '', condition_value: '', action: 'apply_rate', rate_modifier: null, description: '' })
        loadRules()
    } catch (e) { ElMessage.error('保存失败: ' + (e.response?.data?.message || e.message)) }
    finally { ruleSaving.value = false }
}

async function handleDeleteRule(row) {
    try {
        await api.deleteRule(row.id)
        ElMessage.success('已删除')
        loadRules()
    } catch (e) { ElMessage.error('删除失败') }
}

async function toggleRuleActive(row, val) {
    try {
        await api.updateRule(row.id, { is_active: val })
        ElMessage.success(val ? '规则已启用' : '规则已停用')
    } catch (e) {
        ElMessage.error('操作失败')
        loadRules()
    }
}

// Watches for filter changes
watch(reportFilter, () => loadReports(), { deep: true })
watch(docFilter, () => loadDocuments(), { deep: true })
watch(ruleFilter, () => loadRules(), { deep: true })

onMounted(() => { refreshAll() })
</script>

<style scoped>
.tax-compliance-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stat-value { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.primary { color: #409eff; }
.stat-value.info { color: #909399; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
</style>
