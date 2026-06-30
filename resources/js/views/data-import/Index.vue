<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16" justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">批量数据导入</span>
          <span class="text-gray-400 text-sm ml-4">支持 CSV 和 Excel 文件，最大 50MB</span>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button type="primary" @click="openUploadDialog">
            <el-icon><Upload /></el-icon> 上传新文件
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 状态过滤 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="6">
          <el-select v-model="filterEntity" clearable placeholder="实体类型" style="width:100%" @change="fetchTasks">
            <el-option v-for="(lb, key) in entityTypes" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="filterStatus" clearable placeholder="状态" style="width:100%" @change="fetchTasks">
            <el-option label="已上传" value="uploaded" />
            <el-option label="预览中" value="preview" />
            <el-option label="已验证" value="validated" />
            <el-option label="导入中" value="importing" />
            <el-option label="已完成" value="completed" />
            <el-option label="失败" value="failed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-col>
        <el-col :span="12" class="text-right text-sm text-gray-400">
          共 {{ tasks.length }} 条导入记录
        </el-col>
      </el-row>
    </el-card>

    <!-- 任务列表 -->
    <el-card shadow="never" v-loading="loading">
      <el-table :data="tasks" style="width:100%" @row-click="openTaskDetail">
        <el-table-column type="expand">
          <template #default="{ row }">
            <div class="px-4 py-2 text-sm">
              <div v-if="row.import_result">
                <span class="font-medium">结果：</span>
                成功 {{ row.import_result.success || row.success_rows }} / 失败 {{ row.import_result.errors || row.error_rows }}
              </div>
              <div v-if="row.validation_errors?.details" class="mt-1">
                <span class="font-medium">验证：</span>
                错误 {{ row.validation_errors.error_rows }} / 警告 {{ row.validation_errors.warning_rows }}
              </div>
              <div v-if="row.validation_errors?.details?.length" class="mt-1">
                <el-tag v-for="e in row.validation_errors.details.slice(0, 5)" :key="e.row" size="small" class="mr-1">
                  行 {{ e.row }}: {{ e.errors?.join(', ') || '' }}{{ e.warnings?.join(', ') || '' }}
                </el-tag>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="160" />
        <el-table-column prop="entity_type" label="实体类型" width="100">
          <template #default="{ row }">{{ entityTypes[row.entity_type] || row.entity_type }}</template>
        </el-table-column>
        <el-table-column prop="file_type" label="格式" width="60">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.file_type?.toUpperCase() }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="total_rows" label="总行数" width="80" align="center" />
        <el-table-column label="进度" width="180">
          <template #default="{ row }">
            <div v-if="row.status === 'completed' || row.status === 'failed'">
              <el-progress :percentage="row.total_rows ? Math.round((row.processed_rows / row.total_rows) * 100) : 0"
                :status="row.status === 'failed' ? 'exception' : 'success'" :stroke-width="16">
                <span class="text-xs">{{ row.success_rows }}/{{ row.total_rows }}</span>
              </el-progress>
            </div>
            <div v-else-if="row.status === 'importing'">
              <el-progress :percentage="row.total_rows ? Math.round((row.processed_rows / row.total_rows) * 100) : 0"
                :stroke-width="16" indeterminate>
                <span class="text-xs">{{ row.processed_rows }}/{{ row.total_rows }}</span>
              </el-progress>
            </div>
            <div v-else class="text-gray-400 text-xs">等待处理</div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small" effect="dark">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="160">
          <template #default="{ row }">{{ row.created_at?.slice(0, 16) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" link type="primary" @click.stop="resumeProcess(row)">继续</el-button>
              <el-button v-if="row.status === 'uploaded' || row.status === 'preview' || row.status === 'validated'"
                size="small" link type="warning" @click.stop="cancelTask(row)">取消</el-button>
              <el-button size="small" link type="danger" @click.stop="deleteTask(row)">删除</el-button>
            </el-space>
          </template>
        </el-table-column>
      </el-table>
      <el-empty v-if="!tasks.length && !loading" description="暂无导入记录" />
    </el-card>

    <!-- 上传对话框 -->
    <UploadDialog ref="uploadDialogRef" :entity-types="entityTypes" @uploaded="handleUploaded" />

    <!-- 导入工作流对话框 -->
    <ImportWizard ref="wizardRef" @completed="handleCompleted" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Upload } from '@element-plus/icons-vue'
import { getEntityTypes, getImportTasks, cancelImport, deleteImportTask } from '../../api/dataImport'
import UploadDialog from './components/UploadDialog.vue'
import ImportWizard from './components/ImportWizard.vue'

const loading = ref(false)
const tasks = ref([])
const entityTypes = ref({})
const filterEntity = ref('')
const filterStatus = ref('')
const uploadDialogRef = ref(null)
const wizardRef = ref(null)

const statusMap = { uploaded: 'info', preview: '', validated: 'success', importing: 'warning', completed: 'success', failed: 'danger', cancelled: 'info' }
const statusLabels = { uploaded: '已上传', preview: '预览中', validated: '已验证', importing: '导入中', completed: '已完成', failed: '失败', cancelled: '已取消' }
function statusType(s) { return statusMap[s] || 'info' }
function statusLabel(s) { return statusLabels[s] || s }

async function fetchEntityTypes() {
  try {
    const { data } = await getEntityTypes()
    entityTypes.value = data || {}
  } catch (e) { /* ignore */ }
}

async function fetchTasks() {
  loading.value = true
  try {
    const params = {}
    if (filterEntity.value) params.entity_type = filterEntity.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await getImportTasks(params)
    tasks.value = data || []
  } catch (e) {
    ElMessage.error('获取任务列表失败')
  } finally {
    loading.value = false
  }
}

function openUploadDialog() { uploadDialogRef.value?.open() }

function handleUploaded(task) {
  fetchTasks()
  // 自动打开导入向导
  wizardRef.value?.open(task.id)
}

function resumeProcess(row) {
  if (['uploaded', 'preview', 'validated'].includes(row.status)) {
    wizardRef.value?.open(row.id)
  } else {
    ElMessage.info('此任务无法继续')
  }
}

function openTaskDetail(row) {
  if (row.status === 'completed' || row.status === 'failed') {
    wizardRef.value?.open(row.id)
  }
}

async function cancelTask(row) {
  try {
    await cancelImport(row.id)
    ElMessage.success('已取消')
    fetchTasks()
  } catch (e) { ElMessage.error('取消失败') }
}

function deleteTask(row) {
  ElMessageBox.confirm(`删除导入任务「${row.name}」？`, '确认', {
    confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    try {
      await deleteImportTask(row.id)
      ElMessage.success('已删除')
      fetchTasks()
    } catch (e) { ElMessage.error('删除失败') }
  }).catch(() => {})
}

function handleCompleted() { fetchTasks() }

onMounted(() => {
  fetchEntityTypes()
  fetchTasks()
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
.text-right { text-align: right; }
.text-gray-400 { color: #909399; }
.text-sm { font-size: 13px; }
.text-xs { font-size: 12px; }
.ml-4 { margin-left: 16px; }
.mr-1 { margin-right: 4px; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-2 { padding-top: 8px; padding-bottom: 8px; }
.mt-1 { margin-top: 4px; }
</style>
