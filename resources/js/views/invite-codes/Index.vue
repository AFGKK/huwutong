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
        <el-tab-pane label="邀请码管理" name="codes">
          <InviteCodeList />
        </el-tab-pane>
        <el-tab-pane label="渠道分组" name="channels">
          <ChannelList @view-dashboard="viewChannelDashboard" />
        </el-tab-pane>
        <el-tab-pane label="注册追踪" name="tracking">
          <RegistrationTracking />
        </el-tab-pane>
        <el-tab-pane label="自助注册门户" name="portal">
          <PortalConfig />
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <ChannelDashboard ref="dashboardRef" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getOverallDashboard } from '../../api/invite-codes'
import InviteCodeList from './components/InviteCodeList.vue'
import ChannelList from './components/ChannelList.vue'
import RegistrationTracking from './components/RegistrationTracking.vue'
import PortalConfig from './components/PortalConfig.vue'
import ChannelDashboard from './components/ChannelDashboard.vue'

const activeTab = ref('codes')
const dashboardRef = ref(null)
const overviewStats = reactive([
  { label: '总渠道', value: 0, color: 'text-blue' },
  { label: '邀请码总数', value: 0, color: 'text-primary' },
  { label: '总注册数', value: 0, color: 'text-success' },
  { label: '总使用数', value: 0, color: 'text-purple' },
  { label: '今日注册', value: 0, color: 'text-orange' },
  { label: '转化率', value: '0%', color: 'text-primary' },
])

function viewChannelDashboard(row) {
  dashboardRef.value?.open(row)
}

async function loadDashboard() {
  try {
    const { data } = await getOverallDashboard()
    if (data?.stats) {
      overviewStats[0].value = data.stats.total_channels
      overviewStats[1].value = data.stats.total_codes
      overviewStats[2].value = data.stats.total_registrations
      overviewStats[3].value = data.stats.total_uses
      overviewStats[4].value = data.stats.today_registrations
      overviewStats[5].value = data.stats.overall_conversion_rate + '%'
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
.text-blue { color: #409eff; }
.text-primary { color: #303133; }
.text-success { color: #67c23a; }
.text-purple { color: #b37feb; }
.text-orange { color: #e6a23c; }
</style>
