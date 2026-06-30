<template>
  <div>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="8">
        <el-input v-model="searchText" placeholder="搜索任务名称..." clearable size="small"
          @clear="fetchTasks" @keyup.enter="fetchTasks" />
      </el-col>
      <el-col :span="4">
        <el-select v-model="filterStatus" placeholder="状态" clearable size="small" style="width:100%" @change="fetchTasks">
          <el-option label="全部" value="" />
          <el-option v-for="(lb, key) in statuses" :key="key" :label="lb" :value="key" />
        </el-select>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" size="small" @click="openCreateDialog">
          <el-icon><Plus /></el-icon> 新建导出
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="tasks" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" label="任务名称" min-width="160" />
      <el-table-column prop="format" label="格式" width="70" align="center">
        <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.format }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">
            <el-icon v-if="row.status === 'processing'" class="is-loading"><Loading /></el-icon>
            {{ statuses[row.status] || row.status }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="进度" width="200">
        <template #default="{ row }">
          <el-progress :percentage="progressPercent(row)" :stroke-width="16"
            :status="row.status === 'failed' ? 'exception' : ''" striped>
            {{ row.exported_records }}/{{ row.total_records }}
          </el-progress>
        </template>
      </el-table-column>
      <el-table-column label="文件大小" width="100" align="center">
        <template #default="{ row }">{{ formatSize(row.file_size_bytes) }}</template>
      </el-table-column>
      <el-table-column prop="created_at" label="创建时间" width="150" />
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button v-if="row.status === 'completed' && row.file_path" size="small" type="primary" link
              @click="downloadFile(row)">
              <el-icon><Download /></el-icon> 下载
            </el-button>
            <el-popconfirm title="删除此导出任务？" @confirm="deleteTask(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchTasks(page)" @size-change="s => { perPage = s; fetchTasks() }" />
    </div>

    <!-- 创建导出对话框 -->
    <el-dialog v-model="dialogVisible" title="新建导出任务" width="500px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="任务名称">
          <el-input v-model="form.name" placeholder="如：Q2审计日志导出" maxlength="200" />
        </el-form-item>
        <el-form-item label="导出格式">
          <el-radio-group v-model="form.format">
            <el-radio value="csv">CSV</el-radio>
            <el-radio value="json">JSON</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-divider>筛选条件</el-divider>
        <el-form-item label="日志类型">
          <el-select v-model="form.filters.type" clearable placeholder="全部类型" style="width:100%">
            <el-option v-for="(lb, key) in logTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="操作前缀">
          <el-select v-model="form.filters.action_prefix" clearable placeholder="如 license.*" style="width:100%">
            <el-option v-for="p in actionPrefixes" :key="p" :label="p" :value="p" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="开始日期">
              <el-date-picker v-model="form.filters.date_from" type="date" placeholder="开始" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束日期">
              <el-date-picker v-model="form.filters.date_to" type="date" placeholder="结束" style="width:100%" value-format="YYYY-MM-DD" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="搜索">
          <el-input v-model="form.filters.search" placeholder="全文搜索..." />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">创建并导出</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Download, Loading } from '@element-plus/icons-vue'
import { getExportTasks, createExportTask, deleteExportTask, downloadExportFileUrl } from '../../../api/auditExport'

const tasks = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const searchText = ref('')
const filterStatus = ref('')
const dialogVisible = ref(false)
const creating = ref(false)

const form = ref({ name: '', format: 'csv', filters: {} })

const statuses = { pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', cancelled: '已取消' }
const logTypes = { audit: '审计', security: '安全', error: '错误', system: '系统' }
const actionPrefixes = ['license.*', 'subscription.*', 'customer.*', 'invoice.*', 'device.*', 'user.*', 'security.*']

function statusTag(s) { return { pending: '', processing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info' }[s] || '' }
function progressPercent(row) {
  if (!row.total_records) return 0
  return Math.min(100, Math.round((row.exported_records / row.total_records) * 100))
}
function formatSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / 1048576).toFixed(1) + 'MB'
}

async function fetchTasks(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (searchText.value) params.search = searchText.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await getExportTasks(params)
    tasks.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error('获取导出任务列表失败')
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  form.value = { name: '', format: 'csv', filters: {} }
  dialogVisible.value = true
}

async function handleCreate() {
  if (!form.value.name) { ElMessage.warning('请输入任务名称'); return }
  creating.value = true
  try {
    await createExportTask({ ...form.value })
    ElMessage.success('导出任务已创建并开始处理')
    dialogVisible.value = false
    fetchTasks()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '创建失败')
  } finally {
    creating.value = false
  }
}

async function deleteTask(row) {
  try {
    await deleteExportTask(row.id)
    ElMessage.success('任务已删除')
    fetchTasks()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

function downloadFile(row) {
  window.open(downloadExportFileUrl(row.id), '_blank')
}

onMounted(() => fetchTasks())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
:deep(.el-progress__text) { font-size: 12px !important; }
</style>
