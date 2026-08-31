<template>
  <div class="chaos-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><WarningFilled /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-tag type="warning" effect="dark" size="small">{{ t(`${P}.tag_resilience`) }}</el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <el-alert
      :title="t(`${P}.alert_title`)"
      type="warning" show-icon :closable="false" class="mb-4"
    />

    <!-- 关键指标 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_experiments }}</div>
          <div class="stat-label">{{ t(`${P}.stats.total_experiments`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-green">{{ dashboard.completed }}</div>
          <div class="stat-label">{{ t(`${P}.stats.completed`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-orange">{{ dashboard.running }}</div>
          <div class="stat-label">{{ t(`${P}.stats.running`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-blue">{{ dashboard.avg_resilience_score }}</div>
          <div class="stat-label">{{ t(`${P}.stats.avg_resilience_score`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="5">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.system_health?.redis ? 'text-green' : 'text-red'">
            {{ dashboard.system_health?.redis ? t(`${P}.health.ok`) : t(`${P}.health.fail`) }}
          </div>
          <div class="stat-label">{{ t(`${P}.stats.redis_db_health`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 实验管理 -->
        <el-tab-pane :label="t(`${P}.tabs.experiments`)" name="experiments">
          <div class="section-toolbar">
            <el-button type="danger" @click="showCreateDialog = true">
              <el-icon><Plus /></el-icon> {{ t(`${P}.experiments.create_btn`) }}
            </el-button>
            <el-select v-model="expFilter.status" :placeholder="t(`${P}.experiments.status_filter_ph`)" clearable style="width:130px" @change="loadExperiments">
              <el-option v-for="(l,k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="expFilter.type" :placeholder="t(`${P}.experiments.type_filter_ph`)" clearable style="width:160px" @change="loadExperiments">
              <el-option v-for="(t,k) in experimentTypes" :key="k" :label="t.name" :value="k" />
            </el-select>
          </div>

          <el-table :data="experiments" stripe v-loading="expLoading" :empty-text="t(`${P}.experiments.empty`)">
            <el-table-column prop="id" :label="t(`${P}.cols.id`)" width="50" />
            <el-table-column prop="title" :label="t(`${P}.cols.title`)" min-width="180" show-overflow-tooltip />
            <el-table-column :label="t(`${P}.cols.type`)" width="120">
              <template #default="{ row }">
                <el-tag :type="typeTag(row.experiment_type)" size="small">{{ row.experiment_type }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.target`)" width="100">
              <template #default="{ row }"><code>{{ row.target_service }}</code></template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.blast_radius`)" width="90">
              <template #default="{ row }">
                <el-tag :type="radiusTag(row.blast_radius)" size="small">{{ blastRadiusLabel(row.blast_radius) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.status`)" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small" effect="dark">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.resilience_score`)" width="90">
              <template #default="{ row }">
                <span v-if="row.resilience_score !== null" :class="scoreClass(row.resilience_score)">{{ row.resilience_score }}</span>
                <span v-else class="text-gray">-</span>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.actions`)" width="220" fixed="right">
              <template #default="{ row }">
                <el-button size="small" type="primary" @click="executeExp(row)" :disabled="row.status === 'running'" :loading="executingId === row.id">
                  {{ t(`${P}.actions.execute`) }}
                </el-button>
                <el-button size="small" type="warning" @click="rollbackExp(row)" :disabled="row.status !== 'running'">
                  {{ t(`${P}.actions.rollback`) }}
                </el-button>
                <el-popconfirm :title="t('messages.confirm_delete')" @confirm="deleteExp(row)">
                  <template #reference>
                    <el-button size="small" type="danger" text>{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 韧性评分卡 -->
        <el-tab-pane :label="t(`${P}.tabs.scorecard`)" name="scorecard">
          <el-result v-if="scorecard.overall_score !== undefined" :icon="scorecard.grade?.grade >= 'A' ? 'success' : 'warning'">
            <template #title>
              {{ t(`${P}.scorecard.title`, { score: scorecard.overall_score }) }}
              <el-tag :type="scorecard.grade?.color" size="medium" effect="dark" class="ml-2">
                {{ scorecard.grade?.grade }} {{ scorecard.grade?.label }}
              </el-tag>
            </template>
            <template #extra>
              <el-descriptions :column="3" border class="mb-4">
                <el-descriptions-item :label="t(`${P}.scorecard.degradation_verification_rate`)">{{ scorecard.degradation_verification_rate }}%</el-descriptions-item>
                <el-descriptions-item :label="t(`${P}.scorecard.auto_recovery_rate`)">{{ scorecard.auto_recovery_rate }}%</el-descriptions-item>
                <el-descriptions-item :label="t(`${P}.scorecard.total_completed`)">{{ scorecard.total_completed }}</el-descriptions-item>
              </el-descriptions>
              <h4 class="mb-2">{{ t(`${P}.scorecard.by_type`) }}</h4>
              <el-table :data="scorecardByType" stripe size="small">
                <el-table-column prop="name" :label="t(`${P}.cols.experiment_type`)" width="140" />
                <el-table-column :label="t(`${P}.cols.count`)" width="60" prop="count" />
                <el-table-column :label="t(`${P}.cols.avg_score`)" width="80">
                  <template #default="{ row }"><span :class="scoreClass(row.avg_score)">{{ row.avg_score }}</span></template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.degradation_rate`)" width="110">
                  <template #default="{ row }">{{ row.degradation_rate }}%</template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.recovery_rate`)" width="110">
                  <template #default="{ row }">{{ row.recovery_rate }}%</template>
                </el-table-column>
              </el-table>
            </template>
          </el-result>
        </el-tab-pane>

        <!-- Tab 3: GameDay -->
        <el-tab-pane :label="t(`${P}.tabs.gameday`)" name="gameday">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.gameday.plan_title`) }}</span></template>
                <el-descriptions :column="1" border>
                  <el-descriptions-item :label="t(`${P}.gameday.last`)">{{ gameday.last_game_day || t(`${P}.gameday.not_yet`) }}</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.gameday.next`)">
                    <strong>{{ gameday.next_game_day || t(`${P}.gameday.pending_schedule`) }}</strong>
                    <el-tag v-if="gameday.days_until_next !== null" :type="gameday.days_until_next <= 7 ? 'warning' : 'info'" size="small" class="ml-2">
                      {{ gamedayDaysLabel }}
                    </el-tag>
                  </el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.gameday.frequency`)">{{ gameday.frequency_days }} {{ t(`${P}.gameday.frequency_unit`) }}</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.gameday.pending_experiments`)">{{ t(`${P}.gameday.pending_count`, { n: gameday.pending_experiments }) }}</el-descriptions-item>
                </el-descriptions>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.gameday.checklist_title`) }}</span></template>
                <el-timeline v-if="gameday.checklist?.length">
                  <el-timeline-item v-for="(item,i) in gameday.checklist" :key="i" :timestamp="t(`${P}.gameday.step`, { n: i + 1 })" size="small">
                    {{ item }}
                  </el-timeline-item>
                </el-timeline>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- Tab 4: 改进追踪 -->
        <el-tab-pane :label="t(`${P}.tabs.improvements`)" name="improvements">
          <el-table :data="improvements" stripe v-loading="impLoading" :empty-text="t(`${P}.improvements.empty`)">
            <el-table-column prop="experiment_title" :label="t(`${P}.cols.source_experiment`)" min-width="180" show-overflow-tooltip />
            <el-table-column prop="item" :label="t(`${P}.cols.item`)" min-width="250" show-overflow-tooltip />
            <el-table-column :label="t(`${P}.cols.priority`)" width="100">
              <template #default="{ row }">
                <el-tag :type="row.priority === 'high' ? 'danger' : row.priority === 'medium' ? 'warning' : 'info'" size="small">
                  {{ priorityLabel(row.priority) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.status`)" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'done' ? 'success' : row.status === 'in_progress' ? 'warning' : 'info'" size="small">
                  {{ improvementStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建实验对话框 -->
    <el-dialog v-model="showCreateDialog" :title="t(`${P}.create_dialog.title`)" width="600px">
      <el-form :model="createForm" label-width="120px">
        <el-form-item :label="t(`${P}.create_dialog.title_label`)" required>
          <el-input v-model="createForm.title" :placeholder="t(`${P}.create_dialog.title_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.type_label`)" required>
          <el-select v-model="createForm.experiment_type" style="width:100%">
            <el-option v-for="(typeItem,k) in experimentTypes" :key="k" :label="typeItem.name + ' (' + k + ')'" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.target_label`)" required>
          <el-input v-model="createForm.target_service" :placeholder="t(`${P}.create_dialog.target_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.blast_radius_label`)">
          <el-select v-model="createForm.blast_radius" style="width:100%">
            <el-option v-for="opt in blastRadiusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.namespace_label`)">
          <el-input v-model="createForm.target_namespace" :placeholder="t(`${P}.create_dialog.namespace_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.expected_behavior_label`)">
          <el-input v-model="createForm.expected_behavior" type="textarea" :rows="2" :placeholder="t(`${P}.create_dialog.expected_behavior_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.create_dialog.notes_label`)">
          <el-input v-model="createForm.notes" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="createExperiment" :loading="creating">{{ t(`${P}.create_dialog.submit`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { WarningFilled, Refresh, Plus } from '@element-plus/icons-vue';
import chaosApi from '@/api/chaosEngineering';

const { t } = useI18n();
const P = 'chaos_engineering_page';

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

const expFilter = reactive({ status: '', type: '' });
const createForm = reactive({
  title: '', experiment_type: 'redis_outage', target_service: 'redis',
  blast_radius: 'medium', target_namespace: '', expected_behavior: '', notes: '',
});

const statusLabels = computed(() => Object.fromEntries(
  ['draft', 'scheduled', 'running', 'completed', 'failed', 'rolled_back'].map((k) => [k, t(`${P}.status.${k}`)])
));

const blastRadiusLabels = computed(() => Object.fromEntries(
  ['low', 'medium', 'high', 'critical'].map((k) => [k, t(`${P}.blast_radius.${k}`)])
));

const blastRadiusOptions = computed(() =>
  ['low', 'medium', 'high', 'critical'].map((k) => ({ value: k, label: t(`${P}.blast_radius.${k}`) }))
);

const priorityLabels = computed(() => Object.fromEntries(
  ['high', 'medium', 'low'].map((k) => [k, t(`${P}.priority.${k}`)])
));

const improvementStatusLabels = computed(() => Object.fromEntries(
  ['done', 'in_progress', 'pending', 'open'].map((k) => [k, t(`${P}.improvement_status.${k}`)])
));

const scorecardByType = computed(() => {
  const data = scorecard.value.by_type || {};
  return Object.values(data);
});

const gamedayDaysLabel = computed(() => {
  const days = gameday.value.days_until_next;
  if (days === null || days === undefined) return '';
  return days >= 0 ? t(`${P}.gameday.days_until`, { n: days }) : t(`${P}.gameday.overdue`);
});

function blastRadiusLabel(r) {
  return blastRadiusLabels.value[r] || r;
}

function priorityLabel(p) {
  return priorityLabels.value[p] || p;
}

function improvementStatusLabel(s) {
  return improvementStatusLabels.value[s] || s;
}

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
    ElMessage.warning(t(`${P}.messages.required_fields`));
    return;
  }
  creating.value = true;
  try {
    const { data } = await chaosApi.createExperiment(createForm);
    if (data.success) {
      ElMessage.success(t(`${P}.messages.created`));
      showCreateDialog.value = false;
      createForm.title = '';
      await loadExperiments();
    }
  } finally { creating.value = false; }
}

async function executeExp(row) {
  try {
    await ElMessageBox.confirm(
      t(`${P}.confirm.execute_msg`, { title: row.title, target: row.target_service }),
      t(`${P}.confirm.execute_title`),
      { confirmButtonText: t(`${P}.actions.execute`), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    executingId.value = row.id;
    const { data } = await chaosApi.executeExperiment(row.id);
    if (data.success) {
      const score = data.data?.experiment?.resilience_score ?? 'N/A';
      ElMessage.info(t(`${P}.messages.executed`, { score }));
    }
    await loadExperiments();
    await loadScorecard();
  } catch { /* cancelled */ }
  finally { executingId.value = null; }
}

async function rollbackExp(row) {
  try {
    await ElMessageBox.confirm(
      t(`${P}.confirm.rollback_msg`, { title: row.title }),
      t('actions.confirm'),
      { confirmButtonText: t(`${P}.actions.rollback`), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    await chaosApi.rollbackExperiment(row.id);
    ElMessage.success(t(`${P}.messages.rolled_back`));
    await loadExperiments();
  } catch { /* cancelled */ }
}

async function deleteExp(row) {
  await chaosApi.deleteExperiment(row.id);
  ElMessage.success(t(`${P}.messages.deleted`));
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
.text-blue { color: #0f172a; }
.font-bold { font-weight: 700; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { display: flex; gap: 8px; margin-bottom: 16px; align-items: center; }
code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.9em; }
</style>
