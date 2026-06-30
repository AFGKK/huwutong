<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/affiliate.js'

const loading = ref(true)
const summary = ref(null)
const activeCampaigns = ref([])
const downlineList = ref([])
const clickRecords = ref([])
const clickPagination = ref({ total: 0, current_page: 1, per_page: 20 })
const myAgentId = ref(null)

// 商品推广
const promotableSkus = ref([])
const skuLoading = ref(false)
const skuPagination = ref({ current_page: 1, last_page: 1, total: 0 })
const selectedCampaign = ref(null)
const generatingSkuId = ref(null)
const generatedLinks = ref([])
const showGeneratedLinks = ref(false)

const siteOrigin = window.location.origin
const typeLabels = { referral: '推荐返佣', commission: '佣金加成', reward: '奖励计划', rebate: '返现活动' }

// 个性化活动链接 (campaign_id → link)
const campaignLinks = ref({})
const loadingLinks = ref(false)

async function loadSummary() {
    loading.value = true
    try {
        const res = await api.dashboard()
    } catch (e) {}
    loading.value = false
}

async function loadAgentSummary(agentId) {
    try {
        const res = await api.agentSummary(agentId)
        summary.value = res.data.data
    } catch (e) {}
}

async function loadCampaigns() {
    try {
        const res = await api.campaigns({ status: 'active' })
        const d = res.data.data
        activeCampaigns.value = d?.data || d || []
        // 加载后异步获取个性化链接
        loadCampaignLinks()
    } catch (e) {}
}

async function loadCampaignLinks() {
    loadingLinks.value = true
    const links = {}
    for (const c of activeCampaigns.value) {
        try {
            const res = await api.myCampaignLink(c.id)
            if (res.data?.data?.campaign_link) {
                links[c.id] = res.data.data.campaign_link
            }
        } catch (e) {
            // 静默失败，使用默认链接
        }
    }
    campaignLinks.value = links
    loadingLinks.value = false
}

async function loadPromotableSkus(page = 1) {
    skuLoading.value = true
    try {
        const res = await api.promotableSkus({ page, per_page: 12 })
        const d = res.data.data
        promotableSkus.value = d?.data || d || []
        skuPagination.value = {
            current_page: d?.current_page || page,
            last_page: d?.last_page || 1,
            total: d?.total || 0,
        }
    } catch (e) {
        console.error(e)
    } finally {
        skuLoading.value = false
    }
}

async function generateSkuLink(skuId) {
    generatingSkuId.value = skuId
    try {
        const payload = { sku_ids: [skuId] }
        if (selectedCampaign.value) {
            payload.campaign_id = selectedCampaign.value
        }
        const res = await api.generateLink(payload)
        const links = res.data.data || []
        generatedLinks.value = links
        if (links.length) {
            showGeneratedLinks.value = true
            ElMessage.success('商品推广链接已生成')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '生成链接失败')
    } finally {
        generatingSkuId.value = null
    }
}

function copyText(text) {
    navigator.clipboard?.writeText(text)
    ElMessage.success('已复制')
}

async function loadDownline() {
    if (!myAgentId.value) return
    try {
        const res = await api.downline(myAgentId.value)
        downlineList.value = res.data.data || []
    } catch (e) {}
}

async function loadClicks(page = 1) {
    if (!myAgentId.value) return
    try {
        const res = await api.clickLogs({ agent_id: myAgentId.value, page, per_page: 20 })
        const d = res.data.data
        clickRecords.value = d?.data || d || []
        clickPagination.value = {
            total: d?.total || 0,
            current_page: d?.current_page || page,
            per_page: d?.per_page || 20,
        }
    } catch (e) {}
}

