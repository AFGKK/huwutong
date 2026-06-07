<template>
  <div class="api-keys-page">
    <div class="page-header">
      <div class="header-left">
        <h2>API 密钥管理</h2>
        <span class="header-subtitle">管理 API 密钥、权限等级和访问控制</span>
      </div>
      <div class="header-right">
        <el-button @click="fetchAll"><el-icon><Refresh /></el-icon>刷新</el-button>
        <el-button type="primary" @click="showCreate = true"><el-icon><Plus /></el-icon>创建密钥</el-button>
      </div>
    </div>
    <el-tabs v-model="activeTab">
      <el-tab-pane label="密钥列表" name="keys">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">密钥总数</div><div class="stat-value">{{ stats.total }}</div></div></el-card></el-col>
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">已启用</div><div class="stat-value text-success">{{ stats.active }}</div></div></el-card></el-col>
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">即将过期</div><div class="stat-value text-warning">{{ overview.keys_expiring_soon }}</div></div></el-card></el-col>
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">接近限额</div><div class="stat-value text-danger">{{ overview.keys_near_quota }}</div></div></el-card></el-col>
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">总请求数</div><div class="stat-value">{{ overview.total_usage_count || 0 }}</div></div></el-card></el-col>
          <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">最大限额</div><div class="stat-value">{{ maxKeys }}</div></div></el-card></el-col>
        </el-row>
        <el-card shadow="never">
          <el-table :data="keys" v-loading="loading" stripe @row-click="showDetail">
            <el-table-column label="名称" min-width="140">
              <template #default="{ row }">
                <div class="key-name">
                  <span class="name-text">{{ row.name }}</span>
                  <el-tag v-if="!row.is_active" size="small" type="danger" effect="dark">已禁用</el-tag>
                  <el-tag v-if="isExpiring(row)" size="small" type="warning" effect="light">即将过期</el-tag>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="Key ID" min-width="160"><template #default="{ row }"><code class="key-id-text">{{ row.key_id }}</code></template></el-table-column>
            <el-table-column label="等级" width="100"><template #default="{ row }"><el-tag :type="tierType(row.tier)" size="small">{{ tierLabel(row.tier) }}</el-tag></template></el-table-column>
            <el-table-column label="权限" width="90"><template #default="{ row }"><el-tag :type="permType(row.permissions)" size="small">{{ permLabel(row.permissions) }}</el-tag></template></el-table-column>
            <el-table-column label="限流" width="70">{{ row.rate_limit || '---' }}</el-table-column>
            <el-table-column label="配额" width="100">
              <template #default="{ row }">
                <span v-if="row.usage_quota">{{ row.usage_count }}/{{ row.usage_quota }}</span>
                <span v-else class="text-muted">不限</span>
              </template>
            </el-table-column>
            <el-table-column label="每日" width="90">
              <template #default="{ row }">
                <span v-if="row.daily_quota">{{ row.daily_usage }}/{{ row.daily_quota }}</span>
                <span v-else class="text-muted">---</span>
              </template>
            </el-table-column>
            <el-table-column label="绑定 IP" width="110">
              <template #default="{ row }">
                <code v-if="row.allowed_ip" class="ip-text">{{ row.allowed_ip }}</code>
                <span v-else-if="row.allowed_ips" class="ip-text">{{ row.allowed_ips.length }} 个IP</span>
                <span v-else class="text-muted">不限</span>
              </template>
            </el-table-column>
            <el-table-column label="最近使用" width="140">{{ row.last_used_at ? formatTime(row.last_used_at) : '从未使用' }}</el-table-column>
            <el-table-column label="过期" width="130">
              <template #default="{ row }">
                <span v-if="row.expires_at" :class="isExpired(row.expires_at) ? 'text-danger' : ''">{{ formatTime(row.expires_at) }}</span>
                <span v-else class="text-muted">永不过期</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="70"><template #default="{ row }"><el-switch :model-value="row.is_active" :loading="togglingId === row.id" size="small" @change="toggleActive(row)" /></template></el-table-column>
            <el-table-column label="操作" width="220" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click.stop="showEdit(row)">编辑</el-button>
                <el-button text size="small" type="warning" @click.stop="handleRegenerate(row)">重生成</el-button>
                <el-popconfirm title="确定要删除此密钥吗？" confirm-button-text="删除" @confirm.stop="handleDelete(row)">
                  <template #reference><el-button text size="small" type="danger" @click.stop>删除</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
          <el-empty v-if="!loading && keys.length === 0" :image-size="80" description="暂无 API 密钥" />
        </el-card>
      </el-tab-pane>
      <el-tab-pane label="审计日志" name="audit">
        <el-table :data="auditLogs" v-loading="auditLoading" stripe max-height="600">
          <el-table-column label="时间" width="160"><template #default="{ row }">{{ formatTime(row.created_at) }}</template></el-table-column>
          <el-table-column label="操作" width="100"><template #default="{ row }"><el-tag :type="auditActionType(row.action)" size="small">{{ auditActionLabel(row.action) }}</el-tag></template></el-table-column>
          <el-table-column label="密钥" width="200"><template #default="{ row }">{{ row.api_key?.name || row.api_key_id }}</template></el-table-column>
          <el-table-column label="操作者" width="120">{{ row.actor_type }}#{{ row.actor_id }}</el-table-column>
          <el-table-column label="IP" width="120">{{ row.ip_address }}</el-table-column>
          <el-table-column label="备注" min-width="200">{{ row.remark || '---' }}</el-table-column>
        </el-table>
        <div class="pagination-wrap"><el-pagination v-model:current-page="auditPage" :page-size="30" :total="auditTotal" layout="prev, pager, next" @current-change="fetchAuditLogs" /></div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, CopyDocument, View, Hide } from '@element-plus/icons-vue'
