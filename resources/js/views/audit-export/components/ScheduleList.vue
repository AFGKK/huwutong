<template>
  <div>
    <el-row :gutter="12" class="mb-4" justify="space-between" align="middle">
      <el-col :span="12"><span class="text-sm text-gray-400">配置定时导出计划，系统按 Cron 表达式自动执行</span></el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" size="small" @click="openCreate">
          <el-icon><Plus /></el-icon> 新建计划
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="schedules" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" label="计划名称" min-width="150" />
      <el-table-column prop="cron_expression" label="Cron" width="130">
        <template #default="{ row }"><code>{{ row.cron_expression }}</code></template>
      </el-table-column>
      <el-table-column label="格式" width="60" align="center">
        <template #default="{ row }"><el-tag size="small" effect="plain">{{ row.format }}</el-tag></template>
      </el-table-column>
      <el-table-column label="运行次数" width="80" align="center" prop="run_count" />
      <el-table-column label="上次运行" width="150">
        <template #default="{ row }">{{ row.last_run_at || '—' }}</template>
      </el-table-column>
      <el-table-column label="下次运行" width="150">
        <template #default="{ row }">{{ row.next_run_at || '—' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-switch :model-value="row.is_active" @click="toggleSchedule(row)" />
        </template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editSchedule(row)">编辑</el-button>
            <el-popconfirm title="删除此计划？" @confirm="deleteSchedule(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchSchedules(page)" @size-change="s => { perPage = s; fetchSchedules() }" />
    </div>

    <ScheduleDialog ref="dialogRef" @saved="fetchSchedules" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getSchedules, toggleSchedule as apiToggleSchedule, deleteSchedule as apiDeleteSchedule } from '../../../api/auditExport'
import ScheduleDialog from './ScheduleDialog.vue'

const schedules = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const dialogRef = ref(null)

async function fetchSchedules(page = 1) {
  loading.value = true
  try {
    const { data } = await getSchedules({ page, per_page: perPage.value })
    schedules.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error('获取计划列表失败')
  } finally {
    loading.value = false
  }
}

function openCreate() { dialogRef.value?.open('create') }
function editSchedule(row) { dialogRef.value?.open('edit', row) }

async function toggleSchedule(row) {
    try {
      const { data } = await apiToggleSchedule(row.id)
    row.is_active = data?.is_active ?? !row.is_active
    ElMessage.success(row.is_active ? '计划已启用' : '计划已暂停')
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

async function deleteSchedule(row) {
  try {
    await apiDeleteSchedule(row.id)
    ElMessage.success('计划已删除')
    fetchSchedules()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

onMounted(() => fetchSchedules())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-sm { font-size: 13px; }
.text-gray-400 { color: #909399; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
</style>
