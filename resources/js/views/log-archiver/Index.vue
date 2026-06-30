<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">审计日志归档 &amp; 低成本存储</h2>
        <el-button size="small" @click="processExpired" :loading="processing">处理过期取回请求</el-button>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">总归档记录</div>
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
            <div class="text-xs text-gray-500">待处理取回</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_restores || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">可用取回</div>
            <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.available_restores || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 归档策略 -->
        <el-tab-pane label="归档策略" name="policies">
          <div class="mb-3 flex justify-end">
            <el-button size="small" type="primary" @click="showPolicyDialog = true; isEditing = false; policyForm = defaultPolicy()">新建策略</el-button>
          </div>
          <el-table :data="policies" v-loading="loadingP" stripe style="width:100%">
            <el-table-column prop="name" label="名称" width="140" />
            <el-table-column prop="type" label="类型" width="100" />
            <el-table-column prop="storage_tier" label="存储层级" width="100">
              <template #default="{ row }">
                <el-tag :type="tierType(row.storage_tier)" size="small">{{ row.storage_tier }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="archive_after_days" label="归档(天)" width="90" align="center" />
            <el-table-column prop="delete_after_days" label="删除(天)" width="90" align="center" />
            <el-table-column prop="archive_records_count" label="归档次数" width="80" align="center" />
            <el-table-column label="活跃" width="70" align="center">
              <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="last_executed_at" label="上次执行" width="180" />
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="editPolicy(row)">编辑</el-button>
                <el-button size="small" type="primary" @click="handleArchive(row)" :loading="archivingPolicy === row.id">归档</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- 归档记录 -->
        <el-tab-pane label="归档记录" name="records">
          <div class="mb-3 flex gap-2">
            <el-select v-model="rFilters.type" placeholder="类型" clearable size="small" style="width:120px">
              <el-option label="审计" value="audit" /><el-option label="安全" value="security" />
              <el-option label="错误" value="error" /><el-option label="系统" value="system" />
            </el-select>
            <el-select v-model="rFilters.status" placeholder="状态" clearable size="small" style="width:120px">
              <el-option label="已完成" value="completed" /><el-option label="处理中" value="processing" />
              <el-option label="失败" value="failed" /><el-option label="跳过" value="skipped" />
            </el-select>
            <el-button size="small" @click="fetchRecords">搜索</el-button>
          </div>
          <el-table :data="records" v-loading="loadingR" stripe style="width:100%">
            <el-table-column prop="type" label="类型" width="80" />
            <el-table-column label="状态" width="80">
              <template #default="{ row }"><el-tag :type="recStatusType(row.status)" size="small">{{ row.status }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="storage_class" label="存储类型" width="120" />
            <el-table-column prop="archived_logs" label="归档数" width="70" align="center" />
            <el-table-column prop="file_size_bytes" label="大小" width="80">
              <template #default="{ row }">{{ formatBytes(row.file_size_bytes) }}</template>
            </el-table-column>
            <el-table-column prop="archive_date_to" label="归档截止" width="120" />
            <el-table-column prop="executed_at" label="执行时间" width="180" />
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'completed'" size="small" @click="handleRestore(row)">取回</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ rPagination.total }} 条</span>
            <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRecords" />
          </div>
        </el-tab-pane>

        <!-- 取回请求 -->
        <el-tab-pane label="取回请求" name="restores">
          <div class="mb-3 flex gap-2">
            <el-select v-model="sFilters.status" placeholder="状态" clearable size="small" style="width:140px">
              <el-option label="待处理" value="pending" /><el-option label="取回中" value="restoring" />
              <el-option label="可用" value="available" /><el-option label="已过期" value="expired" />
            </el-select>
            <el-button size="small" @click="fetchRestores">搜索</el-button>
          </div>
          <el-table :data="restoreRequests" v-loading="loadingS" stripe style="width:100%">
            <el-table-column label="状态" width="100">
              <template #default="{ row }"><el-tag :type="restoreStatusType(row.status)" size="small">{{ row.status }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
            <el-table-column prop="requester_type" label="请求类型" width="100" />
            <el-table-column prop="requested_at" label="请求时间" width="180" />
            <el-table-column prop="available_until" label="有效期至" width="180" />
            <el-table-column prop="error_message" label="错误信息" width="180" show-overflow-tooltip />
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleExecuteRestore(row)">执行取回</el-button>
                <el-button v-if="['pending','restoring'].includes(row.status)" size="small" type="warning" @click="handleCancelRestore(row)">取消</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ sPagination.total }} 条</span>
            <el-pagination v-model:current-page="sPagination.page" :page-size="sPagination.per_page" :total="sPagination.total" layout="prev, pager, next" small @current-change="fetchRestores" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 策略对话框 -->
    <el-dialog v-model="showPolicyDialog" :title="isEditing ? '编辑策略' : '新建策略'" width="500px">
      <el-form :model="policyForm" label-width="130px">
        <el-form-item label="名称"><el-input v-model="policyForm.name" /></el-form-item>
        <el-form-item label="类型">
          <el-select v-model="policyForm.type" :disabled="isEditing" style="width:100%">
            <el-option label="审计" value="audit" /><el-option label="安全" value="security" />
            <el-option label="错误" value="error" /><el-option label="系统" value="system" />
          </el-select>
        </el-form-item>
        <el-form-item label="存储层级">
          <el-select v-model="policyForm.storage_tier" style="width:100%">
            <el-option label="温存储" value="warm" /><el-option label="冷存储" value="cold" />
            <el-option label="冻结存储" value="frozen" />
          </el-select>
        </el-form-item>
        <el-form-item label="归档天数"><el-input-number v-model="policyForm.archive_after_days" :min="1" :max="3650" style="width:100%" /></el-form-item>
        <el-form-item label="删除天数"><el-input-number v-model="policyForm.delete_after_days" :min="1" :max="7300" style="width:100%" /></el-form-item>
        <el-form-item label="压缩归档"><el-switch v-model="policyForm.compress_archive" /></el-form-item>
        <el-form-item label="启用"><el-switch v-model="policyForm.is_active" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="policyForm.description" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showPolicyDialog = false">取消</el-button>
        <el-button type="primary" @click="handleSavePolicy" :loading="savingPolicy">保存</el-button>
      </template>
    </el-dialog>

    <!-- 取回归档对话框 -->
    <el-dialog v-model="showRestoreDialog" title="取回归档" width="400px">
      <p class="mb-3">确认取回归档记录 #{{ restoreRecordId }}？</p>
      <el-input v-model="restoreReason" type="textarea" :rows="2" placeholder="取回原因（必填）" />
      <template #footer>
        <el-button @click="showRestoreDialog = false">取消</el-button>
        <el-button type="primary" @click="handleConfirmRestore" :loading="restoring">提交取回请求</el-button>
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
const savingPolicy = ref(false);
const archivingPolicy = ref(null);
const restoring = ref(false);
const activeTab = ref('policies');

