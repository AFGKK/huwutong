<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import enhancedApi from '@/api/affiliateEnhanced'
import affiliateApi from '@/api/affiliate'

const { t, locale } = useI18n()
const ns = 'affiliate_enhanced_page'

const activeTab = ref('campaigns')
const loading = ref(false)

// ─── 看板数据 ───
const dashboardData = ref(null)

// ─── 推广链接 ───
const linkForm = ref({ agent_id: null, campaign_id: null, product_id: null })
const generatedLink = ref(null)
const generatingLink = ref(false)
const agentLinks = ref([])
const linkAgentId = ref('')
const linkLoading = ref(false)

// ─── 推广门户 ───
const portalAgentId = ref('')
const portalData = ref(null)
const portalLoading = ref(false)

// ─── 收益账户结算 ───
const settleForm = ref({ agent_id: null, amount: null, source: 'manual', notes: '' })
const settling = ref(false)
const settleResult = ref(null)

// ─── 商品级推广 ───
const productLinkForm = ref({ agent_id: null, product_id: null, campaign_id: null })
const productLinkResult = ref(null)
const productLinkLoading = ref(false)
const productStatsData = ref(null)
const storeDashData = ref(null)

// ─── 转化归因 ───
const attributeForm = ref({ referral_code: '', converted_user_id: null, commission_amount: 0 })
const attributing = ref(false)
const attrResult = ref(null)

const sourceOptions = computed(() => [
    { label: t(`${ns}.source.affiliate_commission`), value: 'affiliate_commission' },
    { label: t(`${ns}.source.store_commission`), value: 'store_commission' },
    { label: t(`${ns}.source.manual`), value: 'manual' },
])

function yesNoLabel(value) {
    return value ? t(`${ns}.bool.yes`) : t(`${ns}.bool.no`)
}

async function loadDashboard() {
    try {
        const res = await affiliateApi.dashboard()
        dashboardData.value = res.data.data
    } catch (e) {}
}

async function generateLink() {
    generatingLink.value = true
    try {
        const res = await enhancedApi.generateLink(linkForm.value)
        generatedLink.value = res.data.data
        ElMessage.success(t(`${ns}.messages.link_generated`))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.generate_failed`))
    } finally {
        generatingLink.value = false
    }
}

async function loadAgentLinks() {
    if (!linkAgentId.value) { ElMessage.warning(t(`${ns}.messages.enter_agent_id`)); return }
    linkLoading.value = true
    try {
        const res = await enhancedApi.agentLinks(linkAgentId.value)
        agentLinks.value = res.data.data || []
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.load_links_failed`))
    } finally {
        linkLoading.value = false
    }
}

async function loadPortal() {
    if (!portalAgentId.value) { ElMessage.warning(t(`${ns}.messages.enter_agent_id`)); return }
    portalLoading.value = true
    try {
        const res = await enhancedApi.agentPortal(portalAgentId.value)
        portalData.value = res.data.data
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.load_portal_failed`))
    } finally {
        portalLoading.value = false
    }
}

async function settleCommission() {
    settling.value = true
    try {
        const res = await enhancedApi.settleCommission(settleForm.value)
        settleResult.value = res.data.data
        ElMessage.success(t(`${ns}.messages.commission_settled`))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.settle_failed`))
    } finally {
        settling.value = false
    }
}

async function generateProductLink() {
    productLinkLoading.value = true
    try {
        const res = await enhancedApi.generateProductLink(productLinkForm.value)
        productLinkResult.value = res.data.data
        ElMessage.success(t(`${ns}.messages.product_link_generated`))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.generate_failed`))
    } finally {
        productLinkLoading.value = false
    }
}

async function loadProductStats() {
    try {
        const res = await enhancedApi.storeDashboard()
        storeDashData.value = res.data.data
    } catch (e) {}
}

async function attributeConversion() {
    attributing.value = true
    try {
        const res = await enhancedApi.attributeWithSettlement(attributeForm.value)
        attrResult.value = res.data.data
        ElMessage.success(
            res.data.data.attributed
                ? t(`${ns}.messages.attributed_settled`)
                : t(`${ns}.messages.no_matching_click`)
        )
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.attribute_failed`))
    } finally {
        attributing.value = false
    }
}

function formatMoney(v) {
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return v != null ? '¥' + Number(v).toLocaleString(loc, { minimumFractionDigits: 2 }) : '¥0.00'
}
function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}
function copyText(text) {
    navigator.clipboard?.writeText(text).then(() => ElMessage.success(t(`${ns}.messages.copied`)))
}

