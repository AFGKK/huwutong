<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import agentManagerApi from '@/api/agentManager'
import channelApi from '@/api/channelPartner'
import {
    Refresh, DataBoard, User, Money, Link,
    Medal, ArrowDown, CircleCheck,
} from '@element-plus/icons-vue'

const { t } = useI18n()

// ─── 顶层 Tab ───
const partnerMainTab = ref('agent')

// ─── 共享常量 ───
const levelKeys = ['regular', 'silver', 'gold', 'platinum']
const statusKeys = ['pending', 'active', 'suspended', 'terminated']

function formatMoney(v) {
    return v ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00'
}

// ====================================================================
//  TAB 1: 代理商（原 agent-manager）
// ====================================================================

const loading = ref(false)
const activeTab = ref('dashboard')

// ─── 仪表盘 ───
const dashboard = ref(null)
const dashboardLoading = ref(false)

async function loadDashboard() {
    dashboardLoading.value = true
    try {
        const res = await agentManagerApi.dashboard()
        dashboard.value = res.data.data
    } catch (e) {
        console.error(e)
    } finally {
        dashboardLoading.value = false
    }
}

const levelColors = { regular: '#909399', silver: '#C0C4CC', gold: '#E6A23C', platinum: '#0f172a' }

const metricKeys = ['total_earned', 'total_withdrawn', 'downline_count', 'tier_referrals_total']

const levelLabels = computed(() => ({
    regular: t('agent_manager_page.levels.regular'),
    silver: t('agent_manager_page.levels.silver'),
    gold: t('agent_manager_page.levels.gold'),
    platinum: t('agent_manager_page.levels.platinum'),
}))

const statusLabels = computed(() => ({
    pending: t('agent_manager_page.status.pending'),
    active: t('agent_manager_page.status.active'),
    suspended: t('agent_manager_page.status.suspended'),
    terminated: t('agent_manager_page.status.terminated'),
}))

const metricLabels = computed(() => ({
    total_earned: t('agent_manager_page.metrics.total_earned'),
    total_withdrawn: t('agent_manager_page.metrics.total_withdrawn'),
    downline_count: t('agent_manager_page.metrics.downline_count'),
    tier_referrals_total: t('agent_manager_page.metrics.tier_referrals_total'),
}))

const levelOptions = computed(() =>
    levelKeys.map((value) => ({ value, label: levelLabels.value[value] }))
)

const statusOptions = computed(() =>
    statusKeys.map((value) => ({ value, label: statusLabels.value[value] }))
)

const metricOptions = computed(() =>
    metricKeys.map((value) => ({ value, label: metricLabels.value[value] }))
)

const periodOptions = computed(() => [
    { value: 'daily', label: t('agent_manager_page.periods.daily') },
    { value: 'monthly', label: t('agent_manager_page.periods.monthly') },
    { value: 'yearly', label: t('agent_manager_page.periods.yearly') },
])

function levelLabel(level) { return levelLabels.value[level] || level }
function statusLabel(status) { return statusLabels.value[status] || status }

// ─── 代理列表 ───
const agents = ref([])
const pagination = ref({ total: 0, current_page: 1, per_page: 20 })
const filters = ref({ status: '', level: '', search: '' })

async function loadAgents(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page, per_page: pagination.value.per_page }
        const res = await agentManagerApi.list(params)
        const data = res.data.data
        agents.value = data.data || []
        pagination.value = { total: data.total, current_page: data.current_page, per_page: data.per_page }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

function searchAgents() {
    loadAgents(1)
}

// ─── 创建代理 ───
const createDialog = ref(false)
const form = ref({
    user_id: null, level: 'regular', commission_rate: 5.0,
    contact_name: '', contact_phone: '', company: '', notes: '', parent_agent_id: null,
})

