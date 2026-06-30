<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/channelPartner.js'

const loading = ref(true)
const dashboard = ref(null)
const agent = ref(null)
const stats = ref(null)
const settlements = ref([])
const referralLinks = ref([])
const tierBenefitsVal = ref(null)

// 提现对话框
const payoutDialog = ref(false)
const payoutForm = ref({ amount: null, payout_method: 'bank_transfer', account_info: {} })
const submitting = ref(false)

// 提现记录
const payouts = ref([])
const payoutLoading = ref(false)
const payoutPagination = ref({ total: 0, current_page: 1, per_page: 20 })

const levelColors = { regular: 'info', silver: 'warning', gold: '', platinum: 'primary' }
const levelLabels = { regular: '普通', silver: '银牌', gold: '金牌', platinum: '铂金' }
const levelBgs = { regular: '#f0f0f0', silver: '#fdf6ec', gold: '#fdf6ec', platinum: '#ecf5ff' }

const payoutMethodLabels = {
    bank_transfer: '银行转账',
    alipay: '支付宝',
    wechat: '微信支付',
    balance: '余额',
}

const statusTypes = { pending: 'warning', processing: '', completed: 'success', failed: 'danger', cancelled: 'info' }
const statusLabels = { pending: '待处理', processing: '处理中', completed: '已完成', failed: '失败', cancelled: '已取消' }

async function loadDashboard() {
    loading.value = true
    try {
        const res = await api.myDashboard()
        const d = res.data.data
        dashboard.value = d
        agent.value = d.agent
        stats.value = d.stats
        settlements.value = d.recent_settlements || []
        referralLinks.value = d.referral_links || []
    } catch (e) {
        ElMessage.error('加载面板失败')
    } finally {
        loading.value = false
    }
}

async function loadTierBenefits() {
    try {
        const res = await api.tierBenefits()
        tierBenefitsVal.value = res.data.data
    } catch (e) {}
}

async function loadPayouts(page = 1) {
    payoutLoading.value = true
    try {
        const res = await api.myPayouts({ page, per_page: 20 })
        payouts.value = res.data.data?.data || res.data.data || []
        payoutPagination.value = {
            total: res.data.data?.total || 0,
            current_page: res.data.data?.current_page || page,
            per_page: res.data.data?.per_page || 20,
        }
    } catch (e) {
        console.error(e)
    } finally {
        payoutLoading.value = false
    }
}

async function handleRequestPayout() {
    try {
        await ElMessageBox.confirm(`确认提现 ¥${payoutForm.value.amount}？`, '确认')
    } catch {
        return
    }

    submitting.value = true
    try {
        await api.requestPayout(payoutForm.value)
        ElMessage.success('提现请求已提交')
        payoutDialog.value = false
        loadDashboard()
        loadPayouts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提现失败')
    } finally {
        submitting.value = false
    }
}

function openPayoutDialog() {
    payoutForm.value = { amount: null, payout_method: 'bank_transfer', account_info: {} }
    payoutDialog.value = true
}

function formatMoney(v) {
    return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00'
}

onMounted(() => {
    loadDashboard()
    loadTierBenefits()
    loadPayouts()
})
</script>

