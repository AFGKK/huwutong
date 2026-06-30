<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">审计日志归档至低成本存储</h2>
        <el-button size="small" @click="processExpired" :loading="processing">处理过期恢复请求</el-button>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">已归档记录</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_archived_records || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">总归档大小</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_size_human || '0 B' }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">待处理恢复</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_restores || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">可恢复</div>
            <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.available_restores || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <!-- 存储分层 -->
      <el-row :gutter="16" class="mt-2">
        <el-col :span="6" v-for="(count, tier) in dashboard.by_tier || {}" :key="tier">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ tierLabel(tier) }}</div>
            <div class="text-xl font-bold mt-1" :class="tierColor(tier)">{{ count || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 归档策略 -->
        <el-tab-pane label="归档策略" name="policies">
          <div class="mb-3 flex justify-end">
            <el-button size="small" type="primary" @click="showPolicyDialog = true">新建策略</el-button>
          </div>
          <el-table :data="policies" v-loading="loadingP" stripe style="width:100%">
            <el-table-column prop="name" label="策略名称" width="180" />
            <el-table-column prop="type" label="类型" width="120" />
            <el-table-column label="存储分层" width="120">
              <template #default="{ row }">
                <el-tag :type="tierColor(row.storage_tier)" size="small">{{ tierLabel(row.storage_tier) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="retention_days" label="保留天数" width="100" align="center" />
            <el-table-column prop="archive_records_count" label="已归档" width="80" align="center" />
            <el-table-column prop="is_active" label="启用" width="70" align="center">
              <template #default="{ row }">
                <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="editPolicy(row)">编辑</el-button>
                <el-button size="small" type="primary" @click="handleArchive(row)" :loading="archiving === row.id">归档</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 归档记录 -->
        <el-tab-pane label="归档记录" name="records">
          <div class="mb-3 flex gap-2">
            <el-select v-model="rFilters.tier" placeholder="存储分层" clearable size="small" style="width:140px">
              <el-option label="热存储" value="hot" />
              <el-option label="温存储" value="warm" />
              <el-option label="冷存储" value="cold" />
              <el-option label="冻结存储" value="frozen" />
            </el-select>
            <el-button size="small" @click="fetchRecords">搜索</el-button>
          </div>
          <el-table :data="records" v-loading="loadingR" stripe style="width:100%">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column label="存储分层" width="100">
              <template #default="{ row }">
                <el-tag :type="tierColor(row.storage_tier)" size="small">{{ tierLabel(row.storage_tier) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="file_name" label="文件名" min-width="200" show-overflow-tooltip />
            <el-table-column prop="file_size_human" label="大小" width="90" align="right" />
            <el-table-column prop="archived_at" label="归档时间" width="180" />
            <el-table-column prop="expires_at" label="到期时间" width="180" />
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.storage_tier !== 'hot'" size="small" type="warning" @click="showRestoreDialog(row)">恢复</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ rPagination.total }} 条</span>
            <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRecords" />
          </div>
        </el-tab-pane>

        <!-- 恢复请求 -->
        <el-tab-pane label="恢复请求" name="restores">
          <el-table :data="restoreRequests" v-loading="loadingS" stripe style="width:100%">
            <el-table-column prop="id" label="ID" width="70" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="rsStatusType(row.status)" size="small">{{ rsStatusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="restore_reason" label="恢复原因" min-width="220" show-overflow-tooltip />
            <el-table-column prop="requested_at" label="请求时间" width="180" />
            <el-table-column prop="available_until" label="可用截止" width="180" />
            <el-table-column label="操作" width="160" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleExecuteRestore(row)">执行恢复</el-button>
                <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="handleCancelRestore(row)">取消</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ sPagination.total }} 条</span>
            <el-pagination v-model:current-page="sPagination.page" :page-size="sPagination.per_page" :total="sPagination.total" layout="prev, pager, next" small @current-change="fetchRestoreRequests" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 策略对话框 -->
    <el-dialog v-model="showPolicyDialog" :title="editingPolicy ? '编辑策略' : '新建策略'" width="500px">
      <el-form :model="policyForm" label-width="120px">
        <el-form-item label="策略名称" required>
          <el-input v-model="policyForm.name" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="policyForm.type" style="width:100%">
            <el-option label="激活记录" value="activation" />
            <el-option label="审计日志" value="audit" />
            <el-option label="事件日志" value="event" />
            <el-option label="Webhook日志" value="webhook" />
            <el-option label="错误日志" value="error" />
            <el-option label="自定义" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="存储分层">
          <el-select v-model="policyForm.storage_tier" style="width:100%">
            <el-option label="热存储（即时访问）" value="hot" />
            <el-option label="温存储（低频）" value="warm" />
            <el-option label="冷存储（低成本）" value="cold" />
            <el-option label="冻结存储（归档）" value="frozen" />
          </el-select>
        </el-form-item>
        <el-form-item label="保留天数">
          <el-input-number v-model="policyForm.retention_days" :min="1" :max="7300" style="width:100%" />
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="policyForm.is_active" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPolicyDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSavePolicy" :loading="savingP">保存</el-button>
      </template>
    </el-dialog>

    <!-- 恢复对话框 -->
    <el-dialog v-model="showRestoreDialogVisible" title="请求恢复归档" width="400px">
      <p class="mb-3">确认恢复归档记录 #{{ restoreTarget?.id }}？</p>
      <el-input v-model="restoreReason" type="textarea" :rows="3" placeholder="恢复原因（必填）" />
      <template #footer>
        <el-button @click="showRestoreDialogVisible = false">取消</el-button>
        <el-button type="warning" @click="handleRequestRestore" :loading="restoring">提交恢复请求</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getArchiverDashboard, getArchivePolicies, getArchiveRecords, getRestoreRequests,
  upsertArchivePolicy, executeArchive, requestRestore, executeRestore,
  cancelRestore, processExpiredRestores,
} from '../../api/logArchiver';

const loadingP = ref(false);
const loadingR = ref(false);
const loadingS = ref(false);
const processing = ref(false);
const archiving = ref(null);
const savingP = ref(false);
const restoring = ref(false);
const activeTab = ref('policies');

const dashboard = ref({});
const policies = ref([]);
const records = ref([]);
const restoreRequests = ref([]);

const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const sPagination = ref({ page: 1, per_page: 20, total: 0 });
const rFilters = ref({ tier: '' });

const showPolicyDialog = ref(false);
const editingPolicy = ref(null);
const policyForm = ref({ name: '', type: 'audit', storage_tier: 'cold', retention_days: 365, is_active: true });

const showRestoreDialogVisible = ref(false);
const restoreTarget = ref(null);
const restoreReason = ref('');

onMounted(async () => {
  await fetchDashboard();
  await fetchPolicies();
});

async function fetchDashboard() {
  try { const res = await getArchiverDashboard(); dashboard.value = res.data.data || {}; } catch (e) {}
}

async function fetchPolicies() {
  loadingP.value = true;
  try { const res = await getArchivePolicies(); policies.value = res.data.data || []; } catch (e) {}
  loadingP.value = false;
}

async function fetchRecords() {
  loadingR.value = true;
  try {
    const params = { ...rFilters.value, page: rPagination.value.page, per_page: rPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getArchiveRecords(params);
    const data = res.data.data;
    records.value = data.data || [];
    rPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}

async function fetchRestoreRequests() {
  loadingS.value = true;
  try {
    const params = { page: sPagination.value.page, per_page: sPagination.value.per_page };
    const res = await getRestoreRequests(params);
    const data = res.data.data;
    restoreRequests.value = data.data || [];
    sPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) {}
  loadingS.value = false;
}

function editPolicy(row) {
  editingPolicy.value = row;
  policyForm.value = { name: row.name, type: row.type, storage_tier: row.storage_tier, retention_days: row.retention_days, is_active: row.is_active, id: row.id };
  showPolicyDialog.value = true;
}

async function handleSavePolicy() {
  savingP.value = true;
  try {
    await upsertArchivePolicy(policyForm.value);
    ElMessage.success('策略保存成功');
    showPolicyDialog.value = false;
    editingPolicy.value = null;
    policyForm.value = { name: '', type: 'audit', storage_tier: 'cold', retention_days: 365, is_active: true };
    await fetchPolicies();
    await fetchDashboard();
  } catch (e) {}
  savingP.value = false;
}

async function handleArchive(row) {
  archiving.value = row.id;
  try {
    const res = await executeArchive(row.id);
    ElMessage.success(res.data.message || '归档执行中');
    await fetchPolicies();
    await fetchDashboard();
  } catch (e) {}
  archiving.value = null;
}

function showRestoreDialog(row) {
  restoreTarget.value = row;
  restoreReason.value = '';
  showRestoreDialogVisible.value = true;
}

async function handleRequestRestore() {
  if (!restoreReason.value) { ElMessage.warning('请输入恢复原因'); return; }
  restoring.value = true;
  try {
    await requestRestore(restoreTarget.value.id, { reason: restoreReason.value });
    ElMessage.success('恢复请求已提交');
    showRestoreDialogVisible.value = false;
    await fetchRecords();
  } catch (e) {}
  restoring.value = false;
}

async function handleExecuteRestore(row) {
  try {
    await executeRestore(row.id);
    ElMessage.success('恢复已执行');
    await fetchRestoreRequests();
    await fetchDashboard();
  } catch (e) {}
}

async function handleCancelRestore(row) {
  try {
    await ElMessageBox.confirm('确认取消此恢复请求？', '确认');
    await cancelRestore(row.id);
    ElMessage.success('已取消');
    await fetchRestoreRequests();
  } catch (e) {}
}

async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredRestores();
    ElMessage.success(res.data.message || '处理完成');
    await fetchDashboard();
  } catch (e) {}
  processing.value = false;
}

function tierLabel(t) { return { hot: '热存储', warm: '温存储', cold: '冷存储', frozen: '冻结存储' }[t] || t; }
function tierColor(t) { return { hot: 'success', warm: 'warning', cold: 'primary', frozen: 'danger' }[t] || 'info'; }
function rsStatusType(s) { return { pending: 'warning', restoring: 'primary', available: 'success', expired: 'info', cancelled: 'default' }[s] || 'info'; }
function rsStatusLabel(s) { return { pending: '待处理', restoring: '恢复中', available: '可获取', expired: '已过期', cancelled: '已取消' }[s] || s; }
</script>
