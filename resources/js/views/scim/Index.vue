<template>
  <div class="scim-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2>SCIM 自动用户同步</h2>
      <p class="text-muted">通过 SCIM 2.0 协议自动同步 IdP（Okta/Azure AD/OneLogin）中的用户和组</p>
    </div>

    <!-- 仪表盘卡片 -->
    <el-row :gutter="20" class="dashboard-cards">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_configs }}</div>
          <div class="stat-label">同步配置</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.active_configs }}</div>
          <div class="stat-label">已启用</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.total_synced }}</div>
          <div class="stat-label">已同步用户</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.failed_syncs }}</div>
          <div class="stat-label">同步失败</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 操作栏 -->
    <div class="action-bar">
      <el-button type="primary" @click="showDialog = true">
        <el-icon><Plus /></el-icon> 新增配置
      </el-button>
    </div>

    <!-- 配置列表 -->
    <el-table :data="configs" v-loading="loading" stripe style="width: 100%">
      <el-table-column prop="name" label="名称" min-width="140" />
      <el-table-column prop="provider" label="提供商" width="120">
        <template #default="{ row }">
          <el-tag :type="providerColor(row.provider)">{{ row.provider }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="base_url" label="基础 URL" min-width="200" show-overflow-tooltip />
      <el-table-column prop="sync_frequency" label="同步频率" width="110">
        <template #default="{ row }">
          <span>{{ freqLabel(row.sync_frequency) }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="enabled" label="状态" width="90">
        <template #default="{ row }">
          <el-switch
            :model-value="row.enabled"
            :loading="row._toggling"
            @change="toggleConfig(row)"
          />
        </template>
      </el-table-column>
      <el-table-column prop="last_sync_at" label="上次同步" width="170">
        <template #default="{ row }">
          <span v-if="row.last_sync_at">{{ row.last_sync_at }}</span>
          <span v-else class="text-muted">从未</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="300" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="testConnection(row)">测试</el-button>
          <el-button size="small" type="warning" :loading="row._syncing" @click="syncNow(row)">立即同步</el-button>
          <el-button size="small" @click="editConfig(row)">编辑</el-button>
          <el-button size="small" @click="showLogs(row)">日志</el-button>
          <el-popconfirm title="确定删除此配置？" @confirm="deleteConfig(row)">
            <template #reference>
              <el-button size="small" type="danger">删除</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 新增/编辑对话框 -->
    <el-dialog v-model="showDialog" :title="editingId ? '编辑配置' : '新增 SCIM 配置'" width="640px" :close-on-click-modal="false">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px" v-loading="saving">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="例如：Okta 生产同步" maxlength="100" />
        </el-form-item>
        <el-form-item label="提供商" prop="provider">
          <el-select v-model="form.provider" style="width:100%" @change="onProviderChange">
            <el-option label="通用 (Generic)" value="generic" />
            <el-option label="Okta" value="okta" />
            <el-option label="Azure AD" value="azure" />
            <el-option label="OneLogin" value="onelogin" />
          </el-select>
        </el-form-item>
        <el-form-item label="基础 URL" prop="base_url">
          <el-input v-model="form.base_url" placeholder="https://example.okta.com/scim" />
        </el-form-item>
        <el-form-item label="API Token" prop="api_token">
          <el-input v-model="form.api_token" type="password" show-password placeholder="IdP 生成的 API Token" />
        </el-form-item>
        <el-form-item label="同步频率">
          <el-select v-model="form.sync_frequency" style="width:100%">
            <el-option label="手动" value="manual" />
            <el-option label="每小时" value="hourly" />
            <el-option label="每天" value="daily" />
            <el-option label="每周" value="weekly" />
          </el-select>
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.enabled" />
        </el-form-item>

        <!-- 属性映射 -->
        <el-divider>属性映射</el-divider>
        <el-form-item label="外部用户名" prop="attribute_mapping.userName">
          <el-input v-model="form.attribute_mapping.userName" placeholder="userName" />
        </el-form-item>
        <el-form-item label="外部邮箱" prop="attribute_mapping.email">
          <el-input v-model="form.attribute_mapping.email" placeholder="emails[0].value" />
        </el-form-item>
        <el-form-item label="外部姓名" prop="attribute_mapping.displayName">
          <el-input v-model="form.attribute_mapping.displayName" placeholder="displayName" />
        </el-form-item>
        <el-form-item label="外部手机" prop="attribute_mapping.phone">
          <el-input v-model="form.attribute_mapping.phone" placeholder="phoneNumbers[0].value" />
        </el-form-item>

        <!-- 角色映射 -->
        <el-divider>角色映射</el-divider>
        <el-form-item label="默认角色">
          <el-select v-model="form.options.default_role" style="width:100%" clearable placeholder="选择默认角色">
            <el-option label="管理员" value="admin" />
            <el-option label="用户" value="user" />
            <el-option label="只读" value="readonly" />
          </el-select>
        </el-form-item>
        <el-form-item label="自动停用">
          <el-switch v-model="form.options.auto_disable" />
          <span class="text-muted" style="margin-left:8px">不再同步的用户自动停用</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDialog = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveConfig">保存</el-button>
      </template>
    </el-dialog>

    <!-- 同步日志对话框 -->
    <el-dialog v-model="showLogDialog" title="同步日志" width="800px">
      <el-table :data="syncLogs" v-loading="logsLoading" stripe style="width:100%">
        <el-table-column prop="started_at" label="开始时间" width="170" />
        <el-table-column prop="finished_at" label="结束时间" width="170" />
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
              {{ row.status }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_count" label="总计" width="70" align="center" />
        <el-table-column prop="success_count" label="成功" width="70" align="center" />
        <el-table-column prop="failed_count" label="失败" width="70" align="center" />
        <el-table-column prop="created_count" label="新增" width="70" align="center" />
        <el-table-column prop="updated_count" label="更新" width="70" align="center" />
        <el-table-column prop="disabled_count" label="停用" width="70" align="center" />
        <el-table-column label="错误" min-width="160" show-overflow-tooltip>
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
import { ref, reactive, onMounted } from 'vue'
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

const rules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  provider: [{ required: true, message: '请选择提供商', trigger: 'change' }],
  base_url: [{ required: true, message: '请输入基础 URL', trigger: 'blur' }],
  api_token: [{ required: true, message: '请输入 API Token', trigger: 'blur' }],
}

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

function freqLabel(freq) {
  const map = { manual: '手动', hourly: '每小时', daily: '每天', weekly: '每周' }
  return map[freq] || freq
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
    ElMessage.error('加载数据失败')
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
      ElMessage.success('配置已更新')
    } else {
      await createScimConfig(payload)
      ElMessage.success('配置已创建')
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
    ElMessage.success('配置已删除')
    await loadData()
  } catch (_) { /* ignore */ }
}

async function toggleConfig(row) {
  row._toggling = true
  try {
    await updateScimConfig(row.id, { enabled: !row.enabled })
    row.enabled = !row.enabled
    ElMessage.success(row.enabled ? '已启用' : '已禁用')
  } catch (_) { /* ignore */ }
  finally { row._toggling = false }
}

async function testConnection(row) {
  try {
    const res = await testScimConnection(row.id)
    ElMessage.success(res.message || '连接测试通过')
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '连接测试失败')
  }
}

async function syncNow(row) {
  row._syncing = true
  try {
    const res = await syncScimNow(row.id)
    ElMessage.success(res.message || '同步已触发')
    await loadData()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '同步失败')
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
  color: #409eff;
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
