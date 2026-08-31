<template>
  <el-drawer v-model="visible" :title="t('channel_dashboard.title', { name: channel?.name ?? '' })" size="650px" destroy-on-close>
    <template v-if="channel">
      <el-row :gutter="12" class="mb-4">
        <el-col :span="8" v-for="s in dashboardStats" :key="s.key">
          <el-card shadow="never">
            <div class="stat-item">
              <div class="stat-label">{{ s.label }}</div>
              <div class="stat-value" :class="s.color">{{ s.value }}</div>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <el-descriptions :column="2" border size="small" class="mb-4">
        <el-descriptions-item :label="t('channel_dashboard.type')">{{ channel.type }}</el-descriptions-item>
        <el-descriptions-item :label="t('channel_dashboard.status')">
          <el-tag :type="channel.status === 'active' ? 'success' : 'info'" size="small">
            {{ channel.status === 'active' ? t('channel_dashboard.active') : t('channel_dashboard.inactive') }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('channel_dashboard.public_reg')">
          <el-tag :type="channel.is_public ? 'success' : 'info'" size="small">
            {{ channel.is_public ? t('channel_dashboard.public') : t('channel_dashboard.private') }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item :label="t('channel_dashboard.codes')">{{ stats?.total_codes || 0 }}</el-descriptions-item>
        <el-descriptions-item :label="t('channel_dashboard.registrations')">{{ stats?.total_registrations || 0 }}</el-descriptions-item>
        <el-descriptions-item :label="t('channel_dashboard.conversion')">{{ stats?.conversion_rate || 0 }}%</el-descriptions-item>
      </el-descriptions>

      <el-divider>{{ t('channel_dashboard.daily_trend') }}</el-divider>
      <div v-if="dailyStats?.length" class="mb-4">
        <div v-for="d in dailyStats.slice(0, 14)" :key="d.stat_date" class="daily-row">
          <span class="daily-date">{{ d.stat_date }}</span>
          <span class="daily-bar">
            <el-progress :percentage="dailyPercent(d.registrations, dailyStats)" :stroke-width="16" striped />
          </span>
          <span class="daily-count">{{ t('channel_dashboard.reg_count', { n: d.registrations }) }}</span>
        </div>
      </div>
      <el-empty v-else :description="t('channel_dashboard.no_daily')" />
    </template>
  </el-drawer>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getChannelDashboard } from '../../../api/invite-codes'

const { t } = useI18n()
const visible = ref(false)
const channel = ref(null)
const stats = ref(null)
const dailyStats = ref([])
const statValues = ref({
  registrations: 0,
  converted: 0,
  conversion: '0%',
  today: 0,
  codes: '0/0',
  usage: '0%',
})

const dashboardStats = computed(() => [
  { key: 'registrations', label: t('channel_dashboard.stats.registrations'), value: statValues.value.registrations, color: 'text-blue' },
  { key: 'converted', label: t('channel_dashboard.stats.converted'), value: statValues.value.converted, color: 'text-success' },
  { key: 'conversion', label: t('channel_dashboard.stats.conversion'), value: statValues.value.conversion, color: 'text-orange' },
  { key: 'today', label: t('channel_dashboard.stats.today'), value: statValues.value.today, color: 'text-purple' },
  { key: 'codes', label: t('channel_dashboard.stats.codes'), value: statValues.value.codes, color: 'text-primary' },
  { key: 'usage', label: t('channel_dashboard.stats.usage'), value: statValues.value.usage, color: 'text-gray' },
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
        statValues.value = {
          registrations: data.stats.total_registrations,
          converted: data.stats.converted,
          conversion: data.stats.conversion_rate + '%',
          today: data.stats.today_registrations,
          codes: `${data.stats.total_codes}/${data.stats.used_codes}`,
          usage: data.stats.code_usage_rate + '%',
        }
      }
    }
  } catch (e) {
    ElMessage.error(t('channel_dashboard.messages.load_failed'))
  }
}

defineExpose({ open })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 4px 0; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.text-blue { color: #0f172a; }
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
