<template>
  <div class="slo-management">
    <!-- 顶部统计卡片 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-blue-500">{{ stats.active_slo || 0 }}</div>
          <div class="stat-label">活跃 SLO</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-green-500">{{ stats.healthy_slo || 0 }}</div>
          <div class="stat-label">健康</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-red-500">{{ stats.exhausted_slo || 0 }}</div>
          <div class="stat-label">错误预算耗尽</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value text-purple-500">{{ stats.total_slo || 0 }}</div>
          <div class="stat-label">总计 SLO</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <el-row :gutter="16" class="mb-3">
      <el-col :span="12">
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon> 新建 SLO
        </el-button>
        <el-button @click="refreshList" :loading="loading">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button @click="handleCalculateAll" :loading="calculating">
          <el-icon><DataAnalysis /></el-icon> 计算全部错误预算
        </el-button>
      </el-col>
    </el-row>

    <!-- SLO列表 -->
    <el-card shadow="never">
      <el-table :data="sloList" v-loading="loading" stripe style="width: 100%">
        <el-table-column prop="name" label="SLO 名称" min-width="160" />
        <el-table-column prop="service_name" label="服务" width="100" />
        <el-table-column prop="sli_type" label="SLI 类型" width="100">
          <template #default="{ row }">
            <el-tag :type="sliTypeTag(row.sli_type)" size="small">
              {{ sliTypeLabel(row.sli_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="target" label="SLO 目标" width="100" align="center">
          <template #default="{ row }">
            <span class="font-mono">{{ row.target }}%</span>
          </template>
        </el-table-column>
        <el-table-column prop="current_sli" label="当前 SLI" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="sliStatusTag(row.current_sli, row.target)" size="small">
              {{ row.current_sli != null ? row.current_sli + '%' : '-' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remaining_budget" label="剩余预算(分钟)" width="130" align="right">
          <template #default="{ row }">
            <span :class="budgetColor(row.remaining_budget)" class="font-mono">
              {{ row.remaining_budget != null ? row.remaining_budget.toFixed(1) : '-' }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="burn_rate" label="燃烧率" width="100" align="center">
          <template #default="{ row }">
            <span class="font-mono">{{ row.burn_rate != null ? row.burn_rate.toFixed(2) : '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="window_days" label="窗口" width="80" align="center">
          <template #default="{ row }">{{ row.window_days }}天</template>
        </el-table-column>
        <el-table-column prop="is_active" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
              {{ row.is_active ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="openDetail(row)">详情</el-button>
            <el-button size="small" @click="openEditDialog(row)">编辑</el-button>
            <el-popconfirm title="确认删除?" @confirm="handleDelete(row)">
              <template #reference>
                <el-button size="small" type="danger">删除</el-button>
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
      :title="dialog.isEdit ? '编辑 SLO' : '新建 SLO'"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" label-position="top">
        <el-form-item label="SLO 名称" prop="name">
          <el-input v-model="form.name" placeholder="例如: API核心接口可用性" />
        </el-form-item>
        <el-form-item label="目标服务" prop="service_name">
          <el-input v-model="form.service_name" placeholder="例如: api, web, worker" />
        </el-form-item>
        <el-form-item label="SLI 类型" prop="sli_type">
          <el-select v-model="form.sli_type" style="width: 100%">
            <el-option v-for="(label, val) in sliTypeOptions" :key="val" :label="label" :value="val" />
          </el-select>
        </el-form-item>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="SLO 目标 (%)" prop="target">
              <el-input-number v-model="form.target" :min="50" :max="99.999" :step="0.1" :precision="2" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="滚动窗口 (天)" prop="window_days">
              <el-input-number v-model="form.window_days" :min="1" :max="365" style="width: 100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="燃烧率告警" prop="burn_rate_alerts">
          <div class="flex flex-col gap-2 w-full">
            <div v-for="(alert, idx) in burnRateAlerts" :key="idx" class="flex gap-2 items-center">
              <el-input-number v-model="alert.window_hours" :min="1" :max="24" size="small" placeholder="窗口(h)" />
              <span class="text-sm text-gray-500">小时内燃烧率超过</span>
              <el-input-number v-model="alert.threshold" :min="0.1" :max="100" :step="0.1" size="small" placeholder="阈值" />
              <span class="text-sm text-gray-500">倍</span>
              <el-button size="small" type="danger" :icon="Delete" circle @click="removeAlert(idx)" />
            </div>
            <el-button size="small" @click="addAlert">
              <el-icon><Plus /></el-icon> 添加告警规则
            </el-button>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailVisible" title="SLO 详情" width="700px" :close-on-click-modal="false">
      <template v-if="currentSlo">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="名称" :span="2">{{ currentSlo.name }}</el-descriptions-item>
          <el-descriptions-item label="服务">{{ currentSlo.service_name }}</el-descriptions-item>
          <el-descriptions-item label="SLI 类型">{{ sliTypeLabel(currentSlo.sli_type) }}</el-descriptions-item>
          <el-descriptions-item label="SLO 目标">{{ currentSlo.target }}%</el-descriptions-item>
          <el-descriptions-item label="当前 SLI">
            <el-tag :type="sliStatusTag(currentSlo.current_sli, currentSlo.target)" size="small">
              {{ currentSlo.current_sli != null ? currentSlo.current_sli + '%' : '-' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="窗口">{{ currentSlo.window_days }}天</el-descriptions-item>
          <el-descriptions-item label="错误预算总额" :span="2">
            {{ totalBudgetStr(currentSlo) }}
          </el-descriptions-item>
          <el-descriptions-item label="剩余预算">
            <span :class="budgetColor(currentSlo.remaining_budget)">
              {{ currentSlo.remaining_budget != null ? currentSlo.remaining_budget.toFixed(2) + ' 分钟' : '-' }}
            </span>
          </el-descriptions-item>
          <el-descriptions-item label="燃烧率">
            {{ currentSlo.burn_rate != null ? currentSlo.burn_rate.toFixed(2) + ' /天' : '-' }}
          </el-descriptions-item>
        </el-descriptions>

        <!-- 每日SLI趋势 -->
        <h4 class="mt-4 mb-2">每日 SLI 趋势</h4>
        <el-table :data="currentSlo.daily_records || []" size="small" max-height="300" stripe>
          <el-table-column prop="record_date" label="日期" width="120" />
          <el-table-column prop="total_requests" label="请求数" width="80" align="center" />
          <el-table-column prop="sli" label="SLI" width="80" align="center">
            <template #default="{ row }">
              {{ row.sli != null ? row.sli + '%' : '-' }}
            </template>
          </el-table-column>
          <el-table-column prop="budget_consumed" label="消耗预算(分钟)" width="120" align="right" />
        </el-table>

        <!-- 最近事件 -->
        <h4 class="mt-4 mb-2">最近事件</h4>
        <el-table :data="currentSlo.budget_events || []" size="small" max-height="200" stripe>
          <el-table-column prop="created_at" label="时间" width="160">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column prop="event_type" label="事件类型" width="130">
            <template #default="{ row }">
              <el-tag :type="eventTypeTag(row.event_type)" size="small">
                {{ eventTypeLabel(row.event_type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="budget_remaining" label="剩余预算" width="100" align="right">
            <template #default="{ row }">{{ row.budget_remaining.toFixed(1) }} 分钟</template>
          </el-table-column>
        </el-table>
      </template>
    </el-dialog>

    <!-- 近期SLO事件 -->
    <el-card shadow="never" class="mt-4">
      <template #header>
        <div class="flex justify-between items-center">
          <span>近期 SLO 事件</span>
        </div>
      </template>
      <el-table :data="recentEvents" size="small" stripe style="width: 100%">
        <el-table-column prop="definition.name" label="SLO" min-width="140" />
        <el-table-column prop="event_type" label="事件" width="140">
          <template #default="{ row }">
            <el-tag :type="eventTypeTag(row.event_type)" size="small">
              {{ eventTypeLabel(row.event_type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="budget_remaining" label="剩余预算(分钟)" width="120" align="right">
          <template #default="{ row }">{{ row.budget_remaining.toFixed(1) }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="160">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, Delete, DataAnalysis } from '@element-plus/icons-vue';
import {
  getSloDashboard, getSloList, getSlo, createSlo, updateSlo,
  deleteSlo, calculateSlo, calculateAllSlo
} from '../../api/slo';

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

const rules = {
  name: [{ required: true, message: '请输入SLO名称', trigger: 'blur' }],
  service_name: [{ required: true, message: '请输入目标服务', trigger: 'blur' }],
  sli_type: [{ required: true, message: '请选择SLI类型', trigger: 'change' }],
  target: [{ required: true, message: '请输入SLO目标', trigger: 'blur' }],
};

const sliTypeOptions = {
  latency: '延迟 (Latency)',
  availability: '可用性 (Availability)',
  throughput: '吞吐量 (Throughput)',
  error_rate: '错误率 (Error Rate)',
};

function sliTypeLabel(type) {
  return sliTypeOptions[type] || type;
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
  const map = { budget_exhausted: '预算耗尽', budget_warning: '预算警告', burn_rate_alert: '燃烧率告警', budget_reset: '预算重置' };
  return map[type] || type;
}

function formatTime(time) {
  if (!time) return '-';
  return new Date(time).toLocaleString('zh-CN');
}

function totalBudgetStr(slo) {
  if (!slo) return '-';
  const totalMin = slo.window_days * 24 * 60 * (1 - slo.target / 100);
  return totalMin.toFixed(2) + ' 分钟';
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
    console.error('获取SLO仪表盘失败', e);
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
    console.error('获取SLO列表失败', e);
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
      ElMessage.success('SLO已更新');
    } else {
      await createSlo(payload);
      ElMessage.success('SLO已创建');
    }
    dialog.visible = false;
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败');
  } finally {
    saving.value = false;
  }
}

async function handleDelete(row) {
  try {
    await deleteSlo(row.id);
    ElMessage.success('SLO已删除');
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error('删除失败');
  }
}

async function openDetail(row) {
  try {
    const { data } = await getSlo(row.id);
    currentSlo.value = data;
    detailVisible.value = true;
  } catch (e) {
    ElMessage.error('获取详情失败');
  }
}

async function handleCalculateAll() {
  calculating.value = true;
  try {
    const { data } = await calculateAllSlo();
    ElMessage.success(data.message || '计算完成');
    fetchList();
    fetchDashboard();
  } catch (e) {
    ElMessage.error('计算失败');
  } finally {
    calculating.value = false;
  }
}

onMounted(() => {
  fetchDashboard();
  fetchList();
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
.text-blue-500 { color: #409eff; }
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
