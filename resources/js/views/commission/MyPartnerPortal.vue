<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/channelPartner.js'

const { t, locale } = useI18n()
const ns = 'my_partner_portal_page'
const ch = 'channel_page'
const cp = 'channel_partners_page'
const cm = 'commission_page'
const ma = 'my_affiliate_page'
const rd = 'risk_dashboard_page'

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
const levelBgs = { regular: '#f0f0f0', silver: '#fdf6ec', gold: '#fdf6ec', platinum: '#f1f5f9' }
const statusTypes = { pending: 'warning', processing: '', completed: 'success', failed: 'danger', cancelled: 'info' }

const dateLocale = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'))

const levelLabels = computed(() => ({
    regular: t(`${ch}.levels.regular`),
    silver: t(`${ch}.levels.silver`),
    gold: t(`${ch}.levels.gold`),
    platinum: t(`${ch}.levels.platinum`),
}))
const agentStatusLabels = computed(() => ({
    active: t(`${ch}.status.active`),
    pending: t(`${cp}.status.pending`),
}))
const settlementStatusLabels = computed(() => ({
    pending: t(`${ch}.settlement_status.pending`),
    pending_release: t(`${ch}.settlement_status.pending_release`),
    released: t(`${ch}.settlement_status.released`),
    refunded: t(`${ch}.settlement_status.refunded`),
}))
const payoutStatusLabels = computed(() => ({
    pending: t(`${cm}.payout_status.pending`),
    processing: t(`${cm}.payout_status.processing`),
    completed: t(`${cm}.payout_status.completed`),
    failed: t(`${cm}.payout_status.failed`),
    cancelled: t(`${cm}.payout_status.cancelled`),
}))
const payoutMethodLabels = computed(() => ({
    bank_transfer: t(`${rd}.payout_methods.bank_transfer`),
    alipay: t(`${rd}.payout_methods.alipay`),
    wechat: t(`${rd}.payout_methods.wechat`),
    balance: t(`${rd}.payout_methods.balance`),
}))

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
        ElMessage.error(t(`${ns}.messages.load_dashboard_failed`))
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
        await ElMessageBox.confirm(
            t(`${ns}.messages.payout_confirm`, { amount: payoutForm.value.amount }),
            t('actions.confirm'),
        )
    } catch {
        return
    }

    submitting.value = true
    try {
        await api.requestPayout(payoutForm.value)
        ElMessage.success(t(`${ns}.messages.payout_submitted`))
        payoutDialog.value = false
        loadDashboard()
        loadPayouts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.payout_failed`))
    } finally {
        submitting.value = false
    }
}

function openPayoutDialog() {
    payoutForm.value = { amount: null, payout_method: 'bank_transfer', account_info: {} }
    payoutDialog.value = true
}

function formatMoney(v) {
    return v != null ? '¥' + Number(v).toLocaleString(dateLocale.value, { minimumFractionDigits: 2 }) : '¥0.00'
}

function fmtDateTime(d) {
    return d ? new Date(d).toLocaleString(dateLocale.value) : '-'
}

function copyReferralUrl(url) {
    navigator.clipboard?.writeText(url)
    ElMessage.success(t(`${ma}.messages.copied`))
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
            <h1 class="text-xl font-semibold">{{ t(`${ns}.title`) }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ t(`${ns}.subtitle`) }}</p>
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
                                    {{ agentStatusLabels[agent.status] || agent.status }}
                                </el-tag>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                {{ t(`${ns}.commission_rate_fmt`, { rate: agent.commission_rate || 0 }) }} &nbsp;|&nbsp;
                                {{ t(`${ns}.referral_code_fmt`, { code: agent.agent_code }) }}
                            </div>
                        </div>
                    </div>
                    <el-button type="primary" @click="openPayoutDialog" :disabled="!stats?.available_balance || stats.available_balance < 100">
                        {{ t(`${ns}.payout.title`) }}
                    </el-button>
                </div>
            </el-card>

            <!-- 收益统计 -->
            <el-row :gutter="16" class="mb-5" v-if="stats">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">{{ t(`${ns}.stats.total_earned`) }}</div>
                        <div class="stat-value text-primary">{{ formatMoney(stats.total_earned) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">{{ t(`${ns}.stats.available_balance`) }}</div>
                        <div class="stat-value text-success">{{ formatMoney(stats.available_balance) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">{{ t(`${ns}.stats.pending_balance`) }}</div>
                        <div class="stat-value text-warning">{{ formatMoney(stats.pending_balance || 0) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">{{ t(`${ns}.stats.total_withdrawn`) }}</div>
                        <div class="stat-value">{{ formatMoney(stats.total_withdrawn || 0) }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 等级权益 -->
            <el-card shadow="never" class="mb-5" v-if="tierBenefitsVal">
                <template #header><span class="font-semibold">{{ t(`${ns}.sections.tier_benefits`) }}</span></template>
                <div class="flex gap-4">
                    <div v-for="b in tierBenefitsVal.benefits" :key="b.label" class="flex-1 text-center p-3 rounded-lg" :class="{ 'ring-2 ring-blue-500': b.label === tierBenefitsVal.current_label }">
                        <div class="font-semibold">{{ b.label }}</div>
                        <div class="text-sm text-gray-500">{{ b.rate }}</div>
                    </div>
                    <div v-if="tierBenefitsVal.next_level" class="flex items-center text-sm text-gray-400">
                        {{ t(`${ns}.next_level_fmt`, { level: tierBenefitsVal.next_level }) }}
                    </div>
                </div>
            </el-card>

            <!-- 推广链接 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">{{ t(`${ns}.sections.my_referral_links`) }}</span></template>
                <el-table :data="referralLinks" stripe v-if="referralLinks.length">
                    <el-table-column prop="name" :label="t(`${ch}.cols.link_name`)" width="150" />
                    <el-table-column :label="t(`${ch}.cols.referral_url`)" min-width="300">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="text-blue-500 text-sm truncate">{{ row.url }}</span>
                                <el-button size="small" text @click="copyReferralUrl(row.url)">{{ t('actions.copy') }}</el-button>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="clicks" :label="t(`${ma}.stats.clicks`)" width="70" align="center" />
                    <el-table-column prop="conversions" :label="t(`${ma}.stats.conversions`)" width="70" align="center" />
                    <el-table-column :label="t(`${ch}.cols.created_at`)" width="160">
                        <template #default="{ row }">{{ fmtDateTime(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else :description="t(`${ns}.empty.no_referral_links`)" />
            </el-card>

            <!-- 最近结算 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">{{ t(`${ns}.sections.recent_settlements`) }}</span></template>
                <el-table :data="settlements" stripe>
                    <el-table-column prop="period" :label="t(`${ch}.cols.period`)" width="80" />
                    <el-table-column prop="commission_amount" :label="t(`${ch}.cols.commission`)" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="t(`${ch}.cols.license`)" min-width="140">
                        <template #default="{ row }">{{ row.subscription?.license_key || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t(`${ch}.cols.status`)" width="90">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'released' ? 'success' : 'warning'" size="small">
                                {{ settlementStatusLabels[row.status] || row.status }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${ch}.cols.time`)" width="160">
                        <template #default="{ row }">{{ fmtDateTime(row.settled_at) }}</template>
                    </el-table-column>
                </el-table>
            </el-card>

            <!-- 提现记录 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">{{ t(`${ns}.sections.payout_records`) }}</span></template>
                <el-table :data="payouts" v-loading="payoutLoading" stripe>
                    <el-table-column prop="amount" :label="t(`${cm}.cols.amount`)" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="fee" :label="t(`${cm}.cols.fee`)" width="90" align="right">
                        <template #default="{ row }">{{ formatMoney(row.fee) }}</template>
                    </el-table-column>
                    <el-table-column prop="net_amount" :label="t(`${cm}.cols.net_amount`)" width="110" align="right">
                        <template #default="{ row }">{{ formatMoney(row.net_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="t(`${cm}.cols.payout_method`)" width="120">
                        <template #default="{ row }">{{ payoutMethodLabels[row.payout_method] || row.payout_method }}</template>
                    </el-table-column>
                    <el-table-column :label="t(`${ch}.cols.status`)" width="90">
                        <template #default="{ row }">
                            <el-tag :type="statusTypes[row.status] || 'info'" size="small">{{ payoutStatusLabels[row.status] || row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${rd}.cols.requested_at`)" width="160">
                        <template #default="{ row }">{{ fmtDateTime(row.requested_at) }}</template>
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
        <el-dialog v-model="payoutDialog" :title="t(`${ns}.payout.title`)" width="450px">
            <el-form :model="payoutForm" label-width="100px">
                <el-form-item :label="t(`${ns}.payout.amount`)">
                    <el-input-number v-model="payoutForm.amount" :min="100" :step="100" :precision="2" style="width:100%" />
                    <div class="text-xs text-gray-400 mt-1">{{ t(`${ns}.payout.min_amount_hint`, { balance: formatMoney(stats?.available_balance) }) }}</div>
                </el-form-item>
                <el-form-item :label="t(`${ns}.payout.method`)">
                    <el-radio-group v-model="payoutForm.payout_method">
                        <el-radio value="bank_transfer">{{ payoutMethodLabels.bank_transfer }}</el-radio>
                        <el-radio value="alipay">{{ payoutMethodLabels.alipay }}</el-radio>
                        <el-radio value="wechat">{{ payoutMethodLabels.wechat }}</el-radio>
                        <el-radio value="balance">{{ payoutMethodLabels.balance }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t(`${ns}.payout.account_info`)">
                    <el-input v-model="payoutForm.account_info.account" :placeholder="t(`${ns}.payout.account_ph`)" class="mb-2" />
                    <el-input v-model="payoutForm.account_info.name" :placeholder="t(`${ns}.payout.account_name_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="payoutDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleRequestPayout">{{ t(`${ns}.payout.submit`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.text-primary { color: #0f172a; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
</style>
