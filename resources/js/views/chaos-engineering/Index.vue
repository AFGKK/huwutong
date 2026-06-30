<template>
  <div class="chaos-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><WarningFilled /></el-icon>
        混沌工程
      </h2>
      <div class="header-actions">
        <el-tag type="warning" effect="dark" size="small">韧性测试</el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="混沌工程韧性测试 — 验证系统降级行为 &amp; 自动恢复 &amp; 告警触发，保障大型客户系统韧性"
      type="warning" show-icon :closable="false" class="mb-4"
    />

    <!-- 关键指标 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_experiments }}</div>
          <div class="stat-label">总实验数</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-green">{{ dashboard.completed }}</div>
          <div class="stat-label">已完成</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-orange">{{ dashboard.running }}</div>
          <div class="stat-label">运行中</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-blue">{{ dashboard.avg_resilience_score }}</div>
          <div class="stat-label">平均韧性评分</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.system_health?.redis ? 'text-green' : 'text-red'">
            {{ dashboard.system_health?.redis ? '✅' : '❌' }}
          </div>
          <div class="stat-label">Redis / DB 健康</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 实验管理 -->
        <el-tab-pane label="实验管理" name="experiments">
          <div class="section-toolbar">
            <el-button type="danger" @click="showCreateDialog = true">
              <el-icon><Plus /></el-icon> 新建实验
            </el-button>
            <el-select v-model="expFilter.status" placeholder="状态筛选" clearable style="width:130px" @change="loadExperiments">
              <el-option v-for="(l,k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="expFilter.type" placeholder="类型筛选" clearable style="width:160px" @change="loadExperiments">
              <el-option v-for="(t,k) in experimentTypes" :key="k" :label="t.name" :value="k" />
            </el-select>
          </div>

          <el-table :data="experiments" stripe v-loading="expLoading" empty-text="暂无实验">
            <el-table-column prop="id" label="ID" width="50" />
            <el-table-column prop="title" label="实验名称" min-width="180" show-overflow-tooltip />
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-tag :type="typeTag(row.experiment_type)" size="small">{{ row.experiment_type }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="目标" width="100">
              <template #default="{ row }"><code>{{ row.target_service }}</code></template>
            </el-table-column>
            <el-table-column label="爆炸半径" width="90">
              <template #default="{ row }">
                <el-tag :type="radiusTag(row.blast_radius)" size="small">{{ row.blast_radius }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small" effect="dark">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="韧性评分" width="90">
              <template #default="{ row }">
                <span v-if="row.resilience_score !== null" :class="scoreClass(row.resilience_score)">{{ row.resilience_score }}</span>
                <span v-else class="text-gray">-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="executeExp(row)" :disabled="row.status === 'running'" :loading="executingId === row.id">
                  执行
                </el-button>
                <el-button size="small" type="warning" @click="rollbackExp(row)" :disabled="row.status !== 'running'">
                  回滚
                </el-button>
                <el-popconfirm title="确认删除？" @confirm="deleteExp(row)">
                  <template #reference>
                    <el-button size="small" type="danger" text>删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 韧性评分卡 -->
        <el-tab-pane label="韧性评分卡" name="scorecard">
          <el-result v-if="scorecard.overall_score !== undefined" :icon="scorecard.grade?.grade >= 'A' ? 'success' : 'warning'">
            <template #title>
              韧性评分: {{ scorecard.overall_score }} 分
              <el-tag :type="scorecard.grade?.color" size="medium" effect="dark" class="ml-2">
                {{ scorecard.grade?.grade }} {{ scorecard.grade?.label }}
              </el-tag>
            </template>
            <template #extra>
              <el-descriptions :column="3" border class="mb-4">
                <el-descriptions-item label="降级验证率">{{ scorecard.degradation_verification_rate }}%</el-descriptions-item>
                <el-descriptions-item label="自动恢复率">{{ scorecard.auto_recovery_rate }}%</el-descriptions-item>
                <el-descriptions-item label="已完成实验">{{ scorecard.total_completed }}</el-descriptions-item>
              </el-descriptions>
              <h4 class="mb-2">按类型评分</h4>
              <el-table :data="scorecardByType" stripe size="small">
                <el-table-column prop="name" label="实验类型" width="140" />
                <el-table-column label="次数" width="60" prop="count" />
                <el-table-column label="平均分" width="80">
                  <template #default="{ row }"><span :class="scoreClass(row.avg_score)">{{ row.avg_score }}</span></template>
                </el-table-column>
                <el-table-column label="降级验证率" width="110">
                  <template #default="{ row }">{{ row.degradation_rate }}%</template>
                </el-table-column>
                <el-table-column label="自动恢复率" width="110">
                  <template #default="{ row }">{{ row.recovery_rate }}%</template>
                </el-table-column>
              </el-table>
            </template>
          </el-result>
        </el-tab-pane>

        <!-- Tab 3: GameDay -->
        <el-tab-pane label="GameDay 演练" name="gameday">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>演练计划</span></template>
                <el-descriptions :column="1" border>
                  <el-descriptions-item label="上次演练">{{ gameday.last_game_day || '尚未进行' }}</el-descriptions-item>
                  <el-descriptions-item label="下次演练">
                    <strong>{{ gameday.next_game_day || '待安排' }}</strong>
                    <el-tag v-if="gameday.days_until_next !== null" :type="gameday.days_until_next <= 7 ? 'warning' : 'info'" size="small" class="ml-2">
                      {{ gameday.days_until_next >= 0 ? gameday.days_until_next + ' 天后' : '已逾期' }}
                    </el-tag>
                  </el-descriptions-item>
                  <el-descriptions-item label="频率">{{ gameday.frequency_days }} 天/次</el-descriptions-item>
                  <el-descriptions-item label="待定实验">{{ gameday.pending_experiments }} 个</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>前置检查清单</span></template>
                <el-timeline v-if="gameday.checklist?.length">
                  <el-timeline-item v-for="(item,i) in gameday.checklist" :key="i" :timestamp="'Step ' + (i+1)" size="small">
                    {{ item }}
                  </el-timeline-item>
                </el-timeline>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- Tab 4: 改进追踪 -->
        <el-tab-pane label="改进追踪" name="improvements">
          <el-table :data="improvements" stripe v-loading="impLoading" empty-text="暂无改进项">
            <el-table-column prop="experiment_title" label="来源实验" min-width="180" show-overflow-tooltip />
            <el-table-column prop="item" label="改进项" min-width="250" show-overflow-tooltip />
            <el-table-column label="优先级" width="100">
              <template #default="{ row }">
                <el-tag :type="row.priority === 'high' ? 'danger' : row.priority === 'medium' ? 'warning' : 'info'" size="small">
                  {{ row.priority }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'done' ? 'success' : row.status === 'in_progress' ? 'warning' : 'info'" size="small">
                  {{ row.status }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建实验对话框 -->
    <el-dialog v-model="showCreateDialog" title="新建混沌实验" width="600px">
      <el-form :model="createForm" label-width="120px">
        <el-form-item label="实验名称" required>
          <el-input v-model="createForm.title" placeholder="例如: Redis 宕机降级验证" />
        </el-form-item>
        <el-form-item label="实验类型" required>
          <el-select v-model="createForm.experiment_type" style="width:100%">
            <el-option v-for="(t,k) in experimentTypes" :key="k" :label="t.name + ' (' + k + ')'" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="目标服务" required>
          <el-input v-model="createForm.target_service" placeholder="redis / database / api" />
        </el-form-item>
        <el-form-item label="爆炸半径">
          <el-select v-model="createForm.blast_radius" style="width:100%">
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="严重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="K8s Namespace">
          <el-input v-model="createForm.target_namespace" placeholder="default" />
        </el-form-item>
        <el-form-item label="预期行为">
          <el-input v-model="createForm.expected_behavior" type="textarea" :rows="2" placeholder="系统应降级到... 自动恢复预期..." />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="createForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="createExperiment" :loading="creating">创建实验</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { WarningFilled, Refresh, Plus } from '@element-plus/icons-vue';
import chaosApi from '@/api/chaosEngineering';

const loading = ref(false);
const activeTab = ref('experiments');
const expLoading = ref(false);
const executingId = ref(null);
const creating = ref(false);
const impLoading = ref(false);
const showCreateDialog = ref(false);

const dashboard = reactive({
  total_experiments: 0, running: 0, completed: 0, passed: 0,
  avg_resilience_score: 0, by_type: {}, recent_experiments: [],
  system_health: { redis: false, database: false },
});

const experiments = ref([]);
const scorecard = ref({});
const gameday = ref({});
const improvements = ref([]);
const experimentTypes = ref({});

const statusLabels = { draft: '草稿', scheduled: '已计划', running: '运行中', completed: '已完成', failed: '失败', rolled_back: '已回滚' };

const expFilter = reactive({ status: '', type: '' });
const createForm = reactive({
  title: '', experiment_type: 'redis_outage', target_service: 'redis',
  blast_radius: 'medium', target_namespace: '', expected_behavior: '', notes: '',
});

const scorecardByType = computed(() => {
  const data = scorecard.value.by_type || {};
  return Object.values(data);
});

function typeTag(type) {
  const map = { redis_outage: 'danger', db_failover: 'danger', pod_kill: 'warning', network_latency: '', disk_full: 'warning', cpu_stress: 'info', memory_stress: 'info' };
  return map[type] || '';
}

function radiusTag(r) {
  const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
  return map[r] || '';
}

function statusTag(s) {
  const map = { draft: 'info', scheduled: 'primary', running: 'warning', completed: 'success', failed: 'danger', rolled_back: '' };
  return map[s] || '';
}

function scoreClass(score) {
  if (score >= 80) return 'text-green font-bold';
  if (score >= 60) return 'text-orange font-bold';
  return 'text-red font-bold';
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, typesRes] = await Promise.all([
      chaosApi.getDashboard(),
      chaosApi.getTypes(),
    ]);
    if (dashRes.success) Object.assign(dashboard, dashRes.data);
    if (typesRes.success) experimentTypes.value = typesRes.data;
  } finally { loading.value = false; }
}

async function loadExperiments() {
  expLoading.value = true;
  try {
    const { data } = await chaosApi.getExperiments({
      status: expFilter.status || undefined,
      type: expFilter.type || undefined,
    });
    if (data.success) experiments.value = data.data.data || data.data || [];
  } finally { expLoading.value = false; }
}

async function loadScorecard() {
  const { data } = await chaosApi.getScorecard();
  if (data.success) scorecard.value = data.data;
}

async function loadGameDay() {
  const { data } = await chaosApi.getGameDay();
  if (data.success) gameday.value = data.data;
}

async function loadImprovements() {
  impLoading.value = true;
  try {
    const { data } = await chaosApi.getImprovements();
    if (data.success) improvements.value = data.data;
  } finally { impLoading.value = false; }
}

async function createExperiment() {
  if (!createForm.title || !createForm.experiment_type || !createForm.target_service) {
    ElMessage.warning('请填写必要字段');
    return;
  }
  creating.value = true;
  try {
    const { data } = await chaosApi.createExperiment(createForm);
    if (data.success) {
      ElMessage.success('实验已创建');
      showCreateDialog.value = false;
      createForm.title = '';
      await loadExperiments();
    }
  } finally { creating.value = false; }
}

async function executeExp(row) {
  try {
    await ElMessageBox.confirm(
      `确定要执行 "${row.title}" 吗？此操作将注入故障到 ${row.target_service}。`,
      '确认执行', { confirmButtonText: '执行', cancelButtonText: '取消', type: 'warning' }
    );
    executingId.value = row.id;
    const { data } = await chaosApi.executeExperiment(row.id);
    if (data.success) ElMessage.info(`实验完成，韧性评分: ${data.data?.experiment?.resilience_score ?? 'N/A'}`);
    await loadExperiments();
    await loadScorecard();
  } catch { /* cancelled */ }
  finally { executingId.value = null; }
}

async function rollbackExp(row) {
  try {
    await ElMessageBox.confirm(`确定要回滚 "${row.title}" 吗？`, '确认', { type: 'warning' });
    await chaosApi.rollbackExperiment(row.id);
    ElMessage.success('已回滚');
    await loadExperiments();
  } catch { /* cancelled */ }
}

async function deleteExp(row) {
  await chaosApi.deleteExperiment(row.id);
  ElMessage.success('已删除');
  await loadExperiments();
}

onMounted(async () => {
  await refreshAll();
  await Promise.all([
    loadExperiments(),
    loadScorecard(),
    loadGameDay(),
    loadImprovements(),
  ]);
});
</script>

<style scoped>
.chaos-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.ml-2 { margin-left: 8px; }
.text-gray { color: #909399; }
.text-green { color: #67c23a; }
.text-orange { color: #e6a23c; }
.text-red { color: #f56c6c; }
.text-blue { color: #409eff; }
.font-bold { font-weight: 700; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #409eff; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.9em; }
</style>