import apiKeyApi from '@/api/apiKey'

const loading = ref(false)
const creating = ref(false)
const updating = ref(false)
const togglingId = ref(null)
const activeTab = ref('keys')
const showCreate = ref(false)
const showSecret = ref(false)
const showSecretText = ref(false)
const showEditDialog = ref(false)
const maxKeys = 20
const keys = ref([])
const newKeyData = ref({})
const currentEditKey = ref(null)
const createFormRef = ref(null)
const editFormRef = ref(null)
const tierConfig = ref({ tiers: [], permissions: [] })
const overview = ref({})
const stats = reactive({ total: 0, active: 0 })

const auditLogs = ref([])
const auditLoading = ref(false)
const auditPage = ref(1)
const auditTotal = ref(0)

const createForm = ref({
  name: '', description: '', permissions: 'read-write', tier: 'standard',
  allowed_endpoints: null, allowed_methods: null,
  rate_limit: null, usage_quota: null, daily_quota: null,
  allowed_ip: '', allowed_ips: null, expires_at: null,
})

const createRules = { name: [{ required: true, message: '请输入密钥名称', trigger: 'blur' }] }

const editForm = ref({})

const permLabels = {
  'read-only': '只读', 'read-write': '读写', 'admin': '管理员',
}
const permTypes = {
  'read-only': 'info', 'read-write': 'warning', 'admin': 'danger',
}
const tierLabels = {
  free: '免费版', standard: '标准版', enterprise: '企业版', custom: '自定义',
}
const tierTypes = {
  free: 'info', standard: 'primary', enterprise: 'success', custom: 'warning',
}
const auditLabels = {
  create: '创建', update: '更新', delete: '删除', regenerate: '重生成', toggle: '切换状态', usage: '使用',
}
const auditTypes = {
  create: 'success', update: 'primary', delete: 'danger', regenerate: 'warning', toggle: 'info', usage: '',
}

function permLabel(v) { return permLabels[v] || v }
function permType(v) { return permTypes[v] || 'info' }
function tierLabel(v) { return tierLabels[v] || v }
function tierType(v) { return tierTypes[v] || 'info' }
function auditActionLabel(v) { return auditLabels[v] || v }
function auditActionType(v) { return auditTypes[v] || '' }
function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : '---' }
function isExpired(d) { return d && new Date(d) < new Date() }
function isExpiring(r) {
  return r.expires_at && !isExpired(r.expires_at) && new Date(r.expires_at) < Date.now() + 7 * 86400000
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制')).catch(() => {
    const ta = document.createElement('textarea')
    ta.value = text
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    ElMessage.success('已复制')
  })
}

async function fetchAll() {
  loading.value = true
  try {
    const [keysRes, overviewRes, configRes] = await Promise.all([
      apiKeyApi.list(),
      apiKeyApi.myOverview(),
      apiKeyApi.getTierConfig(),
    ])
    if (keysRes.data?.success) {
      const d = keysRes.data.data
      keys.value = d.keys || []
      Object.assign(stats, d.stats || {})
    }
    if (overviewRes.data?.success) overview.value = overviewRes.data.data
    if (configRes.data?.success) tierConfig.value = configRes.data.data
  } catch { ElMessage.error('加载失败') }
  finally { loading.value = false }
}

async function fetchAuditLogs() {
  auditLoading.value = true
  try {
    const res = await apiKeyApi.allAuditLogs({ page: auditPage.value })
    if (res.data?.success) {
      auditLogs.value = res.data.data?.data || []
      auditTotal.value = res.data.data?.total || 0
    }
  } catch {}
  finally { auditLoading.value = false }
}

