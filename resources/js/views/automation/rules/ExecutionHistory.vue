<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="6">
          <el-input v-model="filters.rule_name" :placeholder="t('execution_history.search_rule')" clearable size="small"
            @clear="fetchExecutions" @keyup.enter="fetchExecutions" />
        </el-col>
        <el-col :span="4">
          <el-select v-model="filters.status" :placeholder="t('execution_history.status')" clearable size="small" style="width:100%" @change="fetchExecutions">
            <el-option :label="t('execution_history.all')" value="" />
            <el-option :label="t('execution_history.statuses.completed')" value="completed" />
            <el-option :label="t('execution_history.statuses.failed')" value="failed" />
            <el-option :label="t('execution_history.statuses.running')" value="running" />
            <el-option :label="t('execution_history.statuses.skipped')" value="skipped" />
          </el-select>
        </el-col>
        <el-col :span="14" class="text-right">
          <el-button size="small" @click="fetchExecutions" :icon="Refresh">{{ t('actions.refresh') }}</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-table :data="executions" v-loading="loading" stripe style="width:100%">
        <el-table-column :label="t('execution_history.cols.rule')" min-width="160">
          <template #default="{ row }">{{ row.rule?.name ?? row.rule_id }}</template>
        </el-table-column>
        <el-table-column prop="status" :label="t('execution_history.cols.status')" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('execution_history.cols.actions')" width="120" align="center">
          <template #default="{ row }">{{ row.successful_actions }}/{{ row.action_count }}</template>
        </el-table-column>
        <el-table-column prop="execution_time_ms" :label="t('execution_history.cols.duration')" width="100" align="center" />
        <el-table-column :label="t('execution_history.cols.trigger')" width="120">
          <template #default="{ row }">{{ row.trigger_source || '—' }}</template>
        </el-table-column>
        <el-table-column prop="created_at" :label="t('execution_history.cols.time')" width="160" />
        <el-table-column :label="t('execution_history.cols.ops')" width="100" fixed="right">
          <template #default="{ row }">
            <el-button size="small" link type="primary" @click="viewDetail(row)">{{ t('execution_history.detail') }}</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex justify-end" v-if="total > perPage">
        <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
          layout="sizes, prev, pager, next"
          @current-change="page => fetchExecutions(page)" @size-change="s => { perPage = s; fetchExecutions() }" />
      </div>
    </el-card>

    <ExecutionDetail ref="detailRef" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import ExecutionDetail from './ExecutionDetail.vue'

const { t } = useI18n()
const loading = ref(false)
const executions = ref([])
const total = ref(0)
const perPage = ref(20)
const detailRef = ref(null)

const filters = reactive({ rule_name: '', status: '' })

function statusTag(s) { return { completed: 'success', failed: 'danger', running: 'warning', skipped: 'info', pending: '' }[s] || '' }
function statusLabel(s) {
  const key = { completed: 'completed', failed: 'failed', running: 'running', skipped: 'skipped', pending: 'pending' }[s]
  return key ? t(`execution_history.statuses.${key}`) : s
}

async function fetchExecutions(page = 1) {
  loading.value = true
  try {
    const { data } = await api.getAllExecutions({ ...filters, page, per_page: perPage.value })
    executions.value = data.data ?? data ?? []
    total.value = data.total ?? 0
  } catch (e) {
    ElMessage.error(t('execution_history.messages.load_failed'))
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
