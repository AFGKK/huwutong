<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">SDK 完整性自检 &amp; 远程自毁</h2>
        <div class="flex gap-2">
          <el-button size="small" type="danger" @click="showBatchDestroy = true">批量销毁</el-button>
          <el-button size="small" @click="processExpired" :loading="processing">处理过期命令</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">总检查次数</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_checks || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">通过率</div>
            <div class="text-2xl font-bold mt-1" :class="passRateColor">{{ dashboard.pass_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">活跃销毁命令</div>
            <div class="text-2xl font-bold mt-1" :class="activeCmdColor">{{ dashboard.active_commands || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">唯一SDK实例</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.unique_instances || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <el-row :gutter="16" class="mt-2">
        <el-col :span="12">
          <el-card shadow="hover" class="mb-2">
            <div class="text-xs text-gray-500">24小时趋势</div>
            <div class="mt-1">
              <el-tag size="small" type="success">通过 {{ (dashboard.last_24h?.total || 0) - (dashboard.last_24h?.failed || 0) }}</el-tag>
              <el-tag v-if="dashboard.last_24h?.failed" size="small" type="danger" class="ml-1">失败 {{ dashboard.last_24h?.failed }}</el-tag>
            </div>
          </el-card>
        </el-col>
        <el-col :span="12">
          <el-card shadow="hover" class="mb-2">
            <div class="text-xs text-gray-500">按语言统计</div>
            <div class="mt-1 flex flex-wrap gap-1">
              <el-tag v-for="(stats, lang) in dashboard.by_language" :key="lang" size="small">
                {{ lang }}: {{ stats.total }}次 / {{ stats.passed }}通过
              </el-tag>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs: 检查记录 / 销毁命令 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 检查记录 -->
        <el-tab-pane label="完整性检查记录" name="checks">
          <div class="mb-3 flex gap-2">
            <el-input v-model="checkFilters.sdk_instance_id" placeholder="SDK实例ID" style="width:200px" size="small" />
            <el-select v-model="checkFilters.language" placeholder="语言" clearable size="small" style="width:120px">
              <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
            </el-select>
            <el-select v-model="checkFilters.passed" placeholder="结果" clearable size="small" style="width:120px">
              <el-option label="通过" value="true" />
              <el-option label="失败" value="false" />
            </el-select>
            <el-button size="small" @click="fetchChecks">搜索</el-button>
          </div>
          <el-table :data="checks" v-loading="loadingChecks" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" label="SDK实例" width="180" show-overflow-tooltip />
            <el-table-column prop="language" label="语言" width="80" />
            <el-table-column prop="sdk_version" label="版本" width="100" />
            <el-table-column label="结果" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.passed ? 'success' : 'danger'" size="small">{{ row.passed ? '通过' : '失败' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="failed_files" label="失败文件" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">
                <span v-if="row.failed_files?.length" class="text-red-500">{{ row.failed_files.join(', ') }}</span>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column prop="checked_at" label="检查时间" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ checkPagination.total }} 条</span>
            <el-pagination
              v-model:current-page="checkPagination.page"
              :page-size="checkPagination.per_page"
              :total="checkPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchChecks"
            />
          </div>
        </el-tab-pane>

        <!-- 销毁命令 -->
        <el-tab-pane label="远程销毁命令" name="commands">
          <div class="mb-3 flex gap-2">
            <el-select v-model="cmdFilters.status" placeholder="状态" clearable size="small" style="width:140px">
              <el-option label="待处理" value="pending" />
              <el-option label="已下发" value="dispatched" />
              <el-option label="已确认" value="confirmed" />
              <el-option label="已过期" value="expired" />
              <el-option label="已取消" value="cancelled" />
            </el-select>
            <el-button size="small" @click="fetchCommands">搜索</el-button>
            <el-button size="small" type="primary" @click="showCreateCommand = true">新建销毁命令</el-button>
          </div>
          <el-table :data="commands" v-loading="loadingCommands" stripe style="width:100%">
            <el-table-column prop="command_id" label="命令ID" width="200" show-overflow-tooltip />
            <el-table-column prop="sdk_instance_id" label="目标实例" width="160" show-overflow-tooltip />
            <el-table-column prop="language" label="语言" width="70" />
            <el-table-column prop="destroy_mode" label="模式" width="70">
              <template #default="{ row }">
                <el-tag :type="row.destroy_mode === 'hard' ? 'danger' : 'warning'" size="small">{{ row.destroy_mode }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="trigger_type" label="触发类型" width="150" />
            <el-table-column prop="status" label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="affected_count" label="影响实例" width="80" align="center" />
            <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
            <el-table-column prop="created_at" label="创建时间" width="180" />
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending' || row.status === 'dispatched'" size="small" type="warning" @click="handleCancel(row)">取消</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ cmdPagination.total }} 条</span>
            <el-pagination
              v-model:current-page="cmdPagination.page"
              :page-size="cmdPagination.per_page"
              :total="cmdPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchCommands"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 新建销毁命令对话框 -->
    <el-dialog v-model="showCreateCommand" title="新建销毁命令" width="500px">
      <el-form :model="cmdForm" label-width="120px">
        <el-form-item label="SDK实例ID">
          <el-input v-model="cmdForm.sdk_instance_id" placeholder="留空=按条件筛选" />
        </el-form-item>
        <el-form-item label="语言">
          <el-select v-model="cmdForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item label="销毁模式">
          <el-radio-group v-model="cmdForm.destroy_mode">
            <el-radio value="soft">软销毁（停止验证）</el-radio>
            <el-radio value="hard">硬销毁（完全停止）</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="原因" required>
          <el-input v-model="cmdForm.reason" type="textarea" :rows="3" placeholder="说明销毁原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateCommand = false">取消</el-button>
        <el-button type="danger" @click="handleIssueCommand" :loading="issuing">确认下发</el-button>
      </template>
    </el-dialog>

    <!-- 批量销毁对话框 -->
    <el-dialog v-model="showBatchDestroy" title="批量销毁" width="500px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item label="语言">
          <el-select v-model="batchForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item label="SDK版本">
          <el-input v-model="batchForm.sdk_version" placeholder="如 1.0.0" />
        </el-form-item>
        <el-form-item label="仅失败实例">
          <el-switch v-model="batchForm.failed_only" />
        </el-form-item>
        <el-form-item label="最后检查时间">
          <el-date-picker v-model="batchForm.date_before" type="datetime" placeholder="在此时间之前的实例" style="width:100%" />
        </el-form-item>
        <el-form-item label="销毁模式">
          <el-radio-group v-model="batchForm.destroy_mode">
            <el-radio value="soft">软销毁</el-radio>
            <el-radio value="hard">硬销毁</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="原因" required>
          <el-input v-model="batchForm.reason" type="textarea" :rows="3" placeholder="批量销毁原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchDestroy = false">取消</el-button>
        <el-button type="danger" @click="handleBatchDestroy" :loading="batchLoading">确认批量销毁</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getIntegrityDashboard, getIntegrityChecks, getDestroyCommands,
  issueDestroyCommand, cancelDestroyCommand, batchDestroy, processExpiredCommands,
} from '../../api/sdkIntegrity';

// ── 状态 ──
const loadingChecks = ref(false);
const loadingCommands = ref(false);
const processing = ref(false);
const issuing = ref(false);
const batchLoading = ref(false);
const activeTab = ref('checks');
const languages = ['php', 'node', 'python', 'go', 'java'];

const dashboard = ref({});
const checks = ref([]);
const commands = ref([]);

const checkPagination = ref({ page: 1, per_page: 20, total: 0 });
const cmdPagination = ref({ page: 1, per_page: 20, total: 0 });

const checkFilters = ref({ sdk_instance_id: '', language: '', passed: '' });
const cmdFilters = ref({ status: '' });

const showCreateCommand = ref(false);
const cmdForm = ref({
  sdk_instance_id: '', language: '', destroy_mode: 'soft', reason: '',
});

const showBatchDestroy = ref(false);
const batchForm = ref({
  language: '', sdk_version: '', failed_only: false, date_before: null,
  destroy_mode: 'soft', reason: '',
});

// ── 计算属性 ──
const passRateColor = computed(() => {
  const rate = dashboard.value.pass_rate || 100;
  if (rate >= 95) return 'text-green-500';
  if (rate >= 80) return 'text-yellow-500';
  return 'text-red-500';
});
const activeCmdColor = computed(() => {
  const count = dashboard.value.active_commands || 0;
  return count > 0 ? 'text-red-500' : 'text-green-500';
});

// ── 方法 ──
onMounted(async () => {
  await fetchDashboard();
  await fetchChecks();
});

async function fetchDashboard() {
  try {
    const res = await getIntegrityDashboard();
    dashboard.value = res.data.data || {};
  } catch (e) { /* ignore */ }
}

async function fetchChecks() {
  loadingChecks.value = true;
  try {
    const params = { ...checkFilters.value, page: checkPagination.value.page, per_page: checkPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getIntegrityChecks(params);
    const data = res.data.data;
    checks.value = data.data || [];
    checkPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingChecks.value = false;
}

async function fetchCommands() {
  loadingCommands.value = true;
  try {
    const params = { ...cmdFilters.value, page: cmdPagination.value.page, per_page: cmdPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getDestroyCommands(params);
    const data = res.data.data;
    commands.value = data.data || [];
    cmdPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingCommands.value = false;
}

async function handleIssueCommand() {
  issuing.value = true;
  try {
    const payload = { ...cmdForm.value };
    Object.keys(payload).forEach(k => { if (!payload[k]) delete payload[k]; });
    await issueDestroyCommand(payload);
    ElMessage.success('销毁命令已下发');
    showCreateCommand.value = false;
    cmdForm.value = { sdk_instance_id: '', language: '', destroy_mode: 'soft', reason: '' };
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  issuing.value = false;
}

async function handleCancel(row) {
  try {
    await ElMessageBox.confirm(`确认取消命令 ${row.command_id.slice(0, 16)}...？`, '确认', { type: 'warning' });
    await cancelDestroyCommand(row.id);
    ElMessage.success('命令已取消');
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
}

async function handleBatchDestroy() {
  batchLoading.value = true;
  try {
    const payload = { ...batchForm.value };
    if (payload.date_before) payload.date_before = payload.date_before.toISOString();
    Object.keys(payload).forEach(k => { if (payload[k] === '' || payload[k] === null) delete payload[k]; });
    const res = await batchDestroy(payload);
    ElMessage.success(res.data.message || '批量销毁命令已下发');
    showBatchDestroy.value = false;
    batchForm.value = { language: '', sdk_version: '', failed_only: false, date_before: null, destroy_mode: 'soft', reason: '' };
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  batchLoading.value = false;
}

async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredCommands();
    ElMessage.success(res.data.message || '处理完成');
    await fetchCommands();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function statusType(s) {
  return { pending: 'info', dispatched: 'warning', confirmed: 'danger', expired: 'info', cancelled: 'default' }[s] || 'info';
}
function statusLabel(s) {
  return { pending: '待处理', dispatched: '已下发', confirmed: '已确认', expired: '已过期', cancelled: '已取消' }[s] || s;
}
</script>
