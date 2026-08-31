<template>
  <div class="compliance-page">
    <div class="page-header">
      <h2>{{ t('compliance_page.title') }}</h2>
      <div class="header-actions">
        <el-button @click="refreshDashboard" :loading="loading.dashboard" plain>{{ t('compliance_page.buttons.refresh') }}</el-button>
      </div>
    </div>

    <div v-if="pageLoading" class="loading-container">
      <el-skeleton :rows="6" animated />
    </div>

    <div v-else>
      <!-- 合规框架概览卡片 -->
      <el-row :gutter="16" class="stats-row">
        <el-col :xs="12" :sm="6" v-for="fw in frameworks" :key="fw.id">
          <el-card shadow="hover" class="fw-card" :class="fwClass(fw.code)">
            <template #header>
              <span>{{ fw.name }}</span>
            </template>
            <div>{{ fw.code }}</div>
            <div class="fw-meta">
              <template v-if="fw.latest_report">
                <el-tag :type="riskTag(fw.latest_report.risk_level)" size="small" effect="dark">{{ riskLabel(fw.latest_report.risk_level) }}</el-tag>
                <div class="fw-pass-rate">{{ t('compliance_page.cards.pass_rate', { passed: fw.latest_report.passed_count, total: fw.latest_report.passed_count + fw.latest_report.failed_count }) }}</div>
              </template>
              <template v-else>
                <el-tag type="info" size="small">{{ t('compliance_page.cards.no_report') }}</el-tag>
              </template>
            </div>
            <div class="fw-report-count">{{ t('compliance_page.cards.report_count', { n: fw.report_count }) }}</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 操作与统计 -->
      <el-row :gutter="16" class="action-row">
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>
              <div class="card-header"><span>{{ t('compliance_page.cards.generate_report') }}</span></div>
            </template>
            <el-form :model="reportForm" inline>
              <el-form-item :label="t('compliance_page.form.framework')" required>
                <el-select v-model="reportForm.framework_id" :placeholder="t('compliance_page.form.select_framework')" style="width:200px;">
                  <el-option v-for="fw in frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('compliance_page.form.type')">
                <el-select v-model="reportForm.type" style="width:140px;">
                  <el-option v-for="opt in reportTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="generateReport" :loading="loading.generate" plain>{{ t('compliance_page.buttons.generate_report') }}</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-value">{{ stats.total_reports }}</div>
            <div class="stat-label">{{ t('compliance_page.stats.total_reports') }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card info">
            <div class="stat-value">{{ stats.total_exports }}</div>
            <div class="stat-label">{{ t('compliance_page.stats.export_records') }}</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 标准参考 -->
      <el-row :gutter="16" class="standards-row">
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>
              <div class="card-header"><span>{{ t('compliance_page.standards.soc2_domains') }}</span></div>
            </template>
            <div class="standard-domains">
              <div class="domain-item" v-for="(d, i) in soc2Domains" :key="i">
                <el-tag type="success" effect="plain" size="small" round>{{ d.code }}</el-tag>
                <span>{{ d.name }}</span>
              </div>
            </div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>
              <div class="card-header"><span>{{ t('compliance_page.standards.iso_domains') }}</span></div>
            </template>
            <div class="standard-domains">
              <div class="domain-item" v-for="(d, i) in isoDomains" :key="i">
                <el-tag type="primary" effect="plain" size="small" round>{{ d.code }}</el-tag>
                <span>{{ d.name }}</span>
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 报告列表 -->
      <el-card shadow="never" class="report-section">
        <template #header>
          <div class="card-header">
            <span>{{ t('compliance_page.cards.report_history') }}</span>
            <div class="header-actions">
              <el-select v-model="filters.framework_id" :placeholder="t('compliance_page.filters.framework')" clearable @change="loadReports" style="width:150px;margin-right:8px;">
                <el-option v-for="fw in frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
              </el-select>
              <el-select v-model="filters.status" :placeholder="t('compliance_page.filters.status')" clearable @change="loadReports" style="width:120px;">
                <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </div>
          </div>
        </template>
        <el-table :data="reports" v-loading="loading.reports" stripe>
          <el-table-column prop="id" :label="t('compliance_page.cols.id')" width="60" />
          <el-table-column :label="t('compliance_page.cols.framework')" width="140">
            <template #default="{ row }">
              <el-tag v-if="row.framework" :type="fwTag(row.framework.code)" size="small">{{ row.framework.code }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="title" :label="t('compliance_page.cols.title')" min-width="180" show-overflow-tooltip />
          <el-table-column :label="t('compliance_page.cols.risk_level')" width="120">
            <template #default="{ row }">
              <el-tag :type="riskTag(row.risk_level)" effect="dark" size="small">{{ riskLabel(row.risk_level) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('compliance_page.cols.pass_fail')" width="140">
            <template #default="{ row }">
              <span class="pass-count">{{ row.passed_count }}</span><span class="sep">/</span>
              <span class="fail-count">{{ row.failed_count }}</span><span class="sep">/</span>
              <span class="na-count">{{ t('compliance_page.cols.na') }} {{ row.na_count }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('compliance_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('compliance_page.cols.generated_at')" width="170">
            <template #default="{ row }">{{ formatDate(row.generated_at || row.created_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('compliance_page.cols.actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text type="primary" size="small" @click="viewReport(row)">{{ t('actions.view') }}</el-button>
              <el-button text type="warning" size="small" @click="promptExport(row)">{{ t('actions.export') }}</el-button>
              <el-popconfirm :title="t('compliance_page.delete_confirm')" @confirm="deleteReport(row)">
                <template #reference>
                  <el-button text type="danger" size="small">{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
        <div class="pagination-wrap" v-if="pagination.total > 0">
          <el-pagination
            v-model:current-page="pagination.current"
            :page-size="pagination.per_page"
            :total="pagination.total"
            layout="prev, pager, next"
            @current-change="loadReports"
          />
        </div>
      </el-card>
    </div>

    <!-- ─── SOC2/ISO27001 合规准备包增强 (M3-69) ─── -->
    <el-card shadow="hover" class="mt-4">
      <el-tabs v-model="packTab">
        <!-- Tab 1: 准备就绪评分 -->
        <el-tab-pane :label="t('compliance_page.tabs.readiness')" name="readiness">
          <el-row :gutter="16">
            <el-col :span="12" v-for="(fw, code) in packDashboard.frameworks" :key="code">
              <el-card shadow="never" class="mb-3">
                <template #header>
                  <span>{{ fw.framework_name }}</span>
                  <el-progress :percentage="fw.readiness_score" :status="fw.readiness_score >= 80 ? 'success' : fw.readiness_score >= 50 ? 'warning' : 'exception'" style="width:200px;display:inline-block;float:right" />
                </template>
                <el-descriptions :column="2" size="small" border>
                  <el-descriptions-item :label="t('compliance_page.readiness.evidence_total')">{{ fw.evidence_total }}</el-descriptions-item>
                  <el-descriptions-item :label="t('compliance_page.readiness.evidence_validated')">{{ fw.evidence_validated }} ({{ fw.evidence_validation_rate }}%)</el-descriptions-item>
                  <el-descriptions-item :label="t('compliance_page.readiness.gap_analysis')">{{ t('compliance_page.readiness.gaps_resolved', { resolved: fw.gaps_resolved, total: fw.gaps_total }) }}</el-descriptions-item>
                  <el-descriptions-item :label="t('compliance_page.readiness.gaps_open')">{{ fw.gaps_open }}</el-descriptions-item>
                  <el-descriptions-item :label="t('compliance_page.readiness.policy_count')">{{ t('compliance_page.readiness.policy_count_fmt', { n: fw.policy_count }) }}</el-descriptions-item>
                  <el-descriptions-item :label="t('compliance_page.readiness.questionnaire_progress')">{{ fw.questionnaire_answered }}/{{ fw.questionnaire_total }} ({{ fw.questionnaire_progress }}%)</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
          </el-row>
          <el-alert :title="t('compliance_page.readiness.overall')" :description="t('compliance_page.readiness.score', { score: packDashboard.overall_readiness })" :type="packDashboard.overall_readiness >= 80 ? 'success' : packDashboard.overall_readiness >= 50 ? 'warning' : 'error'" show-icon />
        </el-tab-pane>

        <!-- Tab 2: 审计问卷 -->
        <el-tab-pane :label="t('compliance_page.tabs.questionnaire')" name="questionnaire">
          <div class="tab-toolbar">
            <el-select v-model="qFramework" :placeholder="t('compliance_page.placeholders.select_framework')" style="width:150px">
              <el-option v-for="opt in packFrameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button @click="loadQuestions" :loading="qLoading" type="primary" size="small">{{ t('compliance_page.buttons.load_questions') }}</el-button>
          </div>
          <el-card v-if="questions.length" shadow="never">
            <div v-for="(q, i) in questions" :key="q.id" class="question-item">
              <div class="q-header">
                <el-tag size="small" :type="q.severity === 'critical' ? 'danger' : q.severity === 'high' ? 'warning' : 'info'">{{ severityLabel(q.severity) }}</el-tag>
                <strong>{{ q.control_ref }}</strong>
                <span class="q-text">{{ q.question }}</span>
              </div>
              <div class="q-guidance text-gray">{{ q.guidance }}</div>
              <el-input v-model="qAnswers[q.id]" type="textarea" :rows="2" :placeholder="t('compliance_page.placeholders.answer')" class="q-input" />
            </div>
            <el-button type="primary" @click="submitAnswers" :loading="qSubmitting" class="mt-2">{{ t('compliance_page.buttons.submit_answers') }}</el-button>
          </el-card>
        </el-tab-pane>

        <!-- Tab 3: 证据收集清单 -->
        <el-tab-pane :label="t('compliance_page.tabs.evidence')" name="evidence">
          <div class="tab-toolbar">
            <el-select v-model="eFramework" :placeholder="t('compliance_page.placeholders.select_framework')" style="width:150px">
              <el-option v-for="opt in packFrameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button @click="loadEvidenceChecklist" :loading="eLoading" type="primary" size="small">{{ t('compliance_page.buttons.load_checklist') }}</el-button>
          </div>
          <el-table :data="evidenceChecklist" stripe size="small" v-if="evidenceChecklist.length">
            <el-table-column prop="control_ref" :label="t('compliance_page.cols.control_domain')" width="100" />
            <el-table-column prop="title" :label="t('compliance_page.cols.evidence_item')" min-width="200" />
            <el-table-column prop="evidence_type" :label="t('compliance_page.cols.type')" width="140" />
            <el-table-column prop="suggested_source" :label="t('compliance_page.cols.source')" width="140" />
            <el-table-column :label="t('compliance_page.cols.status')" width="120">
              <template #default="{ row }">
                <el-tag :type="statusTag2(row.collection_status)" size="small">{{ collectionStatusLabel(row.collection_status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('compliance_page.cols.actions')" width="160">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="autoCollectEvidence(eFramework, row)" :loading="eCollecting">{{ t('compliance_page.buttons.auto_collect') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 差距分析 -->
        <el-tab-pane :label="t('compliance_page.tabs.gaps')" name="gaps">
          <div class="tab-toolbar">
            <el-select v-model="gFramework" :placeholder="t('compliance_page.placeholders.select_framework')" style="width:150px">
              <el-option v-for="opt in packFrameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="gReportId" :placeholder="t('compliance_page.placeholders.select_report')" style="width:200px">
              <el-option v-for="r in reports" :key="r.id" :label="'#' + r.id + ' ' + (r.title || '')" :value="r.id" />
            </el-select>
            <el-button @click="runGapAnalysis2" :loading="gRunning" type="primary" size="small">{{ t('compliance_page.buttons.run_analysis') }}</el-button>
          </div>
          <el-table :data="gapList" stripe size="small" v-if="gapList.length">
            <el-table-column prop="control_ref" :label="t('compliance_page.cols.control_domain')" width="90" />
            <el-table-column prop="control_title" :label="t('compliance_page.cols.control_name')" min-width="160" />
            <el-table-column :label="t('compliance_page.cols.risk')" width="80">
              <template #default="{ row }"><el-tag :type="riskTag(row.risk_level)" size="small">{{ riskLabel(row.risk_level) }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="current_state" :label="t('compliance_page.cols.current_state')" width="120" />
            <el-table-column :label="t('compliance_page.cols.remediation_status')" width="120">
              <template #default="{ row }">
                <el-tag :type="remTag(row.remediation_status)" size="small">{{ remediationStatusLabel(row.remediation_status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="remediation_plan" :label="t('compliance_page.cols.remediation_plan')" min-width="200" show-overflow-tooltip />
          </el-table>
        </el-tab-pane>

        <!-- Tab 5: 策略文档模板 -->
        <el-tab-pane :label="t('compliance_page.tabs.policies')" name="policies">
          <div class="tab-toolbar">
            <el-select v-model="pFramework" :placeholder="t('compliance_page.placeholders.select_framework')" style="width:150px">
              <el-option v-for="opt in packFrameworkOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-button @click="loadPolicies" :loading="pLoading" type="primary" size="small">{{ t('compliance_page.buttons.load_templates') }}</el-button>
          </div>
          <el-table :data="policyDocs" stripe size="small" v-if="policyDocs.length">
            <el-table-column prop="title" :label="t('compliance_page.cols.doc_name')" min-width="220" />
            <el-table-column prop="category" :label="t('compliance_page.cols.category')" width="120" />
            <el-table-column prop="description" :label="t('compliance_page.cols.description')" min-width="250" show-overflow-tooltip />
            <el-table-column prop="version" :label="t('compliance_page.cols.version')" width="80" />
            <el-table-column :label="t('compliance_page.cols.actions')" width="100">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="generateDoc(row)">{{ t('compliance_page.buttons.generate_doc') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>

    <!-- 策略文档生成弹窗 -->
    <el-dialog v-model="docDialog.visible" :title="t('compliance_page.dialogs.generate_doc', { title: docDialog.doc?.title || '' })" width="640px">
      <el-alert type="info" :closable="false" class="mb-3" :title="t('compliance_page.dialogs.doc_hint')" />
      <el-form label-width="140px" v-loading="docDialog.submitting">
        <el-form-item
          v-for="field in docDialog.fields"
          :key="field"
          :label="fieldLabel(field)"
          :required="true"
        >
          <el-input
            v-if="['purpose','scope','policy_statements','roles_responsibilities','compliance'].includes(field) || field.length > 18"
            v-model="docDialog.values[field]"
            type="textarea"
            :rows="3"
            :placeholder="t('compliance_page.placeholders.enter_field', { field: fieldLabel(field) })"
          />
          <el-input
            v-else
            v-model="docDialog.values[field]"
            :placeholder="t('compliance_page.placeholders.enter_field', { field: fieldLabel(field) })"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="docDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="docDialog.submitting" @click="submitGenerateDoc">{{ t('compliance_page.buttons.generate') }}</el-button>
      </template>
    </el-dialog>

    <!-- 报告详情弹窗 -->
    <el-dialog v-model="reportDialog.visible" :title="reportDialog.title" width="720px" top="5vh">
      <div v-show="reportDialog.loading" class="dialog-loading">
        <el-skeleton :rows="8" animated />
      </div>
      <div v-show="!reportDialog.loading && reportDialog.report">
        <div class="report-detail">
          <el-descriptions :column="2" border>
            <el-descriptions-item :label="t('compliance_page.cols.framework')" :span="1">
              <el-tag v-if="reportDialog.report.framework" :type="fwTag(reportDialog.report.framework.code)" size="small">
                {{ reportDialog.report.framework.code }} - {{ reportDialog.report.framework.name }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.risk_level')" :span="1">
              <el-tag :type="riskTag(reportDialog.report.risk_level)" effect="dark">{{ riskLabel(reportDialog.report.risk_level) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.pass')" :span="1"><span class="pass-count">{{ reportDialog.report.passed_count }}</span></el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.fail')" :span="1"><span class="fail-count">{{ reportDialog.report.failed_count }}</span></el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.na')" :span="1"><span class="na-count">{{ reportDialog.report.na_count }}</span></el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.status')" :span="1">
              <el-tag :type="statusTag(reportDialog.report.status)" size="small">{{ statusLabel(reportDialog.report.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('compliance_page.cols.generated_at')" :span="2">{{ formatDate(reportDialog.report.generated_at) }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-section" v-if="reportDialog.report.summary">
            <h4>{{ t('compliance_page.detail.summary') }}</h4>
            <p>{{ reportDialog.report.summary }}</p>
          </div>
          <div class="detail-section" v-if="reportDialog.report.controls_assessed && reportDialog.report.controls_assessed.length">
            <h4>{{ t('compliance_page.detail.controls_assessed') }}</h4>
            <el-table :data="reportDialog.report.controls_assessed" stripe>
              <el-table-column prop="domain" :label="t('compliance_page.cols.control_domain')" min-width="160" />
              <el-table-column :label="t('compliance_page.cols.result')" width="100">
                <template #default="{ row }">
                  <el-tag :type="domainResultTag(row.status)" size="small">{{ domainResultLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="finding" :label="t('compliance_page.cols.finding')" min-width="200" show-overflow-tooltip />
            </el-table>
          </div>
          <div class="detail-section" v-if="reportDialog.report.evidence_refs && reportDialog.report.evidence_refs.length">
            <h4>{{ t('compliance_page.detail.evidence_refs') }}</h4>
            <el-table :data="reportDialog.report.evidence_refs" stripe>
              <el-table-column prop="type" :label="t('compliance_page.cols.evidence_type')" width="140" />
              <el-table-column prop="count" :label="t('compliance_page.cols.count')" width="80" />
              <el-table-column prop="description" :label="t('compliance_page.cols.description')" min-width="200" />
            </el-table>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="reportDialog.visible = false">{{ t('actions.close') }}</el-button>
        <el-button type="primary" @click="promptExportFromDetail" v-show="!reportDialog.loading && reportDialog.report">{{ t('compliance_page.buttons.export_report') }}</el-button>
      </template>
    </el-dialog>

    <!-- 导出确认弹窗 -->
    <el-dialog v-model="exportDialog.visible" :title="t('compliance_page.dialogs.export_title')" width="400px">
      <el-form>
        <el-form-item :label="t('compliance_page.dialogs.export_format')">
          <el-radio-group v-model="exportDialog.format">
            <el-radio value="json">JSON</el-radio>
            <el-radio value="csv">CSV</el-radio>
          </el-radio-group>
        </el-form-item>
        <p class="export-hint">{{ t('compliance_page.dialogs.export_hint', { id: exportDialog.reportId, format: exportFormatLabel }) }}</p>
      </el-form>
      <template #footer>
        <el-button @click="exportDialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="doExport" :loading="loading.export">{{ t('compliance_page.buttons.confirm_export') }}</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import api from '@/api/compliance';
import packApi from '@/api/compliancePack';

const { t, locale } = useI18n();

const pageLoading = ref(true);
const loading = reactive({
  dashboard: false,
  reports: false,
  generate: false,
  export: false,
});
const frameworks = ref([]);
const reports = ref([]);
const stats = reactive({
  total_reports: 0,
  total_exports: 0,
});
const pagination = reactive({
  current: 1,
  per_page: 15,
  total: 0,
});
const filters = reactive({
  framework_id: null,
  status: null,
});
const reportForm = reactive({
  framework_id: null,
  type: 'on_demand',
});

const reportDialog = reactive({
  visible: false,
  loading: false,
  title: '',
  report: null,
});
const exportDialog = reactive({
  visible: false,
  reportId: null,
  format: 'json',
});
const exportFormatLabel = computed(() => exportDialog.format === 'json' ? 'JSON' : 'CSV');

const reportTypeOptions = computed(() => [
  { value: 'on_demand', label: t('compliance_page.report_types.on_demand') },
  { value: 'scheduled', label: t('compliance_page.report_types.scheduled') },
  { value: 'continuous', label: t('compliance_page.report_types.continuous') },
]);

const statusFilterOptions = computed(() => [
  { value: 'draft', label: t('compliance_page.status.draft') },
  { value: 'generated', label: t('compliance_page.status.generated') },
  { value: 'failed', label: t('compliance_page.status.failed') },
]);

const packFrameworkOptions = computed(() => [
  { value: 'SOC2', label: t('compliance_page.frameworks.SOC2') },
  { value: 'ISO27001', label: t('compliance_page.frameworks.ISO27001') },
]);

const soc2Domains = computed(() => [
  { code: 'SEC', name: t('compliance_page.domains.soc2.SEC') },
  { code: 'AVA', name: t('compliance_page.domains.soc2.AVA') },
  { code: 'PID', name: t('compliance_page.domains.soc2.PID') },
  { code: 'CON', name: t('compliance_page.domains.soc2.CON') },
  { code: 'PRI', name: t('compliance_page.domains.soc2.PRI') },
]);

const isoDomains = computed(() => [
  { code: 'A.5', name: t('compliance_page.domains.iso.A5') },
  { code: 'A.8', name: t('compliance_page.domains.iso.A8') },
  { code: 'A.9', name: t('compliance_page.domains.iso.A9') },
  { code: 'A.10', name: t('compliance_page.domains.iso.A10') },
  { code: 'A.11', name: t('compliance_page.domains.iso.A11') },
  { code: 'A.12', name: t('compliance_page.domains.iso.A12') },
  { code: 'A.13', name: t('compliance_page.domains.iso.A13') },
]);

const riskLabels = computed(() => ({
  low: t('compliance_page.risk.low'),
  medium: t('compliance_page.risk.medium'),
  high: t('compliance_page.risk.high'),
  critical: t('compliance_page.risk.critical'),
}));

const domainResultLabels = computed(() => ({
  pass: t('compliance_page.domain_result.pass'),
  warn: t('compliance_page.domain_result.warn'),
  fail: t('compliance_page.domain_result.fail'),
}));

const statusLabels = computed(() => ({
  draft: t('compliance_page.status.draft'),
  generated: t('compliance_page.status.generated'),
  failed: t('compliance_page.status.failed'),
  archived: t('compliance_page.status.archived'),
}));

const severityLabels = computed(() => ({
  critical: t('compliance_page.severity.critical'),
  high: t('compliance_page.severity.high'),
  medium: t('compliance_page.severity.medium'),
  low: t('compliance_page.severity.low'),
}));

const collectionStatusLabels = computed(() => ({
  collected: t('compliance_page.collection_status.collected'),
  validated: t('compliance_page.collection_status.validated'),
  not_collected: t('compliance_page.collection_status.not_collected'),
  rejected: t('compliance_page.collection_status.rejected'),
}));

const remediationStatusLabels = computed(() => ({
  identified: t('compliance_page.remediation_status.identified'),
  in_progress: t('compliance_page.remediation_status.in_progress'),
  completed: t('compliance_page.remediation_status.completed'),
  waived: t('compliance_page.remediation_status.waived'),
}));

function fwClass(code) {
  const map = { SOC2: 'card-soc2', ISO27001: 'card-iso', GDPR: 'card-gdpr', HIPAA: 'card-hipaa', PCI_DSS: 'card-pci' };
  return map[code] || '';
}

function riskTag(level) {
  const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
  return map[level] || 'info';
}

function riskLabel(level) {
  return riskLabels.value[level] || level;
}

function domainResultTag(status) {
  if (status === 'pass') return 'success';
  if (status === 'warn') return 'warning';
  return 'danger';
}

function domainResultLabel(status) {
  return domainResultLabels.value[status] || status;
}

function statusTag(status) {
  const map = { draft: 'info', generated: 'success', failed: 'danger', archived: '' };
  return map[status] || '';
}

function statusLabel(status) {
  return statusLabels.value[status] || status;
}

function severityLabel(severity) {
  return severityLabels.value[severity] || severity;
}

function collectionStatusLabel(status) {
  return collectionStatusLabels.value[status] || status;
}

function remediationStatusLabel(status) {
  return remediationStatusLabels.value[status] || status;
}

function fwTag(code) {
  const map = { SOC2: 'success', ISO27001: 'primary', GDPR: 'warning', HIPAA: 'danger', PCI_DSS: '' };
  return map[code] || '';
}

function formatDate(val) {
  if (!val) return '-';
  const d = new Date(val);
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return d.toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function fieldLabel(field) {
  const key = `compliance_page.field_labels.${field}`;
  const translated = t(key);
  return translated !== key ? translated : field;
}

async function refreshDashboard() {
  loading.dashboard = true;
  try {
    await loadFrameworks();
    await loadReports();
  } catch (e) {
    ElMessage.error(t('compliance_page.messages.dashboard_load_failed'));
  } finally {
    loading.dashboard = false;
  }
}

async function loadFrameworks() {
  try {
    await api.seedFrameworks().catch(() => {});
    const res = await api.frameworks();
    frameworks.value = res.data || [];
    const dashRes = await api.governanceDashboard().catch(() => ({ data: {} }));
    const dash = dashRes.data || {};
    stats.total_exports = dash.total_exports || 0;
  } catch (e) {
    console.error('loadFrameworks failed', e);
    frameworks.value = [];
  }
}

async function loadReports() {
  loading.reports = true;
  try {
    const params = { page: pagination.current, per_page: pagination.per_page };
    if (filters.framework_id) params.framework_id = filters.framework_id;
    if (filters.status) params.status = filters.status;
    const res = await api.reports(params);
    const page = res.data || res;
    reports.value = page.data || [];
    pagination.total = page.total || 0;
    pagination.current = page.current_page || 1;
    stats.total_reports = page.total || 0;
  } catch (e) {
    console.error('loadReports failed', e);
    reports.value = [];
  } finally {
    loading.reports = false;
  }
}

async function generateReport() {
  if (!reportForm.framework_id) {
    ElMessage.warning(t('compliance_page.messages.select_framework'));
    return;
  }
  loading.generate = true;
  try {
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    await api.generateReport({
      framework_id: reportForm.framework_id,
      type: reportForm.type,
      title: t('compliance_page.messages.report_title', { date: new Date().toLocaleDateString(loc) }),
    });
    ElMessage.success(t('compliance_page.messages.report_generated'));
    await loadReports();
    await loadFrameworks();
  } catch (e) {
    ElMessage.error(t('compliance_page.messages.generate_failed'));
  } finally {
    loading.generate = false;
  }
}

async function viewReport(row) {
  reportDialog.visible = true;
  reportDialog.loading = true;
  reportDialog.title = t('compliance_page.dialogs.report_detail', { id: row.id });
  try {
    const res = await api.showReport(row.id);
    const report = res.data || {};
    if (typeof report.controls_assessed === 'string') {
      report.controls_assessed = JSON.parse(report.controls_assessed);
    }
    if (typeof report.findings === 'string') {
      report.findings = JSON.parse(report.findings);
    }
    if (typeof report.evidence_refs === 'string') {
      report.evidence_refs = JSON.parse(report.evidence_refs);
    }
    reportDialog.report = report;
  } catch (e) {
    ElMessage.error(t('compliance_page.messages.report_detail_failed'));
    reportDialog.report = null;
  } finally {
    reportDialog.loading = false;
  }
}

async function deleteReport(row) {
  try {
    await api.deleteReport(row.id);
    ElMessage.success(t('compliance_page.messages.report_deleted'));
    await loadReports();
    await loadFrameworks();
  } catch (e) {
    ElMessage.error(t('compliance_page.messages.delete_failed'));
  }
}

function promptExport(row) {
  exportDialog.reportId = row.id;
  exportDialog.format = 'json';
  exportDialog.visible = true;
}

function promptExportFromDetail() {
  if (reportDialog.report) {
    promptExport(reportDialog.report);
  }
}

async function doExport() {
  loading.export = true;
  try {
    await api.exportReport(exportDialog.reportId, exportDialog.format);
    ElMessage.success(t('compliance_page.messages.export_submitted'));
    exportDialog.visible = false;
  } catch (e) {
    ElMessage.error(t('compliance_page.messages.export_failed'));
  } finally {
    loading.export = false;
  }
}

// ─── SOC2/ISO27001 合规包状态 (M3-69) ───
const packTab = ref('readiness');
const packDashboard = ref({ frameworks: {}, overall_readiness: 0 });

// 问卷
const qFramework = ref('SOC2');
const qLoading = ref(false);
const qSubmitting = ref(false);
const questions = ref([]);
const qAnswers = ref({});

// 证据
const eFramework = ref('SOC2');
const eLoading = ref(false);
const eCollecting = ref(false);
const evidenceChecklist = ref([]);

// 差距
const gFramework = ref('SOC2');
const gReportId = ref(null);
const gRunning = ref(false);
const gapList = ref([]);

// 策略文档
const pFramework = ref('SOC2');
const pLoading = ref(false);
const policyDocs = ref([]);
const docDialog = reactive({
  visible: false,
  doc: null,
  fields: [],
  values: {},
  submitting: false,
});

function parsePlaceholderFields(row) {
  let fields = row.placeholder_fields;
  if (typeof fields === 'string') {
    try { fields = JSON.parse(fields); } catch { fields = []; }
  }
  if (!Array.isArray(fields) || !fields.length) {
    const tpl = row.content_template || '';
    const matches = [...tpl.matchAll(/\{\{(\w+)\}\}/g)].map(m => m[1]);
    fields = [...new Set(matches)];
  }
  return fields;
}

function statusTag2(st) {
  const map = { collected: 'success', validated: 'success', not_collected: 'info', rejected: 'danger' };
  return map[st] || 'info';
}

function remTag(st) {
  const map = { identified: 'danger', in_progress: 'warning', completed: 'success', waived: 'info' };
  return map[st] || 'info';
}

async function loadPackDashboard() {
  try {
    const { data } = await packApi.getDashboard();
    if (data.success) packDashboard.value = data.data;
  } catch { /* ignore */ }
}

async function loadQuestions() {
  qLoading.value = true;
  try {
    const { data } = await packApi.getQuestionnaire(qFramework.value);
    if (data.success) {
      questions.value = data.data;
      qAnswers.value = {};
    }
  } finally {
    qLoading.value = false;
  }
}

async function submitAnswers() {
  qSubmitting.value = true;
  try {
    const report = reports.value[0];
    if (!report) { ElMessage.warning(t('compliance_page.messages.generate_report_first')); return; }
    const answers = questions.value.map(q => ({
      question_id: q.id,
      response: qAnswers.value[q.id] || '',
    }));
    const { data } = await packApi.submitQuestionnaire(report.id, answers);
    if (data.success) ElMessage.success(data.message);
  } finally {
    qSubmitting.value = false;
  }
}

async function loadEvidenceChecklist() {
  eLoading.value = true;
  try {
    const { data } = await packApi.getEvidenceChecklist(eFramework.value);
    if (data.success) evidenceChecklist.value = data.data;
  } finally {
    eLoading.value = false;
  }
}

async function autoCollectEvidence(framework, row) {
  eCollecting.value = true;
  try {
    const { data } = await packApi.collectEvidence(framework, row.control_ref, row.evidence_type);
    if (data.success) {
      ElMessage.success(t('compliance_page.messages.evidence_collected'));
      await loadEvidenceChecklist();
    }
  } finally {
    eCollecting.value = false;
  }
}

async function runGapAnalysis2() {
  if (!gReportId.value) { ElMessage.warning(t('compliance_page.messages.select_report')); return; }
  gRunning.value = true;
  try {
    const { data } = await packApi.runGapAnalysis(gFramework.value, gReportId.value);
    if (data.success) {
      ElMessage.success(data.message);
      await loadGapList();
    }
  } finally {
    gRunning.value = false;
  }
}

async function loadGapList() {
  try {
    const { data } = await packApi.getGapAnalysis({ framework: gFramework.value });
    if (data.success) gapList.value = data.data.data || data.data || [];
  } catch { /* ignore */ }
}

async function loadPolicies() {
  pLoading.value = true;
  try {
    const { data } = await packApi.getPolicyDocuments(pFramework.value);
    if (data.success) policyDocs.value = data.data;
  } finally {
    pLoading.value = false;
  }
}

function generateDoc(row) {
  const fields = parsePlaceholderFields(row);
  const values = {};
  fields.forEach((f) => {
    values[f] = f === 'version'
      ? (row.version || '1.0')
      : (f === 'effective_date' ? new Date().toISOString().slice(0, 10) : '');
  });
  Object.assign(docDialog, {
    visible: true,
    doc: row,
    fields,
    values,
    submitting: false,
  });
}

async function submitGenerateDoc() {
  const missing = docDialog.fields.filter((f) => !String(docDialog.values[f] || '').trim());
  if (missing.length) {
    ElMessage.warning(t('compliance_page.messages.fill_fields', { fields: missing.map(fieldLabel).join(locale.value === 'en' ? ', ' : '、') }));
    return;
  }
  docDialog.submitting = true;
  try {
    const res = await packApi.generatePolicyDocument(docDialog.doc.id, docDialog.values);
    if (res.success !== false) {
      ElMessage.success(res.message || t('compliance_page.messages.doc_generated', { file: res.data?.file || '' }));
      docDialog.visible = false;
      loadPolicies();
    } else {
      ElMessage.error(res.message || t('compliance_page.messages.generate_doc_failed'));
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('compliance_page.messages.generate_doc_failed'));
  } finally {
    docDialog.submitting = false;
  }
}

// 覆盖原 refreshDashboard 添加合规包数据
const origRefresh = refreshDashboard;
refreshDashboard = async function() {
  await origRefresh();
  await loadPackDashboard();
};

onMounted(async () => {
  await refreshDashboard();
  pageLoading.value = false;
});
</script>

<style scoped>
.compliance-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { font-size: 22px; font-weight: 600; margin: 0; }
.stats-row { margin-bottom: 16px; }
.fw-card { cursor: default; transition: transform 0.2s, box-shadow 0.2s; text-align: center; border-top: 3px solid #0f172a; }
.fw-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.fw-card.card-soc2 { border-top-color: #67c23a; }
.fw-card.card-iso { border-top-color: #0f172a; }
.fw-card.card-gdpr { border-top-color: #e6a23c; }
.fw-card.card-hipaa { border-top-color: #f56c6c; }
.fw-card.card-pci { border-top-color: #909399; }
.fw-meta { margin-bottom: 4px; font-size: 12px; }
.fw-pass-rate { display: block; margin-top: 4px; color: #909399; font-size: 12px; }
.fw-report-count { font-size: 11px; color: #c0c4cc; }
.action-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-card.info .stat-value { color: #0f172a; }
.standards-row { margin-bottom: 16px; }
.standard-domains { display: flex; flex-wrap: wrap; gap: 8px; }
.domain-item { display: flex; align-items: center; gap: 6px; width: calc(50% - 4px); font-size: 13px; color: #606266; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.report-section { margin-bottom: 20px; }
.pagination-wrap { display: flex; justify-content: center; padding: 16px 0 0; }
.pass-count { color: #67c23a; font-weight: 600; }
.fail-count { color: #f56c6c; font-weight: 600; }
.na-count { color: #909399; }
.sep { margin: 0 4px; color: #dcdfe6; }
.loading-container { padding: 40px 0; }
.dialog-loading { padding: 20px; }
.detail-section { margin-top: 20px; }
.detail-section h4 { font-size: 15px; font-weight: 600; margin: 0 0 10px; }
.detail-section p { color: #606266; line-height: 1.6; margin: 0; }
.export-hint { color: #909399; font-size: 13px; margin: 8px 0 0; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }
.mb-3 { margin-bottom: 12px; }
.text-gray { color: #909399; font-size: 0.85em; }
.tab-toolbar { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
.question-item { padding: 12px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 8px; }
.q-header { display: flex; gap: 8px; align-items: center; margin-bottom: 4px; }
.q-text { font-size: 0.95em; }
.q-guidance { font-size: 0.85em; margin-bottom: 8px; margin-left: 20px; }
.q-input { margin-top: 4px; }
</style>
