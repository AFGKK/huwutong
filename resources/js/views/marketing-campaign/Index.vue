<template>
  <div class="marketing-page">
    <div class="page-header">
      <h2><el-icon style="vertical-align:middle;margin-right:8px"><Promotion /></el-icon>多渠道营销自动化</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showCreateDialog = true">
          <el-icon><Plus /></el-icon> 创建活动
        </el-button>
        <el-button @click="refreshAll" :loading="loading" style="margin-left:8px">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value primary">{{ dashboard.active_campaigns }}</div><div class="stat-label">进行中</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_campaigns }}</div><div class="stat-label">总计</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value info">{{ dashboard.draft_campaigns }}</div><div class="stat-label">草稿</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value success">{{ dashboard.completed_campaigns }}</div><div class="stat-label">已完成</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value">{{ dashboard.total_sent }}</div><div class="stat-label">总发送</div></el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="hover"><div class="stat-value warning">{{ dashboard.open_rate }}%</div><div class="stat-label">打开率</div></el-card>
      </el-col>
    </el-row>

    <!-- 活动列表 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="营销活动列表" name="campaigns">
          <div class="tab-toolbar">
            <el-input v-model="search" placeholder="搜索活动名称" clearable style="width:200px" @clear="loadCampaigns" @keyup.enter="loadCampaigns" />
            <el-select v-model="filterStatus" placeholder="状态" clearable style="width:120px;margin-left:8px" @change="loadCampaigns">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in statusLabels" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="filterType" placeholder="类型" clearable style="width:130px;margin-left:8px" @change="loadCampaigns">
              <el-option label="全部" value="" />
              <el-option v-for="(l, k) in typeLabels" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="campaigns" stripe v-loading="campaignsLoading">
            <el-table-column label="活动名称" min-width="180">
              <template #default="{ row }">
                <el-button type="primary" link @click="showDetail(row)">{{ row.name }}</el-button>
                <el-tag v-if="row.is_ab_test" size="small" type="warning" style="margin-left:4px">A/B</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="类型" width="100">
              <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
            </el-table-column>
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="目标" width="70" align="center">
              <template #default="{ row }">{{ row.target_count }}</template>
            </el-table-column>
            <el-table-column label="发送/打开/点击" width="180">
              <template #default="{ row }">{{ row.sent_count }} / {{ row.opened_count }} / {{ row.clicked_count }}</template>
            </el-table-column>
            <el-table-column label="创建人" width="100">
              <template #default="{ row }">{{ row.created_by_name || '-' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="150">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="200" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'draft'" type="primary" link size="small" @click="handleLaunch(row)">启动</el-button>
                <el-button v-if="row.status === 'active'" type="warning" link size="small" @click="handleToggle(row)">暂停</el-button>
                <el-button v-if="row.status === 'paused'" type="primary" link size="small" @click="handleToggle(row)">继续</el-button>
                <el-button v-if="['active','paused'].includes(row.status)" type="success" link size="small" @click="handleComplete(row)">完成</el-button>
                <el-popconfirm v-if="row.status === 'draft'" title="确定删除?" @confirm="handleDelete(row)">
                  <template #reference><el-button type="danger" link size="small">删除</el-button></template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建活动对话框 -->
    <el-dialog v-model="showCreateDialog" title="创建营销活动" width="550px">
      <el-form :model="createForm" label-width="100px">
        <el-form-item label="活动名称" required>
          <el-input v-model="createForm.name" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="createForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="createForm.type" style="width:100%">
            <el-option v-for="(l, k) in typeLabels" :key="k" :label="l" :value="k" />
          </el-select>
        </el-form-item>
        <el-form-item label="受众类型">
          <el-select v-model="createForm.audience_type" style="width:100%">
            <el-option label="所有客户" value="all" />
            <el-option label="客户细分" value="segment" />
            <el-option label="自定义筛选" value="custom" />
          </el-select>
        </el-form-item>
        <el-form-item label="定时发送">
          <el-date-picker v-model="createForm.scheduled_at" type="datetime" placeholder="立即发送" style="width:100%" />
        </el-form-item>
        <el-form-item label="预算">
          <el-input-number v-model="createForm.budget" :min="0" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">取消</el-button>
        <el-button type="primary" @click="handleCreate" :loading="creating">创建</el-button>
      </template>
    </el-dialog>

    <!-- 活动详情对话框 -->
    <el-dialog v-model="showDetailDialog" title="活动详情" width="700px" :close-on-click-modal="false">
      <template v-if="detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="名称">{{ detail.name }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabels[detail.status] }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="类型">{{ typeLabels[detail.type] }}</el-descriptions-item>
          <el-descriptions-item label="A/B测试">{{ detail.is_ab_test ? '是' : '否' }}</el-descriptions-item>
          <el-descriptions-item label="目标受众">{{ detail.target_count }}</el-descriptions-item>
          <el-descriptions-item label="预算">{{ detail.budget ? '¥' + detail.budget : '无' }}</el-descriptions-item>
          <el-descriptions-item label="发送">{{ detail.sent_count }}</el-descriptions-item>
          <el-descriptions-item label="打开率">{{ detail.delivered_count > 0 ? ((detail.opened_count / detail.delivered_count) * 100).toFixed(1) + '%' : '0%' }}</el-descriptions-item>
        </el-descriptions>

        <!-- 步骤列表 -->
        <h4 style="margin:16px 0 8px">营销步骤</h4>
        <el-table :data="detail.steps || []" size="small">
          <el-table-column label="顺序" width="60" align="center">
            <template #default="{ row }">{{ row.step_order }}</template>
          </el-table-column>
          <el-table-column label="动作类型" width="140">
            <template #default="{ row }">{{ stepActionLabels[row.action_type] || row.action_type }}</template>
          </el-table-column>
          <el-table-column label="延迟" width="120">
            <template #default="{ row }">{{ row.delay_type === 'immediate' ? '立即' : row.delay_type === 'delay' ? row.delay_minutes + '分钟后' : '定时' }}</template>
          </el-table-column>
          <el-table-column label="条件" min-width="120">
            <template #default="{ row }">{{ row.conditions ? JSON.stringify(row.conditions) : '无条件' }}</template>
          </el-table-column>
        </el-table>

        <div style="margin-top:12px">
          <el-button @click="handleSimulate(detail.id)" :loading="simulating">模拟发送</el-button>
          <el-button type="primary" @click="showAnalytics(detail.id)">查看分析</el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 分析对话框 -->
    <el-dialog v-model="showAnalyticsDialog" title="活动分析" width="700px">
      <template v-if="analyticsData">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value primary">{{ analyticsData.channel_breakdown ? Object.values(analyticsData.channel_breakdown).reduce((a,b) => a + (b.sent || 0), 0) : 0 }}</div><div class="stat-label">总发送</div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value success">{{ analyticsData.daily_trend ? analyticsData.daily_trend.reduce((a,b) => a + (b.opened || 0), 0) : 0 }}</div><div class="stat-label">打开数</div></el-card></el-col>
          <el-col :span="8"><el-card shadow="hover"><div class="stat-value warning">{{ analyticsData.ab_results ? analyticsData.ab_results.length + '组' : '无' }}</div><div class="stat-label">A/B测试</div></el-card></el-col>
        </el-row>
        <div v-if="analyticsData.ab_results && analyticsData.ab_results.length">
          <h4>A/B测试结果</h4>
          <el-table :data="analyticsData.ab_results" size="small">
            <el-table-column label="变体" prop="ab_variant" width="80" />
            <el-table-column label="发送" prop="sent" width="80" />
            <el-table-column label="打开" prop="opened" width="80" />
            <el-table-column label="点击" prop="clicked" width="80" />
            <el-table-column label="打开率">
              <template #default="{ row }">{{ row.sent > 0 ? ((row.opened / row.sent) * 100).toFixed(1) + '%' : '0%' }}</template>
            </el-table-column>
          </el-table>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Promotion, Plus, Refresh } from '@element-plus/icons-vue'
