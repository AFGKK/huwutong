<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/channelPartner.js'

const loading = ref(false)
const partners = ref([])
const pagination = ref({ total: 0, current_page: 1, per_page: 20 })
const search = ref('')
const filterStatus = ref('')
const filterLevel = ref('')
const activeTab = ref('partners')

// ── 统计数据 ──
const stats = ref({
    total_partners: 0,
    active_partners: 0,
    pending_approval: 0,
    total_settled: 0,
    total_paid: 0,
    pending_payouts: 0,
    level_distribution: {},
    top_partners: [],
    monthly_trend: [],
})

const levelColors = { regular: 'info', silver: 'warning', gold: '', platinum: 'primary' }
const levelLabels = { regular: '普通', silver: '银牌', gold: '金牌', platinum: '铂金' }
const statusLabels = { pending: '待审核', active: '已激活', suspended: '已暂停', terminated: '已终止' }
const statusTypes = { pending: 'warning', active: 'success', suspended: 'info', terminated: 'danger' }

// ── 详情对话框 ──
const detailVisible = ref(false)
const detailAgent = ref(null)
const detailStats = ref(null)
const detailSettlements = ref([])
const detailPerformance = ref([])
const detailLoading = ref(false)

// ── 结算列表 ──
const settlements = ref([])
const settlementPagination = ref({ total: 0, current_page: 1, per_page: 20 })
const settlementLoading = ref(false)

// ── 推广链接列表 ──
const referralLinks = ref([])
const linkPagination = ref({ total: 0, current_page: 1, per_page: 20 })
const linkLoading = ref(false)

const tabMap = {
    partners: { label: '合作伙伴', icon: 'User' },
    settlements: { label: '结算明细', icon: 'List' },
    links: { label: '推广链接', icon: 'Link' },
}

// ── 加载数据 ──

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        stats.value = res.data.data || res.data
    } catch (e) {
        console.error('Failed to load dashboard:', e)
    }
}

async function loadPartners(page = 1) {
    loading.value = true
    try {
        const params = { page, per_page: 20 }
        if (search.value) params.search = search.value
        if (filterStatus.value) params.status = filterStatus.value
        if (filterLevel.value) params.level = filterLevel.value
        const res = await api.listPartners(params)
        partners.value = res.data.data?.data || res.data.data || []
        pagination.value = {
            total: res.data.data?.total || 0,
            current_page: res.data.data?.current_page || page,
            per_page: res.data.data?.per_page || 20,
        }
    } catch (e) {
        console.error('Failed to load partners:', e)
    } finally {
        loading.value = false
    }
}

async function loadSettlements(page = 1) {
    settlementLoading.value = true
    try {
        const res = await api.listSettlements({ page, per_page: 20 })
        settlements.value = res.data.data?.data || res.data.data || []
        settlementPagination.value = {
            total: res.data.data?.total || 0,
            current_page: res.data.data?.current_page || page,
            per_page: res.data.data?.per_page || 20,
        }
    } catch (e) {
        console.error('Failed to load settlements:', e)
    } finally {
        settlementLoading.value = false
    }
}

async function loadLinks(page = 1) {
    linkLoading.value = true
    try {
        const res = await api.listReferralLinks({ page, per_page: 20 })
        referralLinks.value = res.data.data?.data || res.data.data || []
        linkPagination.value = {
            total: res.data.data?.total || 0,
            current_page: res.data.data?.current_page || page,
            per_page: res.data.data?.per_page || 20,
        }
    } catch (e) {
        console.error('Failed to load links:', e)
    } finally {
        linkLoading.value = false
    }
}

function handleSearch() { loadPartners(1) }
function resetFilters() {
    search.value = ''
    filterStatus.value = ''
    filterLevel.value = ''
    loadPartners(1)
}

// ── 查看详情 ──

async function openDetail(agentId) {
    detailVisible.value = true
    detailLoading.value = true
    try {
        const res = await api.showPartner(agentId)
        const d = res.data.data
        detailAgent.value = d.agent || d
        detailStats.value = d.stats
        detailSettlements.value = d.recent_settlements || []
        detailPerformance.value = d.monthly_performance || []
    } catch (e) {
        ElMessage.error('加载详情失败')
    } finally {
        detailLoading.value = false
    }
}

// ── 操作 ──

async function handleApprove(agent) {
    try {
        await ElMessageBox.confirm(`确认批准合作伙伴 ${agent.agent_code}？`, '确认')
        await api.approvePartner(agent.id)
        ElMessage.success('已批准')
        loadPartners(pagination.value.current_page)
        loadDashboard()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败')
    }
}

async function handleLevelChange(agent) {
    try {
        const { value } = await ElMessageBox.prompt('输入新等级 (regular/silver/gold/platinum)', '修改等级', {
            inputValue: agent.level,
            inputValidator: v => ['regular','silver','gold','platinum'].includes(v) || '等级无效',
        })
        await api.updatePartnerLevel(agent.id, value)
        ElMessage.success('等级已更新')
        loadPartners(pagination.value.current_page)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('操作失败')
    }
}

