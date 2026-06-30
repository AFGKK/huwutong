<template>
  <div class="compliance-page">
    <div class="page-header">
      <h2>SOC 2 / ISO 27001 合规包</h2>
      <div class="header-actions">
        <el-button @click="refreshDashboard" :loading="loading.dashboard" plain>刷新</el-button>
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
                <div class="fw-pass-rate">通过率 {{ fw.latest_report.passed_count }}/{{ fw.latest_report.passed_count + fw.latest_report.failed_count }}</div>
              </template>
              <template v-else>
                <el-tag type="info" size="small">未生成报告</el-tag>
              </template>
            </div>
            <div class="fw-report-count">{{ fw.report_count }} 份报告</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 操作与统计 -->
      <el-row :gutter="16" class="action-row">
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>
              <div class="card-header"><span>生成合规报告</span></div>
            </template>
            <el-form :model="reportForm" inline>
              <el-form-item label="框架" required>
                <el-select v-model="reportForm.framework_id" placeholder="选择合规框架" style="width:200px;">
                  <el-option v-for="fw in frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                </el-select>
              </el-form-item>
              <el-form-item label="类型">
                <el-select v-model="reportForm.type" style="width:140px;">
                  <el-option label="按需生成" value="on_demand" />
                  <el-option label="定期生成" value="scheduled" />
                  <el-option label="持续监控" value="continuous" />
                </el-select>
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="generateReport" :loading="loading.generate" plain>生成报告</el-button>
              </el-form-item>
            </el-form>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card">
            <div class="stat-value">{{ stats.total_reports }}</div>
            <div class="stat-label">总报告数</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="stat-card info">
            <div class="stat-value">{{ stats.total_exports }}</div>
            <div class="stat-label">导出记录</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 标准参考 -->
      <el-row :gutter="16" class="standards-row">
        <el-col :span="12">
          <el-card shadow="never">
            <template #header>
              <div class="card-header"><span>SOC 2 控制域</span></div>
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
              <div class="card-header"><span>ISO 27001 控制域</span></div>
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
            <span>合规报告历史</span>
            <div class="header-actions">
              <el-select v-model="filters.framework_id" placeholder="框架筛选" clearable @change="loadReports" style="width:150px;margin-right:8px;">
                <el-option v-for="fw in frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
              </el-select>
              <el-select v-model="filters.status" placeholder="状态" clearable @change="loadReports" style="width:120px;">
                <el-option label="草稿" value="draft" />
                <el-option label="已生成" value="generated" />
                <el-option label="失败" value="failed" />
              </el-select>
            </div>
          </div>
        </template>
        <el-table :data="reports" v-loading="loading.reports" stripe>
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column label="框架" width="140">
            <template #default="{ row }">
              <el-tag v-if="row.framework" :type="fwTag(row.framework.code)" size="small">{{ row.framework.code }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
          <el-table-column label="风险等级" width="120">
            <template #default="{ row }">
              <el-tag :type="riskTag(row.risk_level)" effect="dark" size="small">{{ riskLabel(row.risk_level) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="通过/失败" width="140">
            <template #default="{ row }">
              <span class="pass-count">{{ row.passed_count }}</span><span class="sep">/</span>
              <span class="fail-count">{{ row.failed_count }}</span><span class="sep">/</span>
              <span class="na-count">N/A {{ row.na_count }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="生成时间" width="170">
            <template #default="{ row }">{{ formatDate(row.generated_at || row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button text type="primary" size="small" @click="viewReport(row)">查看</el-button>
              <el-button text type="warning" size="small" @click="promptExport(row)">导出</el-button>
              <el-popconfirm title="确认删除此报告？" @confirm="deleteReport(row)">
                <template #reference>
                  <el-button text type="danger" size="small">删除</el-button>
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
        <el-tab-pane label="准备就绪" name="readiness">
          <el-row :gutter="16">
            <el-col :span="12" v-for="(fw, code) in packDashboard.frameworks" :key="code">
              <el-card shadow="never" class="mb-3">
                <template #header>
                  <span>{{ fw.framework_name }}</span>
                  <el-progress :percentage="fw.readiness_score" :status="fw.readiness_score >= 80 ? 'success' : fw.readiness_score >= 50 ? 'warning' : 'exception'" style="width:200px;display:inline-block;float:right" />
                </template>
                <el-descriptions :column="2" size="small" border>
                  <el-descriptions-item label="证据总数">{{ fw.evidence_total }}</el-descriptions-item>
                  <el-descriptions-item label="已验证">{{ fw.evidence_validated }} ({{ fw.evidence_validation_rate }}%)</el-descriptions-item>
                  <el-descriptions-item label="差距分析">{{ fw.gaps_resolved }}/{{ fw.gaps_total }} 已解决</el-descriptions-item>
                  <el-descriptions-item label="未关闭差距">{{ fw.gaps_open }}</el-descriptions-item>
                  <el-descriptions-item label="策略文档">{{ fw.policy_count }} 份</el-descriptions-item>
                  <el-descriptions-item label="问卷进度">{{ fw.questionnaire_answered }}/{{ fw.questionnaire_total }} ({{ fw.questionnaire_progress }}%)</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
          </el-row>
          <el-alert title="整体准备就绪度" :description="'评分: ' + packDashboard.overall_readiness + '%'" :type="packDashboard.overall_readiness >= 80 ? 'success' : packDashboard.overall_readiness >= 50 ? 'warning' : 'error'" show-icon />
        </el-tab-pane>

        <!-- Tab 2: 审计问卷 -->
        <el-tab-pane label="审计问卷" name="questionnaire">
          <div class="tab-toolbar">
            <el-select v-model="qFramework" placeholder="选择框架" style="width:150px">
              <el-option label="SOC 2" value="SOC2" />
              <el-option label="ISO 27001" value="ISO27001" />
            </el-select>
            <el-button @click="loadQuestions" :loading="qLoading" type="primary" size="small">加载问卷</el-button>
          </div>
          <el-card v-if="questions.length" shadow="never">
            <div v-for="(q, i) in questions" :key="q.id" class="question-item">
              <div class="q-header">
                <el-tag size="small" :type="q.severity === 'critical' ? 'danger' : q.severity === 'high' ? 'warning' : 'info'">{{ q.severity }}</el-tag>
                <strong>{{ q.control_ref }}</strong>
                <span class="q-text">{{ q.question }}</span>
              </div>
              <div class="q-guidance text-gray">{{ q.guidance }}</div>
              <el-input v-model="qAnswers[q.id]" type="textarea" :rows="2" placeholder="输入回答..." class="q-input" />
            </div>
            <el-button type="primary" @click="submitAnswers" :loading="qSubmitting" class="mt-2">提交回答</el-button>
          </el-card>
        </el-tab-pane>

        <!-- Tab 3: 证据收集清单 -->
        <el-tab-pane label="证据收集" name="evidence">
          <div class="tab-toolbar">
            <el-select v-model="eFramework" placeholder="选择框架" style="width:150px">
              <el-option label="SOC 2" value="SOC2" />
              <el-option label="ISO 27001" value="ISO27001" />
            </el-select>
            <el-button @click="loadEvidenceChecklist" :loading="eLoading" type="primary" size="small">加载清单</el-button>
          </div>
          <el-table :data="evidenceChecklist" stripe size="small" v-if="evidenceChecklist.length">
            <el-table-column prop="control_ref" label="控制域" width="100" />
            <el-table-column prop="title" label="证据项" min-width="200" />
            <el-table-column prop="evidence_type" label="类型" width="140" />
            <el-table-column prop="suggested_source" label="来源" width="140" />
            <el-table-column label="状态" width="120">
              <template #default="{ row }">
                <el-tag :type="statusTag2(row.collection_status)" size="small">{{ row.collection_status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="160">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="autoCollectEvidence(eFramework, row)" :loading="eCollecting">自动收集</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 差距分析 -->
        <el-tab-pane label="差距分析" name="gaps">
          <div class="tab-toolbar">
            <el-select v-model="gFramework" placeholder="选择框架" style="width:150px">
              <el-option label="SOC 2" value="SOC2" />
              <el-option label="ISO 27001" value="ISO27001" />
            </el-select>
            <el-select v-model="gReportId" placeholder="选择报告" style="width:200px">
              <el-option v-for="r in reports" :key="r.id" :label="'#' + r.id + ' ' + (r.title || '')" :value="r.id" />
            </el-select>
            <el-button @click="runGapAnalysis2" :loading="gRunning" type="primary" size="small">运行分析</el-button>
          </div>
          <el-table :data="gapList" stripe size="small" v-if="gapList.length">
            <el-table-column prop="control_ref" label="控制域" width="90" />
            <el-table-column prop="control_title" label="控制名称" min-width="160" />
            <el-table-column label="风险" width="80">
              <template #default="{ row }"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="current_state" label="当前状态" width="120" />
            <el-table-column label="整改状态" width="120">
              <template #default="{ row }">
                <el-tag :type="remTag(row.remediation_status)" size="small">{{ row.remediation_status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="remediation_plan" label="整改计划" min-width="200" show-overflow-tooltip />
          </el-table>
        </el-tab-pane>

        <!-- Tab 5: 策略文档模板 -->
        <el-tab-pane label="策略文档" name="policies">
          <div class="tab-toolbar">
            <el-select v-model="pFramework" placeholder="选择框架" style="width:150px">
              <el-option label="SOC 2" value="SOC2" />
              <el-option label="ISO 27001" value="ISO27001" />
            </el-select>
            <el-button @click="loadPolicies" :loading="pLoading" type="primary" size="small">加载模板</el-button>
          </div>
          <el-table :data="policyDocs" stripe size="small" v-if="policyDocs.length">
            <el-table-column prop="title" label="文档名称" min-width="220" />
            <el-table-column prop="category" label="分类" width="120" />
            <el-table-column prop="description" label="说明" min-width="250" show-overflow-tooltip />
            <el-table-column prop="version" label="版本" width="80" />
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="generateDoc(row)">生成文档</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>

    <!-- 报告详情弹窗 -->
    <el-dialog v-model="reportDialog.visible" :title="reportDialog.title" width="720px" top="5vh">
      <div v-show="reportDialog.loading" class="dialog-loading">
        <el-skeleton :rows="8" animated />
      </div>
      <div v-show="!reportDialog.loading && reportDialog.report">
        <div class="report-detail">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="框架" :span="1">
              <el-tag v-if="reportDialog.report.framework" :type="fwTag(reportDialog.report.framework.code)" size="small">
                {{ reportDialog.report.framework.code }} - {{ reportDialog.report.framework.name }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="风险等级" :span="1">
              <el-tag :type="riskTag(reportDialog.report.risk_level)" effect="dark">{{ riskLabel(reportDialog.report.risk_level) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="通过" :span="1"><span class="pass-count">{{ reportDialog.report.passed_count }}</span></el-descriptions-item>
            <el-descriptions-item label="失败" :span="1"><span class="fail-count">{{ reportDialog.report.failed_count }}</span></el-descriptions-item>
            <el-descriptions-item label="N/A" :span="1"><span class="na-count">{{ reportDialog.report.na_count }}</span></el-descriptions-item>
            <el-descriptions-item label="状态" :span="1">
              <el-tag :type="statusTag(reportDialog.report.status)" size="small">{{ statusLabel(reportDialog.report.status) }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="生成时间" :span="2">{{ formatDate(reportDialog.report.generated_at) }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-section" v-if="reportDialog.report.summary">
            <h4>报告摘要</h4>
            <p>{{ reportDialog.report.summary }}</p>
          </div>
          <div class="detail-section" v-if="reportDialog.report.controls_assessed && reportDialog.report.controls_assessed.length">
            <h4>控制域评估</h4>
            <el-table :data="reportDialog.report.controls_assessed" stripe>
              <el-table-column prop="domain" label="控制域" min-width="160" />
              <el-table-column label="结果" width="100">
                <template #default="{ row }">
                  <el-tag :type="domainResultTag(row.status)" size="small">{{ domainResultLabel(row.status) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="finding" label="发现" min-width="200" show-overflow-tooltip />
            </el-table>
          </div>
          <div class="detail-section" v-if="reportDialog.report.evidence_refs && reportDialog.report.evidence_refs.length">
            <h4>证据引用</h4>
            <el-table :data="reportDialog.report.evidence_refs" stripe>
              <el-table-column prop="type" label="证据类型" width="140" />
              <el-table-column prop="count" label="数量" width="80" />
              <el-table-column prop="description" label="描述" min-width="200" />
            </el-table>
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="reportDialog.visible = false">关闭</el-button>
        <el-button type="primary" @click="promptExportFromDetail" v-show="!reportDialog.loading && reportDialog.report">导出报告</el-button>
      </template>
    </el-dialog>

    <!-- 导出确认弹窗 -->
    <el-dialog v-model="exportDialog.visible" title="导出合规报告" width="400px">
      <el-form>
        <el-form-item label="导出格式">
          <el-radio-group v-model="exportDialog.format">
            <el-radio value="json">JSON</el-radio>
            <el-radio value="csv">CSV</el-radio>
          </el-radio-group>
        </el-form-item>
        <p class="export-hint">报告ID: #{{ exportDialog.reportId }}，将生成{{ exportFormatLabel }}文件。</p>
      </el-form>
      <template #footer>
        <el-button @click="exportDialog.visible = false">取消</el-button>
        <el-button type="primary" @click="doExport" :loading="loading.export">确认导出</el-button>
      </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import api from '@/api/compliance';
import packApi from '@/api/compliancePack';

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

const soc2Domains = [
  { code: 'SEC', name: '安全 - 保护系统资源' },
  { code: 'AVA', name: '可用性 - 确保系统可用' },
  { code: 'PID', name: '处理完整性 - 确保数据处理完整' },
  { code: 'CON', name: '保密性 - 保护保密信息' },
  { code: 'PRI', name: '隐私 - 收集与使用个人信息' },
];

const isoDomains = [
  { code: 'A.5', name: '信息安全策略' },
  { code: 'A.8', name: '资产管理' },
  { code: 'A.9', name: '访问控制' },
  { code: 'A.10', name: '加密' },
  { code: 'A.11', name: '物理安全' },
  { code: 'A.12', name: '操作安全' },
  { code: 'A.13', name: '通信安全' },
];

function fwClass(code) {
  const map = { SOC2: 'card-soc2', ISO27001: 'card-iso', GDPR: 'card-gdpr', HIPAA: 'card-hipaa', PCI_DSS: 'card-pci' };
  return map[code] || '';
}

function riskTag(level) {
  const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
  return map[level] || 'info';
}

function riskLabel(level) {
  const map = { low: '低风险', medium: '中风险', high: '高风险', critical: '严重' };
  return map[level] || level;
}

function domainResultTag(status) {
  if (status === 'pass') return 'success';
  if (status === 'warn') return 'warning';
  return 'danger';
}

function domainResultLabel(status) {
  if (status === 'pass') return '通过';
  if (status === 'warn') return '警告';
  return '失败';
}

function statusTag(status) {
  const map = { draft: 'info', generated: 'success', failed: 'danger', archived: '' };
  return map[status] || '';
}

function statusLabel(status) {
  const map = { draft: '草稿', generated: '已生成', failed: '失败', archived: '已归档' };
  return map[status] || status;
}

function fwTag(code) {
  const map = { SOC2: 'success', ISO27001: 'primary', GDPR: 'warning', HIPAA: 'danger', PCI_DSS: '' };
  return map[code] || '';
}

function formatDate(val) {
  if (!val) return '-';
  const d = new Date(val);
  return d.toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

async function refreshDashboard() {
  loading.dashboard = true;
  try {
    await loadFrameworks();
    await loadReports();
  } catch (e) {
    ElMessage.error('加载仪表盘失败');
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
    console.error('加载框架失败', e);
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
    console.error('加载报告失败', e);
    reports.value = [];
  } finally {
    loading.reports = false;
  }
}

async function generateReport() {
  if (!reportForm.framework_id) {
    ElMessage.warning('请选择合规框架');
    return;
  }
  loading.generate = true;
  try {
    await api.generateReport({
      framework_id: reportForm.framework_id,
      type: reportForm.type,
      title: '合规报告 - ' + new Date().toLocaleDateString('zh-CN'),
    });
    ElMessage.success('合规报告已生成');
    await loadReports();
    await loadFrameworks();
  } catch (e) {
    ElMessage.error('生成报告失败');
  } finally {
    loading.generate = false;
  }
}

async function viewReport(row) {
  reportDialog.visible = true;
  reportDialog.loading = true;
  reportDialog.title = '报告详情 #' + row.id;
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
    ElMessage.error('加载报告详情失败');
    reportDialog.report = null;
  } finally {
    reportDialog.loading = false;
  }
}

async function deleteReport(row) {
  try {
    await api.deleteReport(row.id);
    ElMessage.success('报告已删除');
    await loadReports();
    await loadFrameworks();
  } catch (e) {
    ElMessage.error('删除失败');
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
    ElMessage.success('导出任务已提交');
    exportDialog.visible = false;
  } catch (e) {
    ElMessage.error('导出失败');
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
    if (!report) { ElMessage.warning('请先生成一份合规报告'); return; }
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
      ElMessage.success('证据已自动收集');
      await loadEvidenceChecklist();
    }
  } finally {
    eCollecting.value = false;
  }
}

async function runGapAnalysis2() {
  if (!gReportId.value) { ElMessage.warning('请选择报告'); return; }
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
  ElMessage.info('生成策略文档: ' + row.title);
  // 占位 - 实际可弹出表单填写占位字段
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
.fw-card { cursor: default; transition: transform 0.2s, box-shadow 0.2s; text-align: center; border-top: 3px solid #409eff; }
.fw-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.fw-card.card-soc2 { border-top-color: #67c23a; }
.fw-card.card-iso { border-top-color: #409eff; }
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
.stat-card.info .stat-value { color: #409eff; }
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