<template>
    <div>
        <div class="mb-4">
            <h1 class="text-xl font-semibold">合作伙伴中心</h1>
            <p class="text-gray-500 text-sm mt-1">查看您的推广业绩、佣金收益和个人信息。</p>
        </div>

        <div v-loading="loading">
            <!-- 代理商信息卡 -->
            <el-card shadow="never" class="mb-5" v-if="agent">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <el-avatar :size="56" class="flex-shrink-0">
                            {{ agent.agent_code?.slice(0, 2) || 'P' }}
                        </el-avatar>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold">{{ agent.agent_code }}</span>
                                <el-tag :type="levelColors[agent.level] || 'info'" size="small">
                                    {{ levelLabels[agent.level] || agent.level }}
                                </el-tag>
                                <el-tag :type="agent.status === 'active' ? 'success' : 'warning'" size="small">
                                    {{ agent.status === 'active' ? '已激活' : '待审核' }}
                                </el-tag>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                佣金率: {{ agent.commission_rate || 0 }}% &nbsp;|&nbsp;
                                推广码: {{ agent.agent_code }}
                            </div>
                        </div>
                    </div>
                    <el-button type="primary" @click="openPayoutDialog" :disabled="!stats?.available_balance || stats.available_balance < 100">
                        发起提现
                    </el-button>
                </div>
            </el-card>

            <!-- 收益统计 -->
            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">累计收益</div>
                        <div class="stat-value text-primary">{{ formatMoney(stats.total_earned) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">可提现余额</div>
                        <div class="stat-value text-success">{{ formatMoney(stats.available_balance) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">冻结中</div>
                        <div class="stat-value text-warning">{{ formatMoney(stats.pending_balance || 0) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">已提现</div>
                        <div class="stat-value">{{ formatMoney(stats.total_withdrawn || 0) }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 等级权益 -->
            <el-card shadow="never" class="mb-5" v-if="tierBenefitsVal">
                <template #header><span class="font-semibold">等级权益</span></template>
                <div class="flex gap-4">
                    <div v-for="b in tierBenefitsVal.benefits" :key="b.label" class="flex-1 text-center p-3 rounded-lg" :class="{ 'ring-2 ring-blue-500': b.label === tierBenefitsVal.current_label }">
                        <div class="font-semibold">{{ b.label }}</div>
                        <div class="text-sm text-gray-500">{{ b.rate }}</div>
                    </div>
                    <div v-if="tierBenefitsVal.next_level" class="flex items-center text-sm text-gray-400">
                        下一级: {{ tierBenefitsVal.next_level }}
                    </div>
                </div>
            </el-card>

            <!-- 推广链接 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">我的推广链接</span></template>
                <el-table :data="referralLinks" stripe v-if="referralLinks.length">
                    <el-table-column prop="name" label="名称" width="150" />
                    <el-table-column label="推广链接" min-width="300">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="text-blue-500 text-sm truncate">{{ row.url }}</span>
                                <el-button size="small" text @click="navigator.clipboard?.writeText(row.url); ElMessage.success('已复制')">复制</el-button>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="clicks" label="点击" width="70" align="center" />
                    <el-table-column prop="conversions" label="转化" width="70" align="center" />
                    <el-table-column label="创建时间" width="160">
                        <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString('zh-CN') : '-' }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="暂无推广链接，请联系管理员创建" />
            </el-card>

            <!-- 最近结算 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">最近结算</span></template>
                <el-table :data="settlements" stripe>
                    <el-table-column prop="period" label="账期" width="80" />
                    <el-table-column prop="commission_amount" label="佣金" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="License" min-width="140">
                        <template #default="{ row }">{{ row.subscription?.license_key || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'released' ? 'success' : 'warning'" size="small">
                                {{ row.status === 'pending' ? '待结算' : row.status === 'pending_release' ? '待释放' : row.status === 'released' ? '已释放' : '已退款' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="时间" width="160">
                        <template #default="{ row }">{{ row.settled_at ? new Date(row.settled_at).toLocaleString('zh-CN') : '-' }}</template>
                    </el-table-column>
                </el-table>
            </el-card>

            <!-- 提现记录 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">提现记录</span></template>
                <el-table :data="payouts" v-loading="payoutLoading" stripe>
                    <el-table-column prop="amount" label="金额" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="fee" label="手续费" width="90" align="right">
                        <template #default="{ row }">{{ formatMoney(row.fee) }}</template>
                    </el-table-column>
                    <el-table-column prop="net_amount" label="到账金额" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="方式" width="120">
                        <template #default="{ row }">{{ payoutMethodLabels[row.payout_method] || row.payout_method }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="90">
                        <template #default="{ row }">
                            <el-tag :type="statusTypes[row.status] || 'info'" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="申请时间" width="160">
                        <template #default="{ row }">{{ row.requested_at ? new Date(row.requested_at).toLocaleString('zh-CN') : '-' }}</template>
                    </el-table-column>
                </el-table>
                <div class="flex justify-center mt-3">
                    <el-pagination v-model:current-page="payoutPagination.current_page"
                        :page-size="payoutPagination.per_page" :total="payoutPagination.total"
                        layout="prev, pager, next, total" @current-change="loadPayouts" />
                </div>
            </el-card>
        </div>

        <!-- 提现对话框 -->
        <el-dialog v-model="payoutDialog" title="发起提现" width="450px">
            <el-form :model="payoutForm" label-width="100px">
                <el-form-item label="提现金额">
                    <el-input-number v-model="payoutForm.amount" :min="100" :step="100" :precision="2" style="width:100%" />
                    <div class="text-xs text-gray-400 mt-1">最低提现金额 ¥100，可提现余额 {{ formatMoney(stats?.available_balance) }}</div>
                </el-form-item>
                <el-form-item label="提现方式">
                    <el-radio-group v-model="payoutForm.payout_method">
                        <el-radio value="bank_transfer">银行转账</el-radio>
                        <el-radio value="alipay">支付宝</el-radio>
                        <el-radio value="wechat">微信</el-radio>
                        <el-radio value="balance">余额</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="账号信息">
                    <el-input v-model="payoutForm.account_info.account" placeholder="账号" class="mb-2" />
                    <el-input v-model="payoutForm.account_info.name" placeholder="开户名/姓名" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="payoutDialog = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="handleRequestPayout">提交提现</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.text-primary { color: #409eff; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
</style>