function showEdit(row) {
  currentEditKey.value = row
  editForm.value = {
    name: row.name,
    description: row.description || '',
    permissions: row.permissions || 'read-write',
    tier: row.tier || 'standard',
    allowed_endpoints: row.allowed_endpoints || null,
    allowed_methods: row.allowed_methods || null,
    rate_limit: row.rate_limit ?? null,
    usage_quota: row.usage_quota ?? null,
    daily_quota: row.daily_quota ?? null,
    allowed_ip: row.allowed_ip || '',
    allowed_ips: row.allowed_ips || null,
    expires_at: row.expires_at,
    is_active: row.is_active,
  }
  showEditDialog.value = true
}

function resetForm() {
  createForm.value = {
    name: '', description: '', permissions: 'read-write', tier: 'standard',
    allowed_endpoints: null, allowed_methods: null,
    rate_limit: null, usage_quota: null, daily_quota: null,
    allowed_ip: '', allowed_ips: null, expires_at: null,
  }
}

function resetAndRefresh() { resetForm(); fetchAll() }
function showDetail() {}

async function handleCreate() {
  const valid = await createFormRef.value?.validate().catch(() => false)
  if (!valid) return
  creating.value = true
  try {
    const p = { ...createForm.value }
    p.allowed_ip = p.allowed_ip || null
    if (!p.allowed_endpoints?.length) p.allowed_endpoints = null
    if (p.allowed_methods?.length) p.allowed_methods = p.allowed_methods.join(',')
    else p.allowed_methods = null
    if (!p.allowed_ips?.length) p.allowed_ips = null
    const res = await apiKeyApi.create(p)
    if (res.data?.success) {
      newKeyData.value = res.data.data
      showSecret.value = true; showSecretText.value = true
      showCreate.value = false
      ElMessage.success('创建成功')
    }
  } catch (e) { ElMessage.error(e.response?.data?.error?.message || '创建失败') }
  finally { creating.value = false }
}

async function handleUpdate() {
  updating.value = true
  try {
    const p = { ...editForm.value }
    p.allowed_ip = p.allowed_ip || null
    if (!p.allowed_endpoints?.length) p.allowed_endpoints = null
    if (p.allowed_methods?.length) p.allowed_methods = p.allowed_methods.join(',')
    else p.allowed_methods = null
    if (!p.allowed_ips?.length) p.allowed_ips = null
    const res = await apiKeyApi.update(currentEditKey.value.id, p)
    if (res.data?.success) {
      ElMessage.success('已更新')
      showEditDialog.value = false
      fetchAll()
    }
  } catch (e) { ElMessage.error(e.response?.data?.error?.message || '更新失败') }
  finally { updating.value = false }
}

async function handleDelete(row) {
  try {
    const res = await apiKeyApi.delete(row.id)
    if (res.data?.success) { ElMessage.success('已删除'); fetchAll() }
  } catch { ElMessage.error('删除失败') }
}

async function handleRegenerate(row) {
  try {
    await ElMessageBox.confirm(
      '重新生成后旧的 Secret Key 将立即失效。确认重新生成"' + row.name + '"的密钥？',
      '重新生成',
      { confirmButtonText: '确认', cancelButtonText: '取消', type: 'warning' },
    )
    const res = await apiKeyApi.regenerate(row.id)
    if (res.data?.success) {
      newKeyData.value = {
        key_id: res.data.data.key_id,
        secret: res.data.data.secret,
        name: row.name,
        tier: row.tier,
        permissions: row.permissions,
      }
      showSecret.value = true; showSecretText.value = true
      ElMessage.success('密钥已重新生成')
      fetchAll()
    }
  } catch {}
}

async function toggleActive(row) {
  togglingId.value = row.id
  try {
    const res = await apiKeyApi.toggleActive(row.id)
    if (res.data?.success) {
      ElMessage.success(row.is_active ? '已禁用' : '已启用')
      fetchAll()
    }
  } catch { ElMessage.error('操作失败') }
  finally { togglingId.value = null }
}

onMounted(() => { fetchAll(); fetchAuditLogs() })
</script>

<style scoped>
.api-keys-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.mb-4 { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-label { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 6px; }
.stat-value { font-size: 24px; font-weight: 700; color: var(--el-text-color-primary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }
.text-muted { color: var(--el-text-color-placeholder); }
.key-name { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.name-text { font-weight: 500; }
.key-id-text { font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace; font-size: 12px; cursor: pointer; user-select: all; }
.ip-text { font-family: monospace; font-size: 12px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
:deep(.el-card__body) { padding: 16px; }
</style>
