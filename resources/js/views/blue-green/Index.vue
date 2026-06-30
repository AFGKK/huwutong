<template>
  <div class="blue-green-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        蓝绿部署
      </h2>
      <div class="header-actions">
        <el-tag :type="dashboard.active_environment === 'blue' ? 'primary' : 'success'" effect="dark" size="medium">
          🔵 {{ dashboard.active_environment === 'blue' ? 'Blue 活跃' : 'Green 活跃' }}
        </el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="K8s Blue-Green 部署 — Green 环境预热 → 验证通过 → 一键流量切换 → 秒级回滚 → 零停机升级"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_deployments }}</div>
          <div class="stat-label">总部署次数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card stat-success">
          <div class="stat-value text-green">{{ dashboard.successful }}</div>
          <div class="stat-label">成功</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-orange">{{ dashboard.rolled_back }}</div>
          <div class="stat-label">已回滚</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="envClass(dashboard.active_environment)">{{ dashboard.active_environment }}</div>
          <div class="stat-label">当前活跃</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 部署流程 -->
        <el-tab-pane label="部署流程" name="deploy">
          <el-steps :active="currentStep" align-center class="mb-4">
            <el-step title="预热" description="启动 Green 环境" />
            <el-step title="健康检查" description="验证 Green 可用" />
            <el-step title="验证" description="监控确认" />
            <el-step title="切换" description="流量切换" />
            <el-step title="完成" description="监控运行" />
          </el-steps>

          <el-card shadow="never" class="mb-3">
            <template #header><span>开始新部署</span></template>
            <el-form :model="deployForm" inline>
              <el-form-item label="Release">
                <el-input v-model="deployForm.release_id" placeholder="Release ID" style="width:120px" type="number" />
              </el-form-item>
              <el-form-item label="备注">
                <el-input v-model="deployForm.notes" placeholder="描述本次部署" style="width:300px" />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" @click="startDeploy" :loading="deploying" :disabled="deploying">
                  🚀 开始部署
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 流程操作 -->
          <el-card shadow="never" v-if="currentDeployment">
            <template #header>
              <span>当前部署 #{{ currentDeployment.id }} — {{ currentDeployment.release_version }}</span>
              <el-tag :type="statusTag(currentDeployment.status)" size="small" effect="dark" style="float:right">
                {{ currentDeployment.status }}
              </el-tag>
            </template>

            <el-descriptions :column="2" border size="small">
              <el-descriptions-item label="活跃环境">{{ currentDeployment.active_environment }}</el-descriptions-item>
              <el-descriptions-item label="Standby 环境">{{ currentDeployment.standby_environment }}</el-descriptions-item>
              <el-descriptions-item label="创建时间">{{ currentDeployment.created_at }}</el-descriptions-item>
              <el-descriptions-item label="执行人">{{ currentDeployment.performed_by }}</el-descriptions-item>
            </el-descriptions>

            <div class="action-buttons mt-3">
              <el-button type="primary" @click="runHealthCheck" :disabled="currentDeployment.status !== 'warmup'" :loading="hcLoading">
                1️⃣ 健康检查
              </el-button>
              <el-button type="success" @click="runVerify" :disabled="currentDeployment.status !== 'verifying'" :loading="vLoading">
                2️⃣ 验证环境
              </el-button>
              <el-button type="warning" @click="runSwitch" :disabled="currentDeployment.status !== 'switching'" :loading="sLoading">
                3️⃣ 🔄 切换流量
              </el-button>
              <el-button type="danger" @click="runRollback" :disabled="!['live','switching','verifying'].includes(currentDeployment.status)" :loading="rLoading">
                ⏪ 回滚
              </el-button>
            </div>

            <!-- 健康检查结果 -->
            <el-card v-if="currentDeployment.health_check_results" shadow="never" class="mt-3">
              <template #header><span>健康检查结果</span></template>
              <el-table :data="currentDeployment.health_check_results" stripe size="small">
                <el-table-column prop="endpoint" label="端点" width="200" />
                <el-table-column prop="environment" label="环境" width="100" />
                <el-table-column label="状态" width="80">
                  <template #default="{ row }">
                    <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">{{ row.healthy ? '通过' : '失败' }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="status_code" label="HTTP" width="70" />
                <el-table-column prop="latency_ms" label="延迟" width="80">{{ row.latency_ms }}ms</el-table-column>
                <el-table-column prop="error" label="错误" min-width="200" />
              </el-table>
            </el-card>
          </el-card>
        </el-tab-pane>

        <!-- Tab 2: 部署历史 -->
        <el-tab-pane label="部署历史" name="history">
          <el-table :data="historyList" stripe v-loading="histLoading" empty-text="暂无部署记录">
            <el-table-column prop="id" label="ID" width="50" />
            <el-table-column prop="release_version" label="版本" width="120" />
            <el-table-column prop="active_environment" label="活跃" width="80">
              <template #default="{ row }">
                <el-tag :type="row.active_environment === 'blue' ? 'primary' : 'success'" size="small">{{ row.active_environment }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small" effect="dark">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="warmup_completed_at" label="预热完成" width="170" />
            <el-table-column prop="traffic_switched_at" label="切换时间" width="170" />
            <el-table-column prop="rollback_reason" label="回滚原因" min-width="200" show-overflow-tooltip />
            <el-table-column prop="performed_by" label="执行人" width="120" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: K8s 配置 -->
        <el-tab-pane label="K8s 配置" name="k8s">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>管理脚本</span></template>
                <pre class="config-code"># 查看状态
bash deploy/blue-green/manage.sh status

# 部署新版本到 Green
IMAGE=hwt-license-api:v2.0 bash deploy/blue-green/manage.sh deploy

# 切换流量到 Green
bash deploy/blue-green/manage.sh switch green

# 回滚到 Blue
bash deploy/blue-green/manage.sh rollback

# 健康检查 Green
bash deploy/blue-green/manage.sh test</pre>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>Kubectl 快速命令</span></template>
                <pre class="config-code"># 切换流量到 Green
kubectl patch service hwt-api \\
  -p '{"spec":{"selector":{"deployment":"green"}}}'

# 切换流量到 Blue
kubectl patch service hwt-api \\
  -p '{"spec":{"selector":{"deployment":"blue"}}}'

# 查看当前活跃环境
kubectl get service hwt-api \\
  -o jsonpath='{.spec.selector.deployment}'</pre>
              </el-card>
            </el-col>
          </el-row>

          <el-card shadow="never" class="mt-3">
            <template #header><span>配置文件</span></template>
            <el-descriptions :column="2" border size="small">
              <el-descriptions-item label="Blue Deployment">deploy/blue-green/blue-deployment.yaml</el-descriptions-item>
              <el-descriptions-item label="Green Deployment">deploy/blue-green/green-deployment.yaml</el-descriptions-item>
              <el-descriptions-item label="Service (流量入口)">deploy/blue-green/service.yaml</el-descriptions-item>
              <el-descriptions-item label="管理脚本">deploy/blue-green/manage.sh</el-descriptions-item>
            </el-descriptions>
          </el-card>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Connection, Refresh } from '@element-plus/icons-vue';
import blueGreenApi from '@/api/blueGreen';

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

const currentStep = computed(() => {
  if (!currentDeployment.value) return 0;
  const map = { warmup: 0, verifying: 1, switching: 2, live: 3, rolled_back: -1, failed: -1 };
  const step = map[currentDeployment.value.status];
  return step >= 0 ? step : 0;
});

const statusLabels = { warmup: '预热中', verifying: '验证中', switching: '切换中', live: '已上线', rolled_back: '已回滚', failed: '失败' };

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
  if (!deployForm.release_id) { ElMessage.warning('请输入 Release ID'); return; }
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
    await ElMessageBox.confirm('确认切换流量到 Standby 环境？', '确认', { type: 'warning' });
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
    const { value: reason } = await ElMessageBox.prompt('请输入回滚原因', '回滚确认', { confirmButtonText: '回滚', cancelButtonText: '取消', inputPlaceholder: '回滚原因' });
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
.text-blue { color: #409eff; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #409eff; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
.config-code { background: #1d1e1f; color: #e6e6e6; padding: 16px; border-radius: 6px; font-size: 13px; line-height: 1.6; overflow-x: auto; }
</style>
