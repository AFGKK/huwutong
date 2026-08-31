<template>
  <div class="scim-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>{{ t('scim_page.title') }}</h2>
      <p class="text-muted">{{ t('scim_page.subtitle') }}</p>
    </div>

    <!-- 仪表盘卡片 -->
    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_configs }}</div>
          <div class="stat-label">{{ t('scim_page.stats.total_configs') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.active_configs }}</div>
          <div class="stat-label">{{ t('scim_page.stats.active_configs') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_synced }}</div>
          <div class="stat-label">{{ t('scim_page.stats.synced_users') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.failed_syncs }}</div>
          <div class="stat-label">{{ t('scim_page.stats.failed_syncs') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <div class="action-bar">
      <el-button type="primary" @click="showDialog = true">
        <el-icon><Plus /></el-icon> {{ t('scim_page.add_config') }}
      </el-button>
    </div>

    <!-- 配置列表 -->
    <el-table :data="configs" v-loading="loading" stripe style="width: 100%">
      <el-table-column prop="name" :label="t('scim_page.cols.name')" min-width="140" />
      <el-table-column prop="provider" :label="t('scim_page.cols.provider')" width="120">
        <template #default="{ row }">
          <el-tag :type="providerColor(row.provider)">{{ providerLabel(row.provider) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="base_url" :label="t('scim_page.cols.base_url')" min-width="200" show-overflow-tooltip />
      <el-table-column prop="sync_frequency" :label="t('scim_page.cols.sync_frequency')" width="110">
        <template #default="{ row }">
          <span>{{ freqLabel(row.sync_frequency) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="enabled" :label="t('scim_page.cols.status')" width="90">
        <template #default="{ row }">
          <el-switch
            :model-value="row.enabled"
            :loading="row._toggling"
            @change="toggleConfig(row)"
          />
        </template>
      </el-table-column>
      <el-table-column prop="last_sync_at" :label="t('scim_page.cols.last_sync')" width="170">
        <template #default="{ row }">
          <span v-if="row.last_sync_at">{{ row.last_sync_at }}</span>
          <span v-else class="text-muted">{{ t('scim_page.never') }}</span>
        </template>
      </el-table-column>
      <el-table-column :label="t('scim_page.cols.actions')" width="300" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="testConnection(row)">{{ t('scim_page.actions.test') }}</el-button>
          <el-button size="small" type="warning" :loading="row._syncing" @click="syncNow(row)">{{ t('scim_page.actions.sync_now') }}</el-button>
          <el-button size="small" @click="editConfig(row)">{{ t('actions.edit') }}</el-button>
          <el-button size="small" @click="showLogs(row)">{{ t('scim_page.actions.logs') }}</el-button>
          <el-popconfirm :title="t('scim_page.confirm.delete')" @confirm="deleteConfig(row)">
            <template #reference>
              <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="showDialog" :title="editingId ? t('scim_page.dialog.edit_title') : t('scim_page.dialog.create_title')" width="640px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
        <el-form-item :label="t('scim_page.form.name')" prop="name">
          <el-input v-model="form.name" :placeholder="t('scim_page.form.name_ph')" maxlength="100" />
        </el-form-item>
        <el-form-item :label="t('scim_page.form.provider')" prop="provider">
          <el-select v-model="form.provider" style="width:100%" @change="onProviderChange">
            <el-option v-for="opt in providerOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('scim_page.form.base_url')" prop="base_url">
          <el-input v-model="form.base_url" placeholder="https://example.okta.com/scim" />
        </el-form-item>
        <el-form-item :label="t('scim_page.form.api_token')" prop="api_token">
          <el-input v-model="form.api_token" type="password" show-password :placeholder="t('scim_page.form.api_token_ph')" />
        </el-form-item>
        <el-form-item :label="t('scim_page.form.sync_frequency')">
          <el-select v-model="form.sync_frequency" style="width:100%">
            <el-option v-for="opt in frequencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('scim_page.form.enabled')">
          <el-switch v-model="form.enabled" />
        </el-form-item>

        <!-- 属性映射 -->
        <el-divider>{{ t('scim_page.attr_mapping.title') }}</el-divider>
        <el-form-item :label="t('scim_page.attr_mapping.external_username')" prop="attribute_mapping.userName">
          <el-input v-model="form.attribute_mapping.userName" :placeholder="t('scim_page.attr_mapping.username_ph')" />
        </el-form-item>
        <el-form-item :label="t('scim_page.attr_mapping.external_email')" prop="attribute_mapping.email">
          <el-input v-model="form.attribute_mapping.email" :placeholder="t('scim_page.attr_mapping.email_ph')" />
        </el-form-item>
        <el-form-item :label="t('scim_page.attr_mapping.external_display_name')" prop="attribute_mapping.displayName">
          <el-input v-model="form.attribute_mapping.displayName" :placeholder="t('scim_page.attr_mapping.display_name_ph')" />
        </el-form-item>
        <el-form-item :label="t('scim_page.attr_mapping.external_phone')" prop="attribute_mapping.phone">
          <el-input v-model="form.attribute_mapping.phone" :placeholder="t('scim_page.attr_mapping.phone_ph')" />
        </el-form-item>

        <!-- 角色映射 -->
        <el-divider>{{ t('scim_page.role_mapping.title') }}</el-divider>
        <el-form-item :label="t('scim_page.role_mapping.default_role')">
          <el-select v-model="form.options.default_role" style="width:100%" clearable :placeholder="t('scim_page.role_mapping.default_role_ph')">
            <el-option v-for="opt in roleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('scim_page.role_mapping.auto_disable')">
          <el-switch v-model="form.options.auto_disable" />
          <span class="text-muted" style="margin-left:8px">{{ t('scim_page.role_mapping.auto_disable_hint') }}</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="saving" @click="saveConfig">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 同步日志对话框 -->
    <el-dialog v-model="showLogDialog" :title="t('scim_page.logs.title')" width="800px">
      <el-table :data="syncLogs" v-loading="logsLoading" stripe style="width:100%">
        <el-table-column prop="started_at" :label="t('scim_page.logs.started_at')" width="170" />
        <el-table-column prop="finished_at" :label="t('scim_page.logs.finished_at')" width="170" />
        <el-table-column prop="status" :label="t('scim_page.logs.status')" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
              {{ logStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_count" :label="t('scim_page.logs.total')" width="70" align="center" />
        <el-table-column prop="success_count" :label="t('scim_page.logs.success')" width="70" align="center" />
        <el-table-column prop="failed_count" :label="t('scim_page.logs.failed')" width="70" align="center" />
        <el-table-column prop="created_count" :label="t('scim_page.logs.created')" width="70" align="center" />
        <el-table-column prop="updated_count" :label="t('scim_page.logs.updated')" width="70" align="center" />
        <el-table-column prop="disabled_count" :label="t('scim_page.logs.disabled')" width="70" align="center" />
        <el-table-column :label="t('scim_page.logs.error')" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.error_message" class="text-danger">{{ row.error_message }}</span>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-if="logTotal > logPerPage"
        background
        layout="prev, pager, next"
        :total="logTotal"
        :page-size="logPerPage"
        :current-page="logPage"
        @current-change="loadLogs"
        style="margin-top:16px;justify-content:center"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import {
  getScimDashboard,
  getScimConfigs,
  createScimConfig,
  updateScimConfig,
  deleteScimConfig,
  testScimConnection,
  syncScimNow,
  getScimSyncLogs,
  getScimDefaultMapping,
} from '@/api/scim'

const { t } = useI18n()

// ── 数据 ──
const loading = ref(false)
const saving = ref(false)
const configs = ref([])
const dashboard = reactive({
  total_configs: 0,
  active_configs: 0,
  total_synced: 0,
  failed_syncs: 0,
})

const providerKeys = ['generic', 'okta', 'azure', 'onelogin']
const frequencyKeys = ['manual', 'hourly', 'daily', 'weekly']
const roleKeys = [
  { value: 'admin', key: 'team_page.roles.admin' },
  { value: 'user', key: 'scim_page.roles.user' },
  { value: 'readonly', key: 'team_page.roles.readonly' },
]

const providerOptions = computed(() =>
  providerKeys.map((value) => ({ value, label: t(`scim_page.providers.${value}`) }))
)

const frequencyOptions = computed(() =>
  frequencyKeys.map((value) => ({ value, label: t(`scim_page.frequencies.${value}`) }))
)

const roleOptions = computed(() =>
  roleKeys.map(({ value, key }) => ({ value, label: t(key) }))
)

const freqLabels = computed(() =>
  Object.fromEntries(frequencyKeys.map((k) => [k, t(`scim_page.frequencies.${k}`)]))
)

const providerLabels = computed(() =>
  Object.fromEntries(providerKeys.map((k) => [k, t(`scim_page.providers.${k}`)]))
)

const logStatusLabels = computed(() => ({
  success: t('scim_page.log_status.success'),
  partial: t('scim_page.log_status.partial'),
}))

const rules = computed(() => ({
  name: [{ required: true, message: t('scim_page.validation.name_required'), trigger: 'blur' }],
  provider: [{ required: true, message: t('scim_page.validation.provider_required'), trigger: 'change' }],
  base_url: [{ required: true, message: t('scim_page.validation.base_url_required'), trigger: 'blur' }],
  api_token: [{ required: true, message: t('scim_page.validation.api_token_required'), trigger: 'blur' }],
}))

// ── 对话框 ──
const showDialog = ref(false)
const editingId = ref(null)
const formRef = ref(null)
const form = reactive({
  name: '',
  provider: 'generic',
  base_url: '',
  api_token: '',
  sync_frequency: 'manual',
  enabled: false,
  attribute_mapping: {
    userName: 'userName',
    email: 'emails[0].value',
    displayName: 'displayName',
    phone: 'phoneNumbers[0].value',
  },
  options: {
    default_role: 'user',
    auto_disable: true,
  },
})

// ── 日志 ──
const showLogDialog = ref(false)
const logsLoading = ref(false)
const syncLogs = ref([])
const logTotal = ref(0)
const logPerPage = ref(15)
const logPage = ref(1)
const currentLogConfigId = ref(null)

// ── 方法 ──
function providerColor(provider) {
  const map = { okta: 'primary', azure: 'success', onelogin: 'warning', generic: 'info' }
  return map[provider] || 'info'
}

function providerLabel(provider) {
  return providerLabels.value[provider] || provider
}

function freqLabel(freq) {
  return freqLabels.value[freq] || freq
}

function logStatusLabel(status) {
  return logStatusLabels.value[status] || status
}

async function loadData() {
  loading.value = true
  try {
    const [dashRes, configRes] = await Promise.all([
      getScimDashboard(),
      getScimConfigs(),
    ])
    Object.assign(dashboard, dashRes.data || {})
    configs.value = (configRes.data || []).map(c => ({ ...c, _toggling: false, _syncing: false }))
  } catch (e) {
    ElMessage.error(t('scim_page.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editingId.value = null
  form.name = ''
  form.provider = 'generic'
  form.base_url = ''
  form.api_token = ''
  form.sync_frequency = 'manual'
  form.enabled = false
  form.attribute_mapping = { userName: 'userName', email: 'emails[0].value', displayName: 'displayName', phone: 'phoneNumbers[0].value' }
  form.options = { default_role: 'user', auto_disable: true }
}

async function onProviderChange() {
  try {
    const res = await getScimDefaultMapping()
    if (res.data) {
      form.attribute_mapping = { ...form.attribute_mapping, ...res.data }
    }
  } catch (_) { /* ignore */ }
}

function editConfig(row) {
  editingId.value = row.id
  form.name = row.name
  form.provider = row.provider
  form.base_url = row.base_url
  form.api_token = ''
  form.sync_frequency = row.sync_frequency || 'manual'
  form.enabled = row.enabled
  form.attribute_mapping = { ...(row.attribute_mapping || {}) }
  form.options = { ...(row.options || { default_role: 'user', auto_disable: true }) }
  showDialog.value = true
}

async function saveConfig() {
  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return
  saving.value = true
  try {
    const payload = {
      ...form,
      attribute_mapping: Object.keys(form.attribute_mapping).length > 0 ? form.attribute_mapping : null,
      options: Object.keys(form.options).length > 0 ? form.options : null,
    }
    if (editingId.value) {
      await updateScimConfig(editingId.value, payload)
      ElMessage.success(t('scim_page.messages.config_updated'))
    } else {
      await createScimConfig(payload)
      ElMessage.success(t('scim_page.messages.config_created'))
    }
    showDialog.value = false
    resetForm()
    await loadData()
  } catch (e) {
    // 后端已返回消息
  } finally {
    saving.value = false
  }
}

async function deleteConfig(row) {
  try {
    await deleteScimConfig(row.id)
    ElMessage.success(t('scim_page.messages.config_deleted'))
    await loadData()
  } catch (_) { /* ignore */ }
}

async function toggleConfig(row) {
  row._toggling = true
  try {
    await updateScimConfig(row.id, { enabled: !row.enabled })
    row.enabled = !row.enabled
    ElMessage.success(row.enabled ? t('scim_page.messages.enabled') : t('scim_page.messages.disabled'))
  } catch (_) { /* ignore */ }
  finally { row._toggling = false }
}

async function testConnection(row) {
  try {
    const res = await testScimConnection(row.id)
    ElMessage.success(res.message || t('scim_page.messages.test_ok'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('scim_page.messages.test_failed'))
  }
}

async function syncNow(row) {
  row._syncing = true
  try {
    const res = await syncScimNow(row.id)
    ElMessage.success(res.message || t('scim_page.messages.sync_triggered'))
    await loadData()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('scim_page.messages.sync_failed'))
  } finally {
    row._syncing = false
  }
}

async function showLogs(row) {
  currentLogConfigId.value = row.id
  logPage.value = 1
  showLogDialog.value = true
  await loadLogs(1)
}

async function loadLogs(page) {
  if (!currentLogConfigId.value) return
  logPage.value = page
  logsLoading.value = true
  try {
    const res = await getScimSyncLogs(currentLogConfigId.value, { page, per_page: logPerPage.value })
    syncLogs.value = res.data?.data || res.data || []
    logTotal.value = res.data?.total || res.meta?.total || 0
  } catch (_) {
    syncLogs.value = []
  } finally {
    logsLoading.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.scim-page {
  padding: 20px;
}
.page-header {
  margin-bottom: 24px;
}
.page-header h2 {
  margin: 0 0 4px;
  font-size: 22px;
}
.page-header .text-muted {
  color: #909399;
  font-size: 14px;
}
.dashboard-cards .stat-card {
  text-align: center;
}
.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #0f172a;
}
.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}
.action-bar {
  margin-bottom: 16px;
}
.text-muted {
  color: #909399;
  font-size: 12px;
}
.text-danger {
  color: #f56c6c;
}
</style>
