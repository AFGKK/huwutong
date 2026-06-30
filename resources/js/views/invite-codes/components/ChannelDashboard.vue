<template>
  <el-drawer v-model="visible" :title="'渠道看板: ' + (channel?.name ?? '')" size="650px" destroy-on-close>
    <template v-if="channel">
      <el-row :gutter="12" class="mb-4">
        <el-col :span="8" v-for="s in dashboardStats" :key="s.label">
          <el-card shadow="never">
            <div class="stat-item">
              <div class="stat-label">{{ s.label }}</div>
              <div class="stat-value" :class="s.color">{{ s.value }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <el-descriptions :column="2" border size="small" class="mb-4">
        <el-descriptions-item label="类型">{{ channel.type }}</el-descriptions-item>
        <el-descriptions-item label="状态">
          <el-tag :type="channel.status === 'active' ? 'success' : 'info'" size="small">
            {{ channel.status === 'active' ? '活跃' : '停用' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="公开注册">
          <el-tag :type="channel.is_public ? 'success' : 'info'" size="small">
            {{ channel.is_public ? '公开' : '私有' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="邀请码数">{{ stats?.total_codes || 0 }}</el-descriptions-item>
        <el-descriptions-item label="总注册数">{{ stats?.total_registrations || 0 }}</el-descriptions-item>
        <el-descriptions-item label="转化率">{{ stats?.conversion_rate || 0 }}%</el-descriptions-item>
      </el-descriptions>

      <el-divider>每日趋势</el-divider>
      <div v-if="dailyStats?.length" class="mb-4">
        <div v-for="d in dailyStats.slice(0, 14)" :key="d.stat_date" class="daily-row">
          <span class="daily-date">{{ d.stat_date }}</span>
          <span class="daily-bar">
            <el-progress :percentage="dailyPercent(d.registrations, dailyStats)" :stroke-width="16" striped />
          </span>
          <span class="daily-count">{{ d.registrations }} 注册</span>
        </div>
      </div>
      <el-empty v-else description="暂无每日统计数据" />
    </template>
  </el-drawer>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getChannelDashboard } from '../../../api/invite-codes'

const visible = ref(false)
const channel = ref(null)
const stats = ref(null)
const dailyStats = ref([])
const dashboardStats = reactive([
  { label: '注册数', value: 0, color: 'text-blue' },
  { label: '转化数', value: 0, color: 'text-success' },
  { label: '转化率', value: '0%', color: 'text-orange' },
  { label: '今日注册', value: 0, color: 'text-purple' },
  { label: '码数/用量', value: '0/0', color: 'text-primary' },
  { label: '码使用率', value: '0%', color: 'text-gray' },
])

function dailyPercent(reg, all) {
  const max = Math.max(...all.map(d => d.registrations), 1)
  return (reg / max) * 100
}

async function open(row) {
  visible.value = true
  channel.value = row
  try {
    const { data } = await getChannelDashboard(row.id)
    if (data) {
      channel.value = data.channel || row
      stats.value = data.stats
      dailyStats.value = data.daily_stats || []
      if (data.stats) {
        dashboardStats[0].value = data.stats.total_registrations
        dashboardStats[1].value = data.stats.converted
        dashboardStats[2].value = data.stats.conversion_rate + '%'
        dashboardStats[3].value = data.stats.today_registrations
        dashboardStats[4].value = `${data.stats.total_codes}/${data.stats.used_codes}`
        dashboardStats[5].value = data.stats.code_usage_rate + '%'
      }
    }
  } catch (e) {
    ElMessage.error('获取渠道看板失败')
  }
}

defineExpose({ open })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 4px 0; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.text-blue { color: #409eff; }
.text-success { color: #67c23a; }
.text-orange { color: #e6a23c; }
.text-purple { color: #b37feb; }
.text-primary { color: #303133; }
.text-gray { color: #909399; }
.daily-row { display: flex; align-items: center; margin-bottom: 6px; }
.daily-date { width: 100px; font-size: 12px; color: #909399; }
.daily-bar { flex: 1; margin: 0 12px; }
.daily-count { width: 70px; text-align: right; font-size: 12px; color: #606266; }
:deep(.el-divider__text) { font-weight: 600; }
</style>