function copyLink(slug, cid) {
    const link = campaignLinks.value[cid] || `${siteOrigin}/ref/${slug}`
    navigator.clipboard?.writeText(link)
    ElMessage.success('已复制')
}
function formatMoney(v) { return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00' }
function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

onMounted(async () => {
    loading.value = false
    loadCampaigns()
    loadPromotableSkus()
})
</script>

<template>
    <div>
        <div class="mb-4">
            <h1 class="text-xl font-semibold">联盟推广</h1>
            <p class="text-gray-500 text-sm mt-1">参与平台推广活动，邀请客户获取佣金奖励。</p>
        </div>

        <div v-loading="loading">
            <!-- 代理推广总览 -->
            <el-row :gutter="16" class="mb-5" v-if="summary">
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">总点击</div>
                        <div class="stat-value">{{ summary.total_clicks || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">转化次数</div>
                        <div class="stat-value text-success">{{ summary.total_conversions || 0 }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">推广佣金</div>
                        <div class="stat-value text-primary">{{ formatMoney(summary.total_commission) }}</div>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="never" class="stat-card">
                        <div class="stat-label">下级团队</div>
                        <div class="stat-value">{{ summary.downline_count || 0 }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 推广提示卡 -->
            <el-alert title="推广方式" type="info" :closable="false" class="mb-5">
                <template #default>
                    <ol class="ml-4 mt-1 text-sm">
                        <li>1. 选择下方活跃活动，复制您的专属推广链接</li>
                        <li>2. 分享链接给客户，客户点击后系统自动记录</li>
                        <li>3. 客户完成注册/购买后，您将获得佣金</li>
                    </ol>
                </template>
            </el-alert>

            <!-- 活跃活动 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">推广活动</span></template>
                <el-empty v-if="!activeCampaigns.length" description="暂无进行中的推广活动" />
                <el-row :gutter="16" v-else>
                    <el-col :span="8" v-for="c in activeCampaigns" :key="c.id" class="mb-4">
                        <el-card shadow="hover" class="h-full">
                            <div class="font-semibold text-base">{{ c.name }}</div>
                            <div class="text-sm text-gray-500 mt-1">{{ c.description || typeLabels[c.type] || c.type }}</div>
                            <div class="mt-3 text-sm">
                                <div>首单奖励: <strong>{{ formatMoney(c.reward_first) }}</strong></div>
                                <div>续费奖励: <strong>{{ formatMoney(c.reward_renewal) }}</strong></div>
                            </div>
                            <div class="mt-3">
                                <el-tag size="small" class="mr-1">{{ typeLabels[c.type] || c.type }}</el-tag>
                                <el-tag v-if="c.ends_at" size="small" type="warning">
                                    截止 {{ new Date(c.ends_at).toLocaleDateString('zh-CN') }}
                                </el-tag>
                            </div>
                            <div class="mt-2" v-if="c.budget_total">
                                <el-progress :percentage="Math.min((c.budget_used || 0) / c.budget_total * 100, 100)"
                                    :stroke-width="6" :status="(c.budget_used || 0) >= c.budget_total ? 'exception' : ''" />
                                <div class="text-xs text-gray-400 mt-1">
                                    预算 {{ formatMoney(c.budget_used || 0) }} / {{ formatMoney(c.budget_total) }}
                                </div>
                            </div>
                            <div class="mt-3">
                                <el-input :model-value="campaignLinks[c.id] || `${siteOrigin}/ref/${c.slug}`" readonly size="small">
                                    <template #append>
                                        <el-button size="small" :loading="loadingLinks" @click="copyLink(c.slug, c.id)">复制</el-button>
                                    </template>
                                </el-input>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-card>

            <!-- 商品推广 -->
            <el-card shadow="never" class="mb-5">
                <template #header>
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">商品推广</span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400">选择活动(可选):</span>
                            <el-select v-model="selectedCampaign" placeholder="不限活动" clearable style="width:140px" size="small">
                                <el-option v-for="c in activeCampaigns" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </div>
                    </div>
                </template>
                <div v-loading="skuLoading">
                    <el-empty v-if="!promotableSkus.length && !skuLoading" description="暂无可推广商品" />
                    <el-table v-else :data="promotableSkus" stripe size="small">
                        <el-table-column label="商品" min-width="160">
                            <template #default="{ row }">
                                <div class="flex items-center gap-2">
                                    <img v-if="row.image_url" :src="row.image_url" class="sku-img" />
                                    <div>
                                        <div class="text-sm font-medium">{{ row.name }}</div>
                                        <div class="text-xs text-gray-400">{{ row.product_name }}</div>
                                    </div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="price" label="价格" width="100" align="right">
                            <template #default="{ row }">{{ formatMoney(row.price) }}</template>
                        </el-table-column>
                        <el-table-column label="佣金" width="120" align="right">
                            <template #default="{ row }">
                                <div class="text-sm">
                                    <span class="text-success font-semibold">{{ formatMoney(row.commission_amount) }}</span>
                                    <span class="text-xs text-gray-400 ml-1">({{ row.commission_rate }}%)</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120" align="center">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" :loading="generatingSkuId === row.id"
                                    @click="generateSkuLink(row.id)">
                                    生成链接
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3" v-if="skuPagination.last_page > 1">
                        <el-pagination v-model:current-page="skuPagination.current_page"
                            :page-count="skuPagination.last_page" layout="prev, pager, next"
                            @current-change="loadPromotableSkus" size="small" />
                    </div>
                </div>
            </el-card>

            <!-- 生成的链接弹窗 -->
            <el-dialog v-model="showGeneratedLinks" title="商品推广链接" width="550px">
                <div v-for="(link, i) in generatedLinks" :key="i" class="mb-3 p-3 rounded bg-gray-50">
                    <div class="text-sm font-medium mb-1">{{ link.product_name }} - {{ link.sku_name }}</div>
                    <div class="flex gap-2">
                        <el-input :model-value="link.link" readonly size="small" class="flex-1" />
                        <el-button size="small" @click="copyText(link.link)">复制</el-button>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        推广码: {{ link.referral_code }} | 佣金率: {{ link.commission_rate }}% | 佣金: {{ formatMoney(link.commission_amount) }}
                    </div>
                </div>
                <template #footer>
                    <el-button @click="showGeneratedLinks = false">关闭</el-button>
                </template>
            </el-dialog>

            <!-- 点击记录 -->
            <el-card shadow="never" class="mb-5">
                <template #header><span class="font-semibold">点击记录</span></template>
                <el-table :data="clickRecords" stripe v-if="clickRecords.length">
                    <el-table-column prop="id" label="ID" width="70" />
                    <el-table-column label="活动" width="140">
                        <template #default="{ row }">{{ row.campaign?.name || row.campaign_id }}</template>
                    </el-table-column>
                    <el-table-column prop="referral_code" label="推广码" width="110" />
                    <el-table-column label="转化" width="70" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ row.converted ? '是' : '否' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="commission_amount" label="佣金" width="100" align="right">
                        <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="时间" width="160">
                        <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="暂无点击记录，开始推广吧！" />
            </el-card>
        </div>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-primary { color: #409eff; }
.h-full { height: 100%; }
.sku-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.flex-1 { flex: 1; }
</style>
