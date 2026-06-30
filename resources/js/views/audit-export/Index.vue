<template>
  <div>
    <!-- 看板统计 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16">
        <el-col :span="3" v-for="stat in stats" :key="stat.label">
          <div class="dashboard-stat">
            <div class="stat-label">{{ stat.label }}</div>
            <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 导出任务 -->
        <el-tab-pane label="导出任务" name="tasks">
          <ExportTaskList />
        </el-tab-pane>
        <!-- 流式导出 -->
        <el-tab-pane label="流式导出" name="stream">
          <StreamExport />
        </el-tab-pane>
        <!-- 定时导出 -->
        <el-tab-pane label="定时导出计划" name="schedules">
          <ScheduleList />
        </el-tab-pane>
        <!-- 归档策略 -->
        <el-tab-pane label="归档策略" name="archive">
          <ArchivePolicyList />
        </el-tab-pane>
        <!-- 归档记录 -->
        <el-tab-pane label="归档记录" name="records">
          <ArchiveRecords />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getAuditExportDashboard } from '../../api/auditExport'
import ExportTaskList from './components/ExportTaskList.vue'
import StreamExport from './components/StreamExport.vue'
import ScheduleList from './components/ScheduleList.vue'
import ArchivePolicyList from './components/ArchivePolicyList.vue'
import ArchiveRecords from './components/ArchiveRecords.vue'

const activeTab = ref('tasks')
const stats = reactive([
  { label: '日志总数', value: 0, color: 'text-blue' },
  { label: '今日日志', value: 0, color: 'text-green' },
  { label: '导出任务', value: 0, color: 'text-primary' },
  { label: '处理中', value: 0, color: 'text-orange' },
  { label: '定时计划', value: 0, color: 'text-purple' },
  { label: '归档策略', value: 0, color: 'text-cyan' },
  { label: '已归档', value: 0, color: 'text-gray' },
  { label: '已清理', value: 0, color: 'text-red' },
])

async function loadDashboard() {
  try {
    const { data } = await getAuditExportDashboard()
    if (data?.stats) {
      stats[0].value = data.stats.total_logs
      stats[1].value = data.stats.today_logs
      stats[2].value = data.stats.total_exports
      stats[3].value = data.stats.pending_exports + data.stats.processing_exports
      stats[4].value = data.stats.active_schedules
      stats[5].value = data.stats.active_archives
      stats[6].value = data.stats.total_archived
      stats[7].value = data.stats.total_deleted
    }
  } catch (e) { /* ignore */ }
}

onMounted(() => loadDashboard())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.dashboard-stat { padding: 6px 0; text-align: center; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.text-blue { color: #409eff; }
.text-green { color: #67c23a; }
.text-primary { color: #303133; }
.text-orange { color: #e6a23c; }
.text-purple { color: #b37feb; }
.text-cyan { color: #4fc3f7; }
.text-gray { color: #909399; }
.text-red { color: #f56c6c; }
</style>
