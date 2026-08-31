<template>
  <div>
    <!-- 总看板 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="16">
        <el-col :span="4" v-for="stat in overviewStats" :key="stat.label">
          <div class="dashboard-stat">
            <div class="stat-label">{{ stat.label }}</div>
            <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t('invite_codes_page.tabs.codes')" name="codes">
          <InviteCodeList />
        </el-tab-pane>
        <el-tab-pane :label="t('invite_codes_page.tabs.channels')" name="channels">
          <ChannelList @view-dashboard="viewChannelDashboard" />
        </el-tab-pane>
        <el-tab-pane :label="t('invite_codes_page.tabs.tracking')" name="tracking">
          <RegistrationTracking />
        </el-tab-pane>
        <el-tab-pane :label="t('invite_codes_page.tabs.portal')" name="portal">
          <PortalConfig />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <ChannelDashboard ref="dashboardRef" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getOverallDashboard } from '../../api/invite-codes'
import InviteCodeList from './components/InviteCodeList.vue'
import ChannelList from './components/ChannelList.vue'
import RegistrationTracking from './components/RegistrationTracking.vue'
import PortalConfig from './components/PortalConfig.vue'
import ChannelDashboard from './components/ChannelDashboard.vue'

const { t } = useI18n()
const activeTab = ref('codes')
const dashboardRef = ref(null)
const statValues = ref({
  total_channels: 0,
  total_codes: 0,
  total_registrations: 0,
  total_uses: 0,
  today_registrations: 0,
  conversion_rate: '0%',
})

const overviewStats = computed(() => [
  { label: t('invite_codes_page.stats.total_channels'), value: statValues.value.total_channels, color: 'text-blue' },
  { label: t('invite_codes_page.stats.total_codes'), value: statValues.value.total_codes, color: 'text-primary' },
  { label: t('invite_codes_page.stats.total_registrations'), value: statValues.value.total_registrations, color: 'text-success' },
  { label: t('invite_codes_page.stats.total_uses'), value: statValues.value.total_uses, color: 'text-purple' },
  { label: t('invite_codes_page.stats.today_registrations'), value: statValues.value.today_registrations, color: 'text-orange' },
  { label: t('invite_codes_page.stats.conversion_rate'), value: statValues.value.conversion_rate, color: 'text-primary' },
])

function viewChannelDashboard(row) {
  dashboardRef.value?.open(row)
}

async function loadDashboard() {
  try {
    const { data } = await getOverallDashboard()
    if (data?.data?.stats) {
      statValues.value.total_channels = data.data.stats.total_channels
      statValues.value.total_codes = data.data.stats.total_codes
      statValues.value.total_registrations = data.data.stats.total_registrations
      statValues.value.total_uses = data.data.stats.total_uses
      statValues.value.today_registrations = data.data.stats.today_registrations
      statValues.value.conversion_rate = data.data.stats.overall_conversion_rate + '%'
    }
  } catch (e) { /* ignore */ }
}

onMounted(() => loadDashboard())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.dashboard-stat { padding: 8px 0; text-align: center; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }
.text-blue { color: #0f172a; }
.text-primary { color: #303133; }
.text-success { color: #67c23a; }
.text-purple { color: #b37feb; }
.text-orange { color: #e6a23c; }
</style>
