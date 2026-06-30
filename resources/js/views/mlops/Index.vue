<template>
  <div class="mlops-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Cpu /></el-icon>
        AI MLOps 平台
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_models }}</div>
          <div class="stat-label">模型总数</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active_models }}</div>
          <div class="stat-label">活跃模型</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_versions }}</div>
          <div class="stat-label">版本总数</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.production_versions }}</div>
          <div class="stat-label">生产版本</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.drift_summary?.critical_events || 0 }}</div>
          <div class="stat-label">严重漂移</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.drift_summary?.auto_retrain_triggered || 0 }}</div>
          <div class="stat-label">自动重训练</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 框架分布 + 漂移概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>模型框架分布</span></template>
          <div ref="frameworkChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>漂移趋势</span></template>
          <div ref="driftChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>最近训练任务</span></template>
          <div v-if="stats.recent_training?.length" class="recent-list">
            <div v-for="job in stats.recent_training" :key="job.id" class="recent-item">
              <el-tag :type="jobStatusType(job.status)" size="small">{{ job.status }}</el-tag>
              <span class="recent-text">{{ job.job_id }}</span>
              <span class="recent-time">{{ formatTime(job.created_at) }}</span>
            </div>
          </div>
          <el-empty v-else description="暂无训练记录" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 模型管理 -->
        <el-tab-pane label="模型管理" name="models">
          <div class="tab-toolbar">
            <el-select v-model="modelFilter.framework" placeholder="框架" clearable style="width:130px;margin-right:8px" @change="loadModels">
              <el-option label="全部" value="" />
              <el-option v-for="f in frameworks" :key="f" :label="f" :value="f" />
            </el-select>
            <el-select v-model="modelFilter.status" placeholder="状态" clearable style="width:120px;margin-right:8px" @change="loadModels">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="已归档" value="archived" />
              <el-option label="已弃用" value="deprecated" />
            </el-select>
            <el-input v-model="modelFilter.search" placeholder="搜索模型..." clearable style="width:200px" @clear="loadModels" @keyup.enter="loadModels" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateModel">
              <el-icon><Plus /></el-icon> 新建模型
            </el-button>
          </div>
          <el-table :data="models" stripe v-loading="modelsLoading" @row-click="showModelDetail">
            <el-table-column label="模型名称" min-width="160">
              <template #default="{ row }">
                <div>
                  <el-tag v-if="row.status === 'archived'" size="small" type="info" style="margin-right:4px">已归档</el-tag>
                  <el-tag v-if="row.status === 'deprecated'" size="small" type="warning" style="margin-right:4px">已弃用</el-tag>
                  <span style="font-weight:600">{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="标识" prop="model_key" width="140" />
            <el-table-column label="框架" width="100">
              <template #default="{ row }">
                <el-tag>{{ row.framework }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="任务类型" prop="task_type" width="120" />
            <el-table-column label="版本数" prop="versions_count" width="80" align="center" />
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showModelDetail(row)">详情</el-button>
                <el-button size="small" type="primary" @click.stop="showSubmitTraining(row)">训练</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="modelPagination.total > modelPagination.per_page">
            <el-pagination
              v-model:current-page="modelPagination.current_page"
              :page-size="modelPagination.per_page"
              :total="modelPagination.total"
              layout="prev, pager, next"
              @current-change="loadModels"
            />
          </div>
        </el-tab-pane>

        <!-- 训练任务 -->
        <el-tab-pane label="训练任务" name="training">
          <div class="tab-toolbar">
            <el-select v-model="trainingFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadTrainingJobs">
              <el-option label="全部" value="" />
              <el-option label="待处理" value="pending" />
              <el-option label="运行中" value="running" />
              <el-option label="已完成" value="completed" />
              <el-option label="失败" value="failed" />
            </el-select>
          </div>
          <el-table :data="trainingJobs" stripe v-loading="trainingLoading">
            <el-table-column label="任务ID" prop="job_id" width="200" />
            <el-table-column label="模型" min-width="140">
              <template #default="{ row }">{{ row.model?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="jobStatusType(row.status)">{{ row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="耗时" width="100">
              <template #default="{ row }">{{ row.duration_seconds ? `${row.duration_seconds}s` : '—' }}</template>
            </el-table-column>
            <el-table-column label="开始时间" width="160">
              <template #default="{ row }">{{ row.started_at ? formatTime(row.started_at) : '—' }}</template>
            </el-table-column>
            <el-table-column label="完成时间" width="160">
              <template #default="{ row }">{{ row.completed_at ? formatTime(row.completed_at) : '—' }}</template>
            </el-table-column>
            <el-table-column label="错误信息" min-width="200">
              <template #default="{ row }">
                <el-tooltip v-if="row.error_message" :content="row.error_message">
                  <el-tag type="danger" effect="dark">{{ row.error_message?.substring(0, 30) }}...</el-tag>
                </el-tooltip>
                <span v-else>—</span>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="trainingPagination.total > trainingPagination.per_page">
            <el-pagination
              v-model:current-page="trainingPagination.current_page"
              :page-size="trainingPagination.per_page"
              :total="trainingPagination.total"
              layout="prev, pager, next"
              @current-change="loadTrainingJobs"
            />
          </div>
        </el-tab-pane>

        <!-- 漂移监控 -->
        <el-tab-pane label="漂移监控" name="drift">
          <div class="tab-toolbar">
            <el-select v-model="driftFilter.severity" placeholder="严重程度" clearable style="width:140px;margin-right:8px" @change="loadDriftEvents">
              <el-option label="全部" value="" />
              <el-option label="严重" value="critical" />
              <el-option label="警告" value="warning" />
              <el-option label="信息" value="info" />
            </el-select>
            <el-select v-model="driftFilter.metric" placeholder="指标" clearable style="width:120px;margin-right:8px" @change="loadDriftEvents">
              <el-option label="全部" value="" />
              <el-option label="Accuracy" value="accuracy" />
              <el-option label="Precision" value="precision" />
              <el-option label="Recall" value="recall" />
              <el-option label="F1" value="f1" />
            </el-select>
            <el-button type="warning" @click="showManualDriftCheck">
              <el-icon><DataAnalysis /></el-icon> 手动漂移检测
            </el-button>
          </div>

          <!-- 漂移摘要 -->
          <el-row :gutter="16" class="mb-4" v-if="driftSummary">
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value">{{ driftSummary.total_events }}</div>
                <div class="stat-label">总事件数</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value stat-danger">{{ driftSummary.critical_events }}</div>
                <div class="stat-label">严重事件</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value stat-warning">{{ driftSummary.warning_events }}</div>
                <div class="stat-label">警告事件</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value">{{ driftSummary.auto_retrain_triggered }}</div>
                <div class="stat-label">触发重训练</div>
              </el-card>
            </el-col>
          </el-row>

          <el-table :data="driftEvents" stripe v-loading="driftLoading">
            <el-table-column label="模型" min-width="140">
              <template #default="{ row }">{{ row.model_version?.model?.name || '—' }}</template>
            </el-table-column>
            <el-table-column label="版本" width="100">
              <template #default="{ row }">{{ row.model_version?.version || '—' }}</template>
            </el-table-column>
            <el-table-column label="指标" prop="metric" width="100" />
            <el-table-column label="基线值" width="100">
              <template #default="{ row }">{{ row.baseline_value?.toFixed(4) }}</template>
            </el-table-column>
            <el-table-column label="当前值" width="100">
              <template #default="{ row }">{{ row.current_value?.toFixed(4) }}</template>
            </el-table-column>
            <el-table-column label="漂移幅度" width="100">
              <template #default="{ row }">{{ (row.drift_value * 100).toFixed(2) }}%</template>
            </el-table-column>
            <el-table-column label="严重程度" width="100">
              <template #default="{ row }">
                <el-tag :type="severityType(row.severity)">{{ row.severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="自动重训练" width="100" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.auto_retrain_triggered" type="success" effect="dark">是</el-tag>
                <span v-else>—</span>
              </template>
            </el-table-column>
            <el-table-column label="检测时间" width="160">
              <template #default="{ row }">{{ formatTime(row.detected_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="driftPagination.total > driftPagination.per_page">
            <el-pagination
              v-model:current-page="driftPagination.current_page"
              :page-size="driftPagination.per_page"
              :total="driftPagination.total"
              layout="prev, pager, next"
              @current-change="loadDriftEvents"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建模型对话框 -->
    <el-dialog v-model="createModelVisible" title="新建模型" width="560px">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="createForm.name" placeholder="模型名称" />
        </el-form-item>
        <el-form-item label="标识" prop="model_key">
          <el-input v-model="createForm.model_key" placeholder="留空自动生成" />
        </el-form-item>
        <el-form-item label="框架" prop="framework">
          <el-select v-model="createForm.framework" style="width:100%">
            <el-option v-for="f in frameworks" :key="f" :label="f" :value="f" />
          </el-select>
        </el-form-item>
        <el-form-item label="任务类型" prop="task_type">
          <el-select v-model="createForm.task_type" style="width:100%">
            <el-option label="分类" value="classification" />
            <el-option label="回归" value="regression" />
            <el-option label="异常检测" value="anomaly_detection" />
            <el-option label="推荐" value="recommendation" />
            <el-option label="NLP" value="nlp" />
            <el-option label="时序预测" value="time_series" />
          </el-select>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="createForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createModelVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreateModel" :loading="submitting">创建</el-button>
      </template>
    </el-dialog>

    <!-- 模型详情对话框 -->
    <el-dialog v-model="detailVisible" :title="detailModel?.name || '模型详情'" width="800px" top="5vh">
      <template v-if="detailModel">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="名称">{{ detailModel.name }}</el-descriptions-item>
          <el-descriptions-item label="标识">{{ detailModel.model_key }}</el-descriptions-item>
          <el-descriptions-item label="框架">
            <el-tag>{{ detailModel.framework }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="任务类型">{{ detailModel.task_type }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ detailModel.status }}</el-descriptions-item>
          <el-descriptions-item label="版本数">{{ detailModel.version_count || 0 }}</el-descriptions-item>
          <el-descriptions-item label="描述" :span="2">{{ detailModel.description || '—' }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4>版本列表</h4>
        <el-table :data="detailVersions" stripe v-loading="detailVersionsLoading" size="small">
          <el-table-column label="版本号" prop="version" width="100" />
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="versionStatusType(row.status)" size="small">{{ row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="文件大小" width="100">
            <template #default="{ row }">{{ formatFileSize(row.file_size) }}</template>
          </el-table-column>
          <el-table-column label="评估指标" min-width="200">
            <template #default="{ row }">
              <span v-if="row.metrics">
                A:{{ row.metrics.accuracy?.toFixed(3) }} P:{{ row.metrics.precision?.toFixed(3) }}
                R:{{ row.metrics.recall?.toFixed(3) }} F1:{{ row.metrics.f1?.toFixed(3) }}
              </span>
              <span v-else>—</span>
            </template>
          </el-table-column>
          <el-table-column label="部署时间" width="150">
            <template #default="{ row }">{{ row.deployed_at ? formatTime(row.deployed_at) : '—' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="140" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status !== 'production'" size="small" type="success" @click="handleDeploy(row)">部署</el-button>
              <el-tag v-else type="success" size="small">生产中</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>

    <!-- 提交训练对话框 -->
    <el-dialog v-model="trainVisible" title="提交训练任务" width="480px">
      <el-form :model="trainForm" label-width="120px">
        <el-form-item label="模型">
          <strong>{{ trainModel?.name }}</strong>
        </el-form-item>
        <el-form-item label="迭代次数">
          <el-input-number v-model="trainForm.epochs" :min="1" :max="500" :step="10" />
        </el-form-item>
        <el-form-item label="批次大小">
          <el-input-number v-model="trainForm.batch_size" :min="1" :max="256" :step="8" />
        </el-form-item>
        <el-form-item label="早停耐心值">
          <el-input-number v-model="trainForm.early_stopping_patience" :min="0" :max="100" :step="5" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="trainVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitTraining" :loading="submitting">提交训练</el-button>
      </template>
    </el-dialog>

    <!-- 手动漂移检测对话框 -->
    <el-dialog v-model="driftCheckVisible" title="手动漂移检测" width="480px">
      <el-form :model="driftCheckForm" label-width="100px">
        <el-form-item label="选择模型" prop="model_id">
          <el-select v-model="driftCheckForm.model_id" style="width:100%" filterable @change="onDriftModelChange">
            <el-option v-for="m in models" :key="m.id" :label="m.name" :value="m.id" />
          </el-select>
        </el-form-item>
        <template v-if="driftCheckForm.model_id">
          <el-form-item label="Accuracy">
            <el-input-number v-model="driftCheckForm.metrics.accuracy" :min="0" :max="1" :step="0.01" :precision="4" />
          </el-form-item>
          <el-form-item label="Precision">
            <el-input-number v-model="driftCheckForm.metrics.precision" :min="0" :max="1" :step="0.01" :precision="4" />
          </el-form-item>
          <el-form-item label="Recall">
            <el-input-number v-model="driftCheckForm.metrics.recall" :min="0" :max="1" :step="0.01" :precision="4" />
          </el-form-item>
          <el-form-item label="F1">
            <el-input-number v-model="driftCheckForm.metrics.f1" :min="0" :max="1" :step="0.01" :precision="4" />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <el-button @click="driftCheckVisible = false">取消</el-button>
        <el-button type="warning" @click="handleDriftCheck" :loading="submitting">执行检测</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Cpu, Refresh, Plus, DataAnalysis } from '@element-plus/icons-vue';
import mlopsApi from '@/api/mlops';
import * as echarts from 'echarts';

// 状态变量
const loading = ref(false);
const submitting = ref(false);
const activeTab = ref('models');
const frameworks = ['tensorflow', 'pytorch', 'onnx', 'sklearn', 'xgboost'];

// 仪表盘数据
const stats = ref({
  total_models: 0, active_models: 0, total_versions: 0,
  production_versions: 0, drift_summary: {},
  recent_training: [], by_framework: {}, by_task_type: {},
});

// 模型列表
const models = ref([]);
const modelsLoading = ref(false);
const modelFilter = reactive({ framework: '', status: '', search: '' });
const modelPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 训练任务
const trainingJobs = ref([]);
const trainingLoading = ref(false);
const trainingFilter = reactive({ status: '' });
const trainingPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 漂移事件
const driftEvents = ref([]);
const driftSummary = ref(null);
const driftLoading = ref(false);
const driftFilter = reactive({ severity: '', metric: '' });
const driftPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 创建模型
const createModelVisible = ref(false);
const createFormRef = ref(null);
const createForm = reactive({ name: '', model_key: '', framework: 'pytorch', task_type: 'classification', description: '' });
const createRules = {
  name: [{ required: true, message: '请输入模型名称' }],
  framework: [{ required: true, message: '请选择框架' }],
  task_type: [{ required: true, message: '请选择任务类型' }],
};

// 模型详情
const detailVisible = ref(false);
const detailModel = ref(null);
const detailVersions = ref([]);
const detailVersionsLoading = ref(false);

// 训练
const trainVisible = ref(false);
const trainModel = ref(null);
const trainForm = reactive({ epochs: 100, batch_size: 32, early_stopping_patience: 10 });

// 漂移检测
const driftCheckVisible = ref(false);
const driftCheckForm = reactive({ model_id: null, metrics: { accuracy: 0.85, precision: 0.82, recall: 0.80, f1: 0.83 } });

// ECharts refs
const frameworkChartRef = ref(null);
const driftChartRef = ref(null);

onMounted(() => {
  refreshAll();
});

async function refreshAll() {
  loading.value = true;
  try {
    const res = await mlopsApi.dashboard();
    stats.value = res.data;
    await nextTick();
    renderCharts();
  } finally {
    loading.value = false;
  }
  loadModels();
  loadTrainingJobs();
  loadDriftEvents();
}

// ═══════ 图表 ═══════

function renderCharts() {
  // 框架分布
  if (frameworkChartRef.value) {
    const chart = echarts.init(frameworkChartRef.value);
    chart.setOption({
      tooltip: { trigger: 'item' },
      series: [{
        type: 'pie',
        radius: ['40%', '70%'],
        data: Object.entries(stats.value.by_framework || {}).map(([name, value]) => ({ name, value })),
        label: { formatter: '{b}: {c}' },
      }],
    });
  }

  // 漂移趋势（简化版）
  if (driftChartRef.value) {
    const chart = echarts.init(driftChartRef.value);
    const summary = stats.value.drift_summary || {};
    chart.setOption({
      tooltip: { trigger: 'axis' },
      xAxis: { type: 'category', data: Object.keys(summary.by_severity || {}) },
      yAxis: { type: 'value' },
      series: [{
        type: 'bar',
        data: Object.values(summary.by_severity || {}),
        itemStyle: {
          color: (params) => {
            const map = { critical: '#F56C6C', warning: '#E6A23C', info: '#909399' };
            return map[params.name] || '#409EFF';
          },
        },
      }],
    });
  }
}

// ═══════ 模型管理 ═══════

async function loadModels() {
  modelsLoading.value = true;
  try {
    const res = await mlopsApi.listModels({ ...modelFilter, page: modelPagination.current_page });
    models.value = res.data.data || [];
    Object.assign(modelPagination, res.data);
  } finally {
    modelsLoading.value = false;
  }
}

function showCreateModel() {
  createForm.name = '';
  createForm.model_key = '';
  createForm.framework = 'pytorch';
  createForm.task_type = 'classification';
  createForm.description = '';
  createModelVisible.value = true;
}

async function handleCreateModel() {
  const valid = await createFormRef.value.validate().catch(() => false);
  if (!valid) return;

  submitting.value = true;
  try {
    await mlopsApi.createModel(createForm);
    ElMessage.success('模型创建成功');
    createModelVisible.value = false;
    loadModels();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

async function showModelDetail(row) {
  detailModel.value = row;
  detailVisible.value = true;
  detailVersionsLoading.value = true;
  try {
    const res = await mlopsApi.listVersions(row.id);
    detailVersions.value = res.data.data || [];
    // 重新加载详情以获取完整信息
    const detailRes = await mlopsApi.getModel(row.id);
    detailModel.value = detailRes.data.model || detailRes.data;
    if (detailRes.data.version_count !== undefined) {
      detailModel.value.version_count = detailRes.data.version_count;
    }
  } finally {
    detailVersionsLoading.value = false;
  }
}

async function handleDeploy(version) {
  try {
    await ElMessageBox.confirm(`确定将版本 ${version.version} 部署到生产环境？`, '确认部署');
    await mlopsApi.deployVersion(detailModel.value.id, version.id);
    ElMessage.success('部署成功');
    detailVisible.value = false;
    loadModels();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('部署失败');
  }
}

// ═══════ 训练任务 ═══════

async function loadTrainingJobs() {
  trainingLoading.value = true;
  try {
    const res = await mlopsApi.listTrainingJobs({ ...trainingFilter, page: trainingPagination.current_page });
    trainingJobs.value = res.data.data || [];
    Object.assign(trainingPagination, res.data);
  } finally {
    trainingLoading.value = false;
  }
}

function showSubmitTraining(model) {
  trainModel.value = model;
  trainForm.epochs = 100;
  trainForm.batch_size = 32;
  trainForm.early_stopping_patience = 10;
  trainVisible.value = true;
}

async function handleSubmitTraining() {
  submitting.value = true;
  try {
    await mlopsApi.submitTraining(trainModel.value.id, trainForm);
    ElMessage.success('训练任务已提交');
    trainVisible.value = false;
    loadTrainingJobs();
  } finally {
    submitting.value = false;
  }
}

// ═══════ 漂移监控 ═══════

async function loadDriftEvents() {
  driftLoading.value = true;
  try {
    const res = await mlopsApi.listDriftEvents({ ...driftFilter, page: driftPagination.current_page });
    driftEvents.value = res.data.data || [];
    Object.assign(driftPagination, res.data);

    // 加载摘要
    const summaryRes = await mlopsApi.getDriftSummary();
    driftSummary.value = summaryRes.data;
  } finally {
    driftLoading.value = false;
  }
}

function showManualDriftCheck() {
  driftCheckForm.model_id = null;
  driftCheckForm.metrics = { accuracy: 0.85, precision: 0.82, recall: 0.80, f1: 0.83 };
  driftCheckVisible.value = true;
}

function onDriftModelChange(val) {
  const model = models.value.find(m => m.id === val);
  if (model?.productionVersion?.metrics) {
    const m = model.productionVersion.metrics;
    driftCheckForm.metrics = {
      accuracy: m.accuracy ?? 0.85,
      precision: m.precision ?? 0.82,
      recall: m.recall ?? 0.80,
      f1: m.f1 ?? 0.83,
    };
  }
}

async function handleDriftCheck() {
  if (!driftCheckForm.model_id) {
    ElMessage.warning('请选择模型');
    return;
  }
  submitting.value = true;
  try {
    const res = await mlopsApi.detectDrift(driftCheckForm.model_id, driftCheckForm.metrics);
    ElMessage.success(res.message || '漂移检测完成');
    driftCheckVisible.value = false;
    loadDriftEvents();
    refreshAll();
  } finally {
    submitting.value = false;
  }
}

// ═══════ 工具函数 ═══════

function jobStatusType(status) {
  const map = { pending: 'info', running: 'warning', completed: 'success', failed: 'danger' };
  return map[status] || 'info';
}

function versionStatusType(status) {
  const map = { staging: 'info', production: 'success', archived: 'info', failed: 'danger' };
  return map[status] || 'info';
}

function severityType(severity) {
  const map = { critical: 'danger', warning: 'warning', info: 'info' };
  return map[severity] || 'info';
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function formatFileSize(bytes) {
  if (!bytes) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let size = bytes;
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
  return `${size.toFixed(1)} ${units[i]}`;
}
</script>

<style scoped>
.mlops-page {
  padding: 16px;
}
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.page-header h2 {
  margin: 0;
  font-size: 20px;
}
.mb-4 {
  margin-bottom: 16px;
}
.stat-card {
  text-align: center;
  cursor: default;
}
.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.stat-primary { color: #409EFF; }
.tab-toolbar {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
  flex-wrap: wrap;
  gap: 8px;
}
.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.recent-list {
  max-height: 200px;
  overflow-y: auto;
}
.recent-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #f0f0f0;
  font-size: 13px;
}
.recent-text {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.recent-time {
  color: #909399;
  font-size: 12px;
  white-space: nowrap;
}
.customer-cell {
  display: flex;
  flex-direction: column;
}
.customer-name {
  font-weight: 600;
}
.customer-email {
  font-size: 12px;
  color: #909399;
}
</style>
