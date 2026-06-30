<template>
  <div class="gdpr-page">
    <div class="page-header">
      <h2>GDPR 合规管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="refreshStats" :loading="loading.stats">
          刷新统计
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.total_requests ?? 0 }}</div>
          <div class="stat-label">总请求数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card warning">
          <div class="stat-value">{{ stats.pending ?? 0 }}</div>
          <div class="stat-label">待处理</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card success">
          <div class="stat-value">{{ stats.completed ?? 0 }}</div>
          <div class="stat-label">已完成</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card info">
          <div class="stat-value">{{ stats.published_dpa ?? 0 }}</div>
          <div class="stat-label">已发布 DPA</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 标签页 -->
    <el-tabs v-model="activeTab" class="main-tabs">
      <!-- Tab 1: DSR 请求管理 -->
      <el-tab-pane label="数据主体请求" name="requests">
        <el-card shadow="never">
          <template #header>
            <div class="card-header">
              <span>DSR 请求列表</span>
              <div class="header-actions">
                <el-select v-model="filters.status" placeholder="状态筛选" clearable @change="loadRequests" style="width:140px;margin-right:8px;">
                  <el-option label="待处理" value="pending" />
                  <el-option label="处理中" value="processing" />
                  <el-option label="已完成" value="completed" />
                  <el-option label="已拒绝" value="rejected" />
                  <el-option label="失败" value="failed" />
                </el-select>
                <el-select v-model="filters.type" placeholder="类型筛选" clearable @change="loadRequests" style="width:140px;">
                  <el-option label="数据访问" value="access" />
                  <el-option label="数据导出" value="export" />
                  <el-option label="数据更正" value="rectification" />
                  <el-option label="数据删除" value="erasure" />
                  <el-option label="限制处理" value="restrict" />
                  <el-option label="数据可移植性" value="portability" />
                  <el-option label="反对处理" value="object" />
                </el-select>
              </div>
            </div>
          </template>

          <el-table :data="requests" v-loading="loading.requests" stripe>
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column label="用户" min-width="140">
              <template #default="{ row }">
                <div>{{ row.user_name }}</div>
                <div class="text-muted">{{ row.user_email }}</div>
              </template>
            </el-table-column>
            <el-table-column label="类型" width="120">
              <template #default="{ row }">
                <el-tag :type="typeTagType(row.type)" size="small">{{ row.type_label }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">{{ row.status_label }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="reason" label="原因" min-width="150" show-overflow-tooltip />
            <el-table-column label="处理人" width="120">
              <template #default="{ row }">
                {{ row.processor_name || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="提交时间" width="160" />
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showDetail(row)">详情</el-button>
                <el-button
                  size="small"
                  type="primary"
                  :disabled="!['pending', 'failed'].includes(row.status)"
                  @click="handleProcess(row)"
                >处理</el-button>
                <el-button
                  size="small"
                  type="success"
                  v-if="row.status === 'pending'"
                  @click="handleApprove(row)"
                >批准</el-button>
                <el-button
                  size="small"
                  type="danger"
                  v-if="row.status === 'pending'"
                  @click="handleReject(row)"
                >拒绝</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
            <el-pagination
              v-model:current-page="pagination.current_page"
              :page-size="pagination.per_page"
              :total="pagination.total"
              layout="prev, pager, next"
              @current-change="loadRequests"
            />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- Tab 2: DPA 管理 -->
      <el-tab-pane label="数据处理协议 (DPA)" name="dpa">
        <el-card shadow="never">
          <template #header>
            <div class="card-header">
              <span>DPA 协议列表</span>
              <el-button type="primary" size="small" @click="showCreateDpa">
                <el-icon><Plus /></el-icon> 新建 DPA
              </el-button>
            </div>
          </template>

          <el-table :data="dpas" v-loading="loading.dpas" stripe>
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="title" label="标题" min-width="160" />
            <el-table-column prop="version" label="版本" width="80" />
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="dpaStatusTag(row.status)" size="small">
                  {{ row.status === 'draft' ? '草稿' : row.status === 'published' ? '已发布' : '已归档' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="jurisdiction" label="管辖法律" width="140" />
            <el-table-column label="签署数" width="80">
              <template #default="{ row }">{{ row.signatures_count ?? 0 }}</template>
            </el-table-column>
            <el-table-column prop="effective_at" label="生效时间" width="160" />
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button size="small" @click="showDpaDetail(row)">查看</el-button>
                <el-button
                  size="small"
                  type="primary"
                  v-if="row.status === 'draft'"
                  @click="handlePublishDpa(row)"
                >发布</el-button>
                <el-button
                  size="small"
                  type="warning"
                  v-if="row.status === 'draft'"
                  @click="showEditDpa(row)"
                >编辑</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>
    </el-tabs>

    <!-- DSR 请求详情对话框 -->
    <el-dialog v-model="detailVisible" title="DSR 请求详情" width="600px">
      <template v-if="currentDetail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="ID">{{ currentDetail.id }}</el-descriptions-item>
          <el-descriptions-item label="类型">{{ currentDetail.type_label }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTagType(currentDetail.status)" size="small">{{ currentDetail.status_label }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="用户">{{ currentDetail.user_name }} ({{ currentDetail.user_email }})</el-descriptions-item>
          <el-descriptions-item label="原因" :span="2">{{ currentDetail.reason || '无' }}</el-descriptions-item>
          <el-descriptions-item label="处理人">{{ currentDetail.processor_name || '-' }}</el-descriptions-item>
          <el-descriptions-item label="完成时间">{{ currentDetail.completed_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="文件大小">{{ formatFileSize(currentDetail.file_size) }}</el-descriptions-item>
          <el-descriptions-item label="过期时间">{{ currentDetail.expires_at || '-' }}</el-descriptions-item>
          <el-descriptions-item label="拒绝原因" v-if="currentDetail.rejection_reason" :span="2">
            <span class="text-danger">{{ currentDetail.rejection_reason }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="创建时间" :span="2">{{ currentDetail.created_at }}</el-descriptions-item>
        </el-descriptions>
        <div style="margin-top:16px;" v-if="currentDetail.output_file && currentDetail.status === 'completed'">
          <el-button type="primary" @click="handleDownload(currentDetail)">
            <el-icon><Download /></el-icon> 下载导出文件
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 新建/编辑 DPA 对话框 -->
    <el-dialog v-model="dpaFormVisible" :title="isEditingDpa ? '编辑 DPA' : '新建 DPA'" width="700px">
      <el-form :model="dpaForm" label-width="120px" :rules="dpaRules" ref="dpaFormRef">
        <el-form-item label="标题" prop="title">
          <el-input v-model="dpaForm.title" placeholder="数据处理协议" />
        </el-form-item>
        <el-form-item label="版本号" prop="version">
          <el-input v-model="dpaForm.version" placeholder="1.0" style="width:120px;" />
        </el-form-item>
        <el-form-item label="管辖法律" prop="jurisdiction">
          <el-input v-model="dpaForm.jurisdiction" placeholder="中华人民共和国法律" />
        </el-form-item>
        <el-form-item label="协议内容" prop="content">
          <el-input
            v-model="dpaForm.content"
            type="textarea"
            :rows="8"
            placeholder="协议全文，支持 Markdown 格式"
          />
        </el-form-item>
        <el-form-item label="数据类别">
          <el-select v-model="dpaForm.data_categories" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option label="用户账户信息" value="用户账户信息（姓名、邮箱、电话）" />
            <el-option label="授权记录" value="授权记录（License Key、设备指纹）" />
            <el-option label="订阅支付信息" value="订阅与支付信息" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理目的">
          <el-select v-model="dpaForm.processing_purposes" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option label="授权验证" value="提供 License 授权验证服务" />
            <el-option label="客户支持" value="客户支持与故障排查" />
            <el-option label="合规审计" value="合规审计与日志记录" />
          </el-select>
        </el-form-item>
        <el-form-item label="安全措施">
          <el-select v-model="dpaForm.security_measures" multiple filterable allow-create default-first-option style="width:100%;">
            <el-option label="传输加密" value="数据传输加密（TLS 1.3）" />
            <el-option label="静态加密" value="静态数据加密（AES-256）" />
            <el-option label="访问控制" value="访问控制（RBAC + MFA）" />
          </el-select>
        </el-form-item>
        <el-form-item label="到期时间">
          <el-date-picker v-model="dpaForm.expires_at" type="date" placeholder="可选" style="width:100%;" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dpaFormVisible = false">取消</el-button>
        <el-button type="primary" :loading="loading.saveDpa" @click="saveDpa">
          {{ isEditingDpa ? '保存' : '创建' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Download } from '@element-plus/icons-vue'
import {
  getGdprRequests,
  getGdprStats,
  processGdprRequest,
  reviewGdprRequest,
  getDpaList,
  createDpa,
  updateDpa,
  publishDpa,
  downloadGdprExport,
} from '@/api/gdprCompliance'

const activeTab = ref('requests')

const loading = reactive({
  stats: false,
  requests: false,
  dpas: false,
  saveDpa: false,
})

const stats = reactive({
  total_requests: 0,
  pending: 0,
  processing: 0,
  completed: 0,
  rejected: 0,
  failed: 0,
  published_dpa: 0,
})

// DSR 请求管理
const filters = reactive({
  status: '',
  type: '',
})

const requests = ref([])
const pagination = reactive({
  current_page: 1,
  per_page: 20,
  total: 0,
})

async function loadRequests() {
  loading.requests = true
  try {
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
    }
    if (filters.status) params.status = filters.status
    if (filters.type) params.type = filters.type
    const res = await getGdprRequests(params)
    requests.value = res.data?.data ?? []
    if (res.data?.meta) {
      pagination.current_page = res.data.meta.current_page
      pagination.per_page = res.data.meta.per_page
      pagination.total = res.data.meta.total
    }
  } catch (e) {
    ElMessage.error('加载 DSR 请求失败')
  } finally {
    loading.requests = false
  }
}

async function refreshStats() {
  loading.stats = true
  try {
    const res = await getGdprStats()
    Object.assign(stats, res.data ?? {})
  } catch {
    ElMessage.error('加载统计失败')
  } finally {
    loading.stats = false
  }
}

function typeTagType(type) {
  const map = { access: 'primary', export: 'success', rectification: 'warning', erasure: 'danger', restrict: 'info', portability: '', object: '' }
  return map[type] || ''
}

function statusTagType(status) {
  const map = { pending: 'warning', processing: 'primary', completed: 'success', approved: 'success', rejected: 'danger', failed: 'danger' }
  return map[status] || ''
}

// DSR 详情
const detailVisible = ref(false)
const currentDetail = ref(null)

function showDetail(row) {
  currentDetail.value = row
  detailVisible.value = true
}

async function handleProcess(row) {
  try {
    await ElMessageBox.confirm(`确定处理 "${row.type_label}" 请求吗？`, '确认')
    await processGdprRequest(row.id)
    ElMessage.success('请求处理完成')
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '处理失败')
  }
}

async function handleApprove(row) {
  try {
    await ElMessageBox.confirm(`确定批准该 ${row.type_label} 请求？`, '确认')
    await reviewGdprRequest(row.id, { action: 'approve' })
    ElMessage.success('已批准')
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

async function handleReject(row) {
  try {
    const { value } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝请求', {
      confirmButtonText: '拒绝',
      cancelButtonText: '取消',
      inputType: 'textarea',
      inputValidator: (v) => !!v || '请输入拒绝原因',
    })
    await reviewGdprRequest(row.id, { action: 'reject', reason: value })
    ElMessage.success('已拒绝')
    await loadRequests()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '操作失败')
  }
}

function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(1)} ${units[i]}`
}

async function handleDownload(row) {
  try {
    const blob = await downloadGdprExport(row.id)
    const url = window.URL.createObjectURL(new Blob([blob]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `gdpr-export-${row.id}.json`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch {
    ElMessage.error('下载失败')
  }
}

// DPA 管理
const dpas = ref([])

async function loadDpas() {
  loading.dpas = true
  try {
    const res = await getDpaList({ per_page: 50 })
    dpas.value = res.data?.data ?? []
  } catch {
    ElMessage.error('加载 DPA 列表失败')
  } finally {
    loading.dpas = false
  }
}

function dpaStatusTag(status) {
  return status === 'published' ? 'success' : status === 'draft' ? 'info' : 'warning'
}

// DPA 表单
const dpaFormVisible = ref(false)
const isEditingDpa = ref(false)
const editingDpaId = ref(null)
const dpaFormRef = ref(null)

const dpaForm = reactive({
  title: '',
  version: '',
  content: '',
  jurisdiction: '',
  data_categories: [],
  processing_purposes: [],
  security_measures: [],
  expires_at: null,
})

const dpaRules = {
  title: [{ required: true, message: '请输入标题', trigger: 'blur' }],
  version: [{ required: true, message: '请输入版本号', trigger: 'blur' }],
  content: [{ required: true, message: '请输入协议内容', trigger: 'blur' }],
}

function resetDpaForm() {
  dpaForm.title = ''
  dpaForm.version = ''
  dpaForm.content = ''
  dpaForm.jurisdiction = ''
  dpaForm.data_categories = []
  dpaForm.processing_purposes = []
  dpaForm.security_measures = []
  dpaForm.expires_at = null
  isEditingDpa.value = false
  editingDpaId.value = null
}

function showCreateDpa() {
  resetDpaForm()
  dpaFormVisible.value = true
}

function showEditDpa(row) {
  resetDpaForm()
  isEditingDpa.value = true
  editingDpaId.value = row.id
  dpaForm.title = row.title
  dpaForm.version = row.version
  dpaForm.content = row.content || ''
  dpaForm.jurisdiction = row.jurisdiction || ''
  dpaForm.data_categories = row.data_categories || []
  dpaForm.processing_purposes = row.processing_purposes || []
  dpaForm.security_measures = row.security_measures || []
  dpaForm.expires_at = row.expires_at || null
  dpaFormVisible.value = true
}

async function saveDpa() {
  const valid = await dpaFormRef.value?.validate().catch(() => false)
  if (!valid) return

  loading.saveDpa = true
  try {
    const payload = { ...dpaForm }
    if (isEditingDpa.value) {
      await updateDpa(editingDpaId.value, payload)
      ElMessage.success('DPA 已更新')
    } else {
      await createDpa(payload)
      ElMessage.success('DPA 已创建')
    }
    dpaFormVisible.value = false
    await loadDpas()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '保存失败')
  } finally {
    loading.saveDpa = false
  }
}

function showDpaDetail(row) {
  currentDetail.value = row
  detailVisible.value = true
}

async function handlePublishDpa(row) {
  try {
    await ElMessageBox.confirm(`确定发布 DPA "${row.title}" v${row.version}？`, '确认')
    await publishDpa(row.id)
    ElMessage.success('DPA 已发布')
    await loadDpas()
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(e.message || '发布失败')
  }
}

onMounted(async () => {
  await Promise.all([loadRequests(), refreshStats(), loadDpas()])
})
</script>

<style scoped>
.gdpr-page {
  padding: 20px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.page-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-card {
  text-align: center;
}

.stat-card .stat-value {
  font-size: 32px;
  font-weight: 700;
  line-height: 1.2;
}

.stat-card .stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pagination-wrap {
  display: flex;
  justify-content: center;
  margin-top: 16px;
}

.text-muted {
  color: #909399;
  font-size: 12px;
}

.text-danger {
  color: #f56c6c;
}
</style>