import api from '../../api/marketingCampaign'

const loading = ref(false)
const activeTab = ref('campaigns')

const dashboard = reactive({
    total_campaigns: 0, active_campaigns: 0, draft_campaigns: 0, completed_campaigns: 0,
    total_sent: 0, total_delivered: 0, total_opened: 0, total_clicked: 0, total_converted: 0,
    delivery_rate: 0, open_rate: 0, click_rate: 0,
})

const statusLabels = { draft: '草稿', active: '进行中', paused: '已暂停', completed: '已完成', cancelled: '已取消' }
const typeLabels = { email: '邮件营销', sms: '短信营销', in_app: '站内信', multi_channel: '多渠道' }
const stepActionLabels = { send_email: '发送邮件', send_sms: '发送短信', send_notification: '发送站内信', wait: '等待', condition: '条件分支', segment: '细分筛选' }

function statusTag(s) {
    const map = { draft: 'info', active: 'success', paused: 'warning', completed: '', cancelled: 'danger' }
    return map[s] || 'info'
}

function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : '-' }

// Campaigns list
const campaigns = ref([])
const campaignsLoading = ref(false)
const search = ref('')
const filterStatus = ref('')
const filterType = ref('')

async function loadCampaigns() {
    campaignsLoading.value = true
    try {
        const params = {}
        if (search.value) params.search = search.value
        if (filterStatus.value) params.status = filterStatus.value
        if (filterType.value) params.type = filterType.value
        const res = await api.campaigns(params)
        campaigns.value = res.data?.data || res.data || []
    } catch (e) { console.error(e) }
    finally { campaignsLoading.value = false }
}

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        const d = res.data || {}
        Object.assign(dashboard, d)
    } catch (e) { console.error(e) }
}

