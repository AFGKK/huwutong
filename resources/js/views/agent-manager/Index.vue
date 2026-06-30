<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import agentManagerApi from '@/api/agentManager'

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

const levelColors = { regular: '#909399', silver: '#C0C4CC', gold: '#E6A23C', platinum: '#409EFF' }

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
        ElMessage.success('代理创建成功')
        createDialog.value = false
        loadAgents()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
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
        ElMessage.error('加载详情失败')
    } finally {
        detailLoading.value = false
    }
}

// ─── 审核 ───
async function approveAgent(id) {
    try {
        await ElMessageBox.confirm('确认审核通过该代理？', '提示')
        await agentManagerApi.approve(id)
        ElMessage.success('已审核通过')
        loadAgents()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败')
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
        ElMessage.error('加载报表失败')
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

const metricLabels = {
    total_earned: '累计收益',
    total_withdrawn: '已提现',
    downline_count: '下级人数',
    tier_referrals_total: '推荐数',
}

function formatMoney(v) {
    return v ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00'
}

onMounted(() => {
    loadDashboard()
})
</script>

<template>
    <div>
        <el-tabs v-model="activeTab" @tab-change="(tab) => { if (tab === 'leaderboard') loadLeaderboard() }">
            <!-- Tab 1: 仪表盘 -->
            <el-tab-pane label="📊 代理商看板" name="dashboard">
                <div v-loading="dashboardLoading">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">代理商总数</div>
                                    <div class="stat-value">{{ dashboard?.total_agents ?? '-' }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">活跃</div>
                                    <div class="stat-value" style="color:#67C23A">{{ dashboard?.active ?? '-' }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">待审核</div>
                                    <div class="stat-value" style="color:#E6A23C">{{ dashboard?.pending ?? '-' }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">待结算佣金</div>
                                    <div class="stat-value" style="color:#F56C6C">{{ formatMoney(dashboard?.pending_commission) }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">累计佣金</div>
                                    <div class="stat-value">{{ formatMoney(dashboard?.total_earned) }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">已提现</div>
                                    <div class="stat-value">{{ formatMoney(dashboard?.total_withdrawn) }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="hover">
                                <div class="stat-item">
                                    <div class="stat-label">等级分布</div>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <el-tag v-for="(count, level) in dashboard?.by_level ?? {}" :key="level"
                                            :color="levelColors[level] || '#909399'" class="text-white">
                                            {{ level }}: {{ count }}人
                                        </el-tag>
                                    </div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <!-- 月趋势 -->
                    <el-card shadow="hover" class="mb-4">
                        <template #header><span>近6月佣金趋势</span></template>
                        <div v-if="dashboard?.monthly_trend?.length">
                            <el-table :data="dashboard.monthly_trend" size="small" stripe>
                                <el-table-column prop="month" label="月份" width="120" />
                                <el-table-column label="金额">
                                    <template #default="{ row }">{{ formatMoney(row.total) }}</template>
                                </el-table-column>
                            </el-table>
                        </div>
                        <el-empty v-else description="暂无趋势数据" />
                    </el-card>

                    <!-- Top 10 -->
                    <el-card shadow="hover">
                        <template #header><span>🏆 代理商排行榜 Top 10</span></template>
                        <el-table :data="dashboard?.top_agents ?? []" size="small" stripe>
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="name" label="名称" />
                            <el-table-column prop="agent_code" label="编码" width="130" />
                            <el-table-column label="等级" width="100">
                                <template #default="{ row }">
                                    <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ row.level }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="累计收益" width="150">
                                <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                            </el-table-column>
                            <el-table-column label="已提现" width="150">
                                <template #default="{ row }">{{ formatMoney(row.total_withdrawn) }}</template>
                            </el-table-column>
                            <el-table-column prop="downline_count" label="下级" width="80" />
                        </el-table>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- Tab 2: 代理列表 -->
            <el-tab-pane label="👥 代理列表" name="agents">
                <el-card shadow="never">
                    <el-form :model="filters" inline class="mb-4">
                        <el-form-item label="状态">
                            <el-select v-model="filters.status" clearable placeholder="全部" style="width:120px">
                                <el-option label="待审核" value="pending" />
                                <el-option label="活跃" value="active" />
                                <el-option label="冻结" value="suspended" />
                                <el-option label="终止" value="terminated" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="等级">
                            <el-select v-model="filters.level" clearable placeholder="全部" style="width:120px">
                                <el-option label="普通" value="regular" />
                                <el-option label="白银" value="silver" />
                                <el-option label="黄金" value="gold" />
                                <el-option label="铂金" value="platinum" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="搜索">
                            <el-input v-model="filters.search" placeholder="名称/编码/公司" clearable style="width:200px" @keyup.enter="searchAgents" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="searchAgents">查询</el-button>
                            <el-button type="success" @click="createDialog = true">+ 新增代理</el-button>
                        </el-form-item>
                    </el-form>

                    <el-table v-loading="loading" :data="agents" stripe highlight-current-row>
                        <el-table-column prop="agent_code" label="编码" width="130" />
                        <el-table-column label="姓名" width="120">
                            <template #default="{ row }">{{ row.contact_name || row.user?.name || 'N/A' }}</template>
                        </el-table-column>
                        <el-table-column prop="company" label="公司" min-width="150" />
                        <el-table-column label="等级" width="100">
                            <template #default="{ row }">
                                <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ row.level }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                                    {{ { pending: '待审核', active: '活跃', suspended: '冻结', terminated: '终止' }[row.status] || row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="佣金率" width="90">
                            <template #default="{ row }">{{ row.commission_rate }}%</template>
                        </el-table-column>
                        <el-table-column label="累计收益" width="130">
                            <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                        </el-table-column>
                        <el-table-column label="下级" width="70" prop="downline_count" />
                        <el-table-column label="操作" width="240" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="showDetail(row.id)">详情</el-button>
                                <el-button size="small" @click="showPerformance(row.id)">业绩</el-button>
                                <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approveAgent(row.id)">审核</el-button>
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
            <el-tab-pane label="🏆 排行榜" name="leaderboard">
                <el-card shadow="never">
                    <div class="mb-3">
                        <el-radio-group v-model="lbMetric" @change="loadLeaderboard">
                            <el-radio-button value="total_earned">累计收益</el-radio-button>
                            <el-radio-button value="total_withdrawn">已提现</el-radio-button>
                            <el-radio-button value="downline_count">下级人数</el-radio-button>
                            <el-radio-button value="tier_referrals_total">推荐数</el-radio-button>
                        </el-radio-group>
                    </div>
                    <el-table v-loading="lbLoading" :data="leaderboard" stripe>
                        <el-table-column type="index" label="排名" width="70">
                            <template #default="{ $index }">
                                <span :class="[$index < 3 ? 'text-lg font-bold' : '']"
                                    :style="{ color: ['#E6A23C','#C0C4CC','#CD7F32'][$index] || 'inherit' }">
                                    #{{ $index + 1 }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column label="名称">
                            <template #default="{ row }">{{ row.name }}</template>
                        </el-table-column>
                        <el-table-column prop="agent_code" label="编码" width="130" />
                        <el-table-column label="等级" width="100">
                            <template #default="{ row }">
                                <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ row.level }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="指标值" width="160">
                            <template #default="{ row }">
                                <span v-if="lbMetric === 'total_earned' || lbMetric === 'total_withdrawn'">
                                    {{ formatMoney(row.metric) }}
                                </span>
                                <span v-else>{{ row.metric }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="累计收益" width="150">
                            <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                        </el-table-column>
                        <el-table-column prop="downline_count" label="下级" width="80" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 创建代理弹窗 -->
        <el-dialog v-model="createDialog" title="新增代理" width="500px">
            <el-form :model="form" label-width="100px">
                <el-form-item label="用户ID" required>
                    <el-input-number v-model="form.user_id" :min="1" style="width:100%" />
                </el-form-item>
                <el-form-item label="等级" required>
                    <el-select v-model="form.level" style="width:100%">
                        <el-option label="普通" value="regular" />
                        <el-option label="白银" value="silver" />
                        <el-option label="黄金" value="gold" />
                        <el-option label="铂金" value="platinum" />
                    </el-select>
                </el-form-item>
                <el-form-item label="佣金率(%)">
                    <el-input-number v-model="form.commission_rate" :min="0" :max="100" :step="0.5" style="width:100%" />
                </el-form-item>
                <el-form-item label="联系人">
                    <el-input v-model="form.contact_name" />
                </el-form-item>
                <el-form-item label="联系电话">
                    <el-input v-model="form.contact_phone" />
                </el-form-item>
                <el-form-item label="公司名称">
                    <el-input v-model="form.company" />
                </el-form-item>
                <el-form-item label="上级代理ID">
                    <el-input-number v-model="form.parent_agent_id" :min="0" :step="1" style="width:100%" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="form.notes" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createDialog = false">取消</el-button>
                <el-button type="primary" @click="createAgent">确认创建</el-button>
            </template>
        </el-dialog>

        <!-- 代理详情弹窗 -->
        <el-dialog v-model="detailDialog" title="代理详情" width="800px" v-loading="detailLoading">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="编码">{{ detail.agent?.agent_code }}</el-descriptions-item>
                    <el-descriptions-item label="等级">
                        <el-tag :color="levelColors[detail.agent?.level] || '#909399'" class="text-white">
                            {{ detail.agent?.level }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="联系人">{{ detail.agent?.contact_name }}</el-descriptions-item>
                    <el-descriptions-item label="电话">{{ detail.agent?.contact_phone }}</el-descriptions-item>
                    <el-descriptions-item label="公司">{{ detail.agent?.company || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="佣金率">{{ detail.agent?.commission_rate }}%</el-descriptions-item>
                    <el-descriptions-item label="累计收益">{{ formatMoney(detail.agent?.total_earned) }}</el-descriptions-item>
                    <el-descriptions-item label="已提现">{{ formatMoney(detail.agent?.total_withdrawn) }}</el-descriptions-item>
                    <el-descriptions-item label="下级代理">{{ detail.agent?.downline_count ?? 0 }} 人</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="detail.agent?.status === 'active' ? 'success' : 'warning'" size="small">
                            {{ detail.agent?.status }}
                        </el-tag>
                    </el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4 class="mb-2">💰 收益账户</h4>
                <el-descriptions v-if="detail.earnings_account" :column="3" border size="small">
                    <el-descriptions-item label="可用余额">{{ formatMoney(detail.earnings_account.available_balance) }}</el-descriptions-item>
                    <el-descriptions-item label="冻结中">{{ formatMoney(detail.earnings_account.pending_balance) }}</el-descriptions-item>
                    <el-descriptions-item label="已提现">{{ formatMoney(detail.earnings_account.total_withdrawn) }}</el-descriptions-item>
                </el-descriptions>
                <el-empty v-else description="暂无收益账户" />

                <el-divider />
                <h4 class="mb-2">📋 下级代理</h4>
                <el-table :data="detail.downline_agents ?? []" size="small" stripe>
                    <el-table-column label="姓名">
                        <template #default="{ row }">{{ row.name }}</template>
                    </el-table-column>
                    <el-table-column label="等级" width="100">
                        <template #default="{ row }">
                            <el-tag :color="levelColors[row.level] || '#909399'" class="text-white" size="small">{{ row.level }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="收益" width="130">
                        <template #default="{ row }">{{ formatMoney(row.total_earned) }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!detail.downline_agents?.length" description="无下级代理" />

                <el-divider />
                <h4 class="mb-2">📊 月度业绩</h4>
                <el-table :data="detail.monthly_performance ?? []" size="small" stripe>
                    <el-table-column prop="month" label="月份" width="120" />
                    <el-table-column label="金额">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="count" label="笔数" width="80" />
                </el-table>
                <el-empty v-if="!detail.monthly_performance?.length" description="暂无业绩数据" />
            </template>
        </el-dialog>

        <!-- 业绩报表弹窗 -->
        <el-dialog v-model="perfDialog" title="业绩报表" width="600px" v-loading="perfLoading">
            <template v-if="perfData">
                <div class="mb-3">
                    <el-radio-group v-model="perfPeriod" @change="() => {}">
                        <el-radio-button value="daily">日报</el-radio-button>
                        <el-radio-button value="monthly">月报</el-radio-button>
                        <el-radio-button value="yearly">年报</el-radio-button>
                    </el-radio-group>
                </div>
                <el-descriptions :column="3" border size="small" class="mb-3">
                    <el-descriptions-item label="总计">{{ formatMoney(perfData.summary?.total) }}</el-descriptions-item>
                    <el-descriptions-item label="均值">{{ formatMoney(perfData.summary?.average) }}</el-descriptions-item>
                    <el-descriptions-item label="笔数">{{ perfData.summary?.count }}</el-descriptions-item>
                </el-descriptions>
                <el-table :data="perfData.data ?? []" size="small" stripe>
                    <el-table-column prop="period" label="周期" width="120" />
                    <el-table-column label="金额">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="count" label="笔数" width="80" />
                </el-table>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
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
</style>
