<template>
  <div class="report-builder">
    <!-- 顶部统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon bg-blue">
              <el-icon><Document /></el-icon>
            </div>
            <div class="stat-info">
              <span class="stat-value">{{ dashboardStats.total_reports }}</span>
              <span class="stat-label">自定义报表</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon bg-green">
              <el-icon><CopyDocument /></el-icon>
            </div>
            <div class="stat-info">
              <span class="stat-value">{{ dashboardStats.total_templates }}</span>
              <span class="stat-label">报表模板</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon bg-orange">
              <el-icon><Clock /></el-icon>
            </div>
            <div class="stat-info">
              <span class="stat-value">{{ dashboardStats.scheduled_count }}</span>
              <span class="stat-label">定时任务</span>
            </div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon bg-purple">
              <el-icon><Histogram /></el-icon>
            </div>
            <div class="stat-info">
              <span class="stat-value">{{ dashboardStats.total_snapshots }}</span>
              <span class="stat-label">生成快照</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主标签页 -->
    <el-tabs v-model="activeTab" type="border-card">
      <!-- ─── 我的报表 ─── -->
      <el-tab-pane label="我的报表" name="reports">
        <div class="tab-toolbar">
          <el-form :inline="true" :model="listQuery" size="small">
            <el-form-item>
              <el-select v-model="listQuery.category" placeholder="全部分类" clearable @change="fetchReports">
                <el-option label="全部分类" value="" />
                <el-option v-for="cat in categories" :key="cat" :label="categoryLabel(cat)" :value="cat" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="listQuery.data_source" placeholder="全部数据源" clearable @change="fetchReports">
                <el-option label="全部数据源" value="" />
                <el-option v-for="(ds, key) in dataSources" :key="key" :label="ds.label" :value="key" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="showCreateDialog = true">
                <el-icon><Plus /></el-icon> 新建报表
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <el-table :data="reports" v-loading="loading" stripe style="width: 100%">
          <el-table-column prop="name" label="报表名称" min-width="160" show-overflow-tooltip />
          <el-table-column label="分类" width="100">
            <template #default="{ row }">
              <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="数据源" width="120">
            <template #default="{ row }">
              <span>{{ dataSourceLabel(row.data_source) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="图表" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.chart_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="定时" width="60">
            <template #default="{ row }">
              <el-icon v-if="row.is_scheduled" color="#67c23a"><Clock /></el-icon>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="共享" width="60">
            <template #default="{ row }">
              <el-icon v-if="row.is_shared" color="#409eff"><Share /></el-icon>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="上次生成" width="160">
            <template #default="{ row }">
              <span v-if="row.last_generated_at">{{ formatDate(row.last_generated_at) }}</span>
              <span v-else class="text-muted">未生成</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="280" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click="runReport(row)">
                <el-icon><DataAnalysis /></el-icon> 运行
              </el-button>
              <el-button size="small" type="primary" link @click="editReport(row)">
                <el-icon><Edit /></el-icon> 编辑
              </el-button>
              <el-button size="small" type="primary" link @click="showExportDialog(row)">
                <el-icon><Download /></el-icon> 导出
              </el-button>
              <el-popconfirm title="确定删除此报表?" @confirm="handleDelete(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <div class="mt-3 flex-center" v-if="totalPages > 1">
          <el-pagination
            background
            layout="total, prev, pager, next"
            :total="totalItems"
            :page-size="perPage"
            :current-page="currentPage"
            @current-change="changePage"
          />
        </div>
      </el-tab-pane>

      <!-- ─── 报表运行结果 ─── -->
      <el-tab-pane label="运行结果" name="result" v-if="reportResult">
        <div class="mb-3">
          <el-button @click="reportResult = null"><el-icon><Back /></el-icon> 返回</el-button>
          <el-tag class="ml-2">{{ reportResult.total_rows }} 行</el-tag>
        </div>
        <el-table :data="reportResult.rows" stripe border max-height="500" style="width: 100%" v-if="reportResult.rows?.length">
          <el-table-column v-for="col in resultColumns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
        </el-table>
        <div v-if="reportResult.summary && Object.keys(reportResult.summary).length" class="mt-3">
          <el-descriptions :column="3" border title="汇总">
            <el-descriptions-item v-for="(val, key) in reportResult.summary" :key="key" :label="key">
              {{ formatValue(val.total, val.format) }}
              <small class="text-muted ml-1">(avg: {{ formatValue(val.avg, val.format) }})</small>
            </el-descriptions-item>
          </el-descriptions>
        </div>
        <div v-if="reportResult.chart?.datasets?.length" class="mt-4">
          <h4 class="mb-2">图表预览 ({{ reportResult.chart.type }})</h4>
          <el-alert title="图表渲染区域 - 数据已就绪" type="success" :description="`${reportResult.chart.labels?.length || 0} 个标签, ${reportResult.chart.datasets.length} 个数据集`" show-icon />
        </div>
      </el-tab-pane>

      <!-- ─── 看板管理 ─── -->
      <el-tab-pane label="看板管理" name="dashboards">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showDashboardDialog = true">
            <el-icon><Plus /></el-icon> 新建看板
          </el-button>
        </div>
        <el-table :data="dashboards" v-loading="dashLoading" stripe>
          <el-table-column prop="name" label="看板名称" min-width="160" />
          <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
          <el-table-column label="默认" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.is_default" type="success" size="small">默认</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="共享" width="80">
            <template #default="{ row }">
              <el-icon v-if="row.is_shared" color="#409eff"><Share /></el-icon>
              <span v-else>-</span>
            </template>
          </el-table-column>
          <el-table-column label="组件数" width="100">
            <template #default="{ row }">
              {{ row.layout?.widgets?.length || 0 }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" type="primary" link @click="editDashboard(row)">
                <el-icon><Edit /></el-icon> 编辑
              </el-button>
              <el-popconfirm title="确定删除此看板?" @confirm="handleDeleteDashboard(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- ─── 新建/编辑报表对话框 ─── -->
    <el-dialog v-model="showCreateDialog" :title="editMode ? '编辑报表' : '新建报表'" width="800px" top="5vh">
      <el-form ref="reportFormRef" :model="reportForm" :rules="reportRules" label-width="100px" v-loading="formLoading">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="报表名称" prop="name">
              <el-input v-model="reportForm.name" placeholder="输入报表名称" maxlength="200" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="分类" prop="category">
              <el-select v-model="reportForm.category" placeholder="选择分类" style="width:100%">
                <el-option v-for="cat in categories" :key="cat" :label="categoryLabel(cat)" :value="cat" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input v-model="reportForm.description" type="textarea" :rows="2" maxlength="2000" show-word-limit />
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="数据源" prop="data_source">
              <el-select v-model="reportForm.data_source" placeholder="选择数据源" style="width:100%" @change="onDataSourceChange">
                <el-option v-for="(ds, key) in dataSources" :key="key" :label="ds.label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="图表类型" prop="chart_type">
              <el-select v-model="reportForm.chart_type" placeholder="图表类型" style="width:100%">
                <el-option label="表格" value="table" />
                <el-option label="柱状图" value="bar" />
                <el-option label="折线图" value="line" />
                <el-option label="饼图" value="pie" />
                <el-option label="面积图" value="area" />
                <el-option label="雷达图" value="radar" />
                <el-option label="数字卡片" value="number" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="指标" prop="metrics">
          <div class="metrics-grid">
            <el-checkbox-group v-model="selectedMetrics">
              <el-checkbox
                v-for="(mDef, mKey) in currentMetrics"
                :key="mKey"
                :label="mKey"
                :value="mKey"
              >
                {{ mDef.label }}
              </el-checkbox>
            </el-checkbox-group>
          </div>
          <div class="text-muted mt-1" v-if="!reportForm.data_source">请先选择数据源</div>
        </el-form-item>
        <el-form-item label="维度">
          <div class="metrics-grid">
            <el-checkbox-group v-model="selectedDimensions">
              <el-checkbox
                v-for="(dDef, dKey) in currentDimensions"
                :key="dKey"
                :label="dKey"
                :value="dKey"
              >
                {{ dDef.label }}
              </el-checkbox>
            </el-checkbox-group>
          </div>
          <div class="text-muted mt-1" v-if="!reportForm.data_source">请先选择数据源</div>
        </el-form-item>
        <el-form-item label="定时设置">
          <el-switch v-model="reportForm.is_scheduled" active-text="启用定时" />
          <template v-if="reportForm.is_scheduled">
            <el-input v-model="reportForm.schedule_cron" placeholder="Cron 表达式 (如 0 8 * * *)" class="ml-2" style="width: 200px" />
            <el-tag class="ml-2">每天 8:00</el-tag>
          </template>
        </el-form-item>
        <el-form-item label="共享">
          <el-switch v-model="reportForm.is_shared" active-text="共享给团队" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" :loading="formLoading" @click="submitReport">
          {{ editMode ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- ─── 编辑看板对话框 ─── -->
    <el-dialog v-model="showDashboardDialog" :title="dashboardEditMode ? '编辑看板' : '新建看板'" width="500px">
      <el-form ref="dashFormRef" :model="dashForm" :rules="dashRules" label-width="80px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="dashForm.name" placeholder="看板名称" maxlength="200" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="dashForm.description" type="textarea" :rows="2" maxlength="1000" />
        </el-form-item>
        <el-form-item label="共享">
          <el-switch v-model="dashForm.is_shared" />
        </el-form-item>
        <el-form-item label="默认">
          <el-switch v-model="dashForm.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDashboardDialog = false">取消</el-button>
        <el-button type="primary" :loading="formLoading" @click="submitDashboard">
          {{ dashboardEditMode ? '更新' : '创建' }}
        </el-button>
      </template>
    </el-dialog>

    <!-- ─── 导出对话框 ─── -->
    <el-dialog v-model="showExportDlg" title="导出报表" width="400px">
      <el-form label-width="80px">
        <el-form-item label="导出格式">
          <el-radio-group v-model="exportFormat">
            <el-radio value="csv">CSV</el-radio>
            <el-radio value="json">JSON</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showExportDlg = false">取消</el-button>
        <el-button type="primary" :loading="exporting" @click="doExport">导出</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, Edit, Download, Share, Clock, Document, CopyDocument, Histogram, DataAnalysis, Back } from '@element-plus/icons-vue';
import reportBuilderApi from '../../api/reportBuilder';

export default {
  name: 'ReportBuilder',
  components: { Plus, Delete, Edit, Download, Share, Clock, Document, CopyDocument, Histogram, DataAnalysis, Back },
  setup() {
    const activeTab = ref('reports');
    const loading = ref(false);
    const dashLoading = ref(false);
    const formLoading = ref(false);
    const exporting = ref(false);

    // ─── 数据 ───
    const dataSources = ref({});
    const categories = ref([]);
    const reports = ref([]);
    const dashboards = ref([]);
    const reportResult = ref(null);

    const listQuery = reactive({
      category: '',
      data_source: '',
    });

    const currentPage = ref(1);
    const perPage = ref(50);
    const totalItems = ref(0);
    const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value));

    const dashboardStats = reactive({
      total_reports: 0,
      total_templates: 0,
      scheduled_count: 0,
      total_snapshots: 0,
    });

    const resultColumns = computed(() => {
      if (!reportResult.value?.rows?.length) return [];
      return Object.keys(reportResult.value.rows[0]);
    });

    // ─── 新建/编辑报表 ───
    const showCreateDialog = ref(false);
    const editMode = ref(false);
    const editingId = ref(null);
    const reportFormRef = ref(null);

    const reportForm = reactive({
      name: '',
      description: '',
      category: '',
      data_source: '',
      chart_type: 'table',
      metrics: {},
      dimensions: [],
      filters: {},
      sorts: [],
      is_scheduled: false,
      schedule_cron: '',
      is_shared: false,
    });

    const selectedMetrics = ref([]);
    const selectedDimensions = ref([]);

    const reportRules = {
      name: [{ required: true, message: '请输入报表名称', trigger: 'blur' }],
      category: [{ required: true, message: '请选择分类', trigger: 'change' }],
      data_source: [{ required: true, message: '请选择数据源', trigger: 'change' }],
    };

    const currentMetrics = computed(() => {
      if (!reportForm.data_source || !dataSources.value[reportForm.data_source]) return {};
      return dataSources.value[reportForm.data_source].metrics || {};
    });

    const currentDimensions = computed(() => {
      if (!reportForm.data_source || !dataSources.value[reportForm.data_source]) return {};
      return dataSources.value[reportForm.data_source].dimensions || {};
    });

    function onDataSourceChange() {
      selectedMetrics.value = [];
      selectedDimensions.value = [];
      // 自动选择默认指标
      const metrics = currentMetrics.value;
      Object.entries(metrics).forEach(([key, def]) => {
        if (def.default) selectedMetrics.value.push(key);
      });
    }

    // ─── 看板 ───
    const showDashboardDialog = ref(false);
    const dashboardEditMode = ref(false);
    const dashEditId = ref(null);
    const dashFormRef = ref(null);

    const dashForm = reactive({
      name: '',
      description: '',
      is_shared: false,
      is_default: false,
    });

    const dashRules = {
      name: [{ required: true, message: '请输入看板名称', trigger: 'blur' }],
    };

    // ─── 导出 ───
    const showExportDlg = ref(false);
    const exportFormat = ref('csv');
    const exportReportId = ref(null);

    // ─── 工具函数 ───
    function formatDate(d) {
      if (!d) return '-';
      const dt = new Date(d);
      return dt.toLocaleString('zh-CN', { hour12: false });
    }

    function formatValue(val, format) {
      if (val === undefined || val === null) return '-';
      if (format === 'currency') return `¥${Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
      if (format === 'percentage') return `${val}%`;
      if (typeof val === 'number') return val.toLocaleString('zh-CN');
      return val;
    }

    function categoryLabel(cat) {
      const labels = {
        financial: '财务',
        license: 'License',
        customer: '客户',
        audit: '审计',
        custom: '自定义',
      };
      return labels[cat] || cat;
    }

    function categoryTag(cat) {
      const map = { financial: 'danger', license: 'warning', customer: 'success', audit: 'info', custom: '' };
      return map[cat] || '';
    }

    function dataSourceLabel(src) {
      return dataSources.value[src]?.label || src;
    }

    // ─── 数据加载 ───
    async function fetchDashboard() {
      try {
        const { data } = await reportBuilderApi.getDashboard();
        if (data.success) {
          Object.assign(dashboardStats, data.data.stats);
          dataSources.value = data.data.data_sources || {};
          categories.value = data.data.categories || [];
        }
      } catch (e) { /* ignore */ }
    }

    async function fetchReports() {
      loading.value = true;
      try {
        const params = { page: currentPage.value, per_page: perPage.value };
        if (listQuery.category) params.category = listQuery.category;
        if (listQuery.data_source) params.data_source = listQuery.data_source;
        const { data } = await reportBuilderApi.getReports(params);
        if (data.success) {
          reports.value = data.data.data || [];
          totalItems.value = data.data.total || 0;
          currentPage.value = data.data.current_page || 1;
        }
      } catch (e) {
        ElMessage.error('加载报表列表失败');
      } finally {
        loading.value = false;
      }
    }

    async function fetchDashboards() {
      dashLoading.value = true;
      try {
        const { data } = await reportBuilderApi.getDashboards();
        if (data.success) {
          dashboards.value = data.data || [];
        }
      } catch (e) {
        ElMessage.error('加载看板列表失败');
      } finally {
        dashLoading.value = false;
      }
    }

    function changePage(page) {
      currentPage.value = page;
      fetchReports();
    }

    // ─── 操作 ───
    function runReport(report) {
      reportBuilderApi.generateReport(report.id).then(({ data }) => {
        if (data.success) {
          reportResult.value = data.data;
          activeTab.value = 'result';
          ElMessage.success('报表生成成功');
        }
      }).catch(e => {
        ElMessage.error(e.response?.data?.message || '生成失败');
      });
    }

    function editReport(report) {
      editMode.value = true;
      editingId.value = report.id;
      Object.assign(reportForm, {
        name: report.name,
        description: report.description || '',
        category: report.category,
        data_source: report.data_source,
        chart_type: report.chart_type,
        is_scheduled: !!report.is_scheduled,
        schedule_cron: report.schedule_cron || '',
        is_shared: !!report.is_shared,
      });
      selectedMetrics.value = Object.keys(report.metrics || {});
      selectedDimensions.value = report.dimensions || [];
      showCreateDialog.value = true;
    }

    function resetForm() {
      editMode.value = false;
      editingId.value = null;
      reportForm.name = '';
      reportForm.description = '';
      reportForm.category = '';
      reportForm.data_source = '';
      reportForm.chart_type = 'table';
      reportForm.is_scheduled = false;
      reportForm.schedule_cron = '';
      reportForm.is_shared = false;
      selectedMetrics.value = [];
      selectedDimensions.value = [];
    }

    async function submitReport() {
      const valid = await reportFormRef.value?.validate().catch(() => false);
      if (!valid) return;

      formLoading.value = true;
      try {
        // 构建 metrics 对象
        const metrics = {};
        const mDefs = currentMetrics.value;
        selectedMetrics.value.forEach(key => {
          metrics[key] = mDefs[key] || { type: 'count', label: key };
        });

        const payload = {
          ...reportForm,
          metrics,
          dimensions: selectedDimensions.value,
        };

        if (editMode.value && editingId.value) {
          await reportBuilderApi.updateReport(editingId.value, payload);
          ElMessage.success('报表已更新');
        } else {
          await reportBuilderApi.createReport(payload);
          ElMessage.success('报表已创建');
        }

        showCreateDialog.value = false;
        resetForm();
        fetchReports();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
      } finally {
        formLoading.value = false;
      }
    }

    function showExportDialog(report) {
      exportReportId.value = report.id;
      exportFormat.value = 'csv';
      showExportDlg.value = true;
    }

    async function doExport() {
      if (!exportReportId.value) return;
      exporting.value = true;
      try {
        const { data } = await reportBuilderApi.exportReport(exportReportId.value, exportFormat.value);
        if (data.success) {
          ElMessage.success(`导出成功, 文件: ${data.data.filename}`);
        }
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '导出失败');
      } finally {
        exporting.value = false;
        showExportDlg.value = false;
      }
    }

    async function handleDelete(report) {
      try {
        await reportBuilderApi.deleteReport(report.id);
        ElMessage.success('报表已删除');
        fetchReports();
      } catch (e) {
        ElMessage.error('删除失败');
      }
    }

    function editDashboard(dash) {
      dashboardEditMode.value = true;
      dashEditId.value = dash.id;
      Object.assign(dashForm, {
        name: dash.name,
        description: dash.description || '',
        is_shared: !!dash.is_shared,
        is_default: !!dash.is_default,
      });
      showDashboardDialog.value = true;
    }

    function resetDashForm() {
      dashboardEditMode.value = false;
      dashEditId.value = null;
      dashForm.name = '';
      dashForm.description = '';
      dashForm.is_shared = false;
      dashForm.is_default = false;
    }

    async function submitDashboard() {
      const valid = await dashFormRef.value?.validate().catch(() => false);
      if (!valid) return;

      formLoading.value = true;
      try {
        if (dashboardEditMode.value && dashEditId.value) {
          await reportBuilderApi.updateDashboard(dashEditId.value, { ...dashForm });
          ElMessage.success('看板已更新');
        } else {
          await reportBuilderApi.createDashboard({ ...dashForm });
          ElMessage.success('看板已创建');
        }
        showDashboardDialog.value = false;
        resetDashForm();
        fetchDashboards();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
      } finally {
        formLoading.value = false;
      }
    }

    async function handleDeleteDashboard(dash) {
      try {
        await reportBuilderApi.deleteDashboard(dash.id);
        ElMessage.success('看板已删除');
        fetchDashboards();
      } catch (e) {
        ElMessage.error('删除失败');
      }
    }

    // ─── 初始化 ───
    onMounted(() => {
      fetchDashboard();
      fetchReports();
      fetchDashboards();
    });

    return {
      activeTab, loading, dashLoading, formLoading, exporting,
      dataSources, categories, reports, dashboards,
      reportResult, resultColumns,
      listQuery, currentPage, perPage, totalItems, totalPages,
      dashboardStats,
      showCreateDialog, editMode, editingId, reportFormRef, reportForm,
      selectedMetrics, selectedDimensions,
      currentMetrics, currentDimensions,
      reportRules, onDataSourceChange,
      showDashboardDialog, dashboardEditMode, dashEditId, dashFormRef, dashForm, dashRules,
      showExportDlg, exportFormat, exportReportId,
      formatDate, formatValue, categoryLabel, categoryTag, dataSourceLabel,
      fetchReports, fetchDashboards, changePage,
      runReport, editReport, resetForm, submitReport,
      showExportDialog, doExport, handleDelete,
      editDashboard, resetDashForm, submitDashboard, handleDeleteDashboard,
    };
  },
};
</script>

<style scoped>
.report-builder {
  padding: 16px;
}
.stat-card {
  border-radius: 8px;
}
.stat-content {
  display: flex;
  align-items: center;
  gap: 16px;
}
.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
}
.bg-blue { background: linear-gradient(135deg, #409eff, #337ecc); }
.bg-green { background: linear-gradient(135deg, #67c23a, #529b2e); }
.bg-orange { background: linear-gradient(135deg, #e6a23c, #b88230); }
.bg-purple { background: linear-gradient(135deg, #909399, #606266); }
.stat-info {
  display: flex;
  flex-direction: column;
}
.stat-value {
  font-size: 28px;
  font-weight: 700;
  line-height: 1.2;
}
.stat-label {
  font-size: 13px;
  color: #909399;
}
.tab-toolbar {
  margin-bottom: 16px;
}
.metrics-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.text-muted {
  color: #909399;
  font-size: 12px;
}
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-1 { margin-top: 4px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.ml-2 { margin-left: 8px; }
.flex-center {
  display: flex;
  justify-content: center;
}
</style>
