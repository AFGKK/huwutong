<template>
  <div class="blue-green-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-tag :type="dashboard.active_environment === 'blue' ? 'primary' : 'success'" effect="dark" size="medium">
          {{ dashboard.active_environment === 'blue' ? t(`${P}.env.blue_active`) : t(`${P}.env.green_active`) }}
        </el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> {{ t('deploy_page.refresh') }}
        </el-button>
      </div>
    </div>

    <el-alert
      :title="t(`${P}.desc`)"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_deployments }}</div>
          <div class="stat-label">{{ t(`${P}.stats.total_deployments`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-value text-green">{{ dashboard.successful }}</div>
          <div class="stat-label">{{ t('deploy_page.job_status.success') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-orange">{{ dashboard.rolled_back }}</div>
          <div class="stat-label">{{ t('deploy_page.job_status.rolled_back') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="envClass(dashboard.active_environment)">{{ dashboard.active_environment }}</div>
          <div class="stat-label">{{ t(`${P}.stats.active_env`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 部署流程 -->
        <el-tab-pane :label="t(`${P}.tabs.deploy`)" name="deploy">
          <el-steps :active="currentStep" align-center class="mb-4">
            <el-step
              v-for="step in deploySteps"
              :key="step.title"
              :title="step.title"
              :description="step.description"
            />
          </el-steps>

          <el-card shadow="never" class="mb-3">
            <template #header><span>{{ t(`${P}.sections.start_deploy`) }}</span></template>
            <el-form :model="deployForm" inline>
              <el-form-item :label="t('deploy_page.form.release')">
                <el-input v-model="deployForm.release_id" placeholder="Release ID" style="width:120px" type="number" />
              </el-form-item>
              <el-form-item :label="t(`${P}.form.notes`)">
                <el-input v-model="deployForm.notes" :placeholder="t(`${P}.form.notes_ph`)" style="width:300px" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="startDeploy" :loading="deploying" :disabled="deploying">
                  {{ t('deploy_page.start_deploy') }}
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 流程操作 -->
          <el-card shadow="never" v-if="currentDeployment">
            <template #header>
              <span>{{ t(`${P}.sections.current_deployment`, { id: currentDeployment.id, version: currentDeployment.release_version }) }}</span>
              <el-tag :type="statusTag(currentDeployment.status)" size="small" effect="dark" style="float:right">
                {{ statusLabels[currentDeployment.status] || currentDeployment.status }}
              </el-tag>
            </template>

            <el-descriptions :column="2" border size="small">
              <el-descriptions-item :label="t(`${P}.labels.active_env`)">{{ currentDeployment.active_environment }}</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.labels.standby_env`)">{{ currentDeployment.standby_environment }}</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.labels.created_at`)">{{ currentDeployment.created_at }}</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.cols.performed_by`)">{{ currentDeployment.performed_by }}</el-descriptions-item>
            </el-descriptions>

            <div class="action-buttons mt-3">
              <el-button type="primary" @click="runHealthCheck" :disabled="currentDeployment.status !== 'warmup'" :loading="hcLoading">
                1. {{ t(`${P}.actions.health_check`) }}
              </el-button>
              <el-button type="success" @click="runVerify" :disabled="currentDeployment.status !== 'verifying'" :loading="vLoading">
                2. {{ t(`${P}.actions.verify`) }}
              </el-button>
              <el-button type="warning" @click="runSwitch" :disabled="currentDeployment.status !== 'switching'" :loading="sLoading">
                3. {{ t(`${P}.actions.switch_traffic`) }}
              </el-button>
              <el-button type="danger" @click="runRollback" :disabled="!['live','switching','verifying'].includes(currentDeployment.status)" :loading="rLoading">
                {{ t('deploy_page.row_actions.rollback') }}
              </el-button>
            </div>

            <!-- 健康检查结果 -->
            <el-card v-if="currentDeployment.health_check_results" shadow="never" class="mt-3">
              <template #header><span>{{ t(`${P}.sections.health_results`) }}</span></template>
              <el-table :data="currentDeployment.health_check_results" stripe size="small">
                <el-table-column prop="endpoint" :label="t(`${P}.cols.endpoint`)" width="200" />
                <el-table-column prop="environment" :label="t(`${P}.cols.environment`)" width="100" />
                <el-table-column :label="t('deploy_page.cols.status')" width="80">
                  <template #default="{ row }">
                    <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">
                      {{ row.healthy ? t(`${P}.health.pass`) : t(`${P}.health.fail`) }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="status_code" label="HTTP" width="70" />
                <el-table-column :label="t(`${P}.cols.latency`)" width="80">
                  <template #default="{ row }">{{ row.latency_ms }}ms</template>
                </el-table-column>
                <el-table-column prop="error" :label="t(`${P}.cols.error`)" min-width="200" />
              </el-table>
            </el-card>
          </el-card>
        </el-tab-pane>

        <!-- Tab 2: 部署历史 -->
        <el-tab-pane :label="t(`${P}.tabs.history`)" name="history">
          <el-table :data="historyList" stripe v-loading="histLoading" :empty-text="t(`${P}.empty_history`)">
            <el-table-column prop="id" :label="t('deploy_page.cols.id')" width="50" />
            <el-table-column prop="release_version" :label="t('deploy_page.cols.version')" width="120" />
            <el-table-column prop="active_environment" :label="t(`${P}.cols.active`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.active_environment === 'blue' ? 'primary' : 'success'" size="small">{{ row.active_environment }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" :label="t('deploy_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small" effect="dark">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="warmup_completed_at" :label="t(`${P}.cols.warmup_at`)" width="170" />
            <el-table-column prop="traffic_switched_at" :label="t(`${P}.cols.switched_at`)" width="170" />
            <el-table-column prop="rollback_reason" :label="t(`${P}.cols.rollback_reason`)" min-width="200" show-overflow-tooltip />
            <el-table-column prop="performed_by" :label="t(`${P}.cols.performed_by`)" width="120" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: K8s 配置 -->
        <el-tab-pane :label="t(`${P}.tabs.k8s`)" name="k8s">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.sections.manage_scripts`) }}</span></template>
                <pre class="config-code">{{ t(`${P}.config.manage_script`) }}</pre>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.sections.kubectl_quick`) }}</span></template>
                <pre class="config-code">{{ t(`${P}.config.kubectl`) }}</pre>
              </el-card>
            </el-col>
          </el-row>

          <el-card shadow="never" class="mt-3">
            <template #header><span>{{ t(`${P}.sections.config_files`) }}</span></template>
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item :label="t(`${P}.cols.blue_deployment`)">deploy/blue-green/blue-deployment.yaml</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.cols.green_deployment`)">deploy/blue-green/green-deployment.yaml</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.cols.service_entry`)">deploy/blue-green/service.yaml</el-descriptions-item>
              <el-descriptions-item :label="t(`${P}.cols.manage_script`)">deploy/blue-green/manage.sh</el-descriptions-item>
            </el-descriptions>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Connection, Refresh } from '@element-plus/icons-vue';
import blueGreenApi from '@/api/blueGreen';

const P = 'blue_green_page';
const { t } = useI18n();

const STATUS_KEYS = ['warmup', 'verifying', 'switching', 'live', 'rolled_back', 'failed'];
const STEP_KEYS = ['warmup', 'health_check', 'verify', 'switch', 'complete'];

const loading = ref(false);
const activeTab = ref('deploy');
const deploying = ref(false);
const hcLoading = ref(false);
const vLoading = ref(false);
const sLoading = ref(false);
const rLoading = ref(false);
const histLoading = ref(false);

const dashboard = reactive({
  active_environment: 'blue', total_deployments: 0, successful: 0, rolled_back: 0,
  current_deployment: null, history: [], config: {},
});

const currentDeployment = ref(null);
const historyList = ref([]);

const deployForm = reactive({ release_id: '', notes: '' });

const statusLabels = computed(() =>
  Object.fromEntries(STATUS_KEYS.map((key) => [key, t(`${P}.status.${key}`)])),
);

const deploySteps = computed(() =>
  STEP_KEYS.map((key) => ({
    title: t(`${P}.steps.${key}`),
    description: t(`${P}.steps.${key}_desc`),
  })),
);

const currentStep = computed(() => {
  if (!currentDeployment.value) return 0;
  const map = { warmup: 0, verifying: 1, switching: 2, live: 3, rolled_back: -1, failed: -1 };
  const step = map[currentDeployment.value.status];
  return step >= 0 ? step : 0;
});

function statusTag(s) {
  const map = { warmup: 'info', verifying: 'warning', switching: 'warning', live: 'success', rolled_back: '', failed: 'danger' };
  return map[s] || '';
}

function envClass(env) {
  return env === 'blue' ? 'text-blue' : 'text-green';
}

async function refreshAll() {
  loading.value = true;
  try {
    const { data } = await blueGreenApi.getDashboard();
    if (data.success) {
      Object.assign(dashboard, data.data);
      currentDeployment.value = data.data.current_deployment;
    }
  } finally { loading.value = false; }
}

async function loadHistory() {
  histLoading.value = true;
  try {
    const { data } = await blueGreenApi.getHistory();
    if (data.success) historyList.value = data.data.data || data.data || [];
  } finally { histLoading.value = false; }
}

async function startDeploy() {
  if (!deployForm.release_id) {
    ElMessage.warning(t(`${P}.validation.release_id_required`));
    return;
  }
  deploying.value = true;
  try {
    const { data } = await blueGreenApi.startDeployment(Number(deployForm.release_id), deployForm.notes);
    if (data.success) {
      ElMessage.success(data.message);
      currentDeployment.value = data.data;
      await refreshAll();
    }
  } finally { deploying.value = false; }
}

async function runHealthCheck() {
  if (!currentDeployment.value) return;
  hcLoading.value = true;
  try {
    const { data } = await blueGreenApi.healthCheck(currentDeployment.value.id);
    if (data.success) {
      ElMessage.info(data.message);
      await refreshAll();
    }
  } finally { hcLoading.value = false; }
}

async function runVerify() {
  if (!currentDeployment.value) return;
  vLoading.value = true;
  try {
    const { data } = await blueGreenApi.verify(currentDeployment.value.id);
    if (data.success) {
      ElMessage.info(data.message);
      await refreshAll();
    }
  } finally { vLoading.value = false; }
}

async function runSwitch() {
  if (!currentDeployment.value) return;
  try {
    await ElMessageBox.confirm(t(`${P}.dialogs.switch_confirm`), t('actions.confirm'), { type: 'warning' });
    sLoading.value = true;
    const { data } = await blueGreenApi.switchTraffic(currentDeployment.value.id);
    if (data.success) {
      ElMessage.success(data.message);
      await refreshAll();
    }
  } catch { /* cancelled */ }
  finally { sLoading.value = false; }
}

async function runRollback() {
  if (!currentDeployment.value) return;
  try {
    const { value: reason } = await ElMessageBox.prompt(
      t(`${P}.dialogs.rollback_reason_ph`),
      t(`${P}.dialogs.rollback_title`),
      {
        confirmButtonText: t('deploy_page.row_actions.rollback'),
        cancelButtonText: t('actions.cancel'),
        inputPlaceholder: t(`${P}.dialogs.rollback_reason`),
      },
    );
    rLoading.value = true;
    const { data } = await blueGreenApi.rollback(currentDeployment.value.id, reason);
    if (data.success) {
      ElMessage.warning(data.message);
      await refreshAll();
    }
  } catch { /* cancelled */ }
  finally { rLoading.value = false; }
}

onMounted(async () => {
  await refreshAll();
  await loadHistory();
});
</script>

<style scoped>
.blue-green-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.text-green { color: #67c23a; }
.text-orange { color: #e6a23c; }
.text-blue { color: #0f172a; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.config-code { background: #1d1e1f; color: #e6e6e6; padding: 16px; border-radius: 6px; font-size: 13px; line-height: 1.6; overflow-x: auto; white-space: pre-wrap; }
</style>
