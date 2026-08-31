<template>
  <div>
    <!-- 看板统计 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16">
        <el-col :span="3" v-for="stat in stats" :key="stat.key">
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
        <el-tab-pane :label="tabLabels.tasks" name="tasks">
          <ExportTaskList />
        </el-tab-pane>
        <!-- 流式导出 -->
        <el-tab-pane :label="tabLabels.stream" name="stream">
          <StreamExport />
        </el-tab-pane>
        <!-- 定时导出 -->
        <el-tab-pane :label="tabLabels.schedules" name="schedules">
          <ScheduleList />
        </el-tab-pane>
        <!-- 归档策略 -->
        <el-tab-pane :label="tabLabels.archive" name="archive">
          <ArchivePolicyList />
        </el-tab-pane>
        <!-- 归档记录 -->
        <el-tab-pane :label="tabLabels.records" name="records">
          <ArchiveRecords />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAuditExportDashboard } from '../../api/auditExport'
import ExportTaskList from './components/ExportTaskList.vue'
import StreamExport from './components/StreamExport.vue'
import ScheduleList from './components/ScheduleList.vue'
import ArchivePolicyList from './components/ArchivePolicyList.vue'
import ArchiveRecords from './components/ArchiveRecords.vue'

const { t } = useI18n()

const activeTab = ref('tasks')
const statData = reactive({
  total_logs: 0,
  today_logs: 0,
  total_exports: 0,
  processing: 0,
  active_schedules: 0,
  active_archives: 0,
  total_archived: 0,
  total_deleted: 0,
})

const statMeta = [
  { key: 'total_logs', color: 'text-blue' },
  { key: 'today_logs', color: 'text-green' },
  { key: 'total_exports', color: 'text-primary' },
  { key: 'processing', color: 'text-orange' },
  { key: 'active_schedules', color: 'text-purple' },
  { key: 'active_archives', color: 'text-cyan' },
  { key: 'total_archived', color: 'text-gray' },
  { key: 'total_deleted', color: 'text-red' },
]

const stats = computed(() =>
  statMeta.map(({ key, color }) => ({
    key,
    color,
    label: t(`audit_export_page.stats.${key}`),
    value: statData[key],
  }))
)

const tabLabels = computed(() => ({
  tasks: t('audit_export_page.tabs.tasks'),
  stream: t('audit_export_page.tabs.stream'),
  schedules: t('audit_export_page.tabs.schedules'),
  archive: t('audit_export_page.tabs.archive'),
  records: t('audit_export_page.tabs.records'),
}))

async function loadDashboard() {
  try {
    const { data } = await getAuditExportDashboard()
    if (data?.stats) {
      statData.total_logs = data.stats.total_logs
      statData.today_logs = data.stats.today_logs
      statData.total_exports = data.stats.total_exports
      statData.processing = data.stats.pending_exports + data.stats.processing_exports
      statData.active_schedules = data.stats.active_schedules
      statData.active_archives = data.stats.active_archives
      statData.total_archived = data.stats.total_archived
      statData.total_deleted = data.stats.total_deleted
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
.text-blue { color: #0f172a; }
.text-green { color: #67c23a; }
.text-primary { color: #303133; }
.text-orange { color: #e6a23c; }
.text-purple { color: #b37feb; }
.text-cyan { color: #4fc3f7; }
.text-gray { color: #909399; }
.text-red { color: #f56c6c; }
</style>
