<template>
  <div class="slo-management">
    <el-tabs v-model="mainTab">
      <el-tab-pane :label="t('slo_page.tabs.slo')" name="slo">
    <!-- 顶部统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-blue-500">{{ stats.active_slo || 0 }}</div>
          <div class="stat-label">{{ t('slo_page.stats.active_slo') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-green-500">{{ stats.healthy_slo || 0 }}</div>
          <div class="stat-label">{{ t('slo_page.stats.healthy') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-red-500">{{ stats.exhausted_slo || 0 }}</div>
          <div class="stat-label">{{ t('slo_page.stats.exhausted') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-purple-500">{{ stats.total_slo || 0 }}</div>
          <div class="stat-label">{{ t('slo_page.stats.total_slo') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-row :gutter="16" class="mb-3">
      <el-col :span="12">
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon> {{ t('slo_page.actions.create') }}
        </el-button>
        <el-button @click="refreshList" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t('slo_page.actions.refresh') }}
        </el-button>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button @click="handleCalculateAll" :loading="calculating">
          <el-icon><DataAnalysis /></el-icon> {{ t('slo_page.actions.calculate_all') }}
        </el-button>
      </el-col>
    </el-row>

    <!-- SLO列表 -->
    <el-card shadow="never">
      <el-table :data="sloList" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="name" :label="t('slo_page.columns.name')" min-width="160" />
        <el-table-column prop="service_name" :label="t('slo_page.columns.service')" width="100" />
        <el-table-column prop="sli_type" :label="t('slo_page.columns.sli_type')" width="100">
          <template #default="{ row }">
            <el-tag :type="sliTypeTag(row.sli_type)" size="small">
              {{ sliTypeLabel(row.sli_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="target" :label="t('slo_page.columns.target')" width="100" align="center">
          <template #default="{ row }">
            <span class="font-mono">{{ row.target }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="current_sli" :label="t('slo_page.columns.current_sli')" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="sliStatusTag(row.current_sli, row.target)" size="small">
              {{ row.current_sli != null ? row.current_sli + '%' : '-' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remaining_budget" :label="t('slo_page.columns.remaining_budget')" width="130" align="right">
          <template #default="{ row }">
            <span :class="budgetColor(row.remaining_budget)" class="font-mono">
              {{ row.remaining_budget != null ? row.remaining_budget.toFixed(1) : '-' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="burn_rate" :label="t('slo_page.columns.burn_rate')" width="100" align="center">
          <template #default="{ row }">
            <span class="font-mono">{{ row.burn_rate != null ? row.burn_rate.toFixed(2) : '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="window_days" :label="t('slo_page.columns.window')" width="80" align="center">
          <template #default="{ row }">{{ t('slo_page.units.days', { n: row.window_days }) }}</template>
        </el-table-column>
        <el-table-column prop="is_active" :label="t('slo_page.columns.status')" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('slo_page.columns.actions')" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="openDetail(row)">{{ t('slo_page.actions.detail') }}</el-button>
            <el-button size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('slo_page.confirm.delete')" @confirm="handleDelete(row)">
              <template #reference>
                <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-3 text-center" v-if="total > pagination.perPage">
        <el-pagination
          v-model:current-page="pagination.page"
          :page-size="pagination.perPage"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </el-card>

    <!-- 新建/编辑对话框 -->
    <el-dialog
      v-model="dialog.visible"
      :title="dialog.isEdit ? t('slo_page.dialog.edit_title') : t('slo_page.dialog.create_title')"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" label-position="top">
        <el-form-item :label="t('slo_page.form.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('slo_page.form.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('slo_page.form.service')" prop="service_name">
          <el-input v-model="form.service_name" :placeholder="t('slo_page.form.service_ph')" />
        </el-form-item>
        <el-form-item :label="t('slo_page.form.sli_type')" prop="sli_type">
          <el-select v-model="form.sli_type" style="width: 100%">
            <el-option v-for="(label, val) in sliTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item :label="t('slo_page.form.target')" prop="target">
              <el-input-number v-model="form.target" :min="50" :max="99.999" :step="0.1" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('slo_page.form.window_days')" prop="window_days">
              <el-input-number v-model="form.window_days" :min="1" :max="365" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('slo_page.form.description')">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('slo_page.form.burn_rate_alerts')" prop="burn_rate_alerts">
          <div class="flex flex-col gap-2 w-full">
            <div v-for="(alert, idx) in burnRateAlerts" :key="idx" class="flex gap-2 items-center">
              <el-input-number v-model="alert.window_hours" :min="1" :max="24" size="small" :placeholder="t('slo_page.form.alert_window_ph')" />
              <span class="text-sm text-gray-500">{{ t('slo_page.form.alert_within') }}</span>
              <el-input-number v-model="alert.threshold" :min="0.1" :max="100" :step="0.1" size="small" :placeholder="t('slo_page.form.alert_threshold_ph')" />
              <span class="text-sm text-gray-500">{{ t('slo_page.form.alert_multiplier') }}</span>
              <el-button size="small" type="danger" :icon="Delete" circle @click="removeAlert(idx)" />
            </div>
            <el-button size="small" @click="addAlert">
              <el-icon><Plus /></el-icon> {{ t('slo_page.actions.add_alert') }}
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailVisible" :title="t('slo_page.dialog.detail_title')" width="700px" :close-on-click-modal="false">
      <template v-if="currentSlo">
        <el-descriptions :column="2" border>
          <el-descriptions-item :label="t('slo_page.detail.name')" :span="2">{{ currentSlo.name }}</el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.detail.service')">{{ currentSlo.service_name }}</el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.columns.sli_type')">{{ sliTypeLabel(currentSlo.sli_type) }}</el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.columns.target')">{{ currentSlo.target }}%</el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.columns.current_sli')">
            <el-tag :type="sliStatusTag(currentSlo.current_sli, currentSlo.target)" size="small">
              {{ currentSlo.current_sli != null ? currentSlo.current_sli + '%' : '-' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.columns.window')">{{ t('slo_page.units.days', { n: currentSlo.window_days }) }}</el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.detail.total_budget')" :span="2">
            {{ totalBudgetStr(currentSlo) }}
          </el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.detail.remaining_budget')">
            <span :class="budgetColor(currentSlo.remaining_budget)">
              {{ currentSlo.remaining_budget != null ? t('slo_page.units.minutes', { n: currentSlo.remaining_budget.toFixed(2) }) : '-' }}
            </span>
          </el-descriptions-item>
          <el-descriptions-item :label="t('slo_page.detail.burn_rate')">
            {{ currentSlo.burn_rate != null ? t('slo_page.units.per_day', { n: currentSlo.burn_rate.toFixed(2) }) : '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 每日SLI趋势 -->
        <h4 class="mt-4 mb-2">{{ t('slo_page.detail.daily_trend') }}</h4>
        <el-table :data="currentSlo.daily_records || []" size="small" max-height="300" stripe>
          <el-table-column prop="record_date" :label="t('slo_page.columns.date')" width="120" />
          <el-table-column prop="total_requests" :label="t('slo_page.columns.requests')" width="80" align="center" />
          <el-table-column prop="sli" :label="t('slo_page.columns.sli')" width="80" align="center">
            <template #default="{ row }">
              {{ row.sli != null ? row.sli + '%' : '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="budget_consumed" :label="t('slo_page.columns.budget_consumed')" width="120" align="right" />
        </el-table>

        <!-- 最近事件 -->
        <h4 class="mt-4 mb-2">{{ t('slo_page.detail.recent_events') }}</h4>
        <el-table :data="currentSlo.budget_events || []" size="small" max-height="200" stripe>
          <el-table-column prop="created_at" :label="t('slo_page.columns.time')" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column prop="event_type" :label="t('slo_page.columns.event_type')" width="130">
            <template #default="{ row }">
              <el-tag :type="eventTypeTag(row.event_type)" size="small">
                {{ eventTypeLabel(row.event_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="budget_remaining" :label="t('slo_page.detail.remaining_budget')" width="100" align="right">
            <template #default="{ row }">{{ t('slo_page.units.minutes', { n: row.budget_remaining.toFixed(1) }) }}</template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>

    <!-- 近期SLO事件 -->
    <el-card shadow="never" class="mt-4">
      <template #header>
        <div class="flex justify-between items-center">
          <span>{{ t('slo_page.sections.recent_events') }}</span>
        </div>
      </template>
      <el-table :data="recentEvents" size="small" stripe style="width: 100%">
        <el-table-column prop="definition.name" :label="t('slo_page.columns.slo')" min-width="140" />
        <el-table-column prop="event_type" :label="t('slo_page.columns.event')" width="140">
          <template #default="{ row }">
            <el-tag :type="eventTypeTag(row.event_type)" size="small">
              {{ eventTypeLabel(row.event_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="budget_remaining" :label="t('slo_page.columns.remaining_budget')" width="120" align="right">
          <template #default="{ row }">{{ row.budget_remaining.toFixed(1) }}</template>
        </el-table-column>
        <el-table-column prop="created_at" :label="t('slo_page.columns.time')" width="160">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
      </el-table>
    </el-card>
      </el-tab-pane>

      <el-tab-pane :label="t('slo_page.tabs.tracing')" name="tracing">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ tracingStats.total || 0 }}</div><div class="stat-label">{{ t('apm_page.stats.total_requests') }}</div></el-card></el-col>
          <el-col :span="6"><el-card shadow="hover"><div class="stat-value text-red-500">{{ tracingStats.slow || 0 }}</div><div class="stat-label">{{ t('apm_page.stats.slow_requests') }}</div></el-card></el-col>
          <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ tracingStats.avg_duration_ms || 0 }}ms</div><div class="stat-label">{{ t('apm_page.stats.avg_duration') }}</div></el-card></el-col>
          <el-col :span="6"><el-card shadow="hover"><div class="stat-value">{{ tracingStats.error_rate || 0 }}%</div><div class="stat-label">{{ t('slo_page.stats.error_rate') }}</div></el-card></el-col>
        </el-row>
        <el-card shadow="never">
          <el-table :data="tracingList" v-loading="tracingLoading" stripe>
            <el-table-column prop="method" :label="t('apm_page.columns.method')" width="80" />
            <el-table-column prop="path" :label="t('apm_page.columns.path')" min-width="220" show-overflow-tooltip />
            <el-table-column prop="status_code" :label="t('apm_page.columns.status_code')" width="90" />
            <el-table-column prop="duration_ms" :label="t('apm_page.columns.duration')" width="100" />
            <el-table-column prop="created_at" :label="t('apm_page.columns.time')" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('slo_page.columns.actions')" width="90">
              <template #default="{ row }">
                <el-button size="small" link type="primary" @click="openTracingDetail(row.id)">{{ t('slo_page.actions.detail') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, Delete, DataAnalysis } from '@element-plus/icons-vue';
import {
  getSloDashboard, getSloList, getSlo, createSlo, updateSlo,
  deleteSlo, calculateSlo, calculateAllSlo,
  getTracingList, getTracingStats, getTracingDetail,
} from '../../api/slo';

const { t, locale } = useI18n();

const mainTab = ref('slo');
const tracingLoading = ref(false);
const tracingList = ref([]);
const tracingStats = reactive({ total: 0, slow: 0, avg_duration_ms: 0, error_rate: 0 });

const loading = ref(false);
const calculating = ref(false);
const saving = ref(false);
const sloList = ref([]);
const total = ref(0);
const recentEvents = ref([]);
const currentSlo = ref(null);
const detailVisible = ref(false);
const formRef = ref(null);

const stats = reactive({
  total_slo: 0,
  active_slo: 0,
  healthy_slo: 0,
  exhausted_slo: 0,
});

const pagination = reactive({
  page: 1,
  perPage: 20,
});

const dialog = reactive({
  visible: false,
  isEdit: false,
  editId: null,
});

const form = reactive({
  name: '',
  service_name: '',
  sli_type: 'availability',
  target: 99.9,
  window_days: 30,
  description: '',
  burn_rate_alerts: [],
});

const burnRateAlerts = reactive([]);

const sliTypeKeys = ['latency', 'availability', 'throughput', 'error_rate'];

const sliTypeOptions = computed(() =>
  Object.fromEntries(sliTypeKeys.map((key) => [key, t(`slo_page.sli_types.${key}`)])),
);

const eventTypeKeys = ['budget_exhausted', 'budget_warning', 'burn_rate_alert', 'budget_reset'];

const eventTypeLabels = computed(() =>
  Object.fromEntries(eventTypeKeys.map((key) => [key, t(`slo_page.event_types.${key}`)])),
);

const rules = computed(() => ({
  name: [{ required: true, message: t('slo_page.validation.name_required'), trigger: 'blur' }],
  service_name: [{ required: true, message: t('slo_page.validation.service_required'), trigger: 'blur' }],
  sli_type: [{ required: true, message: t('slo_page.validation.sli_type_required'), trigger: 'change' }],
  target: [{ required: true, message: t('slo_page.validation.target_required'), trigger: 'blur' }],
}));

function sliTypeLabel(type) {
  return sliTypeOptions.value[type] || type;
}

function sliTypeTag(type) {
  const map = { latency: 'warning', availability: 'success', throughput: 'primary', error_rate: 'danger' };
  return map[type] || 'info';
}

function sliStatusTag(sli, target) {
  if (sli == null) return 'info';
  if (sli >= target) return 'success';
  if (sli >= target * 0.95) return 'warning';
  return 'danger';
}

function budgetColor(remaining) {
  if (remaining == null) return '';
  if (remaining <= 0) return 'text-red-500';
  if (remaining < 10) return 'text-orange-500';
  return 'text-green-500';
}

function eventTypeTag(type) {
  const map = { budget_exhausted: 'danger', budget_warning: 'warning', burn_rate_alert: 'info', budget_reset: 'success' };
  return map[type] || 'info';
}

function eventTypeLabel(type) {
  return eventTypeLabels.value[type] || type;
}

function formatTime(time) {
  if (!time) return '-';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return new Date(time).toLocaleString(loc);
}

function totalBudgetStr(slo) {
  if (!slo) return '-';
  const totalMin = slo.window_days * 24 * 60 * (1 - slo.target / 100);
  return t('slo_page.units.minutes', { n: totalMin.toFixed(2) });
}

function addAlert() {
  burnRateAlerts.push({ window_hours: 1, threshold: 2 });
}

function removeAlert(idx) {
  burnRateAlerts.splice(idx, 1);
}

async function fetchDashboard() {
  try {
    const { data } = await getSloDashboard();
    Object.assign(stats, data);
    recentEvents.value = data.recent_events || [];
  } catch (e) {
    console.error(t('slo_page.messages.dashboard_failed'), e);
  }
}

async function fetchList() {
  loading.value = true;
  try {
    const { data } = await getSloList({
      page: pagination.page,
      per_page: pagination.perPage,
    });
    sloList.value = data.data || [];
    total.value = data.total || 0;
  } catch (e) {
    console.error(t('slo_page.messages.list_failed'), e);
  } finally {
    loading.value = false;
  }
}

function refreshList() {
  pagination.page = 1;
  fetchList();
  fetchDashboard();
}

function openCreateDialog() {
  dialog.isEdit = false;
  dialog.editId = null;
  form.name = '';
  form.service_name = '';
  form.sli_type = 'availability';
  form.target = 99.9;
  form.window_days = 30;
  form.description = '';
  burnRateAlerts.length = 0;
  dialog.visible = true;
}

function openEditDialog(row) {
  dialog.isEdit = true;
  dialog.editId = row.id;
  form.name = row.name;
  form.service_name = row.service_name;
  form.sli_type = row.sli_type;
  form.target = row.target;
  form.window_days = row.window_days;
  form.description = row.description || '';
  burnRateAlerts.length = 0;
  if (row.burn_rate_alerts) {
    row.burn_rate_alerts.forEach(a => burnRateAlerts.push({ ...a }));
  }
  dialog.visible = true;
}

async function handleSave() {
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  saving.value = true;
  try {
    const payload = {
      ...form,
      burn_rate_alerts: burnRateAlerts.length > 0 ? burnRateAlerts.map(a => ({ ...a })) : null,
    };

    if (dialog.isEdit) {
      await updateSlo(dialog.editId, payload);
      ElMessage.success(t('slo_page.messages.updated'));
    } else {
      await createSlo(payload);
      ElMessage.success(t('slo_page.messages.created'));
    }
    dialog.visible = false;
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('slo_page.messages.save_failed'));
  } finally {
    saving.value = false;
  }
}

async function handleDelete(row) {
  try {
    await deleteSlo(row.id);
    ElMessage.success(t('slo_page.messages.deleted'));
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(t('slo_page.messages.delete_failed'));
  }
}

async function openDetail(row) {
  try {
    const { data } = await getSlo(row.id);
    currentSlo.value = data;
    detailVisible.value = true;
  } catch (e) {
    ElMessage.error(t('slo_page.messages.detail_failed'));
  }
}

async function handleCalculateAll() {
  calculating.value = true;
  try {
    const { data } = await calculateAllSlo();
    ElMessage.success(data.message || t('slo_page.messages.calculate_done'));
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(t('slo_page.messages.calculate_failed'));
  } finally {
    calculating.value = false;
  }
}

async function fetchTracing() {
  tracingLoading.value = true;
  try {
    const [{ data: listRes }, { data: statsRes }] = await Promise.all([
      getTracingList({ per_page: 20 }),
      getTracingStats(),
    ]);
    tracingList.value = listRes.data?.data || listRes.data || [];
    Object.assign(tracingStats, statsRes.data || {});
  } catch (e) {
    console.error(t('slo_page.messages.tracing_failed'), e);
  } finally {
    tracingLoading.value = false;
  }
}

async function openTracingDetail(id) {
  try {
    const { data } = await getTracingDetail(id);
    ElMessage.info(`Trace #${id}: ${data.data?.path || data.path || t('slo_page.messages.trace_loaded')}`);
  } catch {
    ElMessage.error(t('slo_page.messages.detail_failed'));
  }
}

onMounted(() => {
  fetchDashboard();
  fetchList();
  fetchTracing();
});
</script>

<style scoped>
.stat-card {
  text-align: center;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  line-height: 1.2;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
.font-mono {
  font-family: 'SF Mono', 'Fira Code', monospace;
}
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-3 { margin-top: 12px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-blue-500 { color: #0f172a; }
.text-green-500 { color: #67c23a; }
.text-red-500 { color: #f56c6c; }
.text-orange-500 { color: #e6a23c; }
.text-purple-500 { color: #b37feb; }
.text-gray-500 { color: #909399; }
.flex { display: flex; }
.flex-col { flex-direction: column; }
.gap-2 { gap: 8px; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.w-full { width: 100%; }
</style>