const dashboard = ref({});
const policies = ref([]);
const records = ref([]);
const restoreRequests = ref([]);

const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const sPagination = ref({ page: 1, per_page: 20, total: 0 });

const rFilters = ref({ type: '', status: '' });
const sFilters = ref({ status: '' });

const showPolicyDialog = ref(false);
const isEditing = ref(false);
const policyForm = ref(defaultPolicy());

const showRestoreDialog = ref(false);
const restoreRecordId = ref(null);
const restoreReason = ref('');

function defaultPolicy() {
  return { name: '', type: 'audit', storage_tier: 'cold', archive_after_days: 90, delete_after_days: 365, compress_archive: true, is_active: true, description: '' };
}

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
    const d = res.data.data; records.value = d.data || [];
    rPagination.value = { page: d.current_page || 1, per_page: d.per_page || 20, total: d.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}
async function fetchRestores() {
  loadingS.value = true;
  try {
    const params = { ...sFilters.value, page: sPagination.value.page, per_page: sPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getRestoreRequests(params);
    const d = res.data.data; restoreRequests.value = d.data || [];
    sPagination.value = { page: d.current_page || 1, per_page: d.per_page || 20, total: d.total || 0 };
  } catch (e) {}
  loadingS.value = false;
}

function editPolicy(row) {
  isEditing.value = true;
  policyForm.value = { ...row };
  showPolicyDialog.value = true;
}
async function handleSavePolicy() {
  savingPolicy.value = true;
  try {
    await upsertArchivePolicy(policyForm.value);
    ElMessage.success('策略已保存');
    showPolicyDialog.value = false;
    await fetchPolicies();
  } catch (e) {}
  savingPolicy.value = false;
}
async function handleArchive(row) {
  archivingPolicy.value = row.id;
  try {
    const res = await executeArchive(row.id);
    ElMessage.success(res.data.message || '归档完成');
    await fetchPolicies();
    await fetchRecords();
    await fetchDashboard();
  } catch (e) {}
  archivingPolicy.value = null;
}
function handleRestore(row) {
  restoreRecordId.value = row.id;
  restoreReason.value = '';
  showRestoreDialog.value = true;
}
async function handleConfirmRestore() {
  if (!restoreReason.value.trim()) { ElMessage.warning('请输入取回原因'); return; }
  restoring.value = true;
  try {
    await requestRestore(restoreRecordId.value, { reason: restoreReason.value });
    ElMessage.success('取回请求已提交');
    showRestoreDialog.value = false;
    await fetchRestores();
  } catch (e) {}
  restoring.value = false;
}
async function handleExecuteRestore(row) {
  try {
    await executeRestore(row.id);
    ElMessage.success('取回完成');
    await fetchRestores();
  } catch (e) {}
}
async function handleCancelRestore(row) {
  try {
    await ElMessageBox.confirm('确认取消此取回请求？', '确认');
    await cancelRestore(row.id);
    ElMessage.success('已取消');
    await fetchRestores();
  } catch (e) {}
}
async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredRestores();
    ElMessage.success(res.data.message || '处理完成');
    await fetchRestores();
  } catch (e) {}
  processing.value = false;
}

function tierType(t) { return { warm: 'warning', cold: 'primary', frozen: 'info' }[t] || 'info'; }
function recStatusType(s) { return { completed: 'success', processing: 'warning', failed: 'danger', skipped: 'info' }[s] || 'info'; }
function restoreStatusType(s) { return { pending: 'warning', restoring: 'primary', available: 'success', expired: 'info', failed: 'danger', cancelled: 'default' }[s] || 'info'; }
function formatBytes(b) {
  if (!b) return '0 B';
  const u = ['B','KB','MB','GB','TB']; let i = 0;
  while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
  return b.toFixed(1) + ' ' + u[i];
}
</script>
