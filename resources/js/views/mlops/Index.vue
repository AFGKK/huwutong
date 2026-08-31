<template>
  <div class="mlops-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Cpu /></el-icon>
        {{ t('mlops_page.title') }}
      </h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('mlops_page.refresh') }}
        </el-button>
      </div>
    </div>

    <!-- 指标卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_models }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.total_models') }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active_models }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.active_models') }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_versions }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.total_versions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-primary">{{ stats.production_versions }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.production_versions') }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.drift_summary?.critical_events || 0 }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.critical_drift') }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.drift_summary?.auto_retrain_triggered || 0 }}</div>
          <div class="stat-label">{{ t('mlops_page.stats.auto_retrain') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 框架分布 + 漂移概览 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>{{ t('mlops_page.sections.framework_distribution') }}</span></template>
          <div ref="frameworkChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>{{ t('mlops_page.sections.drift_trend') }}</span></template>
          <div ref="driftChartRef" style="height:200px"></div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>{{ t('mlops_page.sections.recent_training') }}</span></template>
          <div v-if="stats.recent_training?.length" class="recent-list">
            <div v-for="job in stats.recent_training" :key="job.id" class="recent-item">
              <el-tag :type="jobStatusType(job.status)" size="small">{{ jobStatusLabel(job.status) }}</el-tag>
              <span class="recent-text">{{ job.job_id }}</span>
              <span class="recent-time">{{ formatTime(job.created_at) }}</span>
            </div>
          </div>
          <el-empty v-else :description="t('mlops_page.empty.no_training')" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- 模型管理 -->
        <el-tab-pane :label="t('mlops_page.tabs.models')" name="models">
          <div class="tab-toolbar">
            <el-select v-model="modelFilter.framework" :placeholder="t('mlops_page.filters.framework')" clearable style="width:130px;margin-right:8px" @change="loadModels">
              <el-option :label="t('mlops_page.filters.all')" value="" />
              <el-option v-for="f in frameworks" :key="f" :label="f" :value="f" />
            </el-select>
            <el-select v-model="modelFilter.status" :placeholder="t('mlops_page.filters.status')" clearable style="width:120px;margin-right:8px" @change="loadModels">
              <el-option :label="t('mlops_page.filters.all')" value="" />
              <el-option v-for="opt in modelStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-input v-model="modelFilter.search" :placeholder="t('mlops_page.filters.search_models')" clearable style="width:200px" @clear="loadModels" @keyup.enter="loadModels" />
            <el-button type="primary" style="margin-left:auto" @click="showCreateModel">
              <el-icon><Plus /></el-icon> {{ t('mlops_page.btn.create_model') }}
            </el-button>
          </div>
          <el-table :data="models" stripe v-loading="modelsLoading" @row-click="showModelDetail">
            <el-table-column :label="t('mlops_page.cols.model_name')" min-width="160">
              <template #default="{ row }">
                <div>
                  <el-tag v-if="row.status === 'archived'" size="small" type="info" style="margin-right:4px">{{ t('mlops_page.status.archived') }}</el-tag>
                  <el-tag v-if="row.status === 'deprecated'" size="small" type="warning" style="margin-right:4px">{{ t('mlops_page.status.deprecated') }}</el-tag>
                  <span style="font-weight:600">{{ row.name }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.model_key')" prop="model_key" width="140" />
            <el-table-column :label="t('mlops_page.cols.framework')" width="100">
              <template #default="{ row }">
                <el-tag>{{ row.framework }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.task_type')" width="120">
              <template #default="{ row }">{{ taskTypeLabel(row.task_type) }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.version_count')" prop="versions_count" width="80" align="center" />
            <el-table-column :label="t('mlops_page.cols.created_at')" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.actions')" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click.stop="showModelDetail(row)">{{ t('mlops_page.btn.detail') }}</el-button>
                <el-button size="small" type="primary" @click.stop="showSubmitTraining(row)">{{ t('mlops_page.btn.train') }}</el-button>
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
        <el-tab-pane :label="t('mlops_page.tabs.training')" name="training">
          <div class="tab-toolbar">
            <el-select v-model="trainingFilter.status" :placeholder="t('mlops_page.filters.status')" clearable style="width:130px;margin-right:8px" @change="loadTrainingJobs">
              <el-option :label="t('mlops_page.filters.all')" value="" />
              <el-option v-for="opt in trainingStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
          <el-table :data="trainingJobs" stripe v-loading="trainingLoading">
            <el-table-column :label="t('mlops_page.cols.job_id')" prop="job_id" width="200" />
            <el-table-column :label="t('mlops_page.cols.model')" min-width="140">
              <template #default="{ row }">{{ row.model?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.filters.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="jobStatusType(row.status)">{{ jobStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.duration')" width="100">
              <template #default="{ row }">{{ row.duration_seconds ? `${row.duration_seconds}s` : '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.started_at')" width="160">
              <template #default="{ row }">{{ row.started_at ? formatTime(row.started_at) : '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.completed_at')" width="160">
              <template #default="{ row }">{{ row.completed_at ? formatTime(row.completed_at) : '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.error_message')" min-width="200">
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
        <el-tab-pane :label="t('mlops_page.tabs.drift')" name="drift">
          <div class="tab-toolbar">
            <el-select v-model="driftFilter.severity" :placeholder="t('mlops_page.filters.severity')" clearable style="width:140px;margin-right:8px" @change="loadDriftEvents">
              <el-option :label="t('mlops_page.filters.all')" value="" />
              <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="driftFilter.metric" :placeholder="t('mlops_page.filters.metric')" clearable style="width:120px;margin-right:8px" @change="loadDriftEvents">
              <el-option :label="t('mlops_page.filters.all')" value="" />
              <el-option label="Accuracy" value="accuracy" />
              <el-option label="Precision" value="precision" />
              <el-option label="Recall" value="recall" />
              <el-option label="F1" value="f1" />
            </el-select>
            <el-button type="warning" @click="showManualDriftCheck">
              <el-icon><DataAnalysis /></el-icon> {{ t('mlops_page.btn.manual_drift_check') }}
            </el-button>
          </div>

          <!-- 漂移摘要 -->
          <el-row :gutter="16" class="mb-4" v-if="driftSummary">
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value">{{ driftSummary.total_events }}</div>
                <div class="stat-label">{{ t('mlops_page.stats.total_events') }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value stat-danger">{{ driftSummary.critical_events }}</div>
                <div class="stat-label">{{ t('mlops_page.stats.critical_events') }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value stat-warning">{{ driftSummary.warning_events }}</div>
                <div class="stat-label">{{ t('mlops_page.stats.warning_events') }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover">
                <div class="stat-value">{{ driftSummary.auto_retrain_triggered }}</div>
                <div class="stat-label">{{ t('mlops_page.stats.retrain_triggered') }}</div>
              </el-card>
            </el-col>
          </el-row>

          <el-table :data="driftEvents" stripe v-loading="driftLoading">
            <el-table-column :label="t('mlops_page.cols.model')" min-width="140">
              <template #default="{ row }">{{ row.model_version?.model?.name || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.version')" width="100">
              <template #default="{ row }">{{ row.model_version?.version || '—' }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.metric')" prop="metric" width="100" />
            <el-table-column :label="t('mlops_page.cols.baseline_value')" width="100">
              <template #default="{ row }">{{ row.baseline_value?.toFixed(4) }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.current_value')" width="100">
              <template #default="{ row }">{{ row.current_value?.toFixed(4) }}</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.drift_magnitude')" width="100">
              <template #default="{ row }">{{ (row.drift_value * 100).toFixed(2) }}%</template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.severity')" width="100">
              <template #default="{ row }">
                <el-tag :type="severityType(row.severity)">{{ severityLabel(row.severity) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.auto_retrain')" width="100" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.auto_retrain_triggered" type="success" effect="dark">{{ t('mlops_page.yes') }}</el-tag>
                <span v-else>—</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('mlops_page.cols.detected_at')" width="160">
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
    <el-dialog v-model="createModelVisible" :title="t('mlops_page.dialogs.create_model')" width="560px">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item :label="t('mlops_page.form.name')" prop="name">
          <el-input v-model="createForm.name" :placeholder="t('mlops_page.form.model_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.model_key')" prop="model_key">
          <el-input v-model="createForm.model_key" :placeholder="t('mlops_page.form.auto_key_ph')" />
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.framework')" prop="framework">
          <el-select v-model="createForm.framework" style="width:100%">
            <el-option v-for="f in frameworks" :key="f" :label="f" :value="f" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.task_type')" prop="task_type">
          <el-select v-model="createForm.task_type" style="width:100%">
            <el-option v-for="opt in taskTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.description')" prop="description">
          <el-input v-model="createForm.description" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createModelVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleCreateModel" :loading="submitting">{{ t('mlops_page.btn.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 模型详情对话框 -->
    <el-dialog v-model="detailVisible" :title="detailModel?.name || t('mlops_page.dialogs.model_detail')" width="800px" top="5vh">
      <template v-if="detailModel">
        <el-descriptions :column="2" border>
          <el-descriptions-item :label="t('mlops_page.form.name')">{{ detailModel.name }}</el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.form.model_key')">{{ detailModel.model_key }}</el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.form.framework')">
            <el-tag>{{ detailModel.framework }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.form.task_type')">{{ taskTypeLabel(detailModel.task_type) }}</el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.filters.status')">{{ modelStatusLabel(detailModel.status) }}</el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.cols.version_count')">{{ detailModel.version_count || 0 }}</el-descriptions-item>
          <el-descriptions-item :label="t('mlops_page.form.description')" :span="2">{{ detailModel.description || '—' }}</el-descriptions-item>
        </el-descriptions>

        <el-divider />
        <h4>{{ t('mlops_page.detail.version_list') }}</h4>
        <el-table :data="detailVersions" stripe v-loading="detailVersionsLoading" size="small">
          <el-table-column :label="t('mlops_page.cols.version_number')" prop="version" width="100" />
          <el-table-column :label="t('mlops_page.filters.status')" width="90">
            <template #default="{ row }">
              <el-tag :type="versionStatusType(row.status)" size="small">{{ versionStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('mlops_page.cols.file_size')" width="100">
            <template #default="{ row }">{{ formatFileSize(row.file_size) }}</template>
          </el-table-column>
          <el-table-column :label="t('mlops_page.cols.metrics')" min-width="200">
            <template #default="{ row }">
              <span v-if="row.metrics">
                A:{{ row.metrics.accuracy?.toFixed(3) }} P:{{ row.metrics.precision?.toFixed(3) }}
                R:{{ row.metrics.recall?.toFixed(3) }} F1:{{ row.metrics.f1?.toFixed(3) }}
              </span>
              <span v-else>—</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('mlops_page.cols.deployed_at')" width="150">
            <template #default="{ row }">{{ row.deployed_at ? formatTime(row.deployed_at) : '—' }}</template>
          </el-table-column>
          <el-table-column :label="t('mlops_page.cols.actions')" width="140" fixed="right">
            <template #default="{ row }">
              <el-button v-if="row.status !== 'production'" size="small" type="success" @click="handleDeploy(row)">{{ t('mlops_page.btn.deploy') }}</el-button>
              <el-tag v-else type="success" size="small">{{ t('mlops_page.detail.in_production') }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>

    <!-- 提交训练对话框 -->
    <el-dialog v-model="trainVisible" :title="t('mlops_page.dialogs.submit_training')" width="480px">
      <el-form :model="trainForm" label-width="120px">
        <el-form-item :label="t('mlops_page.form.model')">
          <strong>{{ trainModel?.name }}</strong>
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.epochs')">
          <el-input-number v-model="trainForm.epochs" :min="1" :max="500" :step="10" />
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.batch_size')">
          <el-input-number v-model="trainForm.batch_size" :min="1" :max="256" :step="8" />
        </el-form-item>
        <el-form-item :label="t('mlops_page.form.early_stopping_patience')">
          <el-input-number v-model="trainForm.early_stopping_patience" :min="0" :max="100" :step="5" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="trainVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSubmitTraining" :loading="submitting">{{ t('mlops_page.btn.submit_training') }}</el-button>
      </template>
    </el-dialog>

    <!-- 手动漂移检测对话框 -->
    <el-dialog v-model="driftCheckVisible" :title="t('mlops_page.dialogs.manual_drift_check')" width="480px">
      <el-form :model="driftCheckForm" label-width="100px">
        <el-form-item :label="t('mlops_page.form.select_model')" prop="model_id">
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
        <el-button @click="driftCheckVisible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" @click="handleDriftCheck" :loading="submitting">{{ t('mlops_page.btn.run_check') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Cpu, Refresh, Plus, DataAnalysis } from '@element-plus/icons-vue';
import mlopsApi from '@/api/mlops';
import * as echarts from 'echarts';

const { t, locale } = useI18n();

// 状态变量
const loading = ref(false);
const submitting = ref(false);
const activeTab = ref('models');
const frameworks = ['tensorflow', 'pytorch', 'onnx', 'sklearn', 'xgboost'];

const modelStatusOptions = computed(() => [
  { value: 'active', label: t('mlops_page.status.active') },
  { value: 'archived', label: t('mlops_page.status.archived') },
  { value: 'deprecated', label: t('mlops_page.status.deprecated') },
]);

const trainingStatusOptions = computed(() => [
  { value: 'pending', label: t('mlops_page.status.pending') },
  { value: 'running', label: t('mlops_page.status.running') },
  { value: 'completed', label: t('mlops_page.status.completed') },
  { value: 'failed', label: t('mlops_page.status.failed') },
]);

const severityOptions = computed(() => [
  { value: 'critical', label: t('mlops_page.severity.critical') },
  { value: 'warning', label: t('mlops_page.severity.warning') },
  { value: 'info', label: t('mlops_page.severity.info') },
]);

const taskTypeOptions = computed(() => [
  { value: 'classification', label: t('mlops_page.task_type.classification') },
  { value: 'regression', label: t('mlops_page.task_type.regression') },
  { value: 'anomaly_detection', label: t('mlops_page.task_type.anomaly_detection') },
  { value: 'recommendation', label: t('mlops_page.task_type.recommendation') },
  { value: 'nlp', label: t('mlops_page.task_type.nlp') },
  { value: 'time_series', label: t('mlops_page.task_type.time_series') },
]);

const jobStatusLabels = computed(() => ({
  pending: t('mlops_page.status.pending'),
  running: t('mlops_page.status.running'),
  completed: t('mlops_page.status.completed'),
  failed: t('mlops_page.status.failed'),
}));

const modelStatusLabels = computed(() => ({
  active: t('mlops_page.status.active'),
  archived: t('mlops_page.status.archived'),
  deprecated: t('mlops_page.status.deprecated'),
}));

const versionStatusLabels = computed(() => ({
  staging: t('mlops_page.status.staging'),
  production: t('mlops_page.status.production'),
  archived: t('mlops_page.status.archived'),
  failed: t('mlops_page.status.failed'),
}));

const severityLabels = computed(() => ({
  critical: t('mlops_page.severity.critical'),
  warning: t('mlops_page.severity.warning'),
  info: t('mlops_page.severity.info'),
}));

const taskTypeLabels = computed(() => ({
  classification: t('mlops_page.task_type.classification'),
  regression: t('mlops_page.task_type.regression'),
  anomaly_detection: t('mlops_page.task_type.anomaly_detection'),
  recommendation: t('mlops_page.task_type.recommendation'),
  nlp: t('mlops_page.task_type.nlp'),
  time_series: t('mlops_page.task_type.time_series'),
}));

const createRules = computed(() => ({
  name: [{ required: true, message: t('mlops_page.validation.name_required') }],
  framework: [{ required: true, message: t('mlops_page.validation.framework_required') }],
  task_type: [{ required: true, message: t('mlops_page.validation.task_type_required') }],
}));

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
    const severityKeys = Object.keys(summary.by_severity || {});
    chart.setOption({
      tooltip: { trigger: 'axis' },
      xAxis: { type: 'category', data: severityKeys.map(k => severityLabels.value[k] || k) },
      yAxis: { type: 'value' },
      series: [{
        type: 'bar',
        data: Object.values(summary.by_severity || {}),
        itemStyle: {
          color: (params) => {
            const map = { critical: '#F56C6C', warning: '#E6A23C', info: '#909399' };
            const key = severityKeys[params.dataIndex];
            return map[key] || '#0f172a';
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
    ElMessage.success(t('mlops_page.messages.model_created'));
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
    await ElMessageBox.confirm(
      t('mlops_page.messages.deploy_confirm', { version: version.version }),
      t('mlops_page.dialogs.confirm_deploy'),
    );
    await mlopsApi.deployVersion(detailModel.value.id, version.id);
    ElMessage.success(t('mlops_page.messages.deploy_success'));
    detailVisible.value = false;
    loadModels();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('mlops_page.messages.deploy_failed'));
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
    ElMessage.success(t('mlops_page.messages.training_submitted'));
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
    ElMessage.warning(t('mlops_page.messages.select_model_required'));
    return;
  }
  submitting.value = true;
  try {
    const res = await mlopsApi.detectDrift(driftCheckForm.model_id, driftCheckForm.metrics);
    ElMessage.success(res.message || t('mlops_page.messages.drift_check_done'));
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

function jobStatusLabel(status) {
  return jobStatusLabels.value[status] || status;
}

function versionStatusType(status) {
  const map = { staging: 'info', production: 'success', archived: 'info', failed: 'danger' };
  return map[status] || 'info';
}

function versionStatusLabel(status) {
  return versionStatusLabels.value[status] || status;
}

function modelStatusLabel(status) {
  return modelStatusLabels.value[status] || status;
}

function severityType(severity) {
  const map = { critical: 'danger', warning: 'warning', info: 'info' };
  return map[severity] || 'info';
}

function severityLabel(severity) {
  return severityLabels.value[severity] || severity;
}

function taskTypeLabel(taskType) {
  return taskTypeLabels.value[taskType] || taskType;
}

function formatTime(ts) {
  if (!ts) return '—';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(ts).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
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
.stat-primary { color: #0f172a; }
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