function statusLabel(s) { return statusLabels[s] || s }
function statusType(s) { return statusTypes[s] || 'info' }
function levelLabel(l) { return levelLabels[l] || l }

function formatMoney(v) { return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00' }

function tabChanged(tab) {
    if (tab === 'settlements' && settlements.value.length === 0) loadSettlements()
    if (tab === 'links' && referralLinks.value.length === 0) loadLinks()
}

onMounted(() => {
    loadDashboard()
    loadPartners()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>商业管理</el-breadcrumb-item>
            <el-breadcrumb-item>合作伙伴管理</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-5">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">合作伙伴总数</div>
                    <div class="stat-value">{{ stats.total_partners }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">活跃</div>
                    <div class="stat-value text-success">{{ stats.active_partners }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">待审核</div>
                    <div class="stat-value text-warning">{{ stats.pending_approval }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">总佣金</div>
                    <div class="stat-value">{{ formatMoney(stats.total_settled) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">已提现</div>
                    <div class="stat-value">{{ formatMoney(stats.total_paid) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">待支付</div>
                    <div class="stat-value text-warning">{{ formatMoney(stats.pending_payouts) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 等级分布 -->
        <el-row :gutter="16" class="mb-5" v-if="Object.keys(stats.level_distribution || {}).length">
            <el-col :span="24">
                <el-card shadow="never">
                    <template #header><span class="font-semibold">等级分布</span></template>
                    <div class="flex gap-4">
                        <div v-for="(count, level) in stats.level_distribution" :key="level" class="flex-1 text-center">
                            <el-tag :type="levelColors[level] || 'info'" size="large" class="mb-1">
                                {{ levelLabels[level] || level }}
                            </el-tag>
                            <div class="text-xl font-bold mt-1">{{ count }}</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 月份趋势图 (简短) -->
        <el-card shadow="never" class="mb-5" v-if="stats.monthly_trend?.length">
            <template #header><span class="font-semibold">月度佣金趋势 (近12个月)</span></template>
            <div class="flex items-end gap-1" style="height:120px">
                <div v-for="m in stats.monthly_trend" :key="m.period" class="flex-1 flex flex-col items-center">
                    <div class="text-xs text-gray-500 mb-1">{{ formatMoney(m.amount) }}</div>
                    <div class="w-full bg-blue-500 rounded" :style="{ height: Math.max(4, (m.amount / Math.max(...stats.monthly_trend.map(x => x.amount))) * 80) + 'px' }"></div>
                    <div class="text-xs text-gray-400 mt-1">{{ m.period.slice(-2) }}</div>
                </div>
            </div>
        </el-card>

        <!-- 标签页 -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="tabChanged">
                <!-- ── 合作伙伴列表 ── -->
                <el-tab-pane label="合作伙伴" name="partners">
                    <div class="flex gap-3 mb-4 flex-wrap items-center">
                        <el-input v-model="search" placeholder="搜索编号/联系人/公司" style="width:260px" clearable @keyup.enter="handleSearch" />
                        <el-select v-model="filterStatus" placeholder="按状态" clearable style="width:130px">
                            <el-option label="待审核" value="pending" />
                            <el-option label="已激活" value="active" />
                            <el-option label="已暂停" value="suspended" />
                            <el-option label="已终止" value="terminated" />
                        </el-select>
                        <el-select v-model="filterLevel" placeholder="按等级" clearable style="width:130px">
                            <el-option label="普通" value="regular" />
                            <el-option label="银牌" value="silver" />
                            <el-option label="金牌" value="gold" />
                            <el-option label="铂金" value="platinum" />
                        </el-select>
                        <el-button type="primary" @click="handleSearch">搜索</el-button>
                        <el-button @click="resetFilters">重置</el-button>
                    </div>

                    <el-table :data="partners" v-loading="loading" stripe>
                        <el-table-column prop="agent_code" label="编号" width="110" />
                        <el-table-column label="合作伙伴" min-width="160">
                            <template #default="{ row }">{{ row.name }}{{ row.email ? `<${row.email}>` : '' }}</template>
                        </el-table-column>
                        <el-table-column label="等级" width="90">
                            <template #default="{ row }">
                                <el-tag :type="levelColors[row.level] || 'info'" size="small">{{ levelLabel(row.level) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="佣金率" width="80">
                            <template #default="{ row }">{{ row.commission_rate ?? '-' }}%</template>
                        </el-table-column>
                        <el-table-column label="公司/联系人" min-width="140">
                            <template #default="{ row }">{{ row.company || row.contact_name || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="累计收益" width="110" align="right">
                            <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                        </el-table-column>
                        <el-table-column label="可提现余额" width="110" align="right">
                            <template #default="{ row }">{{ formatMoney(row.available_balance) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openDetail(row.id)">详情</el-button>
                                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="handleApprove(row)">批准</el-button>
                                <el-button size="small" @click="handleLevelChange(row)">改等级</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page" :total="pagination.total"
                            layout="prev, pager, next, total" @current-change="loadPartners" />
                    </div>
                </el-tab-pane>

                <!-- ── 结算明细 ── -->
                <el-tab-pane label="结算明细" name="settlements">
                    <el-table :data="settlements" v-loading="settlementLoading" stripe>
                        <el-table-column prop="id" label="ID" width="70" />
                        <el-table-column label="合作伙伴" width="180">
                            <template #default="{ row }">{{ row.agent?.agent_code || row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column label="License" width="140">
                            <template #default="{ row }">{{ row.subscription?.license_key || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="账期" width="80">
                            <template #default="{ row }">{{ row.period }}</template>
                        </el-table-column>
                        <el-table-column label="发票金额" width="110" align="right">
                            <template #default="{ row }">{{ formatMoney(row.invoice_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="佣金率" width="80" align="center">
                            <template #default="{ row }">{{ row.commission_rate }}%</template>
                        </el-table-column>
                        <el-table-column label="佣金金额" width="110" align="right">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'released' ? 'success' : row.status === 'refunded' ? 'danger' : 'warning'" size="small">
                                    {{ row.status === 'pending' ? '待结算' : row.status === 'pending_release' ? '待释放' : row.status === 'released' ? '已释放' : '已退款' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="结算时间" width="160">
                            <template #default="{ row }">{{ row.settled_at ? new Date(row.settled_at).toLocaleString('zh-CN') : '-' }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="settlementPagination.current_page"
                            :page-size="settlementPagination.per_page" :total="settlementPagination.total"
                            layout="prev, pager, next, total" @current-change="loadSettlements" />
                    </div>
                </el-tab-pane>

                <!-- ── 推广链接 ── -->
                <el-tab-pane label="推广链接" name="links">
                    <el-table :data="referralLinks" v-loading="linkLoading" stripe>
                        <el-table-column prop="id" label="ID" width="70" />
                        <el-table-column label="合作伙伴" width="180">
                            <template #default="{ row }">{{ row.agent?.agent_code || row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column prop="name" label="链接名称" min-width="120" />
                        <el-table-column prop="code" label="推广码" width="130" />
                        <el-table-column label="目标 URL" min-width="180">
                            <template #default="{ row }">
                                <span class="text-gray-500 text-sm truncate block max-w-[200px]">{{ row.target_url || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="clicks" label="点击" width="70" align="center" />
                        <el-table-column prop="conversions" label="转化" width="70" align="center" />
                        <el-table-column label="活跃" width="70" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="160">
                            <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString('zh-CN') : '-' }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="linkPagination.current_page"
                            :page-size="linkPagination.per_page" :total="linkPagination.total"
                            layout="prev, pager, next, total" @current-change="loadLinks" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- ── 详情对话框 ── -->
        <el-dialog v-model="detailVisible" title="合作伙伴详情" width="800px">
            <div v-loading="detailLoading">
                <template v-if="detailAgent">
                    <el-descriptions :column="2" border class="mb-4">
                        <el-descriptions-item label="编号">{{ detailAgent.agent_code }}</el-descriptions-item>
                        <el-descriptions-item label="等级">
                            <el-tag :type="levelColors[detailAgent.level] || 'info'">{{ levelLabel(detailAgent.level) }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="状态">
                            <el-tag :type="statusType(detailAgent.status)">{{ statusLabel(detailAgent.status) }}</el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="佣金率">{{ detailAgent.commission_rate }}%</el-descriptions-item>
                        <el-descriptions-item label="累计收益">{{ formatMoney(detailAgent.total_earned) }}</el-descriptions-item>
                        <el-descriptions-item label="已提现">{{ formatMoney(detailAgent.total_withdrawn) }}</el-descriptions-item>
                        <el-descriptions-item label="公司">{{ detailAgent.company || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="联系人">{{ detailAgent.contact_name || '-' }} / {{ detailAgent.contact_phone || '-' }}</el-descriptions-item>
                    </el-descriptions>

                    <div class="font-semibold mb-2">业绩统计</div>
                    <el-row :gutter="12" class="mb-4" v-if="detailStats">
                        <el-col :span="6" v-for="(val, key) in detailStats" :key="key">
                            <el-card shadow="never" class="mb-2">
                                <div class="text-xs text-gray-500">{{ key }}</div>
                                <div class="text-base font-bold">{{ typeof val === 'number' ? formatMoney(val) : val }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <div class="font-semibold mb-2">月度业绩</div>
                    <el-table :data="detailPerformance" stripe size="small" class="mb-3">
                        <el-table-column prop="period" label="月份" width="80" />
                        <el-table-column prop="amount" label="佣金金额" width="120">
                            <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column prop="count" label="笔数" width="70" />
                    </el-table>

                    <div class="font-semibold mb-2">最近结算</div>
                    <el-table :data="detailSettlements" stripe size="small">
                        <el-table-column prop="period" label="账期" width="70" />
                        <el-table-column prop="commission_amount" label="佣金" width="100">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="License" width="130">
                            <template #default="{ row }">{{ row.subscription?.license_key || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'released' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" width="160">
                            <template #default="{ row }">{{ row.settled_at ? new Date(row.settled_at).toLocaleString('zh-CN') : '-' }}</template>
                        </el-table-column>
                    </el-table>
                </template>
            </div>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
</style>