async function createAgent() {
    try {
        await agentManagerApi.create(form.value)
        ElMessage.success(t('agent_manager_page.msg_created'))
        createDialog.value = false
        loadAgents()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 代理详情 ───
const detailDialog = ref(false)
const detail = ref(null)
const detailLoading = ref(false)

async function showDetail(id) {
    detailLoading.value = true
    detailDialog.value = true
    try {
        const res = await agentManagerApi.show(id)
        detail.value = res.data.data
    } catch (e) {
        ElMessage.error(t('agent_manager_page.msg_detail_failed'))
    } finally {
        detailLoading.value = false
    }
}

// ─── 审核 ───
async function approveAgent(id) {
    try {
        await ElMessageBox.confirm(t('agent_manager_page.confirm_approve'), t('actions.confirm'))
        await agentManagerApi.approve(id)
        ElMessage.success(t('agent_manager_page.msg_approved'))
        loadAgents()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

// ─── 绩效报表 ───
const perfDialog = ref(false)
const perfData = ref(null)
const perfPeriod = ref('monthly')
const perfLoading = ref(false)

async function showPerformance(id) {
    perfLoading.value = true
    perfDialog.value = true
    try {
        const res = await agentManagerApi.performance(id, perfPeriod.value)
        perfData.value = res.data.data
    } catch (e) {
        ElMessage.error(t('agent_manager_page.msg_perf_failed'))
    } finally {
        perfLoading.value = false
    }
}

// ─── 排行榜 ───
const leaderboard = ref([])
const lbMetric = ref('total_earned')
const lbLoading = ref(false)

async function loadLeaderboard() {
    lbLoading.value = true
    try {
        const res = await agentManagerApi.leaderboard({ metric: lbMetric.value, limit: 20 })
        leaderboard.value = res.data.data || []
    } catch (e) {
        console.error(e)
    } finally {
        lbLoading.value = false
    }
}

// ====================================================================
//  TAB 2: 渠道伙伴（原 channel，ch_ 前缀）
// ====================================================================

const ch_tabVisited = ref(false)

watch(partnerMainTab, (val) => {
    if (val === 'channel' && !ch_tabVisited.value) {
        ch_tabVisited.value = true
    }
})

watch(ch_tabVisited, (visited) => {
    if (visited) {
        ch_loadDashboard()
        ch_loadPartners()
        ch_loadSettlements()
        ch_loadReferralLinks()
    }
})

const ch_activeTab = ref('dashboard')
const ch_refreshing = ref(false)

// ── 看板 ──
const ch_stats = reactive({
    total_partners: 0, active_partners: 0, pending_approval: 0,
    total_settled: 0, total_paid: 0, pending_payouts: 0,
    level_distribution: {}, monthly_trend: [], top_partners: [],
})

// ── 合作伙伴 ──
const ch_loadingPartners = ref(false)
const ch_partners = ref([])
const ch_partnerPagination = ref(null)
const ch_searchPartner = ref('')
const ch_filterStatus = ref('')
const ch_filterLevel = ref('')

// 详情
const ch_showPartnerDialog = ref(false)
const ch_loadingDetail = ref(false)
const ch_detailAgent = ref(null)
const ch_detailStats = ref(null)
const ch_detailSettlements = ref([])
const ch_monthlyPerformance = ref([])

// ── 结算 ──
const ch_loadingSettlements = ref(false)
const ch_settlements = ref([])
const ch_settlementPagination = ref(null)
const ch_settlementFilter = reactive({ agent_id: null, status: null })

// ── 推广链接 ──
const ch_loadingLinks = ref(false)
const ch_referralLinks = ref([])
const ch_linkPagination = ref(null)
const ch_linkFilter = reactive({ agent_id: null })

// ── 合作伙伴选项 ──
const ch_partnerOptions = ref([])

const ch_tierColors = { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#0f172a' }

const ch_settlementStatusKeys = ['pending', 'pending_release', 'released', 'refunded']

const ch_levelLabels = computed(() => ({
    regular: t('channel_page.levels.regular'),
    silver: t('channel_page.levels.silver'),
    gold: t('channel_page.levels.gold'),
    platinum: t('channel_page.levels.platinum'),
}))

const ch_statusLabels = computed(() => ({
    active: t('channel_page.status.active'),
    pending: t('channel_page.status.pending'),
    suspended: t('channel_page.status.suspended'),
    terminated: t('channel_page.status.terminated'),
}))

const ch_settlementStatusLabels = computed(() => ({
    pending: t('channel_page.settlement_status.pending'),
    pending_release: t('channel_page.settlement_status.pending_release'),
    released: t('channel_page.settlement_status.released'),
    refunded: t('channel_page.settlement_status.refunded'),
}))

const ch_statusOptions = computed(() =>
    statusKeys.map((value) => ({ value, label: ch_statusLabels.value[value] }))
)

const ch_levelOptions = computed(() =>
    levelKeys.map((value) => ({ value, label: ch_levelLabels.value[value] }))
)

const ch_settlementStatusOptions = computed(() =>
    ch_settlementStatusKeys.map((value) => ({ value, label: ch_settlementStatusLabels.value[value] }))
)

const ch_tierData = computed(() => ({
    regular: {
        label: t('channel_page.tiers.regular.label'),
        rate: t('channel_page.tiers.regular.rate'),
        benefits: [
            t('channel_page.tiers.regular.benefit_1'),
            t('channel_page.tiers.regular.benefit_2'),
            t('channel_page.tiers.regular.benefit_3'),
        ],
    },
    silver: {
        label: t('channel_page.tiers.silver.label'),
        rate: t('channel_page.tiers.silver.rate'),
        min_requirements: t('channel_page.tiers.silver.min_requirements'),
        benefits: [
            t('channel_page.tiers.silver.benefit_1'),
            t('channel_page.tiers.silver.benefit_2'),
            t('channel_page.tiers.silver.benefit_3'),
        ],
    },
    gold: {
        label: t('channel_page.tiers.gold.label'),
        rate: t('channel_page.tiers.gold.rate'),
        min_requirements: t('channel_page.tiers.gold.min_requirements'),
        benefits: [
            t('channel_page.tiers.gold.benefit_1'),
            t('channel_page.tiers.gold.benefit_2'),
            t('channel_page.tiers.gold.benefit_3'),
            t('channel_page.tiers.gold.benefit_4'),
        ],
    },
    platinum: {
        label: t('channel_page.tiers.platinum.label'),
        rate: t('channel_page.tiers.platinum.rate'),
        min_requirements: t('channel_page.tiers.platinum.min_requirements'),
        benefits: [
            t('channel_page.tiers.platinum.benefit_1'),
            t('channel_page.tiers.platinum.benefit_2'),
            t('channel_page.tiers.platinum.benefit_3'),
            t('channel_page.tiers.platinum.benefit_4'),
            t('channel_page.tiers.platinum.benefit_5'),
            t('channel_page.tiers.platinum.benefit_6'),
        ],
    },
}))

// ============= 渠道工具 =============

function ch_formatDate(d) {
    return d ? new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }) : '-'
}

function ch_formatMoney(v) {
    return v ? Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '0.00'
}

function ch_percentOf(count, total) {
    return total > 0 ? Math.round((count / total) * 100) : 0
}

function ch_trendPercent(amount, trend) {
    const max = Math.max(...(trend || []).map(m => Number(m.amount) || 0))
    return max > 0 ? Math.round((amount / max) * 100) : 0
}

function ch_levelLabel(l) {
    return ch_levelLabels.value[l] || l
}

function ch_levelTag(l) {
    return { regular: 'info', silver: 'info', gold: 'warning', platinum: 'primary' }[l] || 'info'
}

function ch_levelColor(l) {
    return { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#0f172a' }[l]
}

function ch_statusLabel(s) {
    return ch_statusLabels.value[s] || s
}

function ch_settlementStatusType(s) {
    return { pending: 'warning', pending_release: 'info', released: 'success', refunded: 'danger' }[s] || 'info'
}

function ch_settlementStatusLabel(s) {
    return ch_settlementStatusLabels.value[s] || s
}

// ============= 渠道加载数据 =============

async function ch_loadDashboard() {
    try {
        const { data: res } = await channelApi.dashboard()
        Object.assign(ch_stats, res.data || {})
    } catch { /* ignore */ }
}

async function ch_loadPartners(page = 1) {
    ch_loadingPartners.value = true
    try {
        const params = { page, per_page: 20 }
        if (ch_searchPartner.value) params.search = ch_searchPartner.value
        if (ch_filterStatus.value) params.status = ch_filterStatus.value
        if (ch_filterLevel.value) params.level = ch_filterLevel.value
        const { data: res } = await channelApi.partners(params)
        const paginated = res.data
        ch_partners.value = paginated?.data || paginated || []
        if (paginated?.current_page) ch_partnerPagination.value = paginated

        ch_partnerOptions.value = (paginated?.data || paginated || []).map(p => ({
            id: p.id,
            label: `${p.agent_code} - ${p.name || p.contact_name}`,
        }))
    } catch {
        ch_partners.value = []
    } finally {
        ch_loadingPartners.value = false
    }
}

async function ch_loadSettlements(page = 1) {
    ch_loadingSettlements.value = true
    try {
        const params = { page, per_page: 20 }
        if (ch_settlementFilter.agent_id) params.agent_id = ch_settlementFilter.agent_id
        if (ch_settlementFilter.status) params.status = ch_settlementFilter.status
        const { data: res } = await channelApi.settlements(params)
        const paginated = res.data
        ch_settlements.value = paginated?.data || paginated || []
        if (paginated?.current_page) ch_settlementPagination.value = paginated
    } catch {
        ch_settlements.value = []
    } finally {
        ch_loadingSettlements.value = false
    }
}

async function ch_loadReferralLinks(page = 1) {
    ch_loadingLinks.value = true
    try {
        const params = { page, per_page: 20 }
        if (ch_linkFilter.agent_id) params.agent_id = ch_linkFilter.agent_id
        const { data: res } = await channelApi.referralLinks(params)
        const paginated = res.data
        ch_referralLinks.value = paginated?.data || paginated || []
        if (paginated?.current_page) ch_linkPagination.value = paginated
    } catch {
        ch_referralLinks.value = []
    } finally {
        ch_loadingLinks.value = false
    }
}

// ============= 渠道操作 =============

async function ch_refreshAll() {
    ch_refreshing.value = true
    await Promise.all([ch_loadDashboard(), ch_loadPartners(), ch_loadSettlements(), ch_loadReferralLinks()])
    ch_refreshing.value = false
    ElMessage.success(t('channel_page.msg_refreshed'))
}

async function ch_viewPartnerDetail(row) {
    ch_showPartnerDialog.value = true
    ch_loadingDetail.value = true
    ch_detailAgent.value = null
    ch_detailStats.value = null
    ch_detailSettlements.value = []
    ch_monthlyPerformance.value = []
    try {
        const { data: res } = await channelApi.showPartner(row.id)
        const d = res.data
        ch_detailAgent.value = d.agent
        ch_detailStats.value = d.stats
        ch_detailSettlements.value = d.recent_settlements || []
        ch_monthlyPerformance.value = d.monthly_performance || []
    } catch {
        ElMessage.error(t('messages.load_failed'))
    } finally {
        ch_loadingDetail.value = false
    }
}

async function ch_handleApprove(row) {
    try {
        await channelApi.approvePartner(row.id)
        ElMessage.success(t('channel_page.msg_approved'))
        ch_loadPartners()
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('channel_page.msg_approve_failed'))
    }
}

async function ch_handleChangeLevel(row, level) {
    try {
        await channelApi.updatePartnerLevel(row.id, { level })
        ElMessage.success(t('channel_page.msg_level_updated'))
        ch_loadPartners()
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('channel_page.msg_update_failed'))
    }
}

onMounted(() => {
    loadDashboard()
})
</script>

<template>
    <el-tabs v-model="partnerMainTab" type="border-card">

        <!-- ================================================================
             TAB 1: 代理商
        ================================================================ -->
        <el-tab-pane :label="t('agent_manager_page.tabs.dashboard')" name="agent">
            <div>
                <el-tabs v-model="activeTab" @tab-change="(tab) => { if (tab === 'leaderboard') loadLeaderboard() }">
                    <!-- Tab 1: 仪表盘 -->
                    <el-tab-pane :label="t('agent_manager_page.tabs.dashboard')" name="dashboard">
                        <div v-loading="dashboardLoading">
                            <el-row :gutter="16" class="mb-4">
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.total_agents') }}</div>
                                            <div class="stat-value">{{ dashboard?.total_agents ?? '-' }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.active') }}</div>
                                            <div class="stat-value" style="color:#67C23A">{{ dashboard?.active ?? '-' }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.pending') }}</div>
                                            <div class="stat-value" style="color:#E6A23C">{{ dashboard?.pending ?? '-' }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.pending_commission') }}</div>
                                            <div class="stat-value" style="color:#F56C6C">{{ formatMoney(dashboard?.pending_commission) }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>

                            <el-row :gutter="16" class="mb-4">
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.total_earned') }}</div>
                                            <div class="stat-value">{{ formatMoney(dashboard?.total_earned) }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="6">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.total_withdrawn') }}</div>
                                            <div class="stat-value">{{ formatMoney(dashboard?.total_withdrawn) }}</div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="12">
                                    <el-card shadow="hover">
                                        <div class="stat-item">
                                            <div class="stat-label">{{ t('agent_manager_page.stats.level_distribution') }}</div>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <el-tag v-for="(count, level) in dashboard?.by_level ?? {}" :key="level"
                                                    :color="levelColors[level] || '#909399'" class="text-white">
                                                    {{ t('agent_manager_page.level_count_fmt', { level: levelLabel(level), count }) }}
                                                </el-tag>
                                            </div>
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>

                            <!-- 月趋势 -->
                            <el-card shadow="hover" class="mb-4">
                                <template #header><span>{{ t('agent_manager_page.trend_title') }}</span></template>
                                <div v-if="dashboard?.monthly_trend?.length">
                                    <el-table :data="dashboard.monthly_trend" size="small" stripe>
                                        <el-table-column prop="month" :label="t('agent_manager_page.cols.month')" width="120" />
                                        <el-table-column :label="t('agent_manager_page.cols.amount')">
                                            <template #default="{ row }">{{ formatMoney(row.total) }}</template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                                <el-empty v-else :description="t('agent_manager_page.empty_trend')" />
                            </el-card>

                            <!-- Top 10 -->
                            <el-card shadow="hover">
                                <template #header><span>{{ t('agent_manager_page.top_agents_title') }}</span></template>
                                <el-table :data="dashboard?.top_agents ?? []" size="small" stripe>
                                    <el-table-column type="index" :label="t('agent_manager_page.cols.index')" width="50" />
                                    <el-table-column prop="name" :label="t('agent_manager_page.cols.name')" />
                                    <el-table-column prop="agent_code" :label="t('agent_manager_page.cols.code')" width="130" />
                                    <el-table-column :label="t('agent_manager_page.cols.level')" width="100">
                                        <template #default="{ row }">
                                            <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ levelLabel(row.level) }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('agent_manager_page.cols.total_earned')" width="150">
                                        <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('agent_manager_page.cols.total_withdrawn')" width="150">
                                        <template #default="{ row }">{{ formatMoney(row.total_withdrawn) }}</template>
                                    </el-table-column>
                                    <el-table-column prop="downline_count" :label="t('agent_manager_page.cols.downline')" width="80" />
                                </el-table>
                            </el-card>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 2: 代理列表 -->
                    <el-tab-pane :label="t('agent_manager_page.tabs.agents')" name="agents">
                        <el-card shadow="never">
                            <el-form :model="filters" inline class="mb-4">
                                <el-form-item :label="t('agent_manager_page.filters.status')">
                                    <el-select v-model="filters.status" clearable :placeholder="t('agent_manager_page.filters.all')" style="width:120px">
                                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item :label="t('agent_manager_page.filters.level')">
                                    <el-select v-model="filters.level" clearable :placeholder="t('agent_manager_page.filters.all')" style="width:120px">
                                        <el-option v-for="opt in levelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item :label="t('agent_manager_page.filters.search')">
                                    <el-input v-model="filters.search" :placeholder="t('agent_manager_page.search_ph')" clearable style="width:200px" @keyup.enter="searchAgents" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="searchAgents">{{ t('actions.search') }}</el-button>
                                    <el-button type="success" @click="createDialog = true">+ {{ t('agent_manager_page.buttons.add_agent') }}</el-button>
                                </el-form-item>
                            </el-form>

                            <el-table v-loading="loading" :data="agents" stripe highlight-current-row>
                                <el-table-column prop="agent_code" :label="t('agent_manager_page.cols.code')" width="130" />
                                <el-table-column :label="t('agent_manager_page.cols.contact_name')" width="120">
                                    <template #default="{ row }">{{ row.contact_name || row.user?.name || 'N/A' }}</template>
                                </el-table-column>
                                <el-table-column prop="company" :label="t('agent_manager_page.cols.company')" min-width="150" />
                                <el-table-column :label="t('agent_manager_page.cols.level')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ levelLabel(row.level) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.status')" width="90">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                                            {{ statusLabel(row.status) }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.commission_rate')" width="90">
                                    <template #default="{ row }">{{ row.commission_rate }}%</template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.total_earned')" width="130">
                                    <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.downline')" width="70" prop="downline_count" />
                                <el-table-column :label="t('agent_manager_page.cols.actions')" width="240" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" @click="showDetail(row.id)">{{ t('agent_manager_page.buttons.detail') }}</el-button>
                                        <el-button size="small" @click="showPerformance(row.id)">{{ t('agent_manager_page.buttons.performance') }}</el-button>
                                        <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approveAgent(row.id)">{{ t('agent_manager_page.buttons.approve') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>

                            <div class="mt-3 flex justify-center">
                                <el-pagination background layout="prev, pager, next, total"
                                    :total="pagination.total" :page-size="pagination.per_page"
                                    v-model:current-page="pagination.current_page"
                                    @current-change="loadAgents" />
                            </div>
                        </el-card>
                    </el-tab-pane>

                    <!-- Tab 3: 排行榜 -->
                    <el-tab-pane :label="t('agent_manager_page.tabs.leaderboard')" name="leaderboard">
                        <el-card shadow="never">
                            <div class="mb-3">
                                <el-radio-group v-model="lbMetric" @change="loadLeaderboard">
                                    <el-radio-button v-for="opt in metricOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio-button>
                                </el-radio-group>
                            </div>
                            <el-table v-loading="lbLoading" :data="leaderboard" stripe>
                                <el-table-column type="index" :label="t('agent_manager_page.cols.rank')" width="70">
                                    <template #default="{ $index }">
                                        <span :class="[$index < 3 ? 'text-lg font-bold' : '']"
                                            :style="{ color: ['#E6A23C','#C0C4CC','#CD7F32'][$index] || 'inherit' }">
                                            #{{ $index + 1 }}
                                        </span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.name')">
                                    <template #default="{ row }">{{ row.name }}</template>
                                </el-table-column>
                                <el-table-column prop="agent_code" :label="t('agent_manager_page.cols.code')" width="130" />
                                <el-table-column :label="t('agent_manager_page.cols.level')" width="100">
                                    <template #default="{ row }">
                                        <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ levelLabel(row.level) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.metric_value')" width="160">
                                    <template #default="{ row }">
                                        <span v-if="lbMetric === 'total_earned' || lbMetric === 'total_withdrawn'">
                                            {{ formatMoney(row.metric) }}
                                        </span>
                                        <span v-else>{{ row.metric }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('agent_manager_page.cols.total_earned')" width="150">
                                    <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                                </el-table-column>
                                <el-table-column prop="downline_count" :label="t('agent_manager_page.cols.downline')" width="80" />
                            </el-table>
                        </el-card>
                    </el-tab-pane>
                </el-tabs>

                <!-- 创建代理弹窗 -->
                <el-dialog v-model="createDialog" :title="t('agent_manager_page.dialogs.create_title')" width="500px">
                    <el-form :model="form" label-width="100px">
                        <el-form-item :label="t('agent_manager_page.form.user_id')" required>
                            <el-input-number v-model="form.user_id" :min="1" style="width:100%" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.level')" required>
                            <el-select v-model="form.level" style="width:100%">
                                <el-option v-for="opt in levelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.commission_rate')">
                            <el-input-number v-model="form.commission_rate" :min="0" :max="100" :step="0.5" style="width:100%" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.contact_name')">
                            <el-input v-model="form.contact_name" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.contact_phone')">
                            <el-input v-model="form.contact_phone" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.company')">
                            <el-input v-model="form.company" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.parent_agent_id')">
                            <el-input-number v-model="form.parent_agent_id" :min="0" :step="1" style="width:100%" />
                        </el-form-item>
                        <el-form-item :label="t('agent_manager_page.form.notes')">
                            <el-input v-model="form.notes" type="textarea" :rows="3" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="createDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="createAgent">{{ t('agent_manager_page.buttons.confirm_create') }}</el-button>
                    </template>
                </el-dialog>

                <!-- 代理详情弹窗 -->
                <el-dialog v-model="detailDialog" :title="t('agent_manager_page.dialogs.detail_title')" width="800px" v-loading="detailLoading">
                    <template v-if="detail">
                        <el-descriptions :column="2" border>
                            <el-descriptions-item :label="t('agent_manager_page.cols.code')">{{ detail.agent?.agent_code }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.level')">
                                <el-tag :color="levelColors[detail.agent?.level] || '#909399'" class="text-white">
                                    {{ levelLabel(detail.agent?.level) }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.form.contact_name')">{{ detail.agent?.contact_name }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.phone')">{{ detail.agent?.contact_phone }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.company')">{{ detail.agent?.company || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.commission_rate')">{{ detail.agent?.commission_rate }}%</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.total_earned')">{{ formatMoney(detail.agent?.total_earned) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.total_withdrawn')">{{ formatMoney(detail.agent?.total_withdrawn) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.sections.downline')">{{ t('agent_manager_page.detail_downline_fmt', { n: detail.agent?.downline_count ?? 0 }) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.status')">
                                <el-tag :type="detail.agent?.status === 'active' ? 'success' : 'warning'" size="small">
                                    {{ statusLabel(detail.agent?.status) }}
                                </el-tag>
                            </el-descriptions-item>
                        </el-descriptions>

                        <el-divider />
                        <h4 class="mb-2">{{ t('agent_manager_page.sections.earnings') }}</h4>
                        <el-descriptions v-if="detail.earnings_account" :column="3" border size="small">
                            <el-descriptions-item :label="t('agent_manager_page.earnings.available')">{{ formatMoney(detail.earnings_account.available_balance) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.earnings.pending')">{{ formatMoney(detail.earnings_account.pending_balance) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.earnings.withdrawn')">{{ formatMoney(detail.earnings_account.total_withdrawn) }}</el-descriptions-item>
                        </el-descriptions>
                        <el-empty v-else :description="t('agent_manager_page.empty_earnings')" />

                        <el-divider />
                        <h4 class="mb-2">{{ t('agent_manager_page.sections.downline') }}</h4>
                        <el-table :data="detail.downline_agents ?? []" size="small" stripe>
                            <el-table-column :label="t('agent_manager_page.cols.contact_name')">
                                <template #default="{ row }">{{ row.name }}</template>
                            </el-table-column>
                            <el-table-column :label="t('agent_manager_page.cols.level')" width="100">
                                <template #default="{ row }">
                                    <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ levelLabel(row.level) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('agent_manager_page.cols.status')" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ statusLabel(row.status) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('agent_manager_page.cols.earnings')" width="130">
                                <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-if="!detail.downline_agents?.length" :description="t('agent_manager_page.empty_downline')" />

                        <el-divider />
                        <h4 class="mb-2">{{ t('agent_manager_page.sections.monthly_perf') }}</h4>
                        <el-table :data="detail.monthly_performance ?? []" size="small" stripe>
                            <el-table-column prop="month" :label="t('agent_manager_page.cols.month')" width="120" />
                            <el-table-column :label="t('agent_manager_page.cols.amount')">
                                <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                            </el-table-column>
                            <el-table-column prop="count" :label="t('agent_manager_page.cols.count')" width="80" />
                        </el-table>
                        <el-empty v-if="!detail.monthly_performance?.length" :description="t('agent_manager_page.empty_performance')" />
                    </template>
                </el-dialog>

                <!-- 业绩报表弹窗 -->
                <el-dialog v-model="perfDialog" :title="t('agent_manager_page.dialogs.perf_title')" width="600px" v-loading="perfLoading">
                    <template v-if="perfData">
                        <div class="mb-3">
                            <el-radio-group v-model="perfPeriod" @change="() => {}">
                                <el-radio-button v-for="opt in periodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio-button>
                            </el-radio-group>
                        </div>
                        <el-descriptions :column="3" border size="small" class="mb-3">
                            <el-descriptions-item :label="t('agent_manager_page.summary.total')">{{ formatMoney(perfData.summary?.total) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.summary.average')">{{ formatMoney(perfData.summary?.average) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('agent_manager_page.cols.count')">{{ perfData.summary?.count }}</el-descriptions-item>
                        </el-descriptions>
                        <el-table :data="perfData.data ?? []" size="small" stripe>
                            <el-table-column prop="period" :label="t('agent_manager_page.cols.period')" width="120" />
                            <el-table-column :label="t('agent_manager_page.cols.amount')">
                                <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                            </el-table-column>
                            <el-table-column prop="count" :label="t('agent_manager_page.cols.count')" width="80" />
                        </el-table>
                    </template>
                </el-dialog>
            </div>
        </el-tab-pane>

        <!-- ================================================================
             TAB 2: 渠道伙伴（懒加载）
        ================================================================ -->
        <el-tab-pane :label="t('channel_page.title')" name="channel">
            <div v-if="ch_tabVisited" class="channel-tab">
                <div class="page-header">
                    <div class="header-left">
                        <span class="header-subtitle">{{ t('channel_page.subtitle') }}</span>
                    </div>
                    <div class="header-right">
                        <el-button type="primary" plain @click="ch_refreshAll" :loading="ch_refreshing">
                            <el-icon><Refresh /></el-icon> {{ t('channel_page.buttons.refresh') }}
                        </el-button>
                    </div>
                </div>

                <!-- 统计卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value">{{ ch_stats.total_partners || '-' }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.total_partners') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value text-success">{{ ch_stats.active_partners || '-' }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.active_partners') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value text-warning">{{ ch_stats.pending_approval || '-' }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.pending_approval') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value text-primary">¥{{ ch_formatMoney(ch_stats.total_settled) }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.total_commission') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value text-success">¥{{ ch_formatMoney(ch_stats.total_paid) }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.total_paid') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="4">
                        <el-card shadow="never">
                            <div class="ch-stat-item">
                                <div class="ch-stat-value text-danger">¥{{ ch_formatMoney(ch_stats.pending_payouts) }}</div>
                                <div class="ch-stat-label">{{ t('channel_page.stats.pending_payouts') }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-tabs v-model="ch_activeTab" type="border-card">
                    <!-- ============ 看板 ============ -->
                    <el-tab-pane :label="t('channel_page.tabs.dashboard')" name="dashboard">
                        <template #label>
                            <el-icon><DataBoard /></el-icon> {{ t('channel_page.tabs.dashboard') }}
                        </template>

                        <el-row :gutter="16">
                            <!-- 等级分布 -->
                            <el-col :span="8">
                                <el-card shadow="never">
                                    <template #header>{{ t('channel_page.dashboard.level_distribution') }}</template>
                                    <div v-if="Object.keys(ch_stats.level_distribution || {}).length">
                                        <div v-for="(count, level) in ch_stats.level_distribution" :key="level" class="dist-bar">
                                            <div class="dist-info">
                                                <span>{{ ch_levelLabel(level) }}</span>
                                                <span>{{ t('channel_page.level_count_fmt', { count }) }}</span>
                                            </div>
                                            <el-progress
                                                :percentage="ch_percentOf(count, ch_stats.total_partners)"
                                                :color="ch_levelColor(level)"
                                                :stroke-width="18"
                                            />
                                        </div>
                                    </div>
                                    <el-empty v-else :description="t('messages.no_data')" />
                                </el-card>
                            </el-col>

                            <!-- 月度趋势 -->
                            <el-col :span="8">
                                <el-card shadow="never">
                                    <template #header>{{ t('channel_page.dashboard.monthly_trend') }}</template>
                                    <div v-if="ch_stats.monthly_trend?.length">
                                        <div v-for="m in ch_stats.monthly_trend" :key="m.period" class="trend-bar">
                                            <div class="trend-info">
                                                <span class="trend-period">{{ m.period }}</span>
                                                <span class="trend-amount">¥{{ ch_formatMoney(m.amount) }}</span>
                                            </div>
                                            <el-progress
                                                :percentage="ch_trendPercent(m.amount, ch_stats.monthly_trend)"
                                                color="#0f172a"
                                                :stroke-width="14"
                                            />
                                        </div>
                                    </div>
                                    <el-empty v-else :description="t('channel_page.empty_trend')" />
                                </el-card>
                            </el-col>

                            <!-- TOP 合作伙伴 -->
                            <el-col :span="8">
                                <el-card shadow="never">
                                    <template #header>
                                        <span class="flex-between">
                                            <span>{{ t('channel_page.dashboard.top_partners') }}</span>
                                            <el-button text type="primary" size="small" @click="ch_activeTab = 'partners'">{{ t('channel_page.buttons.view_all') }}</el-button>
                                        </span>
                                    </template>
                                    <el-table :data="ch_stats.top_partners || []" size="small" max-height="400">
                                        <el-table-column :label="t('channel_page.cols.rank')" width="50" type="index" />
                                        <el-table-column :label="t('channel_page.cols.name')" min-width="100" prop="name" />
                                        <el-table-column :label="t('channel_page.cols.level')" width="80">
                                            <template #default="{ row }">
                                                <el-tag :type="ch_levelTag(row.level)" size="small">{{ ch_levelLabel(row.level) }}</el-tag>
                                            </template>
                                        </el-table-column>
                                        <el-table-column :label="t('channel_page.cols.monthly_commission')" width="120">
                                            <template #default="{ row }">
                                                ¥{{ ch_formatMoney(row.monthly_amount) }}
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <!-- ============ 合作伙伴管理 ============ -->
                    <el-tab-pane :label="t('channel_page.tabs.partners')" name="partners">
                        <template #label>
                            <el-icon><User /></el-icon> {{ t('channel_page.tabs.partners') }}
                        </template>

                        <div class="tab-toolbar">
                            <el-form :inline="true" size="small">
                                <el-form-item>
                                    <el-input v-model="ch_searchPartner" :placeholder="t('channel_page.filters.search_partner_ph')" clearable @clear="ch_loadPartners" @keyup.enter="ch_loadPartners" style="width: 220px;" />
                                </el-form-item>
                                <el-form-item>
                                    <el-select v-model="ch_filterStatus" :placeholder="t('channel_page.filters.status')" clearable @change="ch_loadPartners">
                                        <el-option v-for="opt in ch_statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item>
                                    <el-select v-model="ch_filterLevel" :placeholder="t('channel_page.filters.level')" clearable @change="ch_loadPartners">
                                        <el-option v-for="opt in ch_levelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-form>
                        </div>

                        <el-table :data="ch_partners" v-loading="ch_loadingPartners" stripe>
                            <el-table-column :label="t('channel_page.cols.code')" width="120" prop="agent_code" />
                            <el-table-column :label="t('channel_page.cols.name_company')" min-width="140">
                                <template #default="{ row }">
                                    <div>{{ row.name || row.contact_name }}</div>
                                    <div class="text-muted" style="font-size:12px;">{{ row.company || '-' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.email')" width="180" prop="email" />
                            <el-table-column :label="t('channel_page.cols.level')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="ch_levelTag(row.level)" size="small">{{ ch_levelLabel(row.level) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.status')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                                        {{ ch_statusLabel(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.commission_rate')" width="80">
                                <template #default="{ row }">{{ row.effective_rate }}%</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.total_earned')" width="110">
                                <template #default="{ row }">¥{{ ch_formatMoney(row.total_earned) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.available_balance')" width="110">
                                <template #default="{ row }">
                                    <span class="text-success">¥{{ ch_formatMoney(row.available_balance) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.actions')" width="200" fixed="right">
                                <template #default="{ row }">
                                    <el-button size="small" @click="ch_viewPartnerDetail(row)">{{ t('channel_page.buttons.detail') }}</el-button>
                                    <el-button
                                        v-if="row.status === 'pending'"
                                        size="small" type="success"
                                        @click="ch_handleApprove(row)"
                                    >
                                        {{ t('actions.approve') }}
                                    </el-button>
                                    <el-dropdown trigger="click" v-if="row.status === 'active'">
                                        <el-button size="small">
                                            {{ t('channel_page.buttons.level') }} <el-icon><ArrowDown /></el-icon>
                                        </el-button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item
                                                    v-for="l in levelKeys"
                                                    :key="l"
                                                    :disabled="l === row.level"
                                                    @click="ch_handleChangeLevel(row, l)"
                                                >
                                                    {{ ch_levelLabel(l) }}
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div class="pagination-wrap" v-if="ch_partnerPagination">
                            <el-pagination
                                v-model:current-page="ch_partnerPagination.current_page"
                                :page-size="ch_partnerPagination.per_page"
                                :total="ch_partnerPagination.total"
                                layout="total, prev, pager, next"
                                @current-change="ch_loadPartners"
                            />
                        </div>

                        <!-- 合作伙伴详情 Dialog -->
                        <el-dialog v-model="ch_showPartnerDialog" :title="t('channel_page.partner_detail_title', { code: ch_detailAgent?.agent_code || '' })" width="800px">
                            <div v-loading="ch_loadingDetail">
                                <el-descriptions :column="3" border size="small" v-if="ch_detailAgent">
                                    <el-descriptions-item :label="t('channel_page.fields.name')">{{ ch_detailAgent.name || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('channel_page.fields.email')">{{ ch_detailAgent.email || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('channel_page.fields.contact_phone')">{{ ch_detailAgent.contact_phone || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('channel_page.fields.company')">{{ ch_detailAgent.company || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('channel_page.cols.level')">
                                        <el-tag :type="ch_levelTag(ch_detailAgent.level)" size="small">{{ ch_levelLabel(ch_detailAgent.level) }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('channel_page.cols.status')">
                                        <el-tag :type="ch_detailAgent.status === 'active' ? 'success' : 'info'" size="small">
                                            {{ ch_statusLabel(ch_detailAgent.status) }}
                                        </el-tag>
                                    </el-descriptions-item>
                                </el-descriptions>

                                <el-divider>{{ t('channel_page.sections.performance_stats') }}</el-divider>
                                <el-row :gutter="12" v-if="ch_detailStats">
                                    <el-col :span="6">
                                        <div class="mini-stat">
                                            <div class="mini-value">¥{{ ch_formatMoney(ch_detailStats.total_settled) }}</div>
                                            <div class="mini-label">{{ t('channel_page.mini_stats.total_settled') }}</div>
                                        </div>
                                    </el-col>
                                    <el-col :span="6">
                                        <div class="mini-stat">
                                            <div class="mini-value">¥{{ ch_formatMoney(ch_detailStats.available_balance) }}</div>
                                            <div class="mini-label">{{ t('channel_page.mini_stats.available_balance') }}</div>
                                        </div>
                                    </el-col>
                                    <el-col :span="6">
                                        <div class="mini-stat">
                                            <div class="mini-value">{{ ch_detailStats.active_subscriptions }}</div>
                                            <div class="mini-label">{{ t('channel_page.mini_stats.active_subscriptions') }}</div>
                                        </div>
                                    </el-col>
                                    <el-col :span="6">
                                        <div class="mini-stat">
                                            <div class="mini-value">{{ ch_detailStats.settlement_count }}</div>
                                            <div class="mini-label">{{ t('channel_page.mini_stats.settlement_count') }}</div>
                                        </div>
                                    </el-col>
                                </el-row>

                                <el-divider>{{ t('channel_page.sections.monthly_performance') }}</el-divider>
                                <el-table :data="ch_monthlyPerformance || []" size="small" max-height="250">
                                    <el-table-column prop="period" :label="t('channel_page.cols.month')" width="100" />
                                    <el-table-column :label="t('channel_page.cols.commission_amount')" width="120">
                                        <template #default="{ row }">¥{{ ch_formatMoney(row.amount) }}</template>
                                    </el-table-column>
                                    <el-table-column prop="count" :label="t('channel_page.cols.settlement_count')" width="80" />
                                </el-table>

                                <el-divider>{{ t('channel_page.sections.recent_settlements') }}</el-divider>
                                <el-table :data="ch_detailSettlements || []" size="small" max-height="200">
                                    <el-table-column :label="t('channel_page.cols.license')" width="150" prop="subscription?.license_key" />
                                    <el-table-column :label="t('channel_page.cols.amount')" width="100">
                                        <template #default="{ row }">¥{{ ch_formatMoney(row.commission_amount) }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('channel_page.cols.status')" width="80">
                                        <template #default="{ row }">
                                            <el-tag size="small" :type="row.status === 'released' ? 'success' : 'warning'">
                                                {{ ch_settlementStatusLabel(row.status) }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('channel_page.cols.time')" width="160">
                                        <template #default="{ row }">{{ row.created_at ? ch_formatDate(row.created_at) : '-' }}</template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </el-dialog>
                    </el-tab-pane>

                    <!-- ============ 结算明细 ============ -->
                    <el-tab-pane :label="t('channel_page.tabs.settlements')" name="settlements">
                        <template #label>
                            <el-icon><Money /></el-icon> {{ t('channel_page.tabs.settlements_short') }}
                        </template>

                        <div class="tab-toolbar">
                            <el-form :inline="true" size="small">
                                <el-form-item>
                                    <el-select v-model="ch_settlementFilter.agent_id" clearable :placeholder="t('channel_page.filters.select_partner_ph')" style="width: 180px;" @change="ch_loadSettlements">
                                        <el-option v-for="p in ch_partnerOptions" :key="p.id" :label="p.label" :value="p.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item>
                                    <el-select v-model="ch_settlementFilter.status" clearable :placeholder="t('channel_page.filters.status')" @change="ch_loadSettlements">
                                        <el-option v-for="opt in ch_settlementStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-form>
                        </div>

                        <el-table :data="ch_settlements" v-loading="ch_loadingSettlements" stripe>
                            <el-table-column prop="period" :label="t('channel_page.cols.period')" width="80" />
                            <el-table-column :label="t('channel_page.cols.partner')" min-width="120">
                                <template #default="{ row }">{{ row.agent?.user?.name || row.agent?.agent_code }}</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.license')" width="150" prop="subscription?.license_key" />
                            <el-table-column :label="t('channel_page.cols.invoice_amount')" width="100">
                                <template #default="{ row }">¥{{ ch_formatMoney(row.invoice_amount) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.commission_rate')" width="70">
                                <template #default="{ row }">{{ row.commission_rate }}%</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.commission')" width="100">
                                <template #default="{ row }">
                                    <strong>¥{{ ch_formatMoney(row.commission_amount) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.status')" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="ch_settlementStatusType(row.status)" size="small">
                                        {{ ch_settlementStatusLabel(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.settled_at')" width="150">
                                <template #default="{ row }">{{ row.created_at ? ch_formatDate(row.created_at) : '-' }}</template>
                            </el-table-column>
                        </el-table>

                        <div class="pagination-wrap" v-if="ch_settlementPagination">
                            <el-pagination
                                v-model:current-page="ch_settlementPagination.current_page"
                                :page-size="ch_settlementPagination.per_page"
                                :total="ch_settlementPagination.total"
                                layout="total, prev, pager, next"
                                @current-change="ch_loadSettlements"
                            />
                        </div>
                    </el-tab-pane>

                    <!-- ============ 推广链接 ============ -->
                    <el-tab-pane :label="t('channel_page.tabs.links')" name="links">
                        <template #label>
                            <el-icon><Link /></el-icon> {{ t('channel_page.tabs.links') }}
                        </template>

                        <div class="tab-toolbar">
                            <el-form :inline="true" size="small">
                                <el-form-item>
                                    <el-select v-model="ch_linkFilter.agent_id" clearable :placeholder="t('channel_page.filters.select_partner_ph')" style="width: 180px;" @change="ch_loadReferralLinks">
                                        <el-option v-for="p in ch_partnerOptions" :key="p.id" :label="p.label" :value="p.id" />
                                    </el-select>
                                </el-form-item>
                            </el-form>
                        </div>

                        <el-table :data="ch_referralLinks" v-loading="ch_loadingLinks" stripe>
                            <el-table-column :label="t('channel_page.cols.partner')" min-width="120">
                                <template #default="{ row }">{{ row.agent?.user?.name || '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.link_name')" width="120" prop="name" />
                            <el-table-column :label="t('channel_page.cols.referral_code')" width="120">
                                <template #default="{ row }">
                                    <code>{{ row.code }}</code>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.referral_url')" min-width="250">
                                <template #default="{ row }">
                                    <span class="mono" style="font-size:12px;">{{ row.target_url || '-' }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.active')" width="60">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                        {{ row.is_active ? t('channel_page.yes') : t('channel_page.no') }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('channel_page.cols.created_at')" width="150">
                                <template #default="{ row }">{{ row.created_at ? ch_formatDate(row.created_at) : '-' }}</template>
                            </el-table-column>
                        </el-table>

                        <div class="pagination-wrap" v-if="ch_linkPagination">
                            <el-pagination
                                v-model:current-page="ch_linkPagination.current_page"
                                :page-size="ch_linkPagination.per_page"
                                :total="ch_linkPagination.total"
                                layout="total, prev, pager, next"
                                @current-change="ch_loadReferralLinks"
                            />
                        </div>
                    </el-tab-pane>

                    <!-- ============ 等级权益 ============ -->
                    <el-tab-pane :label="t('channel_page.tabs.tiers')" name="tiers">
                        <template #label>
                            <el-icon><Medal /></el-icon> {{ t('channel_page.tabs.tiers') }}
                        </template>

                        <el-row :gutter="16">
                            <el-col :span="6" v-for="(info, tier) in ch_tierData" :key="tier">
                                <el-card shadow="hover" :class="['tier-card', 'tier-' + tier]">
                                    <div class="tier-header">
                                        <div class="tier-icon" :style="{ background: ch_tierColors[tier] }">
                                            <el-icon :size="32"><Medal /></el-icon>
                                        </div>
                                        <div class="tier-name">{{ info.label }}</div>
                                    </div>
                                    <div class="tier-rate">{{ t('channel_page.tier_rate_fmt', { rate: info.rate }) }}</div>
                                    <div v-if="info.min_requirements" class="tier-req">{{ t('channel_page.tier_req_fmt', { req: info.min_requirements }) }}</div>
                                    <el-divider />
                                    <div class="tier-benefits">
                                        <div v-for="b in info.benefits" :key="b" class="benefit-item">
                                            <el-icon color="#67c23a"><CircleCheck /></el-icon>
                                            <span>{{ b }}</span>
                                        </div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                </el-tabs>
            </div>
            <div v-else class="ch-loading-placeholder">
                <el-skeleton :rows="10" animated />
            </div>
        </el-tab-pane>

    </el-tabs>
</template>

<style scoped>
/* ─── Agent tab styles ─── */
.stat-item { text-align: center; padding: 8px 0; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 6px; }
.stat-value { font-size: 24px; font-weight: 700; }
.flex { display: flex; }
.flex-wrap { flex-wrap: wrap; }
.gap-2 { gap: 8px; }
.mt-2 { margin-top: 8px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.text-white { color: #fff; }
.text-lg { font-size: 18px; }
.font-bold { font-weight: 700; }
.justify-center { display: flex; justify-content: center; }

/* ─── Channel tab styles ─── */
.channel-tab { padding: 0; }
.ch-loading-placeholder { padding: 40px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 8px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.text-muted { color: var(--el-text-color-secondary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }
.text-primary { color: var(--el-color-primary); }

.ch-stat-item { text-align: center; padding: 8px 0; }
.ch-stat-label { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 4px; }
.ch-stat-value { font-size: 24px; font-weight: 700; }

.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.dist-bar, .trend-bar {
    margin-bottom: 12px;
}
.dist-info, .trend-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 13px;
}
.trend-period { font-weight: 600; }
.trend-amount { color: var(--el-color-primary); }

.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.mini-stat { text-align: center; padding: 12px; background: var(--el-fill-color-lighter); border-radius: 8px; }
.mini-value { font-size: 20px; font-weight: 700; color: var(--el-color-primary); }
.mini-label { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }

.mono { font-family: 'Courier New', monospace; font-size: 12px; }

/* 等级卡片 */
.tier-card {
    text-align: center;
    transition: transform 0.2s;
}
.tier-card:hover { transform: translateY(-4px); }
.tier-header { margin-bottom: 12px; }
.tier-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    margin-bottom: 8px;
}
.tier-name { font-size: 18px; font-weight: 700; }
.tier-rate { font-size: 15px; margin: 8px 0; }
.tier-req { font-size: 12px; color: var(--el-color-warning); }
.tier-benefits { text-align: left; }
.benefit-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 0;
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
