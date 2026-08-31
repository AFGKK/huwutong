<template>
  <div class="accounting-page">
    <div class="page-header mb-6">
      <h1 class="text-2xl font-bold text-gray-800">{{ t('accounting_page.title') }}</h1>
      <p class="text-sm text-gray-500 mt-1">{{ t('accounting_page.subtitle') }}</p>
    </div>

    <div v-if="loading" class="text-center py-16">
      <el-skeleton :rows="5" animated />
    </div>

    <template v-else>
      <!-- 已配置的集成列表 -->
      <el-card shadow="never" class="mb-6">
        <template #header>
          <div class="flex justify-between items-center">
            <span>{{ t('accounting_page.configured_title') }}</span>
            <el-button type="primary" size="small" @click="showAddDialog = true">
              <el-icon><Plus /></el-icon> {{ t('accounting_page.add_integration') }}
            </el-button>
          </div>
        </template>

        <el-table :data="integrations" v-loading="loading" stripe size="small">
          <el-table-column :label="cols.system" width="200">
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <span class="text-lg">{{ providerIcon(row.provider) }}</span>
                <div>
                  <div class="font-medium">{{ row.name || providerName(row.provider) }}</div>
                  <div class="text-xs text-gray-400">{{ row.provider }}</div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="environment" :label="cols.environment" width="100">
            <template #default="{ row }">
              <el-tag :type="row.environment === 'production' ? 'success' : 'warning'" size="small">
                {{ row.environment === 'production' ? t('accounting_page.env_production') : t('accounting_page.env_sandbox') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="cols.connection" width="120">
            <template #default="{ row }">
              <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                {{ row.is_active ? t('accounting_page.connected') : t('accounting_page.disconnected') }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="sync_mappings_count" :label="cols.synced" width="80" />
          <el-table-column prop="last_sync_at" :label="cols.last_sync" min-width="160" />
          <el-table-column :label="cols.actions" width="300" fixed="right">
            <template #default="{ row }">
              <el-button text size="small" @click="testConn(row)">{{ t('accounting_page.actions.test') }}</el-button>
              <el-button text size="small" :disabled="!row.is_active" @click="syncNow(row)">{{ t('accounting_page.actions.sync') }}</el-button>
              <el-button text size="small" @click="viewLogs(row)">{{ t('accounting_page.actions.logs') }}</el-button>
              <el-button text size="small" @click="editIntegration(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm :title="t('accounting_page.delete_confirm')" @confirm="deleteIntegration(row)">
                <template #reference>
                  <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                </template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>

        <el-empty v-if="integrations.length === 0" :description="t('accounting_page.empty_integrations')" />
      </el-card>

      <!-- 可用提供商 -->
      <el-card shadow="never">
        <template #header><span>{{ t('accounting_page.supported_title') }}</span></template>
        <el-row :gutter="20">
          <el-col :span="6" v-for="p in providers" :key="p.key">
            <el-card shadow="hover" class="provider-card" :class="{ 'opacity-50': isConfigured(p.key) }">
              <div class="text-center p-4">
                <div class="text-4xl mb-2">{{ p.icon }}</div>
                <div class="font-medium text-sm">{{ p.name || providerName(p.key) }}</div>
                <div class="text-xs text-gray-400 mt-1">
                  {{ p.type === 'international' ? t('accounting_page.type_international') : t('accounting_page.type_domestic') }}
                </div>
                <div class="mt-3">
                  <el-tag v-if="isConfigured(p.key)" type="success" size="small">{{ t('accounting_page.status_configured') }}</el-tag>
                  <el-tag v-else type="info" size="small">{{ t('accounting_page.status_not_configured') }}</el-tag>
                </div>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </el-card>
    </template>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="showAddDialog" :title="dialogTitle" width="560px">
      <el-form :model="form" label-position="top" size="small">
        <el-form-item :label="t('accounting_page.form.provider')" required>
          <el-select v-model="form.provider" :disabled="!!editingId" style="width:100%">
            <el-option v-for="key in providerKeys" :key="key" :label="providerLabels[key]" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('accounting_page.form.name')">
          <el-input v-model="form.name" :placeholder="providerName(form.provider)" />
        </el-form-item>
        <el-form-item :label="t('accounting_page.form.environment')">
          <el-radio-group v-model="form.environment">
            <el-radio value="sandbox">{{ t('accounting_page.env_sandbox_label') }}</el-radio>
            <el-radio value="production">{{ t('accounting_page.env_production_label') }}</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- OAuth 类型 -->
        <template v-if="form.provider === 'quickbooks' || form.provider === 'xero'">
          <el-form-item :label="t('crm_integration_page.form.client_id')">
            <el-input v-model="form.client_id" />
          </el-form-item>
          <el-form-item :label="t('crm_integration_page.form.client_secret')">
            <el-input v-model="form.client_secret" type="password" show-password />
          </el-form-item>
        </template>

        <!-- API Key 类型 -->
        <template v-if="form.provider === 'yonyou' || form.provider === 'kingdee'">
          <el-form-item :label="t('accounting_page.form.api_endpoint')">
            <el-input v-model="form.api_endpoint" :placeholder="t('accounting_page.form.api_endpoint_ph')" />
          </el-form-item>
          <el-form-item :label="t('accounting_page.form.company_id')">
            <el-input v-model="form.company_id" />
          </el-form-item>
          <el-form-item :label="t('crm_integration_page.form.username')">
            <el-input v-model="form.username" />
          </el-form-item>
          <el-form-item :label="t('crm_integration_page.form.password')">
            <el-input v-model="form.password" type="password" show-password />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <el-button @click="showAddDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="saveIntegration" :loading="saving">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 同步日志对话框 -->
    <el-dialog v-model="showLogDialog" :title="t('accounting_page.sync_logs_title')" width="700px">
      <el-table :data="syncLogs" v-loading="logLoading" size="small">
        <el-table-column prop="sync_type" :label="cols.type" width="80" />
        <el-table-column prop="direction" :label="cols.direction" width="80" />
        <el-table-column prop="entity_type" :label="cols.entity" width="120" />
        <el-table-column prop="total_count" :label="cols.total" width="60" />
        <el-table-column prop="success_count" :label="cols.success" width="60" />
        <el-table-column prop="fail_count" :label="cols.failed" width="60" />
        <el-table-column prop="created_at" :label="cols.time" width="160" />
        <el-table-column prop="error_message" :label="cols.error" min-width="150" show-overflow-tooltip />
      </el-table>
      <el-empty v-if="syncLogs.length === 0" :description="t('accounting_page.empty_logs')" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  getAccountingIntegrations,
  createAccountingIntegration,
  updateAccountingIntegration,
  deleteAccountingIntegration,
  testAccountingConnection,
  syncPendingAccounting,
  getAccountingLogs,
} from '@/api/accounting';

const { t } = useI18n();

const loading = ref(true);
const saving = ref(false);
const showAddDialog = ref(false);
const editingId = ref(null);
const integrations = ref([]);
const providers = ref([]);

const providerKeys = ['quickbooks', 'xero', 'yonyou', 'kingdee'];

const providerLabels = computed(() => ({
  quickbooks: t('accounting_page.providers.quickbooks'),
  xero: t('accounting_page.providers.xero'),
  yonyou: t('accounting_page.providers.yonyou'),
  kingdee: t('accounting_page.providers.kingdee'),
}));

const cols = computed(() => ({
  system: t('accounting_page.cols.system'),
  environment: t('accounting_page.cols.environment'),
  connection: t('accounting_page.cols.connection'),
  synced: t('accounting_page.cols.synced'),
  last_sync: t('accounting_page.cols.last_sync'),
  actions: t('accounting_page.cols.actions'),
  type: t('accounting_page.cols.type'),
  direction: t('accounting_page.cols.direction'),
  entity: t('accounting_page.cols.entity'),
  total: t('accounting_page.cols.total'),
  success: t('accounting_page.cols.success'),
  failed: t('accounting_page.cols.failed'),
  time: t('accounting_page.cols.time'),
  error: t('accounting_page.cols.error'),
}));

const dialogTitle = computed(() =>
  editingId.value ? t('accounting_page.edit_dialog_title') : t('accounting_page.add_dialog_title'),
);

// 表单
const form = reactive({
  provider: 'quickbooks', name: '', environment: 'sandbox',
  client_id: '', client_secret: '',
  api_endpoint: '', company_id: '', username: '', password: '',
});

// 日志
const showLogDialog = ref(false);
const logLoading = ref(false);
const syncLogs = ref([]);
const currentIntegrationId = ref(null);

const providerIcon = (key) => ({ quickbooks: 'QB', xero: 'Xe', yonyou: 'YY', kingdee: 'KD' }[key] || '');

function providerName(key) {
  return providerLabels.value[key] || key;
}

function isConfigured(key) {
  return integrations.value.some(i => i.provider === key);
}

async function loadData() {
  loading.value = true;
  try {
    const res = await getAccountingIntegrations();
    if (res.data?.success) {
      integrations.value = res.data.data.integrations || [];
      providers.value = res.data.data.providers || [];
    }
  } catch (e) {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

function resetForm() {
  form.provider = 'quickbooks';
  form.name = '';
  form.environment = 'sandbox';
  form.client_id = '';
  form.client_secret = '';
  form.api_endpoint = '';
  form.company_id = '';
  form.username = '';
  form.password = '';
  editingId.value = null;
}

function editIntegration(row) {
  editingId.value = row.id;
  Object.assign(form, {
    provider: row.provider,
    name: row.name,
    environment: row.environment,
    client_id: row.client_id || '',
    client_secret: '',
    api_endpoint: row.api_endpoint || '',
    company_id: row.company_id || '',
    username: row.username || '',
    password: '',
  });
  showAddDialog.value = true;
}

async function saveIntegration() {
  saving.value = true;
  try {
    if (editingId.value) {
      await updateAccountingIntegration(editingId.value, form);
      ElMessage.success(t('accounting_page.messages.update_ok'));
    } else {
      await createAccountingIntegration(form);
      ElMessage.success(t('accounting_page.messages.create_ok'));
    }
    showAddDialog.value = false;
    resetForm();
    await loadData();
  } catch (e) {
    ElMessage.error(t('messages.failed'));
  } finally {
    saving.value = false;
  }
}

async function deleteIntegration(row) {
  try {
    await deleteAccountingIntegration(row.id);
    ElMessage.success(t('accounting_page.messages.delete_ok'));
    await loadData();
  } catch (e) {
    ElMessage.error(t('accounting_page.messages.delete_fail'));
  }
}

async function testConn(row) {
  try {
    const res = await testAccountingConnection(row.id);
    if (res.data?.success) {
      const d = res.data.data;
      if (d.connected) {
        ElMessage.success(t('accounting_page.messages.connect_ok', { detail: d.company_name || d.message || '' }));
      } else {
        ElMessage.error(t('accounting_page.messages.connect_fail', { error: d.error || '' }));
      }
    }
    await loadData();
  } catch (e) {
    ElMessage.error(t('accounting_page.messages.test_fail'));
  }
}

async function syncNow(row) {
  try {
    const res = await syncPendingAccounting(row.id);
    if (res.data?.success) {
      const d = res.data.data;
      ElMessage.success(t('accounting_page.messages.sync_ok', { total: d.total, success: d.success, failed: d.failed }));
    }
  } catch (e) {
    ElMessage.error(t('accounting_page.messages.sync_fail'));
  }
}

async function viewLogs(row) {
  currentIntegrationId.value = row.id;
  showLogDialog.value = true;
  logLoading.value = true;
  try {
    const res = await getAccountingLogs(row.id);
    if (res.data?.success) {
      syncLogs.value = res.data.data.data || [];
    }
  } finally {
    logLoading.value = false;
  }
}

onMounted(loadData);
</script>

<style scoped>
.accounting-page { padding: 24px; }
.provider-card { transition: all 0.2s; cursor: default; }
.provider-card:hover { transform: translateY(-2px); }
</style>
