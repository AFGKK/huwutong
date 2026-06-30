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
        <el-icon><Plus /></el-icon> 新建SOP模板
      </el-button>
      <el-button @click="fetchData">
        <el-icon><Refresh /></el-icon> 刷新
      </el-button>
    </div>

    <!-- SOP模板列表 -->
    <el-tabs v-model="sopTab">
      <el-tab-pane label="SOP模板" name="templates">
        <el-table :data="templates" v-loading="loading" stripe>
          <el-table-column prop="name" label="SOP名称" min-width="160" />
          <el-table-column prop="severity" label="等级" width="90">
            <template #default="{ row }">
              <el-tag :type="severityType(row.severity)" effect="plain" size="small">{{ severityLabel(row.severity) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag :type="row.status === 'active' ? 'success' : 'info'" effect="plain" size="small">{{ row.status === 'active' ? '启用' : row.status }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="自动执行" width="90">
            <template #default="{ row }"><el-tag :type="row.is_auto_execute ? 'warning' : 'info'" effect="plain" size="small">{{ row.is_auto_execute ? '是' : '否' }}</el-tag></template>
          </el-table-column>
          <el-table-column label="步骤数" width="80">
            <template #default="{ row }">{{ row.steps?.length || 0 }}</template>
          </el-table-column>
          <el-table-column label="优先级" width="70" prop="sort_order" />
          <el-table-column label="创建者" width="120">
            <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
          </el-table-column>
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" type="primary" @click="viewTemplate(row)">详情</el-button>
              <el-button text size="small" type="success" @click="handleExecute(row)">执行</el-button>
              <el-button text size="small" @click="editTemplate(row)">编辑</el-button>
              <el-button text size="small" type="danger" @click="handleDelete(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="执行记录" name="executions">
        <el-table :data="executions" v-loading="execLoading" stripe>
          <el-table-column label="SOP模板" min-width="160">
            <template #default="{ row }">{{ row.template?.name || '已删除' }}</template>
          </el-table-column>
          <el-table-column label="触发方式" width="100">
            <template #default="{ row }">
              <el-tag :type="row.triggered_by === 'event' ? 'warning' : 'primary'" size="small" effect="plain">
                {{ row.triggered_by === 'event' ? '自动' : '手动' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="110">
            <template #default="{ row }">
              <el-tag :type="execStatusType(row.status)" size="small" effect="plain">{{ execStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="进度" width="120">
            <template #default="{ row }">{{ row.completed_steps }}/{{ row.total_steps }} 步骤</template>
          </el-table-column>
          <el-table-column label="关联事件" width="150">
            <template #default="{ row }">{{ row.event?.event_type || '-' }}</template>
          </el-table-column>
          <el-table-column label="总结" min-width="150">
            <template #default="{ row }">{{ row.result_summary || '-' }}</template>
          </el-table-column>
          <el-table-column label="执行时间" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 创建/编辑SOP对话框 -->
    <el-dialog v-model="showCreateTemplate" :title="editId ? '编辑SOP模板' : '新建SOP模板'" width="700px">
      <el-form :model="form" label-width="120px" size="small">
        <el-form-item label="名称" required>
          <el-input v-model="form.name" maxlength="200" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="严重等级">
              <el-select v-model="form.severity" style="width:100%">
                <el-option label="信息" value="info" />
                <el-option label="警告" value="warning" />
                <el-option label="严重" value="critical" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="状态">
              <el-select v-model="form.status" style="width:100%">
                <el-option label="启用" value="active" />
                <el-option label="停用" value="inactive" />
                <el-option label="草稿" value="draft" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="自动执行">
              <el-switch v-model="form.is_auto_execute" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="优先级">
          <el-input-number v-model="form.sort_order" :min="0" :max="999" style="width:120px" />
        </el-form-item>

        <!-- 触发条件 -->
        <el-divider>触发条件</el-divider>
        <el-form-item label="事件类型">
          <el-select v-model="form.triggerEventTypes" multiple placeholder="所有类型" style="width:100%">
            <el-option v-for="t in eventTypeOptions" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="阈值">
              <el-input-number v-model="form.triggerThreshold" :min="0" style="width:100%" placeholder="0=不限制" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="时间窗口(分)">
              <el-input-number v-model="form.triggerWindow" :min="0" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>

        <!-- SOP步骤 -->
        <el-divider>SOP步骤</el-divider>
        <div v-for="(step, idx) in form.steps" :key="idx" class="sop-step-row">
          <el-row :gutter="8" align="middle">
            <el-col :span="2"><el-tag size="small">#{{ idx + 1 }}</el-tag></el-col>
            <el-col :span="6">
              <el-select v-model="step.action_type" placeholder="动作类型" style="width:100%">
                <el-option v-for="at in actionTypeOptions" :key="at.value" :label="at.label" :value="at.value" />
              </el-select>
            </el-col>
            <el-col :span="12">
              <el-input v-model="step.description" placeholder="步骤描述" />
            </el-col>
            <el-col :span="4" class="text-right">
              <el-button text size="small" type="danger" @click="removeStep(idx)">
                <el-icon><Delete /></el-icon>
              </el-button>
            </el-col>
          </el-row>
        </div>
        <el-button size="small" @click="addStep">
          <el-icon><Plus /></el-icon> 添加步骤
        </el-button>
      </el-form>
      <template #footer>
        <el-button @click="showCreateTemplate = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">{{ editId ? '更新' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 执行SOP对话框 -->
    <el-dialog v-model="showExecuteDlg" title="执行SOP" width="400px">
      <p>确定执行「{{ executeTarget?.name }}」？</p>
      <el-form label-width="100px" size="small" class="mt-3">
        <el-form-item label="关联事件ID">
          <el-input v-model="executeEventId" placeholder="可选" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showExecuteDlg = false">取消</el-button>
        <el-button type="primary" :loading="executing" @click="confirmExecute">执行</el-button>
      </template>
    </el-dialog>

    <!-- SOP详情对话框 -->
    <el-dialog v-model="showDetail" :title="detail?.name || 'SOP详情'" width="600px">
      <el-descriptions v-if="detail" :column="2" border>
        <el-descriptions-item label="名称" :span="2">{{ detail.name }}</el-descriptions-item>
        <el-descriptions-item label="等级">
          <el-tag :type="severityType(detail.severity)" effect="plain" size="small">{{ severityLabel(detail.severity) }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="detail.status === 'active' ? 'success' : 'info'" effect="plain" size="small">{{ detail.status }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="自动执行">{{ detail.is_auto_execute ? '是' : '否' }}</el-descriptions-item>
        <el-descriptions-item label="优先级">{{ detail.sort_order }}</el-descriptions-item>
      </el-descriptions>
      <div v-if="detail?.description" class="mt-3"><strong>描述：</strong>{{ detail.description }}</div>
      <div v-if="detail?.steps?.length" class="mt-3">
        <h5>SOP步骤 ({{ detail.steps.length }})：</h5>
        <div v-for="(s, i) in detail.steps" :key="i" class="sop-step-detail">
          <el-tag size="small">#{{ i + 1 }}</el-tag>
          <el-tag type="primary" size="small" effect="plain" class="ml-1">{{ actionLabel(s.action_type) }}</el-tag>
          <span class="ml-1">{{ s.description }}</span>
        </div>
      </div>
      <div v-if="detail?.trigger_conditions" class="mt-3">
        <h5>触发条件：</h5>
        <pre class="json-pre">{{ formatJson(detail.trigger_conditions) }}</pre>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Delete } from '@element-plus/icons-vue';
import sopApi from '@/api/securitySop';

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
const actionTypeOptions = [
  { value: 'log_event', label: '记录日志' },
  { value: 'notify_admin', label: '通知管理员' },
  { value: 'notify_user', label: '通知用户' },
  { value: 'block_ip', label: '封禁IP' },
  { value: 'terminate_sessions', label: '终止会话' },
  { value: 'disable_account', label: '禁用账户' },
  { value: 'require_mfa', label: '强制MFA' },
  { value: 'send_alert_email', label: '发送告警邮件' },
  { value: 'create_ticket', label: '创建工单' },
  { value: 'custom_webhook', label: '自定义Webhook' },
];

const statItems = computed(() => [
  { label: '活跃SOP', value: stats.value.active_templates || 0, color: '#67c23a' },
  { label: '总SOP', value: stats.value.total_templates || 0, color: '#409eff' },
  { label: '执行次数', value: stats.value.total_executions || 0, color: '#b37feb' },
  { label: '自动执行', value: stats.value.auto_executions || 0, color: '#e6a23c' },
  { label: '失败', value: stats.value.failed_executions || 0, color: '#f56c6c' },
  { label: '待处理事件', value: stats.value.open_events || 0, color: '#f56c6c' },
]);

function severityType(s) {
  return { info: 'info', warning: 'warning', critical: 'danger' }[s] || 'info';
}
function severityLabel(s) {
  return { info: '信息', warning: '警告', critical: '严重' }[s] || s;
}
function execStatusType(s) {
  return { pending: 'info', in_progress: 'warning', completed: 'success', failed: 'danger', partially_completed: 'warning' }[s] || 'info';
}
function execStatusLabel(s) {
  return { pending: '待处理', in_progress: '执行中', completed: '已完成', failed: '失败', partially_completed: '部分完成' }[s] || s;
}
function actionLabel(v) {
  const map = Object.fromEntries(actionTypeOptions.map(o => [o.value, o.label]));
  return map[v] || v;
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
function formatTime(t) {
  if (!t) return '-';
  const d = new Date(t);
  return d.toLocaleDateString('zh-CN') + ' ' + d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

import { computed } from 'vue';

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

    // Clean null values
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
      ElMessage.success(editId.value ? '已更新' : '已创建');
      showCreateTemplate.value = false;
      resetForm();
      fetchData();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
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
      ElMessage.success('SOP已执行');
      showExecuteDlg.value = false;
      fetchExecutions();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '执行失败'); }
  finally { executing.value = false; }
}
async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(`确认删除SOP「${row.name}」？`, '提示', { type: 'warning' });
    const res = await sopApi.deleteSopTemplate(row.id);
    if (res.data.success) { ElMessage.success('已删除'); fetchData(); }
  } catch (e) { if (e !== 'cancel') ElMessage.error('删除失败'); }
}

// Watch tab change to load execution data
import { watch } from 'vue';
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