function refreshAll() {
    loading.value = true
    Promise.all([loadDashboard(), loadCampaigns()])
        .finally(() => { loading.value = false })
}

// Create
const showCreateDialog = ref(false)
const createForm = reactive({ name: '', description: '', type: 'email', audience_type: 'all', scheduled_at: null, budget: null })
const creating = ref(false)

async function handleCreate() {
    if (!createForm.name) { ElMessage.warning('请输入活动名称'); return }
    creating.value = true
    try {
        await api.createCampaign(createForm)
        ElMessage.success('活动已创建')
        showCreateDialog.value = false
        createForm.name = ''; createForm.description = ''; createForm.type = 'email'; createForm.audience_type = 'all'; createForm.scheduled_at = null; createForm.budget = null
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error('创建失败: ' + (e.response?.data?.message || e.message)) }
    finally { creating.value = false }
}

// Detail
const showDetailDialog = ref(false)
const detail = ref(null)

async function showDetail(row) {
    try {
        const res = await api.showCampaign(row.id)
        detail.value = res.data || {}
        showDetailDialog.value = true
    } catch (e) { ElMessage.error('加载失败') }
}

// Launch / Toggle / Complete / Delete
async function handleLaunch(row) {
    try {
        await api.launchCampaign(row.id)
        ElMessage.success('活动已启动')
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error('启动失败: ' + (e.response?.data?.message || e.message)) }
}

async function handleToggle(row) {
    try {
        await api.toggleCampaign(row.id)
        ElMessage.success(row.status === 'active' ? '已暂停' : '已继续')
        loadCampaigns()
    } catch (e) { ElMessage.error('操作失败') }
}

async function handleComplete(row) {
    try {
        await api.completeCampaign(row.id)
        ElMessage.success('活动已标记完成')
        loadCampaigns()
        loadDashboard()
    } catch (e) { ElMessage.error('操作失败') }
}

async function handleDelete(row) {
    try {
        await api.deleteCampaign(row.id)
        ElMessage.success('已删除')
        loadCampaigns()
    } catch (e) { ElMessage.error('删除失败') }
}

// Simulate
const simulating = ref(false)
async function handleSimulate(id) {
    simulating.value = true
    try {
        const res = await api.simulateSend(id)
        const d = res.data || {}
        ElMessage.success(`模拟发送完成：${d.sent || 0} 条`)
        loadCampaigns()
    } catch (e) { ElMessage.error('模拟失败') }
    finally { simulating.value = false }
}

// Analytics
const showAnalyticsDialog = ref(false)
const analyticsData = ref(null)

async function showAnalytics(id) {
    try {
        const res = await api.analytics(id)
        analyticsData.value = res.data || {}
        showAnalyticsDialog.value = true
    } catch (e) { ElMessage.error('加载分析失败') }
}

onMounted(() => { refreshAll() })
</script>

<style scoped>
.marketing-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.header-actions { display: flex; align-items: center; }
.mb-4 { margin-bottom: 16px; }

.stat-value { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value.primary { color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.info { color: #909399; }

.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
</style>
