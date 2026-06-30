<template>
  <div>
    <!-- 概览卡片 -->
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">SDK 本地缓存 &amp; 离线宽限期</h2>
        <div class="flex gap-2">
          <el-button size="small" type="danger" @click="showBatchInvalidate = true">批量失效</el-button>
          <el-button size="small" @click="processExpired" :loading="processing">处理过期缓存</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">总缓存记录</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_records || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">活跃缓存</div>
            <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.active || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">宽限期内</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.in_grace_period || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">离线实例</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.offline_instances || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <el-row :gutter="16" class="mt-2">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">已失效</div>
            <div class="text-2xl font-bold mt-1 text-orange-500">{{ dashboard.invalidated || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">被篡改</div>
            <div class="text-2xl font-bold mt-1 text-red-500">{{ dashboard.tampered || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">缓存命中率</div>
            <div class="text-2xl font-bold mt-1" :class="hitRateColor">{{ dashboard.cache_hit_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">已过期</div>
            <div class="text-2xl font-bold mt-1 text-gray-500">{{ dashboard.expired || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <!-- Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 缓存记录 -->
        <el-tab-pane label="缓存记录" name="records">
          <div class="mb-3 flex gap-2 flex-wrap">
            <el-input v-model="recordFilters.sdk_instance_id" placeholder="SDK实例ID" style="width:180px" size="small" />
            <el-input v-model="recordFilters.license_key" placeholder="License Key" style="width:160px" size="small" />
            <el-select v-model="recordFilters.status" placeholder="状态" clearable size="small" style="width:120px">
              <el-option label="活跃" value="active" />
              <el-option label="已失效" value="invalidated" />
              <el-option label="已篡改" value="tampered" />
              <el-option label="已过期" value="expired" />
            </el-select>
            <el-select v-model="recordFilters.language" placeholder="语言" clearable size="small" style="width:100px">
              <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
            </el-select>
            <el-button size="small" @click="fetchRecords">搜索</el-button>
          </div>
          <el-table :data="records" v-loading="loadingRecords" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" label="SDK实例" width="160" show-overflow-tooltip />
            <el-table-column prop="license_key" label="License Key" width="140" show-overflow-tooltip />
            <el-table-column prop="language" label="语言" width="60" />
            <el-table-column prop="sdk_version" label="版本" width="80" />
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="离线" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_offline" type="warning" size="small">是</el-tag>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column prop="access_count" label="访问" width="60" align="center" />
            <el-table-column prop="cached_at" label="缓存时间" width="170" />
            <el-table-column prop="expires_at" label="过期时间" width="170" />
            <el-table-column prop="grace_expires_at" label="宽限期止" width="170" />
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'active'" size="small" type="danger" @click="handleInvalidateLicense(row)">失效</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ recordPagination.total }} 条</span>
            <el-pagination
              v-model:current-page="recordPagination.page"
              :page-size="recordPagination.per_page"
              :total="recordPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchRecords"
            />
          </div>
        </el-tab-pane>

        <!-- 失效日志 -->
        <el-tab-pane label="失效日志" name="logs">
          <div class="mb-3 flex gap-2">
            <el-select v-model="logFilters.trigger_type" placeholder="触发类型" clearable size="small" style="width:160px">
              <el-option label="License变更" value="license_change" />
              <el-option label="设备变更" value="device_change" />
              <el-option label="功能变更" value="feature_change" />
              <el-option label="手动" value="manual" />
            </el-select>
            <el-button size="small" @click="fetchLogs">搜索</el-button>
          </div>
          <el-table :data="logs" v-loading="loadingLogs" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" label="目标实例" width="160" show-overflow-tooltip />
            <el-table-column prop="license_key" label="License" width="140" show-overflow-tooltip />
            <el-table-column prop="trigger_type" label="触发类型" width="120" />
            <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
            <el-table-column prop="source" label="来源" width="80" />
            <el-table-column prop="created_at" label="时间" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">共 {{ logPagination.total }} 条</span>
            <el-pagination
              v-model:current-page="logPagination.page"
              :page-size="logPagination.per_page"
              :total="logPagination.total"
              layout="prev, pager, next"
              small
              @current-change="fetchLogs"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 失效 License 缓存对话框 -->
    <el-dialog v-model="showInvalidateLicense" title="失效License缓存" width="400px">
      <p class="mb-3">确认失效 License <strong>{{ invalidateLicenseKey }}</strong> 的所有SDK缓存？</p>
      <el-input v-model="invalidateReason" placeholder="失效原因（可选）" />
      <template #footer>
        <el-button @click="showInvalidateLicense = false">取消</el-button>
        <el-button type="danger" @click="handleConfirmInvalidateLicense" :loading="invalidating">确认失效</el-button>
      </template>
    </el-dialog>

    <!-- 批量失效对话框 -->
    <el-dialog v-model="showBatchInvalidate" title="批量失效缓存" width="450px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item label="语言">
          <el-select v-model="batchForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item label="SDK版本">
          <el-input v-model="batchForm.sdk_version" placeholder="如 1.0.0" />
        </el-form-item>
        <el-form-item label="仅离线缓存">
          <el-switch v-model="batchForm.offline_only" />
        </el-form-item>
        <el-form-item label="仅过期缓存">
          <el-switch v-model="batchForm.expired_only" />
        </el-form-item>
        <el-form-item label="原因" required>
          <el-input v-model="batchForm.reason" type="textarea" :rows="2" placeholder="批量失效原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchInvalidate = false">取消</el-button>
        <el-button type="danger" @click="handleBatchInvalidate" :loading="batchLoading">确认批量失效</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getCacheDashboard, getCacheRecords, getInvalidationLogs,
  invalidateByLicense, batchInvalidate, processExpiredCache,
} from '../../api/sdkLocalCache';

// ── 状态 ──
const loadingRecords = ref(false);
const loadingLogs = ref(false);
const processing = ref(false);
const invalidating = ref(false);
const batchLoading = ref(false);
const activeTab = ref('records');
const languages = ['php', 'node', 'python', 'go', 'java'];

const dashboard = ref({});
const records = ref([]);
const logs = ref([]);

const recordPagination = ref({ page: 1, per_page: 20, total: 0 });
const logPagination = ref({ page: 1, per_page: 20, total: 0 });

const recordFilters = ref({ sdk_instance_id: '', license_key: '', status: '', language: '' });
const logFilters = ref({ trigger_type: '' });

const showInvalidateLicense = ref(false);
const invalidateLicenseKey = ref('');
const invalidateReason = ref('');

const showBatchInvalidate = ref(false);
const batchForm = ref({
  language: '', sdk_version: '', offline_only: false, expired_only: false, reason: '',
});

// ── 计算属性 ──
const hitRateColor = computed(() => {
  const rate = dashboard.value.cache_hit_rate || 100;
  if (rate >= 90) return 'text-green-500';
  if (rate >= 70) return 'text-yellow-500';
  return 'text-red-500';
});

// ── 方法 ──
onMounted(async () => {
  await fetchDashboard();
  await fetchRecords();
});

async function fetchDashboard() {
  try {
    const res = await getCacheDashboard();
    dashboard.value = res.data.data || {};
  } catch (e) { /* ignore */ }
}

async function fetchRecords() {
  loadingRecords.value = true;
  try {
    const params = { ...recordFilters.value, page: recordPagination.value.page, per_page: recordPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getCacheRecords(params);
    const data = res.data.data;
    records.value = data.data || [];
    recordPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingRecords.value = false;
}

async function fetchLogs() {
  loadingLogs.value = true;
  try {
    const params = { ...logFilters.value, page: logPagination.value.page, per_page: logPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getInvalidationLogs(params);
    const data = res.data.data;
    logs.value = data.data || [];
    logPagination.value = { page: data.current_page || 1, per_page: data.per_page || 20, total: data.total || 0 };
  } catch (e) { /* ignore */ }
  loadingLogs.value = false;
}

function handleInvalidateLicense(row) {
  invalidateLicenseKey.value = row.license_key;
  invalidateReason.value = '';
  showInvalidateLicense.value = true;
}

async function handleConfirmInvalidateLicense() {
  invalidating.value = true;
  try {
    await invalidateByLicense({
      license_key: invalidateLicenseKey.value,
      reason: invalidateReason.value || 'manual_invalidation',
    });
    ElMessage.success('缓存已失效');
    showInvalidateLicense.value = false;
    await fetchRecords();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  invalidating.value = false;
}

async function handleBatchInvalidate() {
  batchLoading.value = true;
  try {
    const payload = { ...batchForm.value };
    Object.keys(payload).forEach(k => { if (payload[k] === '' || payload[k] === null) delete payload[k]; });
    const res = await batchInvalidate(payload);
    ElMessage.success(res.data.message || '批量失效完成');
    showBatchInvalidate.value = false;
    batchForm.value = { language: '', sdk_version: '', offline_only: false, expired_only: false, reason: '' };
    await fetchRecords();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  batchLoading.value = false;
}

async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredCache();
    ElMessage.success(res.data.message || '处理完成');
    await fetchRecords();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function statusType(s) {
  return { active: 'success', expired: 'info', invalidated: 'warning', tampered: 'danger' }[s] || 'info';
}
function statusLabel(s) {
  return { active: '活跃', expired: '已过期', invalidated: '已失效', tampered: '已篡改' }[s] || s;
}
</script>
