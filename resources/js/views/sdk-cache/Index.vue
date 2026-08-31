<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-medium">{{ t(`${P}.title`) }}</h2>
        <div class="flex gap-2">
          <el-button size="small" type="danger" @click="showBatchInvalidate = true">{{ t(`${P}.batch_invalidate`) }}</el-button>
          <el-button size="small" @click="processExpired" :loading="processing">{{ t(`${P}.process_expired`) }}</el-button>
        </div>
      </div>
      <el-row :gutter="16">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.total`) }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.total_records || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.active`) }}</div>
            <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.active || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.in_grace`) }}</div>
            <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.in_grace_period || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.offline`) }}</div>
            <div class="text-2xl font-bold mt-1">{{ dashboard.offline_instances || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
      <el-row :gutter="16" class="mt-2">
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.invalidated`) }}</div>
            <div class="text-2xl font-bold mt-1 text-orange-500">{{ dashboard.invalidated || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.tampered`) }}</div>
            <div class="text-2xl font-bold mt-1 text-red-500">{{ dashboard.tampered || 0 }}</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.hit_rate`) }}</div>
            <div class="text-2xl font-bold mt-1" :class="hitRateColor">{{ dashboard.cache_hit_rate || 100 }}%</div>
          </el-card>
        </el-col>
        <el-col :span="6">
          <el-card shadow="hover" class="mb-2 text-center">
            <div class="text-xs text-gray-500">{{ t(`${P}.stats.expired`) }}</div>
            <div class="text-2xl font-bold mt-1 text-gray-500">{{ dashboard.expired || 0 }}</div>
          </el-card>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.records`)" name="records">
          <div class="mb-3 flex gap-2 flex-wrap">
            <el-input v-model="recordFilters.sdk_instance_id" :placeholder="t(`${P}.filters.sdk_instance`)" style="width:180px" size="small" />
            <el-input v-model="recordFilters.license_key" :placeholder="t(`${P}.filters.license_key`)" style="width:160px" size="small" />
            <el-select v-model="recordFilters.status" :placeholder="t(`${P}.filters.status`)" clearable size="small" style="width:120px">
              <el-option :label="t(`${P}.status.active`)" value="active" />
              <el-option :label="t(`${P}.status.invalidated`)" value="invalidated" />
              <el-option :label="t(`${P}.status.tampered`)" value="tampered" />
              <el-option :label="t(`${P}.status.expired`)" value="expired" />
            </el-select>
            <el-select v-model="recordFilters.language" :placeholder="t(`${P}.filters.language`)" clearable size="small" style="width:100px">
              <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
            </el-select>
            <el-button size="small" @click="fetchRecords">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="records" v-loading="loadingRecords" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" :label="t(`${P}.cols.sdk_instance`)" width="160" show-overflow-tooltip />
            <el-table-column prop="license_key" :label="t(`${P}.cols.license_key`)" width="140" show-overflow-tooltip />
            <el-table-column prop="language" :label="t(`${P}.cols.language`)" width="60" />
            <el-table-column prop="sdk_version" :label="t(`${P}.cols.version`)" width="80" />
            <el-table-column :label="t(`${P}.cols.status`)" width="90">
              <template #default="{ row }">
                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.offline`)" width="60" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_offline" type="warning" size="small">{{ t(`${P}.yes`) }}</el-tag>
                <span v-else>-</span>
              </template>
            </el-table-column>
            <el-table-column prop="access_count" :label="t(`${P}.cols.access`)" width="60" align="center" />
            <el-table-column prop="cached_at" :label="t(`${P}.cols.cached_at`)" width="170" />
            <el-table-column prop="expires_at" :label="t(`${P}.cols.expires_at`)" width="170" />
            <el-table-column prop="grace_expires_at" :label="t(`${P}.cols.grace_expires`)" width="170" />
            <el-table-column :label="t(`${P}.cols.actions`)" width="120" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'active'" size="small" type="danger" @click="handleInvalidateLicense(row)">{{ t(`${P}.invalidate`) }}</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t(`${P}.total_n`, { n: recordPagination.total }) }}</span>
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

        <el-tab-pane :label="t(`${P}.tabs.logs`)" name="logs">
          <div class="mb-3 flex gap-2">
            <el-select v-model="logFilters.trigger_type" :placeholder="t(`${P}.filters.trigger_type`)" clearable size="small" style="width:160px">
              <el-option :label="t(`${P}.triggers.license_change`)" value="license_change" />
              <el-option :label="t(`${P}.triggers.device_change`)" value="device_change" />
              <el-option :label="t(`${P}.triggers.feature_change`)" value="feature_change" />
              <el-option :label="t(`${P}.triggers.manual`)" value="manual" />
            </el-select>
            <el-button size="small" @click="fetchLogs">{{ t('actions.search') }}</el-button>
          </div>
          <el-table :data="logs" v-loading="loadingLogs" stripe style="width:100%">
            <el-table-column prop="sdk_instance_id" :label="t(`${P}.cols.target_instance`)" width="160" show-overflow-tooltip />
            <el-table-column prop="license_key" :label="t(`${P}.cols.license`)" width="140" show-overflow-tooltip />
            <el-table-column prop="trigger_type" :label="t(`${P}.cols.trigger_type`)" width="120" />
            <el-table-column prop="reason" :label="t(`${P}.cols.reason`)" min-width="200" show-overflow-tooltip />
            <el-table-column prop="source" :label="t(`${P}.cols.source`)" width="80" />
            <el-table-column prop="created_at" :label="t(`${P}.cols.time`)" width="180" />
          </el-table>
          <div class="mt-3 flex justify-between items-center">
            <span class="text-sm text-gray-500">{{ t(`${P}.total_n`, { n: logPagination.total }) }}</span>
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

    <el-dialog v-model="showInvalidateLicense" :title="t(`${P}.dialog.invalidate_title`)" width="400px">
      <p class="mb-3">{{ t(`${P}.dialog.invalidate_confirm`, { key: invalidateLicenseKey }) }}</p>
      <el-input v-model="invalidateReason" :placeholder="t(`${P}.dialog.reason_optional`)" />
      <template #footer>
        <el-button @click="showInvalidateLicense = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="danger" @click="handleConfirmInvalidateLicense" :loading="invalidating">{{ t(`${P}.dialog.confirm_invalidate`) }}</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showBatchInvalidate" :title="t(`${P}.dialog.batch_title`)" width="450px">
      <el-form :model="batchForm" label-width="120px">
        <el-form-item :label="t(`${P}.filters.language`)">
          <el-select v-model="batchForm.language" clearable style="width:100%">
            <el-option v-for="l in languages" :key="l" :label="l" :value="l" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t(`${P}.dialog.sdk_version`)">
          <el-input v-model="batchForm.sdk_version" :placeholder="t(`${P}.dialog.sdk_version_ph`)" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialog.offline_only`)">
          <el-switch v-model="batchForm.offline_only" />
        </el-form-item>
        <el-form-item :label="t(`${P}.dialog.expired_only`)">
          <el-switch v-model="batchForm.expired_only" />
        </el-form-item>
        <el-form-item :label="t(`${P}.cols.reason`)" required>
          <el-input v-model="batchForm.reason" type="textarea" :rows="2" :placeholder="t(`${P}.dialog.batch_reason_ph`)" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchInvalidate = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="danger" @click="handleBatchInvalidate" :loading="batchLoading">{{ t(`${P}.dialog.confirm_batch`) }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
  getCacheDashboard, getCacheRecords, getInvalidationLogs,
  invalidateByLicense, batchInvalidate, processExpiredCache,
} from '../../api/sdkLocalCache';

const { t } = useI18n();
const P = 'sdk_cache_page';

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

const hitRateColor = computed(() => {
  const rate = dashboard.value.cache_hit_rate || 100;
  if (rate >= 90) return 'text-green-500';
  if (rate >= 70) return 'text-yellow-500';
  return 'text-red-500';
});

onMounted(async () => {
  await fetchDashboard();
  await fetchRecords();
});

watch(activeTab, (tab) => {
  if (tab === 'logs') fetchLogs();
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
    ElMessage.success(t(`${P}.messages.invalidated`));
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
    ElMessage.success(res.data.message || t(`${P}.messages.batch_done`));
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
    ElMessage.success(res.data.message || t(`${P}.messages.process_done`));
    await fetchRecords();
    await fetchDashboard();
  } catch (e) { /* ignore */ }
  processing.value = false;
}

function statusType(s) {
  return { active: 'success', expired: 'info', invalidated: 'warning', tampered: 'danger' }[s] || 'info';
}
function statusLabel(s) {
  const key = `${P}.status.${s}`;
  const translated = t(key);
  return translated === key ? s : translated;
}
</script>