onMounted(() => {
    loadDashboard()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${ns}.breadcrumb.business`) }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t(`${ns}.breadcrumb.current`) }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 看板统计 -->
        <el-row :gutter="16" class="mb-4" v-if="dashboardData?.overview">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.total_clicks`) }}</div>
                        <div class="stat-value">{{ dashboardData.overview.total_clicks }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.total_conversions`) }}</div>
                        <div class="stat-value" style="color:#67C23A">{{ dashboardData.overview.total_conversions }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.conversion_rate`) }}</div>
                        <div class="stat-value" style="color:#0f172a">{{ dashboardData.overview.conversion_rate }}%</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.total_commission`) }}</div>
                        <div class="stat-value">{{ formatMoney(dashboardData.overview.total_commission) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.active_campaigns`) }}</div>
                        <div class="stat-value" style="color:#E6A23C">{{ dashboardData.overview.active_campaigns }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <div class="stat-label">{{ t(`${ns}.stats.monthly_commission`) }}</div>
                        <div class="stat-value">{{ formatMoney(dashboardData.monthly_trend?.[dashboardData.monthly_trend.length - 1]?.commission || 0) }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- Tab 1: 推广链接 -->
                <el-tab-pane :label="t(`${ns}.tabs.links`)" name="links">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.links.generate_title`) }}</span></template>
                                <el-form :model="linkForm" label-width="110px">
                                    <el-form-item :label="t(`${ns}.cols.agent_id`)" required>
                                        <el-input-number v-model="linkForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.campaign_id`)">
                                        <el-input-number v-model="linkForm.campaign_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.product_id`)">
                                        <el-input-number v-model="linkForm.product_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="generatingLink" @click="generateLink">{{ t(`${ns}.buttons.generate_link`) }}</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="generatedLink" class="p-3 bg-green-50 rounded">
                                    <div class="mb-2"><strong>{{ t(`${ns}.labels.referral_code`) }}</strong><code>{{ generatedLink.referral_code }}</code></div>
                                    <div class="mb-2"><strong>{{ t(`${ns}.labels.short_url`) }}</strong><code class="text-sm break-all">{{ generatedLink.short_url }}</code></div>
                                    <div class="mb-2"><strong>{{ t(`${ns}.labels.full_url`) }}</strong><code class="text-xs break-all">{{ generatedLink.full_url }}</code></div>
                                    <el-button size="small" @click="copyText(generatedLink.short_url)">{{ t(`${ns}.buttons.copy_short`) }}</el-button>
                                    <el-button size="small" @click="copyText(generatedLink.full_url)">{{ t(`${ns}.buttons.copy_full`) }}</el-button>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header>
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold">{{ t(`${ns}.links.agent_list_title`) }}</span>
                                        <div class="flex gap-2">
                                            <el-input v-model="linkAgentId" :placeholder="t(`${ns}.placeholders.agent_id`)" style="width:140px" />
                                            <el-button @click="loadAgentLinks">{{ t(`${ns}.buttons.query`) }}</el-button>
                                        </div>
                                    </div>
                                </template>
                                <el-table :data="agentLinks" v-loading="linkLoading" size="small" stripe>
                                    <el-table-column prop="invite_code" :label="t(`${ns}.cols.referral_code`)" width="120" />
                                    <el-table-column :label="t(`${ns}.cols.converted`)" width="70" align="center">
                                        <template #default="{ row }">
                                            <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ yesNoLabel(row.converted) }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="clicks" :label="t(`${ns}.cols.clicks`)" width="60" />
                                    <el-table-column :label="t(`${ns}.cols.product`)" width="70">
                                        <template #default="{ row }">{{ row.product_id || '-' }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t(`${ns}.cols.created_at`)" width="160">
                                        <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t(`${ns}.cols.actions`)" width="60">
                                        <template #default="{ row }">
                                            <el-button size="small" @click="copyText(row.landing_url)">{{ t('actions.copy') }}</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-if="!agentLinks.length && linkLoading === false" :description="t(`${ns}.empty.enter_agent_id_query`)" />
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- Tab 2: 推广门户 -->
                <el-tab-pane :label="t(`${ns}.tabs.portal`)" name="portal">
                    <div class="flex gap-2 mb-4">
                        <el-input v-model="portalAgentId" :placeholder="t(`${ns}.placeholders.portal_agent_id`)" style="width:260px" />
                        <el-button type="primary" :loading="portalLoading" @click="loadPortal">{{ t(`${ns}.buttons.view_portal`) }}</el-button>
                    </div>
                    <div v-if="portalData" v-loading="portalLoading">
                        <el-row :gutter="16" class="mb-4">
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">{{ t(`${ns}.stats.total_clicks`) }}</div>
                                        <div class="stat-value">{{ portalData.summary?.clicks_total ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">{{ t(`${ns}.stats.total_conversions`) }}</div>
                                        <div class="stat-value" style="color:#67C23A">{{ portalData.summary?.conversions_total ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">{{ t(`${ns}.stats.conversion_rate`) }}</div>
                                        <div class="stat-value" style="color:#0f172a">{{ portalData.summary?.conversion_rate ?? 0 }}%</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-item">
                                        <div class="stat-label">{{ t(`${ns}.stats.downline_count`) }}</div>
                                        <div class="stat-value">{{ portalData.summary?.downline_count ?? 0 }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 收益账户 -->
                        <el-card shadow="never" class="mb-4" v-if="portalData.earnings_account">
                            <template #header><span>{{ t(`${ns}.portal.earnings_account`) }}</span></template>
                            <el-descriptions :column="4" border size="small">
                                <el-descriptions-item :label="t(`${ns}.earnings.pending_balance`)">{{ formatMoney(portalData.earnings_account.pending_balance) }}</el-descriptions-item>
                                <el-descriptions-item :label="t(`${ns}.earnings.available_balance`)">{{ formatMoney(portalData.earnings_account.available_balance) }}</el-descriptions-item>
                                <el-descriptions-item :label="t(`${ns}.earnings.total_withdrawn`)">{{ formatMoney(portalData.earnings_account.total_withdrawn) }}</el-descriptions-item>
                                <el-descriptions-item :label="t(`${ns}.earnings.pending_commission`)">{{ formatMoney(portalData.pending_commission) }}</el-descriptions-item>
                            </el-descriptions>
                            <div class="mt-3">
                                <el-button type="primary" @click="$router.push('/admin/withdrawals')">
                                    {{ t(`${ns}.buttons.go_withdraw`, { amount: formatMoney(portalData.withdrawal?.available_balance) }) }}
                                </el-button>
                                <span class="text-xs text-gray-400 ml-2">{{ t(`${ns}.labels.min_withdrawal`) }} {{ formatMoney(portalData.withdrawal?.min_withdrawal) }}</span>
                            </div>
                        </el-card>

                        <!-- 最近转化 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t(`${ns}.portal.recent_conversions`) }}</span></template>
                            <el-table :data="portalData.recent_conversions ?? []" size="small" stripe>
                                <el-table-column :label="t(`${ns}.cols.customer`)" prop="user_name" />
                                <el-table-column :label="t(`${ns}.cols.commission`)" width="130">
                                    <template #default="{ row }">{{ formatMoney(row.commission) }}</template>
                                </el-table-column>
                                <el-table-column :label="t(`${ns}.cols.time`)" width="160">
                                    <template #default="{ row }">{{ fmtDate(row.converted_at) }}</template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-if="!portalData.recent_conversions?.length" :description="t(`${ns}.empty.no_conversions`)" />
                        </el-card>

                        <!-- 推广链接 -->
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${ns}.portal.referral_links`, { count: portalData.referral_links?.length ?? 0 }) }}</span></template>
                            <el-table :data="portalData.referral_links ?? []" size="small" stripe>
                                <el-table-column prop="invite_code" :label="t(`${ns}.cols.referral_code`)" width="120" />
                                <el-table-column :label="t(`${ns}.cols.converted`)" width="80" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ yesNoLabel(row.converted) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="clicks" :label="t(`${ns}.cols.click_count`)" width="70" />
                                <el-table-column :label="t(`${ns}.cols.landing_page`)" min-width="200">
                                    <template #default="{ row }">
                                        <span class="text-xs break-all">{{ row.landing_url }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </div>
                    <el-empty v-else-if="!portalLoading" :description="t(`${ns}.empty.enter_agent_id_portal`)" />
                </el-tab-pane>

                <!-- Tab 3: 收益账户结算 -->
                <el-tab-pane :label="t(`${ns}.tabs.settlement`)" name="settlement">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.settlement.settle_title`) }}</span></template>
                                <el-form :model="settleForm" label-width="120px">
                                    <el-form-item :label="t(`${ns}.cols.agent_id`)" required>
                                        <el-input-number v-model="settleForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.amount_yuan`)" required>
                                        <el-input-number v-model="settleForm.amount" :min="0.01" :precision="2" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.source`)">
                                        <el-select v-model="settleForm.source" style="width:100%">
                                            <el-option v-for="opt in sourceOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.notes`)">
                                        <el-input v-model="settleForm.notes" type="textarea" :rows="2" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="settling" @click="settleCommission">{{ t(`${ns}.buttons.settle`) }}</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="settleResult" class="p-3 bg-green-50 rounded mt-2">
                                    {{ t(`${ns}.settlement.success`, { id: settleResult.id, amount: formatMoney(settleResult.commission_amount) }) }}
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.settlement.attribute_title`) }}</span></template>
                                <el-form :model="attributeForm" label-width="120px">
                                    <el-form-item :label="t(`${ns}.cols.referral_code`)" required>
                                        <el-input v-model="attributeForm.referral_code" :placeholder="t(`${ns}.placeholders.referral_code`)" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.converted_user_id`)" required>
                                        <el-input-number v-model="attributeForm.converted_user_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.commission_amount`)">
                                        <el-input-number v-model="attributeForm.commission_amount" :min="0" :precision="2" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="attributing" @click="attributeConversion">{{ t(`${ns}.buttons.attribute_settle`) }}</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="attrResult" :class="['p-3 rounded mt-2', attrResult.attributed ? 'bg-green-50' : 'bg-yellow-50']">
                                    {{ attrResult.attributed ? t(`${ns}.attribute.success_attributed`) : t(`${ns}.attribute.no_click_record`) }}
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- Tab 4: 电商分销 (M2-149) -->
                <el-tab-pane :label="t(`${ns}.tabs.store`)" name="store">
                    <el-row :gutter="24">
                        <el-col :span="10">
                            <el-card shadow="never" class="mb-4">
                                <template #header><span class="font-semibold">{{ t(`${ns}.store.generate_product_link`) }}</span></template>
                                <el-form :model="productLinkForm" label-width="110px">
                                    <el-form-item :label="t(`${ns}.cols.agent_id`)" required>
                                        <el-input-number v-model="productLinkForm.agent_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.product_id`)" required>
                                        <el-input-number v-model="productLinkForm.product_id" :min="1" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item :label="t(`${ns}.cols.campaign_id`)">
                                        <el-input-number v-model="productLinkForm.campaign_id" :min="0" style="width:100%" />
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="productLinkLoading" @click="generateProductLink">{{ t(`${ns}.buttons.generate_product_link`) }}</el-button>
                                    </el-form-item>
                                </el-form>
                                <div v-if="productLinkResult" class="p-3 bg-green-50 rounded">
                                    <div><strong>{{ t(`${ns}.labels.referral_code`) }}</strong><code>{{ productLinkResult.referral_code }}</code></div>
                                    <div><strong>{{ t(`${ns}.labels.link`) }}</strong><code class="text-xs break-all">{{ productLinkResult.full_url }}</code></div>
                                    <el-button size="small" class="mt-2" @click="copyText(productLinkResult.full_url)">{{ t(`${ns}.buttons.copy_link`) }}</el-button>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="14">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="font-semibold">{{ t(`${ns}.store.dashboard_title`) }}</span>
                                    <el-button size="small" class="ml-2" @click="loadProductStats">{{ t(`${ns}.buttons.refresh`) }}</el-button>
                                </template>
                                <div v-if="storeDashData">
                                    <h4 class="mb-2">{{ t(`${ns}.store.product_stats_title`) }}</h4>
                                    <el-table :data="storeDashData.product_stats ?? []" size="small" stripe>
                                        <el-table-column prop="name" :label="t(`${ns}.cols.material_name`)" min-width="150" />
                                        <el-table-column prop="clicks" :label="t(`${ns}.cols.clicks`)" width="70" />
                                        <el-table-column prop="conversions" :label="t(`${ns}.cols.conversion_count`)" width="70" />
                                        <el-table-column prop="conversion_rate" :label="t(`${ns}.cols.conversion_rate`)" width="80">
                                            <template #default="{ row }">{{ row.conversion_rate }}%</template>
                                        </el-table-column>
                                        <el-table-column :label="t(`${ns}.cols.status`)" width="70">
                                            <template #default="{ row }">
                                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag>
                                            </template>
                                        </el-table-column>
                                    </el-table>

                                    <h4 class="mt-4 mb-2">{{ t(`${ns}.store.top_agents_title`) }}</h4>
                                    <el-table :data="storeDashData.top_agents ?? []" size="small" stripe>
                                        <el-table-column prop="agent_id" :label="t(`${ns}.cols.agent_id`)" width="100" />
                                        <el-table-column prop="conversions" :label="t(`${ns}.cols.conversion_count`)" width="80" />
                                        <el-table-column :label="t(`${ns}.cols.commission`)" width="130">
                                            <template #default="{ row }">{{ formatMoney(row.commission) }}</template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                                <el-empty v-else-if="!storeDashData" :description="t(`${ns}.empty.no_data_refresh`)" />
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<style scoped>
.stat-item { text-align: center; padding: 6px 0; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.text-xs { font-size: 12px; }
.break-all { word-break: break-all; }
</style>
