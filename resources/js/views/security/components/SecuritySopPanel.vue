<template>
  <div class="sop-panel">
    <!-- SOP统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4" v-for="s in statItems" :key="s.label">
        <el-card shadow="hover" class="sop-stat-card">
          <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
          <div class="stat-label">{{ s.label }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <div class="tab-toolbar">
      <el-button type="primary" @click="showCreateTemplate = true">
        <el-icon><Plus /></el-icon> {{ t('security_sop_panel.create_template') }}
      </el-button>
      <el-button @click="fetchData">
        <el-icon><Refresh /></el-icon> {{ t('security_page.refresh') }}
      </el-button>
    </div>

    <!-- SOP模板列表 -->
    <el-tabs v-model="sopTab">
      <el-tab-pane :label="t('security_sop_panel.tabs.templates')" name="templates">
        <el-table :data="templates" v-loading="loading" stripe>
          <el-table-column prop="name" :label="t('security_sop_panel.cols.name')" min-width="160" />
          <el-table-column prop="severity" :label="t('security_sop_panel.cols.severity')" width="90">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.status')" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'info'" effect="plain" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.auto_execute')" width="90">
            <template #default="{ row }"><el-tag :type="row.is_auto_execute ? 'warning' : 'info'" effect="plain" size="small">{{ row.is_auto_execute ? t('security_sop_panel.yes') : t('security_sop_panel.no') }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.step_count')" width="80">
            <template #default="{ row }">{{ row.steps?.length || 0 }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.priority')" width="70" prop="sort_order" />
          <el-table-column :label="t('security_sop_panel.cols.creator')" width="120">
            <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.actions')" width="220" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" type="primary" @click="viewTemplate(row)">{{ t('actions.view_details') }}</el-button>
              <el-button text size="small" type="success" @click="handleExecute(row)">{{ t('security_sop_panel.execute') }}</el-button>
              <el-button text size="small" @click="editTemplate(row)">{{ t('actions.edit') }}</el-button>
              <el-button text size="small" type="danger" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('security_sop_panel.tabs.executions')" name="executions">
        <el-table :data="executions" v-loading="execLoading" stripe>
          <el-table-column :label="t('security_sop_panel.cols.template')" min-width="160">
            <template #default="{ row }">{{ row.template?.name || t('security_sop_panel.deleted_template') }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.trigger')" width="100">
            <template #default="{ row }">
              <el-tag :type="row.triggered_by === 'event' ? 'warning' : 'primary'" size="small" effect="plain">
                {{ row.triggered_by === 'event' ? t('security_sop_panel.trigger.auto') : t('security_sop_panel.trigger.manual') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.status')" width="110">
            <template #default="{ row }">
              <el-tag :type="execStatusType(row.status)" size="small" effect="plain">{{ execStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.progress')" width="120">
            <template #default="{ row }">{{ t('security_sop_panel.progress_steps', { completed: row.completed_steps, total: row.total_steps }) }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.related_event')" width="150">
            <template #default="{ row }">{{ row.event?.event_type || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.summary')" min-width="150">
            <template #default="{ row }">{{ row.result_summary || '-' }}</template>
          </el-table-column>
          <el-table-column :label="t('security_sop_panel.cols.executed_at')" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建/编辑SOP对话框 -->
    <el-dialog v-model="showCreateTemplate" :title="editId ? t('security_sop_panel.edit_template') : t('security_sop_panel.create_template')" width="700px">
      <el-form :model="form" label-width="120px" size="small">
        <el-form-item :label="t('security_sop_panel.form.name')" required>
          <el-input v-model="form.name" maxlength="200" />
        </el-form-item>
        <el-form-item :label="t('security_sop_panel.form.description')">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item :label="t('security_sop_panel.form.severity')">
              <el-select v-model="form.severity" style="width:100%">
                <el-option v-for="opt in severityOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('security_sop_panel.form.status')">
              <el-select v-model="form.status" style="width:100%">
                <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('security_sop_panel.form.auto_execute')">
              <el-switch v-model="form.is_auto_execute" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('security_sop_panel.form.priority')">
          <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width:120px" />
        </el-form-item>

        <!-- 触发条件 -->
        <el-divider>{{ t('security_sop_panel.form.trigger_conditions') }}</el-divider>
        <el-form-item :label="t('security_sop_panel.form.event_types')">
          <el-select v-model="form.triggerEventTypes" multiple :placeholder="t('security_sop_panel.form.all_event_types')" style="width:100%">
            <el-option v-for="ev in eventTypeOptions" :key="ev" :label="ev" :value="ev" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('security_sop_panel.form.threshold')">
              <el-input-number v-model="form.triggerThreshold" :min="0" style="width:100%" :placeholder="t('security_sop_panel.form.threshold_ph')" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('security_sop_panel.form.time_window')">
              <el-input-number v-model="form.triggerWindow" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- SOP步骤 -->
        <el-divider>{{ t('security_sop_panel.form.sop_steps') }}</el-divider>
        <div v-for="(step, idx) in form.steps" :key="idx" class="sop-step-row">
          <el-row :gutter="8" align="middle">
            <el-col :span="2"><el-tag size="small">#{{ idx + 1 }}</el-tag></el-col>
            <el-col :span="6">
              <el-select v-model="step.action_type" :placeholder="t('security_sop_panel.form.action_type')" style="width:100%">
                <el-option v-for="at in actionTypeOptions" :key="at.value" :label="at.label" :value="at.value" />
              </el-select>
            </el-col>
            <el-col :span="12">
              <el-input v-model="step.description" :placeholder="t('security_sop_panel.form.step_description')" />
            </el-col>
            <el-col :span="4" class="text-right">
              <el-button text size="small" type="danger" @click="removeStep(idx)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-col>
          </el-row>
        </div>
        <el-button size="small" @click="addStep">
          <el-icon><Plus /></el-icon> {{ t('security_sop_panel.form.add_step') }}
        </el-button>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTemplate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">{{ editId ? t('actions.update') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 执行SOP对话框 -->
    <el-dialog v-model="showExecuteDlg" :title="t('security_sop_panel.execute_title')" width="400px">
      <p>{{ t('security_sop_panel.execute_confirm', { name: executeTarget?.name || '' }) }}</p>
      <el-form label-width="100px" size="small" class="mt-3">
        <el-form-item :label="t('security_sop_panel.form.related_event_id')">
          <el-input v-model="executeEventId" :placeholder="t('security_sop_panel.form.optional')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showExecuteDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="executing" @click="confirmExecute">{{ t('security_sop_panel.execute') }}</el-button>
      </template>
    </el-dialog>

    <!-- SOP详情对话框 -->
    <el-dialog v-model="showDetail" :title="detail?.name || t('security_sop_panel.detail_title')" width="600px">
      <el-descriptions v-if="detail" :column="2" border>
        <el-descriptions-item :label="t('security_sop_panel.form.name')" :span="2">{{ detail.name }}</el-descriptions-item>
        <el-descriptions-item :label="t('security_sop_panel.cols.severity')">
          <el-tag :type="severityType(detail.severity)" effect="plain" size="small">{{ severityLabel(detail.severity) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('security_sop_panel.cols.status')">
          <el-tag :type="detail.status === 'active' ? 'success' : 'info'" effect="plain" size="small">{{ statusLabel(detail.status) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('security_sop_panel.cols.auto_execute')">{{ detail.is_auto_execute ? t('security_sop_panel.yes') : t('security_sop_panel.no') }}</el-descriptions-item>
        <el-descriptions-item :label="t('security_sop_panel.cols.priority')">{{ detail.sort_order }}</el-descriptions-item>
      </el-descriptions>
      <div v-if="detail?.description" class="mt-3"><strong>{{ t('security_sop_panel.detail.description') }}</strong>{{ detail.description }}</div>
      <div v-if="detail?.steps?.length" class="mt-3">
        <h5>{{ t('security_sop_panel.detail.steps_title', { count: detail.steps.length }) }}</h5>
        <div v-for="(s, i) in detail.steps" :key="i" class="sop-step-detail">
          <el-tag size="small">#{{ i + 1 }}</el-tag>
          <el-tag type="primary" size="small" effect="plain" class="ml-1">{{ actionLabel(s.action_type) }}</el-tag>
          <span class="ml-1">{{ s.description }}</span>
        </div>
      </div>
      <div v-if="detail?.trigger_conditions" class="mt-3">
        <h5>{{ t('security_sop_panel.detail.trigger_conditions') }}</h5>
        <pre class="json-pre">{{ formatJson(detail.trigger_conditions) }}</pre>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Delete } from '@element-plus/icons-vue';
import sopApi from '@/api/securitySop';

const { t, locale } = useI18n();

const loading = ref(false);
const execLoading = ref(false);
const templates = ref([]);
const executions = ref([]);
const sopTab = ref('templates');
const showCreateTemplate = ref(false);
const showExecuteDlg = ref(false);
const showDetail = ref(false);
const editId = ref(null);
const saving = ref(false);
const executing = ref(false);
const executeTarget = ref(null);
const executeEventId = ref('');
const detail = ref(null);
const stats = ref({});
const form = reactive({
  name: '', description: '', severity: 'warning', status: 'active',
  is_auto_execute: false, sort_order: 0,
  triggerEventTypes: [], triggerThreshold: 0, triggerWindow: 0,
  steps: [],
});
const eventTypeOptions = ['login_failed', 'login_success', 'logout', 'session_expired', 'ip_blocked', 'mfa_challenge', 'password_changed', 'suspicious_activity', 'geo_anomaly'];

const SEVERITY_KEYS = ['info', 'warning', 'critical'];
const STATUS_KEYS = ['active', 'inactive', 'draft'];
const EXEC_STATUS_KEYS = ['pending', 'in_progress', 'completed', 'failed', 'partially_completed'];
const ACTION_TYPE_KEYS = [
  'log_event', 'notify_admin', 'notify_user', 'block_ip', 'terminate_sessions',
  'disable_account', 'require_mfa', 'send_alert_email', 'create_ticket', 'custom_webhook',
];

const statItems = computed(() => [
  { label: t('security_sop_panel.stats.active_templates'), value: stats.value.active_templates || 0, color: '#67c23a' },
  { label: t('security_sop_panel.stats.total_templates'), value: stats.value.total_templates || 0, color: '#0f172a' },
  { label: t('security_sop_panel.stats.total_executions'), value: stats.value.total_executions || 0, color: '#b37feb' },
  { label: t('security_sop_panel.stats.auto_executions'), value: stats.value.auto_executions || 0, color: '#e6a23c' },
  { label: t('security_sop_panel.stats.failed_executions'), value: stats.value.failed_executions || 0, color: '#f56c6c' },
  { label: t('security_sop_panel.stats.open_events'), value: stats.value.open_events || 0, color: '#f56c6c' },
]);

const severityOptions = computed(() =>
  SEVERITY_KEYS.map((value) => ({ value, label: t(`security_sop_panel.severity.${value}`) })),
);
const statusOptions = computed(() =>
  STATUS_KEYS.map((value) => ({ value, label: t(`security_sop_panel.status.${value}`) })),
);
const actionTypeOptions = computed(() =>
  ACTION_TYPE_KEYS.map((value) => ({ value, label: t(`security_sop_panel.action_types.${value}`) })),
);

function severityType(s) {
  return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info';
}
function severityLabel(s) {
  return t(`security_sop_panel.severity.${s}`) || s;
}
function statusLabel(s) {
  return t(`security_sop_panel.status.${s}`) || s;
}
function execStatusType(s) {
  return { pending: 'info', in_progress: 'warning', completed: 'success', failed: 'danger', partially_completed: 'warning' }[s] || 'info';
}
function execStatusLabel(s) {
  return t(`security_sop_panel.exec_status.${s}`) || s;
}
function actionLabel(v) {
  return t(`security_sop_panel.action_types.${v}`) || v;
}
function addStep() {
  form.steps.push({ action_type: 'log_event', description: '', order: form.steps.length + 1 });
}
function removeStep(idx) {
  form.steps.splice(idx, 1);
}
function formatJson(obj) {
  try { return JSON.stringify(obj, null, 2); } catch { return String(obj); }
}
function formatTime(time) {
  if (!time) return '-';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  const d = new Date(time);
  return d.toLocaleDateString(loc) + ' ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
}

async function fetchData() {
  loading.value = true;
  try {
    const [tmplRes, statsRes] = await Promise.all([
      sopApi.getSopTemplates({ per_page: 50 }),
      sopApi.getSopStats(),
    ]);
    if (tmplRes.data.success) templates.value = tmplRes.data.data?.data || tmplRes.data.data || [];
    if (statsRes.data.success) stats.value = statsRes.data.data || {};
  } catch (e) { /* ignore */ }
  finally { loading.value = false; }
}

async function fetchExecutions() {
  execLoading.value = true;
  try {
    const res = await sopApi.getSopExecutions({ per_page: 20 });
    if (res.data.success) executions.value = res.data.data?.data || res.data.data || [];
  } catch (e) { /* ignore */ }
  finally { execLoading.value = false; }
}

async function submitForm() {
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      description: form.description,
      severity: form.severity,
      status: form.status,
      is_auto_execute: form.is_auto_execute,
      sort_order: form.sort_order || 0,
      trigger_conditions: form.triggerEventTypes.length || form.triggerThreshold > 0
        ? {
            event_types: form.triggerEventTypes.length ? form.triggerEventTypes : null,
            threshold: form.triggerThreshold || null,
            time_window_minutes: form.triggerWindow || null,
          }
        : null,
      steps: form.steps.map((s, i) => ({ ...s, order: i + 1 })),
    };

    if (payload.trigger_conditions) {
      Object.keys(payload.trigger_conditions).forEach(k => {
        if (payload.trigger_conditions[k] === null) delete payload.trigger_conditions[k];
      });
      if (!Object.keys(payload.trigger_conditions).length) payload.trigger_conditions = null;
    }

    const res = editId.value
      ? await sopApi.updateSopTemplate(editId.value, payload)
      : await sopApi.createSopTemplate(payload);

    if (res.data.success) {
      ElMessage.success(editId.value ? t('security_sop_panel.messages.updated') : t('security_sop_panel.messages.created'));
      showCreateTemplate.value = false;
      resetForm();
      fetchData();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { saving.value = false; }
}

function resetForm() {
  editId.value = null;
  form.name = ''; form.description = ''; form.severity = 'warning';
  form.status = 'active'; form.is_auto_execute = false; form.sort_order = 0;
  form.triggerEventTypes = []; form.triggerThreshold = 0; form.triggerWindow = 0;
  form.steps = [];
}

function viewTemplate(row) {
  detail.value = row;
  showDetail.value = true;
}
function editTemplate(row) {
  editId.value = row.id;
  form.name = row.name;
  form.description = row.description || '';
  form.severity = row.severity;
  form.status = row.status;
  form.is_auto_execute = row.is_auto_execute;
  form.sort_order = row.sort_order || 0;

  const cond = row.trigger_conditions || {};
  form.triggerEventTypes = cond.event_types || [];
  form.triggerThreshold = cond.threshold || 0;
  form.triggerWindow = cond.time_window_minutes || 0;

  form.steps = (row.steps || []).map((s, i) => ({ ...s, order: i + 1 }));
  showCreateTemplate.value = true;
}
function handleExecute(row) {
  executeTarget.value = row;
  executeEventId.value = '';
  showExecuteDlg.value = true;
}
async function confirmExecute() {
  executing.value = true;
  try {
    const res = await sopApi.executeSop(executeTarget.value.id, executeEventId.value || null);
    if (res.data.success) {
      ElMessage.success(t('security_sop_panel.messages.executed'));
      showExecuteDlg.value = false;
      fetchExecutions();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('security_sop_panel.messages.execute_failed')); }
  finally { executing.value = false; }
}
async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      t('security_sop_panel.messages.delete_confirm', { name: row.name }),
      t('actions.confirm'),
      { type: 'warning' },
    );
    const res = await sopApi.deleteSopTemplate(row.id);
    if (res.data.success) { ElMessage.success(t('security_sop_panel.messages.deleted')); fetchData(); }
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('security_sop_panel.messages.delete_failed')); }
}

watch(sopTab, (v) => {
  if (v === 'executions') fetchExecutions();
});

onMounted(() => { fetchData(); });
</script>

<style scoped>
.sop-panel { }
.tab-toolbar { margin-bottom: 12px; }
.sop-stat-card { text-align: center; }
.sop-stat-card .stat-value { font-size: 22px; font-weight: 700; }
.sop-stat-card .stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.sop-step-row { margin-bottom: 8px; }
.sop-step-detail { margin: 4px 0; }
.json-pre { background: #f5f7fa; padding: 8px; border-radius: 4px; font-size: 12px; overflow-x: auto; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.ml-1 { margin-left: 4px; }
.text-right { text-align: right; }
</style>
