<template>
  <div class="tax-compliance-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><List /></el-icon>{{ t('tax_compliance_page.title') }}</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('tax_compliance_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value warning">{{ dashboard.overdue_documents }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.overdue_documents') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.pending_documents }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.pending_documents') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value success">{{ dashboard.filed_reports }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.filed_reports') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value primary">¥{{ formatMoney(dashboard.quarter_liability) }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.quarter_liability') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.active_rules }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.active_rules') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.covered_countries }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.covered_countries') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value">{{ dashboard.upcoming_due }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.upcoming_due') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-value info">{{ dashboard.pending_reports }}</div>
          <div class="stat-label">{{ t('tax_compliance_page.stats.pending_reports') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 税务报告 -->
        <el-tab-pane :label="t('tax_compliance_page.tabs.reports')" name="reports">
          <div class="tab-toolbar">
            <el-select v-model="reportFilter.country" :placeholder="t('tax_compliance_page.filters.country')" clearable style="width:100px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option v-for="c in countries" :key="c" :label="c" :value="c" />
            </el-select>
            <el-select v-model="reportFilter.status" :placeholder="t('tax_compliance_page.filters.status')" clearable style="width:120px;margin-left:8px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option :label="reportStatusLabels.draft" value="draft" />
              <el-option :label="reportStatusLabels.final" value="final" />
              <el-option :label="reportStatusLabels.filed" value="filed" />
            </el-select>
            <el-select v-model="reportFilter.report_type" :placeholder="t('tax_compliance_page.filters.report_type')" clearable style="width:140px;margin-left:8px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option v-for="(l, k) in reportTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showGenerateDialog = true">
              <el-icon><Plus /></el-icon> {{ t('tax_compliance_page.buttons.generate_report') }}
            </el-button>
          </div>
          <el-table :data="reports" stripe v-loading="reportsLoading">
            <el-table-column :label="t('tax_compliance_page.cols.report_type')" width="120">
              <template #default="{ row }">{{ reportTypes[row.report_type] || row.report_type }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.country')" width="70">
              <template #default="{ row }">{{ row.country }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.period')" width="100">
              <template #default="{ row }">{{ row.period }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.total_sales')" width="120" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_sales) }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.tax')" width="100" align="right">
              <template #default="{ row }">¥{{ formatMoney(row.total_tax_payable) }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.exempt_reverse')" width="100">
              <template #default="{ row }">{{ formatMoney(row.total_exempt_sales + row.total_reverse_charge) }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.status')" width="90">
              <template #default="{ row }">
                <el-tag :type="row.status === 'filed' ? 'success' : row.status === 'final' ? 'warning' : 'info'" size="small">
                  {{ reportStatusLabels[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.actions')" width="100" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status !== 'filed'" type="primary" link size="small" @click="handleFileReport(row)">{{ t('tax_compliance_page.buttons.file_report') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 合规文档 -->
        <el-tab-pane :label="t('tax_compliance_page.tabs.documents')" name="documents">
          <div class="tab-toolbar">
            <el-input v-model="docSearch" :placeholder="t('tax_compliance_page.filters.search_docs_ph')" clearable style="width:200px" @clear="loadDocuments" @keyup.enter="loadDocuments" />
            <el-select v-model="docFilter.status" :placeholder="t('tax_compliance_page.filters.status')" clearable style="width:120px;margin-left:8px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option v-for="(l, k) in docStatuses" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="docFilter.document_type" :placeholder="t('tax_compliance_page.filters.document_type')" clearable style="width:150px;margin-left:8px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option v-for="(l, k) in docTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showDocDialog = true">
              <el-icon><Plus /></el-icon> {{ t('tax_compliance_page.buttons.new_document') }}
            </el-button>
          </div>
          <el-table :data="documents" stripe v-loading="docLoading">
            <el-table-column :label="t('tax_compliance_page.cols.document_type')" width="110">
              <template #default="{ row }">{{ docTypes[row.document_type] || row.document_type }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.country')" width="60">{{ row.country }}</el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.title')" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ row.title }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.reference_number')" width="120">{{ row.reference_number || '-' }}</el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.document_date')" width="100">
              <template #default="{ row }">{{ row.document_date }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.due_date')" width="100">
              <template #default="{ row }">{{ row.due_date || '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.status')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'overdue' ? 'danger' : row.status === 'completed' ? 'success' : row.status === 'archived' ? 'info' : 'warning'" size="small">
                  {{ docStatuses[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" link size="small" @click="editDoc(row)">{{ t('actions.edit') }}</el-button>
                <el-popconfirm :title="t('tax_compliance_page.confirm.delete')" @confirm="handleDeleteDoc(row)">
                  <template #reference>
                    <el-button type="danger" link size="small">{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 税务规则 -->
        <el-tab-pane :label="t('tax_compliance_page.tabs.rules')" name="rules">
          <div class="tab-toolbar">
            <el-select v-model="ruleFilter.rule_type" :placeholder="t('tax_compliance_page.filters.rule_type')" clearable style="width:140px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option v-for="(l, k) in ruleTypes" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="ruleFilter.is_active" :placeholder="t('tax_compliance_page.filters.status')" clearable style="width:100px;margin-left:8px">
              <el-option :label="t('tax_compliance_page.filters.all')" value="" />
              <el-option :label="t('tax_compliance_page.filters.active')" value="true" />
              <el-option :label="t('tax_compliance_page.filters.inactive')" value="false" />
            </el-select>
            <div style="flex:1" />
            <el-button type="primary" @click="showRuleDialog = true">
              <el-icon><Plus /></el-icon> {{ t('tax_compliance_page.buttons.new_rule') }}
            </el-button>
          </div>
          <el-table :data="rules" stripe v-loading="rulesLoading">
            <el-table-column :label="t('tax_compliance_page.cols.rule_name')" min-width="160">
              <template #default="{ row }">{{ row.name }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.type')" width="100">
              <template #default="{ row }">{{ ruleTypes[row.rule_type] || row.rule_type }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.country')" width="60">
              <template #default="{ row }">{{ row.country || t('tax_compliance_page.labels.global') }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.condition')" width="120">
              <template #default="{ row }">{{ row.condition_type ? row.condition_type + '=' + row.condition_value : t('tax_compliance_page.labels.no_condition') }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.action')" width="100">
              <template #default="{ row }">{{ ruleActions[row.action] || row.action }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.rate_modifier')" width="80">
              <template #default="{ row }">{{ row.rate_modifier !== null ? (row.rate_modifier * 100).toFixed(1) + '%' : '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.active')" width="60">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" size="small" @change="v => toggleRuleActive(row, v)" />
              </template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.description')" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">{{ row.description || '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('tax_compliance_page.cols.actions')" width="100" fixed="right">
              <template #default="{ row }">
                <el-popconfirm :title="t('tax_compliance_page.confirm.delete')" @confirm="handleDeleteRule(row)">
                  <template #reference>
                    <el-button type="danger" link size="small">{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 生成报告对话框 -->
    <el-dialog v-model="showGenerateDialog" :title="t('tax_compliance_page.dialogs.generate_report_title')" width="400px">
      <el-form :model="genForm" label-width="80px">
        <el-form-item :label="t('tax_compliance_page.forms.country')" required>
          <el-input v-model="genForm.country" :placeholder="t('tax_compliance_page.placeholders.country_code')" style="width:120px" maxlength="2" @input="v => genForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.period')" required>
          <el-date-picker v-model="genForm.period" type="month" :placeholder="t('tax_compliance_page.placeholders.select_month')" format="YYYY-MM" value-format="YYYY-MM" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.report_type')">
          <el-select v-model="genForm.report_type" style="width:100%">
            <el-option v-for="(l, k) in reportTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showGenerateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleGenerate" :loading="generating">{{ t('tax_compliance_page.buttons.generate') }}</el-button>
      </template>
    </el-dialog>

    <!-- 文档编辑对话框 -->
    <el-dialog v-model="showDocDialog" :title="editingDocId ? t('tax_compliance_page.dialogs.edit_document') : t('tax_compliance_page.dialogs.new_document')" width="500px">
      <el-form :model="docForm" label-width="100px">
        <el-form-item :label="t('tax_compliance_page.forms.document_type')" required>
          <el-select v-model="docForm.document_type" style="width:100%">
            <el-option v-for="(l, k) in docTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.country')" required>
          <el-input v-model="docForm.country" :placeholder="t('tax_compliance_page.placeholders.country_code')" maxlength="2" style="width:100px" @input="v => docForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.title')" required>
          <el-input v-model="docForm.title" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.reference_number')">
          <el-input v-model="docForm.reference_number" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.document_date')" required>
          <el-date-picker v-model="docForm.document_date" type="date" :placeholder="t('tax_compliance_page.placeholders.select_date')" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.due_date')">
          <el-date-picker v-model="docForm.due_date" type="date" :placeholder="t('tax_compliance_page.placeholders.optional')" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.status')">
          <el-select v-model="docForm.status" style="width:100%">
            <el-option v-for="(l, k) in docStatuses" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.notes')">
          <el-input v-model="docForm.notes" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDocDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveDoc" :loading="docSaving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 规则编辑对话框 -->
    <el-dialog v-model="showRuleDialog" :title="t('tax_compliance_page.dialogs.new_rule_title')" width="500px">
      <el-form :model="ruleForm" label-width="100px">
        <el-form-item :label="t('tax_compliance_page.forms.rule_name')" required>
          <el-input v-model="ruleForm.name" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.rule_type')" required>
          <el-select v-model="ruleForm.rule_type" style="width:100%">
            <el-option v-for="(l, k) in ruleTypes" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.country')">
          <el-input v-model="ruleForm.country" :placeholder="t('tax_compliance_page.placeholders.country_global')" maxlength="2" style="width:120px" @input="v => ruleForm.country = v.toUpperCase()" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.condition_type')">
          <el-input v-model="ruleForm.condition_type" :placeholder="t('tax_compliance_page.placeholders.condition_type')" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.condition_value')">
          <el-input v-model="ruleForm.condition_value" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.action')" required>
          <el-select v-model="ruleForm.action" style="width:100%">
            <el-option v-for="(l, k) in ruleActions" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.rate_modifier')">
          <el-input-number v-model="ruleForm.rate_modifier" :min="0" :max="1" :step="0.01" style="width:100%" />
        </el-form-item>
        <el-form-item :label="t('tax_compliance_page.forms.description')">
          <el-input v-model="ruleForm.description" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showRuleDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="ruleSaving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { List, Refresh, Plus } from '@element-plus/icons-vue'
import api from '../../api/taxCompliance'

const { t, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('reports')

// Dashboard
const dashboard = reactive({
    overdue_documents: 0, pending_documents: 0, upcoming_due: 0,
    pending_reports: 0, filed_reports: 0,
    active_rules: 0, covered_countries: 0, quarter_liability: 0,
})

const reportTypes = computed(() => ({
    vat_return: t('tax_compliance_page.report_types.vat_return'),
    gst_return: t('tax_compliance_page.report_types.gst_return'),
    sales_tax: t('tax_compliance_page.report_types.sales_tax'),
    cross_border: t('tax_compliance_page.report_types.cross_border'),
    liability_summary: t('tax_compliance_page.report_types.liability_summary'),
}))
const docTypes = computed(() => ({
    tax_return: t('tax_compliance_page.doc_types.tax_return'),
    filing_receipt: t('tax_compliance_page.doc_types.filing_receipt'),
    correspondence: t('tax_compliance_page.doc_types.correspondence'),
    certificate: t('tax_compliance_page.doc_types.certificate'),
    audit_letter: t('tax_compliance_page.doc_types.audit_letter'),
}))
const docStatuses = computed(() => ({
    pending: t('tax_compliance_page.doc_statuses.pending'),
    completed: t('tax_compliance_page.doc_statuses.completed'),
    overdue: t('tax_compliance_page.doc_statuses.overdue'),
    archived: t('tax_compliance_page.doc_statuses.archived'),
}))
const ruleTypes = computed(() => ({
    reduced_rate: t('tax_compliance_page.rule_types.reduced_rate'),
    exemption: t('tax_compliance_page.rule_types.exemption'),
    threshold: t('tax_compliance_page.rule_types.threshold'),
    special_zone: t('tax_compliance_page.rule_types.special_zone'),
}))
const ruleActions = computed(() => ({
    apply_rate: t('tax_compliance_page.rule_actions.apply_rate'),
    exempt: t('tax_compliance_page.rule_actions.exempt'),
    reduce_rate: t('tax_compliance_page.rule_actions.reduce_rate'),
    reverse_charge: t('tax_compliance_page.rule_actions.reverse_charge'),
}))
const reportStatusLabels = computed(() => ({
    draft: t('tax_compliance_page.report_status.draft'),
    final: t('tax_compliance_page.report_status.final'),
    filed: t('tax_compliance_page.report_status.filed'),
}))

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
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return v.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
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
        ElMessage.warning(t('tax_compliance_page.messages.fill_country_period'))
        return
    }
    generating.value = true
    try {
        await api.generateReport(genForm)
        ElMessage.success(t('tax_compliance_page.messages.report_generated'))
        showGenerateDialog.value = false
        loadReports()
    } catch (e) {
        ElMessage.error(t('tax_compliance_page.messages.generate_failed', { msg: e.response?.data?.message || e.message }))
    }
    finally { generating.value = false }
}

async function handleFileReport(row) {
    try {
        await api.fileReport(row.id)
        ElMessage.success(t('tax_compliance_page.messages.marked_filed'))
        loadReports()
        loadDashboard()
    } catch (e) { ElMessage.error(t('messages.failed')) }
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
            ElMessage.success(t('tax_compliance_page.messages.doc_updated'))
        } else {
            await api.storeDocument(docForm)
            ElMessage.success(t('tax_compliance_page.messages.doc_created'))
        }
        showDocDialog.value = false
        editingDocId.value = null
        Object.assign(docForm, { document_type: 'correspondence', country: '', title: '', reference_number: '', document_date: '', due_date: '', status: 'pending', notes: '' })
        loadDocuments()
        loadDashboard()
    } catch (e) {
        ElMessage.error(t('tax_compliance_page.messages.save_failed', { msg: e.response?.data?.message || e.message }))
    }
    finally { docSaving.value = false }
}

async function handleDeleteDoc(row) {
    try {
        await api.deleteDocument(row.id)
        ElMessage.success(t('tax_compliance_page.messages.deleted'))
        loadDocuments()
    } catch (e) { ElMessage.error(t('tax_compliance_page.messages.delete_failed')) }
}

// Rule CRUD
async function handleSaveRule() {
    ruleSaving.value = true
    try {
        await api.storeRule(ruleForm)
        ElMessage.success(t('tax_compliance_page.messages.rule_created'))
        showRuleDialog.value = false
        Object.assign(ruleForm, { name: '', rule_type: 'reduced_rate', country: '', condition_type: '', condition_value: '', action: 'apply_rate', rate_modifier: null, description: '' })
        loadRules()
    } catch (e) {
        ElMessage.error(t('tax_compliance_page.messages.save_failed', { msg: e.response?.data?.message || e.message }))
    }
    finally { ruleSaving.value = false }
}

async function handleDeleteRule(row) {
    try {
        await api.deleteRule(row.id)
        ElMessage.success(t('tax_compliance_page.messages.deleted'))
        loadRules()
    } catch (e) { ElMessage.error(t('tax_compliance_page.messages.delete_failed')) }
}

async function toggleRuleActive(row, val) {
    try {
        await api.updateRule(row.id, { is_active: val })
        ElMessage.success(val ? t('tax_compliance_page.messages.rule_enabled') : t('tax_compliance_page.messages.rule_disabled'))
    } catch (e) {
        ElMessage.error(t('messages.failed'))
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
.stat-value.primary { color: #0f172a; }
.stat-value.info { color: #909399; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
</style>
