<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="6">
          <el-input v-model="filters.rule_name" placeholder="搜索规则名称..." clearable size="small"
            @clear="fetchExecutions" @keyup.enter="fetchExecutions" />
        </el-col>
        <el-col :span="4">
          <el-select v-model="filters.status" placeholder="状态" clearable size="small" style="width:100%" @change="fetchExecutions">
            <el-option label="全部" value="" />
            <el-option label="完成" value="completed" />
            <el-option label="失败" value="failed" />
            <el-option label="运行中" value="running" />
            <el-option label="跳过" value="skipped" />
          </el-select>
        </el-col>
        <el-col :span="14" class="text-right">
          <el-button size="small" @click="fetchExecutions" :icon="Refresh">刷新</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-table :data="executions" v-loading="loading" stripe style="width:100%">
        <el-table-column label="规则" min-width="160">
          <template #default="{ row }">{{ row.rule?.name ?? row.rule_id }}</template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="动作" width="120" align="center">
          <template #default="{ row }">{{ row.successful_actions }}/{{ row.action_count }}</template>
        </el-table-column>
        <el-table-column prop="execution_time_ms" label="耗时(ms)" width="100" align="center" />
        <el-table-column label="触发源" width="120">
          <template #default="{ row }">{{ row.trigger_source || '—' }}</template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="viewDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex justify-end" v-if="total > perPage">
        <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
          layout="sizes, prev, pager, next"
          @current-change="page => fetchExecutions(page)" @size-change="s => { perPage = s; fetchExecutions() }" />
      </div>
    </el-card>

    <!-- 执行详情 -->
    <ExecutionDetail ref="detailRef" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import ExecutionDetail from './ExecutionDetail.vue'

const loading = ref(false)
const executions = ref([])
const total = ref(0)
const perPage = ref(20)
const detailRef = ref(null)

const filters = reactive({ rule_name: '', status: '' })

function statusTag(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function statusLabel(s) { return { completed: '完成', failed: '失败', running: '运行中', skipped: '跳过', pending: '待处理' }[s] || s }

async function fetchExecutions(page = 1) {
  loading.value = true
  try {
    const { data } = await api.getAllExecutions({ ...filters, page, per_page: perPage.value })
    executions.value = data.data ?? data ?? []
    total.value = data.total ?? 0
  } catch (e) {
    ElMessage.error('获取执行历史失败')
  } finally {
    loading.value = false
  }
}

function viewDetail(row) {
  detailRef.value?.open(row)
}

onMounted(() => fetchExecutions())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
</style>
