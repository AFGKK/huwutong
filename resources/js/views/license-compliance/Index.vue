<template>
  <div class="compliance-report-page">
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">📋 License 合规审计报告</h1>
      <p class="text-sm text-gray-500 mt-1">面向客户的合规报告，支持 Excel/CSV 下载，用于企业内部审计</p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <el-skeleton :rows="5" animated />
    </div>

    <template v-else>
      <!-- 统计 -->
      <el-row :gutter="20" class="mb-6">
        <el-col :span="6" v-for="s in statCards" :key="s.label">
          <el-card shadow="never" class="text-center">
            <div class="text-2xl font-bold" :style="{ color: s.color }">{{ s.value }}</div>
            <div class="text-sm text-gray-500 mt-1">{{ s.label }}</div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 操作栏 -->
      <el-card shadow="never" class="mb-6">
        <div class="flex justify-between items-center">
          <div class="flex gap-3">
            <el-select v-model="filter.type" placeholder="报告类型" clearable size="small" style="width:160px" @change="fetchReports">
              <el-option label="全部" value="" />
              <el-option v-for="(label, key) in typeLabels" :key="key" :label="label" :value="key" />
            </el-select>
            <el-select v-model="filter.status" placeholder="状态" clearable size="small" style="width:120px" @change="fetchReports">
              <el-option label="全部" value="" />
              <el-option label="已完成" value="completed" />
              <el-option label="生成中" value="generating" />
              <el-option label="失败" value="failed" />
            </el-select>
          </div>
          <el-button type="primary" size="small" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon> 生成新报告
          </el-button>
        </div>
      </el-card>

      <!-- 报告列表 -->
      <el-card shadow="never">
        <el-table :data="reports" v-loading="loading" stripe size="small">
          <el-table-column prop="title" label="报告名称" min-width="200" />
          <el-table-column label="类型" width="160">
            <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
          </el-table-column>
          <el-table-column label="格式" width="80">
            <template #default="{ row }">
              <el-tag size="small">{{ row.format?.toUpperCase() }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">
                {{ row.status }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="file_size" label="大小" width="100">
            <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
          </el-table-column>
          <el-table-column prop="generated_at" label="生成时间" width="160" />
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status === 'completed'" text size="small" type="primary" @click="downloadReport(row)">
                下载
              </el-button>
              <el-button v-else-if="row.status === 'generating'" text size="small" disabled>
                生成中...
              </el-button>
              <el-button text size="small" @click="viewReport(row)">详情</el-button>
              <el-popconfirm title="删除此报告？" @confirm="deleteReport(row)">
                <template #reference>
                  <el-button text size="small" type="danger">删除</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <div class="mt-4 flex justify-center" v-if="pagination.total > pagination.per_page">
          <el-pagination v-model:current-page="pagination.current_page" :page-size="pagination.per_page" :total="pagination.total" layout="prev, pager, next" @current-change="fetchReports" />
        </div>

        <el-empty v-if="reports.length === 0" description="暂无合规报告" />
      </el-card>
    </template>

    <!-- 生成报告对话框 -->
    <el-dialog v-model="showCreateDialog" title="生成合规审计报告" width="540px">
      <el-form :model="createForm" label-position="top" size="small">
        <el-form-item label="报告类型" required>
          <el-select v-model="createForm.type" style="width:100%">
            <el-option v-for="(label, key) in typeLabels" :key="key" :label="label" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="格式">
          <el-radio-group v-model="createForm.format">
            <el-radio value="xlsx">Excel (.xlsx)</el-radio>
            <el-radio value="csv">CSV (.csv)</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="客户（可选）">
          <el-select v-model="createForm.customer_id" filterable clearable placeholder="不指定则包含全部" style="width:100%">
            <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="起始日期">
              <el-date-picker v-model="createForm.report_period_start" type="date" placeholder="可选" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束日期">
              <el-date-picker v-model="createForm.report_period_end" type="date" placeholder="可选" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="License 状态筛选">
          <el-select v-model="createForm.filters.status" clearable placeholder="全部" style="width:100%">
            <el-option label="全部" value="" />
            <el-option label="活跃" value="active" />
            <el-option label="已过期" value="expired" />
            <el-option label="已吊销" value="revoked" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="generateReport" :loading="generating">生成报告</el-button>
      </template>
    </el-dialog>

    <!-- 报告详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="报告详情" width="600px">
      <template v-if="detail">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="报告名称" :span="2">{{ detail.title }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ typeLabels[detail.type] }}</el-descriptions-item>
          <el-descriptions-item label="格式">{{ detail.format?.toUpperCase() }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="detail.status === 'completed' ? 'success' : 'danger'" size="small">{{ detail.status }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="文件大小">{{ formatSize(detail.file_size) }}</el-descriptions-item>
          <el-descriptions-item label="生成时间">{{ detail.generated_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="下载时间">{{ detail.downloaded_at || '尚未下载' }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="detail.summary_data" class="mt-4">
          <h4 class="text-sm font-semibold text-gray-700 mb-2">摘要数据</h4>
          <el-table :data="summaryRows" size="small" stripe>
            <el-table-column prop="label" label="指标" />
            <el-table-column prop="value" label="数值" />
          </el-table>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getComplianceReports,
  getComplianceReport,
  createComplianceReport,
  deleteComplianceReport,
  getComplianceReportStats,
  getComplianceReportDownloadUrl,
} from '@/api/licenseCompliance';

const loading = ref(true);
const generating = ref(false);
const showCreateDialog = ref(false);
const showDetailDialog = ref(false);
const reports = ref([]);
const detail = ref(null);
const customers = ref([]);

const typeLabels = {
  full_inventory: '📦 完整 License 清单',
  activation_audit: '🔍 激活使用审计',
  compliance_summary: '✅ 合规摘要报告',
  custom: '⚙️ 自定义报告',
};

const filter = reactive({ type: '', status: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const createForm = reactive({
  type: 'compliance_summary',
  format: 'xlsx',
  customer_id: null,
  report_period_start: null,
  report_period_end: null,
  filters: { status: '', product_id: null },
});

const statCards = ref([
  { label: '报告总数', value: 0, color: '#409eff' },
  { label: '已完成', value: 0, color: '#67c23a' },
  { label: '生成中', value: 0, color: '#e6a23c' },
  { label: '失败', value: 0, color: '#f56c6c' },
]);

const summaryRows = computed(() => {
  if (!detail.value?.summary_data) return [];
  const m = {
    total_licenses: 'License 总数',
    active_licenses: '活跃 License',
    expired_licenses: '已过期 License',
    total_activations: '激活记录总数',
    compliant_licenses: '合规 License',
    overused_licenses: '超额使用 License',
  };
  return Object.entries(m).map(([k, label]) => ({
    label,
    value: detail.value.summary_data[k] ?? '-',
  }));
});

function formatSize(bytes) {
  if (!bytes) return '-';
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

async function loadData() {
  loading.value = true;
  try {
    const [r, s] = await Promise.all([
      getComplianceReports(),
      getComplianceReportStats(),
    ]);
    if (r.data?.success) {
      reports.value = r.data.data.data || [];
      pagination.current_page = r.data.data.current_page;
      pagination.total = r.data.data.total;
    }
    if (s.data?.success) {
      statCards.value = [
        { label: '报告总数', value: s.data.data.total_reports, color: '#409eff' },
        { label: '已完成', value: s.data.data.completed, color: '#67c23a' },
        { label: '生成中', value: s.data.data.pending, color: '#e6a23c' },
        { label: '失败', value: s.data.data.failed, color: '#f56c6c' },
      ];
    }
  } catch (e) {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

async function fetchReports(page) {
  loading.value = true;
  try {
    const res = await getComplianceReports({ ...filter, page: page || pagination.current_page });
    if (res.data?.success) {
      reports.value = res.data.data.data || [];
      pagination.current_page = res.data.data.current_page;
      pagination.total = res.data.data.total;
    }
  } finally {
    loading.value = false;
  }
}

async function generateReport() {
  generating.value = true;
  try {
    await createComplianceReport(createForm);
    ElMessage.success('报告正在生成中');
    showCreateDialog.value = false;
    createForm.type = 'compliance_summary';
    createForm.format = 'xlsx';
    createForm.customer_id = null;
    createForm.report_period_start = null;
    createForm.report_period_end = null;
    createForm.filters = { status: '', product_id: null };
    await loadData();
  } catch (e) {
    ElMessage.error('生成失败');
  } finally {
    generating.value = false;
  }
}

function downloadReport(row) {
  const token = localStorage.getItem('auth_token');
  const url = getComplianceReportDownloadUrl(row.id);
  // 使用 fetch 添加认证头
  fetch(url, { headers: { Authorization: `Bearer ${token}` } })
    .then(res => {
      if (!res.ok) throw new Error('Download failed');
      return res.blob();
    })
    .then(blob => {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = row.file_name || `report.${row.format}`;
      a.click();
      URL.revokeObjectURL(a.href);
    })
    .catch(() => ElMessage.error('下载失败'));
}

async function viewReport(row) {
  try {
    const res = await getComplianceReport(row.id);
    if (res.data?.success) {
      detail.value = res.data.data;
      showDetailDialog.value = true;
    }
  } catch (e) {
    ElMessage.error('加载详情失败');
  }
}

async function deleteReport(row) {
  try {
    await deleteComplianceReport(row.id);
    ElMessage.success('删除成功');
    await loadData();
  } catch (e) {
    ElMessage.error('删除失败');
  }
}

onMounted(loadData);
</script>

<style scoped>
.compliance-report-page { padding: 24px; }
</style>
