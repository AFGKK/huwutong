<template>
  <div>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="6">
        <el-select v-model="filterPolicy" placeholder="策略" clearable style="width:100%" @change="fetchRecords">
          <el-option label="全部" value="" />
          <el-option v-for="p in policies" :key="p.id" :label="p.name" :value="p.id" />
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width:100%" @change="fetchRecords">
          <el-option label="全部" value="" />
          <el-option label="完成" value="completed" />
          <el-option label="处理中" value="processing" />
          <el-option label="失败" value="failed" />
        </el-select>
      </el-col>
      <el-col :span="14" class="text-right">
        <el-button size="small" @click="fetchRecords" :icon="Refresh">刷新</el-button>
      </el-col>
    </el-row>

    <el-table :data="records" v-loading="loading" stripe style="width:100%">
      <el-table-column label="策略" min-width="140">
        <template #default="{ row }">{{ row.policy?.name || row.type }}</template>
      </el-table-column>
      <el-table-column label="类型" width="80">
        <template #default="{ row }"><el-tag size="small">{{ row.type }}</el-tag></template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="归档数" width="80" align="center" prop="archived_logs" />
      <el-table-column label="清理数" width="80" align="center" prop="deleted_logs" />
      <el-table-column label="总日志" width="80" align="center" prop="total_logs" />
      <el-table-column label="归档文件" min-width="200">
        <template #default="{ row }">
          <span class="text-xs">{{ row.archive_file || '—' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="文件大小" width="90" align="center">
        <template #default="{ row }">{{ formatSize(row.file_size_bytes) }}</template>
      </el-table-column>
      <el-table-column label="执行时间" width="150" prop="executed_at" />
      <el-table-column label="创建时间" width="150" prop="created_at" />
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchRecords(page)" @size-change="s => { perPage = s; fetchRecords() }" />
    </div>

    <el-empty v-if="!loading && !records.length" description="暂无归档记录" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { getArchiveRecords, getArchivePolicies } from '../../../api/auditExport'

const records = ref([])
const policies = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const filterPolicy = ref('')
const filterStatus = ref('')

function statusTag(s) { return { completed: 'success', processing: 'warning', failed: 'danger', pending: '' }[s] || '' }
function statusLabel(s) { return { completed: '完成', processing: '处理中', failed: '失败', pending: '待处理' }[s] || s }
function formatSize(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024) return bytes + 'B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB'
  return (bytes / 1048576).toFixed(1) + 'MB'
}

async function fetchRecords(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filterPolicy.value) params.policy_id = filterPolicy.value
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await getArchiveRecords(params)
    records.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error('获取归档记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchRecords()
  getArchivePolicies().then(r => { policies.value = r.data || [] }).catch(() => {})
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-xs { font-size: 12px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
</style>
