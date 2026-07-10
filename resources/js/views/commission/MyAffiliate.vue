<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Picture } from '@element-plus/icons-vue'
import api from '../../api/affiliate.js'

const loading = ref(false)
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
const skuSearch = ref('')            // 搜索关键词
const selectedSkuIds = ref([])       // 多选
const categories = ref([])           // 分类列表
const activeCategory = ref('')       // 当前选中分类
const productLinks = ref({})         // sku_id → generated link
const skuActiveFormat = ref({})      // sku_id → current format tab

// 推广样式选项（类似阿里妈妈联盟）
const promoFormats = [
    { key: 'text', icon: '🔗', label: '文字链' },
    { key: 'image', icon: '🖼️', label: '图片推广' },
    { key: 'copy', icon: '📝', label: '推广文案' },
    { key: 'qrcode', icon: '📱', label: '二维码' },
]

const siteOrigin = window.location.origin
const typeLabels = { referral: '推荐返佣', commission: '佣金加成', reward: '奖励计划', rebate: '返现活动' }

// 个性化活动链接 (campaign_id → link)
const campaignLinks = ref({})
const loadingLinks = ref(false)

// 推广素材
const campaignCreatives = ref({})  // campaign_id → creatives[]
const loadingCreatives = ref({})    // campaign_id → boolean
const showCreativeDialog = ref(false)
const currentCreativeCampaign = ref(null)

// 素材推广链接生成
const generatingCreativeId = ref(null)
const creativeLinks = ref({})        // creative_id → link

// 关系链
const uplineList = ref([])
const loadingDownline = ref(false)
const loadingUpline = ref(false)

// 我的素材
const myCreativesList = ref([])
const myCreativeLoading = ref(false)
const submittingCreative = ref(false)
const submitForm = reactive({
    campaign_id: null, type: 'banner', name: '', url: '', content: '', image_url: '',
})

async function loadAgentSummary(agentId) {
    try {
        const res = await api.agentSummary(agentId)
        summary.value = res.data.data
        // 加载点击记录
        loadClicks()
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

async function loadMyCreatives() {
    myCreativeLoading.value = true
    try {
        const res = await api.myCreatives()
        const d = res.data?.data || res.data
        myCreativesList.value = d?.data || d || []
    } catch (e) {
        myCreativesList.value = []
    } finally {
        myCreativeLoading.value = false
    }
}

async function handleSubmitCreative() {
    if (!submitForm.campaign_id) { ElMessage.warning('请选择推广活动'); return }
    if (!submitForm.name.trim()) { ElMessage.warning('请输入素材名称'); return }
    submittingCreative.value = true
    try {
        await api.submitCreative({ ...submitForm })
        ElMessage.success('✅ 素材已提交，等待审核')
        // 重置表单
        submitForm.campaign_id = null
        submitForm.type = 'banner'
        submitForm.name = ''
        submitForm.url = ''
        submitForm.content = ''
        submitForm.image_url = ''
        // 刷新列表
        loadMyCreatives()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败')
    } finally {
        submittingCreative.value = false
    }
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
        const params = { page, per_page: 12 }
        if (skuSearch.value) params.search = skuSearch.value
        if (activeCategory.value) params.category_id = activeCategory.value
        const res = await api.promotableSkus(params)
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

async function loadCategories() {
    try {
        const res = await api.categories?.() || { data: { data: [] } }
        // 从商品SKU数据中提取分类
        const cats = new Map()
        for (const sku of promotableSkus.value) {
            if (sku.category_id && sku.category_name) {
                cats.set(sku.category_id, sku.category_name)
            }
        }
        categories.value = Array.from(cats.entries()).map(([id, name]) => ({ id, name }))
    } catch (e) {}
}

/** 搜索去抖 */
let searchTimer
function onSearchInput() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => loadPromotableSkus(1), 400)
}

/** 批量生成链接 */
async function batchGenerateLinks() {
    if (!selectedSkuIds.value.length) return
    generatingSkuId.value = 'batch'
    try {
        const payload = { sku_ids: selectedSkuIds.value }
        if (selectedCampaign.value) payload.campaign_id = selectedCampaign.value
        const res = await api.generateLink(payload)
        const links = res.data.data || []
        generatedLinks.value = links
        if (links.length) {
            showGeneratedLinks.value = true
            selectedSkuIds.value = []
            ElMessage.success(`已生成 ${links.length} 个推广链接`)
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '批量生成失败')
    } finally {
        generatingSkuId.value = null
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
        if (links.length) {
            productLinks.value[skuId] = links[0].link
            // 默认选中文字链样式
            if (!skuActiveFormat.value[skuId]) {
                skuActiveFormat.value[skuId] = 'text'
            }
            ElMessage.success('推广链接已生成')
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

/** 复制商品HTML推广代码 */
function copySkuHtml(sku) {
    const link = productLinks.value[sku.id]
    if (!link) { ElMessage.warning('请先生成链接'); return }
    const html = `<a href="${link}" target="_blank">${sku.name}</a>`
    navigator.clipboard?.writeText(html)
    ElMessage.success('HTML代码已复制')
}

/** 复制推广文案 */
function copySkuCopy(sku) {
    const link = productLinks.value[sku.id]
    if (!link) { ElMessage.warning('请先生成链接'); return }
    const text = `【推荐】${sku.name}\n原价: ${formatMoney(sku.price)}\n${link}`
    navigator.clipboard?.writeText(text)
    ElMessage.success('推广文案已复制')
}

async function loadDownline() {
    if (!myAgentId.value) return
    loadingDownline.value = true
    try {
        const res = await api.downline(myAgentId.value)
        downlineList.value = res.data.data || []
    } catch (e) {} finally {
        loadingDownline.value = false
    }
}

async function loadUpline() {
    if (!myAgentId.value) return
    loadingUpline.value = true
    try {
        const res = await api.upline(myAgentId.value)
        uplineList.value = res.data.data || []
    } catch (e) {} finally {
        loadingUpline.value = false
    }
}

/** 获取当前用户的推广员信息 */
async function loadMyAgent() {
    try {
        const res = await api.myAgent()
        if (res.data?.data?.id) {
            myAgentId.value = res.data.data.id
            await Promise.all([loadDownline(), loadUpline(), loadAgentSummary(myAgentId.value)])
        }
    } catch (e) {}
}

async function loadClicks(page = 1) {
    if (!myAgentId.value) return
    try {
        const res = await api.clickLogs({ agent_id: myAgentId.value, page, per_page: 20 })
        const meta = res.data?.meta || {}
        clickRecords.value = res.data?.data || []
        clickPagination.value = {
            total: meta.total || 0,
            current_page: meta.current_page || page,
            per_page: meta.per_page || 20,
            last_page: meta.last_page || 1,
        }
    } catch (e) {}
}

function copyLink(slug, cid) {
    const link = campaignLinks.value[cid] || `${siteOrigin}/ref/${slug}`
    navigator.clipboard?.writeText(link)
    ElMessage.success('已复制')
}

// ─── 推广素材 ───

/** 加载某个活动的推广素材 */
async function loadCreatives(campaign) {
    const cid = campaign.id
    loadingCreatives.value[cid] = true
    try {
        const res = await api.creatives(cid)
        const list = res.data.data || []
        campaignCreatives.value[cid] = list.filter(c => c.is_active !== false)
    } catch (e) {
        campaignCreatives.value[cid] = []
    } finally {
        loadingCreatives.value[cid] = false
    }
}

/** 打开素材弹窗 */
async function openCreatives(campaign) {
    currentCreativeCampaign.value = campaign
    await loadCreatives(campaign)
    showCreativeDialog.value = true
}

/** 生成素材推广链接 */
async function generateCreativeLink(creative) {
    generatingCreativeId.value = creative.id
    const cid = currentCreativeCampaign.value.id

    // 获取推广码
    let referralCode = ''
    try {
        const res = await api.myCampaignLink(cid)
        if (res.data?.data?.referral_code) {
            referralCode = res.data.data.referral_code
        }
        if (res.data?.data?.campaign_link) {
            campaignLinks.value[cid] = res.data.data.campaign_link
        }
    } catch (e) {}

    // 如果素材有自定义推广链接 URL，使用追踪中间页 /go/{id}
    if (creative.url) {
        creativeLinks.value[creative.id] = `${siteOrigin}/go/${creative.id}?ref=${referralCode}`
    } else {
        // 没有自定义 URL 时使用活动落地页
        creativeLinks.value[creative.id] = campaignLinks.value[cid] ||
            `${siteOrigin}/ref/${currentCreativeCampaign.value.slug}`
    }
    generatingCreativeId.value = null
    ElMessage.success('素材推广链接已生成')
}

/** 复制素材链接 */
function copyCreativeLink(creativeId) {
    const link = creativeLinks.value[creativeId]
    if (link) {
        navigator.clipboard?.writeText(link)
        ElMessage.success('已复制')
    } else {
        ElMessage.warning('请先生成链接')
    }
}

const creativeTypeLabels = {
    banner: '横幅',
    landing_page: '落地页',
    link: '推广链接',
    coupon: '优惠券',
    qr_code: '二维码',
    image: '图片',
    text: '文案',
    video: '视频',
}

// 当前激活的 Tab
const activeTab = ref('campaigns')

// 素材弹窗 - 类型筛选
const creativeTab = ref('all')

/** 按类型统计素材数量 */
function countByType(type) {
    const cid = currentCreativeCampaign.value?.id
    if (!cid) return 0
    return (campaignCreatives.value[cid] || []).filter(c => c.type === type).length
}

/** 按类型过滤素材 */
function filteredCreatives(tab) {
    const cid = currentCreativeCampaign.value?.id
    if (!cid) return []
    const all = campaignCreatives.value[cid] || []
    if (tab === 'all') return all
    return all.filter(c => c.type === tab)
}
function formatMoney(v) { return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00' }
function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

onMounted(async () => {
    loadCampaigns()
    loadPromotableSkus()
    loadMyAgent()
    loadMyCreatives()
})
</script>

<template>
    <div class="affiliate-page">
        <!-- 页头 + 统计卡片紧凑一行 -->
        <div class="header-row mb-3">
            <div class="header-title">
                <h1 class="text-xl font-semibold">联盟推广</h1>
                <p class="text-gray-500 text-xs">推广活动 · 商品推广 · 数据统计</p>
            </div>
            <div class="header-stats" v-if="summary">
                <div class="mini-stat">
                    <span class="mini-num">{{ summary.total_clicks || 0 }}</span>
                    <span class="mini-label">点击</span>
                </div>
                <div class="mini-stat">
                    <span class="mini-num text-success">{{ summary.total_conversions || 0 }}</span>
                    <span class="mini-label">转化</span>
                </div>
                <div class="mini-stat">
                    <span class="mini-num text-primary">{{ formatMoney(summary.total_commission) }}</span>
                    <span class="mini-label">佣金</span>
                </div>
                <div class="mini-stat">
                    <span class="mini-num">{{ summary.downline_count || 0 }}</span>
                    <span class="mini-label">团队</span>
                </div>
            </div>
        </div>

        <div v-loading="loading">
            <!-- 主内容 Tab 切换 -->
            <el-tabs v-model="activeTab" type="border-card" class="main-tabs">
                <!-- Tab 1: 推广活动 -->
                <el-tab-pane label="📢 推广活动" name="campaigns">
                    <!-- 推广提示 -->
                    <el-alert title="复制专属链接 → 分享给客户 → 获得佣金" type="info" :closable="true" show-icon class="mb-3" />

                    <el-empty v-if="!activeCampaigns.length" description="暂无进行中的推广活动" />
                    <el-row :gutter="12" v-else>
                        <el-col :span="8" v-for="c in activeCampaigns" :key="c.id" class="mb-3">
                            <el-card shadow="hover" class="h-full campaign-card">
                                <div class="flex items-start justify-between">
                                    <div class="font-semibold text-base">{{ c.name }}</div>
                                    <el-tag size="small" type="success">{{ typeLabels[c.type] || c.type }}</el-tag>
                                </div>
                                <div class="text-xs text-gray-400 mt-1">{{ c.description || '' }}</div>
                                <div class="mt-2 flex gap-3 text-sm">
                                    <span>首单 <strong class="text-amber-500">{{ formatMoney(c.reward_first) }}</strong></span>
                                    <span>续费 <strong class="text-amber-500">{{ formatMoney(c.reward_renewal) }}</strong></span>
                                </div>
                                <div class="mt-2" v-if="c.budget_total">
                                    <el-progress :percentage="Math.min((c.budget_used || 0) / c.budget_total * 100, 100)"
                                        :stroke-width="4" :status="(c.budget_used || 0) >= c.budget_total ? 'exception' : ''" />
                                    <div class="text-xs text-gray-400 mt-1">预算 {{ formatMoney(c.budget_used || 0) }} / {{ formatMoney(c.budget_total) }}</div>
                                </div>
                                <el-tag v-if="c.ends_at" size="small" type="warning" class="mt-2">截止 {{ new Date(c.ends_at).toLocaleDateString('zh-CN') }}</el-tag>
                                <div class="mt-2">
                                    <el-input :model-value="campaignLinks[c.id] || `${siteOrigin}/ref/${c.slug}`" readonly size="small">
                                        <template #append>
                                            <el-button size="small" :loading="loadingLinks" @click="copyLink(c.slug, c.id)">复制</el-button>
                                        </template>
                                    </el-input>
                                </div>
                                <div class="mt-2 flex gap-2">
                                    <el-button size="small" type="success" plain @click="openCreatives(c)">
                                        <el-icon style="vertical-align:-2px;margin-right:2px"><Picture /></el-icon>素材
                                    </el-button>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- Tab 2: 商品推广 -->
                <el-tab-pane label="🛒 商品推广" name="products">
                    <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <el-input v-model="skuSearch" placeholder="搜索商品" size="small" style="width:140px" clearable @input="onSearchInput" />
                            <el-select v-model="activeCategory" placeholder="分类" clearable size="small" style="width:120px" @change="loadPromotableSkus(1)">
                                <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                            </el-select>
                            <el-select v-model="selectedCampaign" placeholder="活动" clearable size="small" style="width:130px">
                                <el-option v-for="c in activeCampaigns" :key="c.id" :label="c.name" :value="c.id" />
                            </el-select>
                        </div>
                    </div>
                    <div v-loading="skuLoading">
                        <el-empty v-if="!promotableSkus.length && !skuLoading" description="暂无可推广商品" />
                        <div v-else class="product-grid">
                            <div v-for="sku in promotableSkus" :key="sku.id" class="product-card">
                                <!-- 商品信息 -->
                                <div class="product-header">
                                    <img v-if="sku.image_url" :src="sku.image_url" class="product-img" />
                                    <div class="product-info">
                                        <div class="product-name">{{ sku.name }}</div>
                                        <div class="product-meta">{{ sku.product_name }}</div>
                                        <el-tag v-if="sku.category_name" size="small" type="info">{{ sku.category_name }}</el-tag>
                                    </div>
                                </div>
                                <div class="product-price-row">
                                    <span class="product-price">{{ formatMoney(sku.price) }}</span>
                                    <span class="product-commission">佣金 <strong class="text-success">{{ formatMoney(sku.commission_amount) }}</strong> ({{ sku.commission_rate }}%)</span>
                                </div>
                                <!-- 推广样式选择 -->
                                <div class="promo-formats">
                                    <div class="format-tabs">
                                        <span v-for="fmt in promoFormats" :key="fmt.key"
                                            :class="['format-tab', { active: skuActiveFormat[sku.id] === fmt.key }]"
                                            @click="skuActiveFormat[sku.id] = fmt.key">
                                            {{ fmt.icon }} {{ fmt.label }}
                                        </span>
                                    </div>
                                    <div class="format-content">
                                        <!-- 文字链 -->
                                        <div v-if="skuActiveFormat[sku.id] === 'text'">
                                            <div class="format-result">{{ productLinks[sku.id] || (siteOrigin + '/ref/' + (activeCampaigns[0]?.slug || '') + '?sku=' + sku.id) }}</div>
                                            <div class="format-actions">
                                                <el-button size="small" type="primary" :loading="generatingSkuId === sku.id" @click="generateSkuLink(sku.id)">生成链接</el-button>
                                                <el-button size="small" v-if="productLinks[sku.id]" @click="copyText(productLinks[sku.id])">复制链接</el-button>
                                                <el-button size="small" v-if="productLinks[sku.id]" @click="copySkuHtml(sku)">复制HTML</el-button>
                                            </div>
                                        </div>
                                        <!-- 图片推广 -->
                                        <div v-if="skuActiveFormat[sku.id] === 'image'">
                                            <div class="format-img-preview" v-if="sku.image_url">
                                                <img :src="sku.image_url" class="preview-img" />
                                                <div class="preview-overlay">点击预览</div>
                                            </div>
                                            <div class="format-actions">
                                                <el-button size="small" type="primary" :loading="generatingSkuId === sku.id" @click="generateSkuLink(sku.id)">生成推广图片</el-button>
                                                <el-button size="small" v-if="productLinks[sku.id]" @click="copyText(productLinks[sku.id])">复制链接</el-button>
                                                <el-button size="small" v-if="sku.image_url" @click="copyText(sku.image_url)">复制图片地址</el-button>
                                            </div>
                                        </div>
                                        <!-- 推广文案 -->
                                        <div v-if="skuActiveFormat[sku.id] === 'copy'">
                                            <el-input type="textarea" :rows="3" :model-value="'【推荐】' + sku.name + '\n原价: ' + formatMoney(sku.price) + '\n' + (productLinks[sku.id] || '请先生成链接')" readonly size="small" />
                                            <div class="format-actions mt-2">
                                                <el-button size="small" type="primary" :loading="generatingSkuId === sku.id" @click="generateSkuLink(sku.id)">生成链接</el-button>
                                                <el-button size="small" v-if="productLinks[sku.id]" @click="copySkuCopy(sku)">复制文案</el-button>
                                            </div>
                                        </div>
                                        <!-- 二维码 -->
                                        <div v-if="skuActiveFormat[sku.id] === 'qrcode'">
                                            <div class="format-qr-placeholder" v-if="!productLinks[sku.id]">
                                                <el-button size="small" type="primary" :loading="generatingSkuId === sku.id" @click="generateSkuLink(sku.id)">先生成链接</el-button>
                                            </div>
                                            <div v-else class="format-qr-area">
                                                <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' + encodeURIComponent(productLinks[sku.id])" class="qr-img" />
                                                <el-button size="small" @click="copyText(productLinks[sku.id])">复制链接</el-button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-center mt-3" v-if="skuPagination.last_page > 1">
                            <el-pagination v-model:current-page="skuPagination.current_page"
                                :page-count="skuPagination.last_page" layout="prev, pager, next"
                                @current-change="loadPromotableSkus" size="small" />
                        </div>
                    </div>
                </el-tab-pane>

                <!-- Tab 3: 推广数据 -->
                <el-tab-pane label="📊 推广数据" name="data">
                    <el-row :gutter="16" class="mb-3">
                        <el-col :span="8">
                            <el-card shadow="never" class="stat-card-sm">
                                <div class="stat-label">总点击</div>
                                <div class="stat-val">{{ clickPagination.total }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never" class="stat-card-sm">
                                <div class="stat-label">转化次数</div>
                                <div class="stat-val text-success">{{ clickRecords.filter(r => r.converted).length }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never" class="stat-card-sm">
                                <div class="stat-label">累计佣金</div>
                                <div class="stat-val text-primary">{{ formatMoney(clickRecords.reduce((s, r) => s + (r.commission_amount || 0), 0)) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <el-table :data="clickRecords" stripe size="small" v-if="clickRecords.length">
                        <el-table-column prop="id" label="#" width="50" />
                        <el-table-column label="活动" width="110">
                            <template #default="{ row }">{{ row.campaign?.name || row.campaign_id }}</template>
                        </el-table-column>
                        <el-table-column label="计费" width="75">
                            <template #default="{ row }">
                                <el-tag size="small" type="info">{{ (row.campaign?.billing_mode || 'cpa').toUpperCase() }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="费用" width="80" align="right">
                            <template #default="{ row }">
                                <span v-if="row.campaign?.billing_mode === 'cpc'">{{ formatMoney(row.campaign?.cost_per_click || 0) }}/次</span>
                                <span v-else-if="row.campaign?.billing_mode === 'cpm'">{{ formatMoney(row.campaign?.cost_per_impression || 0) }}/千次</span>
                                <span v-else>{{ formatMoney(row.commission_amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="referral_code" label="推广码" width="95" />
                        <el-table-column label="转化" width="60" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ row.converted ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="commission_amount" label="佣金" width="85" align="right">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="时间" width="140">
                            <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无点击记录，开始推广吧！" :image-size="60" />
                    <div class="flex justify-center mt-3" v-if="clickPagination.last_page > 1">
                        <el-pagination v-model:current-page="clickPagination.current_page"
                            :page-count="clickPagination.last_page" layout="prev, pager, next"
                            @current-change="loadClicks" size="small" />
                    </div>
                </el-tab-pane>

                <!-- Tab 5: 我的素材 -->
                <el-tab-pane label="📦 我的素材" name="mycreatives">
                    <div v-loading="myCreativeLoading">
                        <!-- 提交新素材 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header><span class="font-semibold">📤 提交新素材</span></template>
                            <el-form :model="submitForm" label-width="80px" size="small">
                                <el-row :gutter="12">
                                    <el-col :span="12">
                                        <el-form-item label="推广活动">
                                            <el-select v-model="submitForm.campaign_id" placeholder="选择活动" style="width:100%">
                                                <el-option v-for="c in activeCampaigns" :key="c.id" :label="c.name" :value="c.id" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item label="素材类型">
                                            <el-select v-model="submitForm.type" style="width:100%">
                                                <el-option label="横幅" value="banner" />
                                                <el-option label="图片" value="image" />
                                                <el-option label="文案" value="text" />
                                                <el-option label="推广链接" value="link" />
                                                <el-option label="优惠券" value="coupon" />
                                                <el-option label="二维码" value="qr_code" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-form-item label="素材名称">
                                    <el-input v-model="submitForm.name" placeholder="输入素材名称" />
                                </el-form-item>
                                <el-form-item label="推广链接">
                                    <el-input v-model="submitForm.url" placeholder="推广目标URL（选填）" />
                                </el-form-item>
                                <el-form-item label="推广文案">
                                    <el-input v-model="submitForm.content" type="textarea" :rows="2" placeholder="素材描述/推广文案（选填）" />
                                </el-form-item>
                                <el-form-item label="图片地址">
                                    <el-input v-model="submitForm.image_url" placeholder="图片URL（选填）" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" :loading="submittingCreative" @click="handleSubmitCreative">📤 提交审核</el-button>
                                    <span style="font-size:12px;color:#909399;margin-left:8px">提交后需管理员审核通过方可使用</span>
                                </el-form-item>
                            </el-form>
                        </el-card>

                        <!-- 已提交素材列表 -->
                        <el-card shadow="never">
                            <template #header><span class="font-semibold">📋 我的提交记录</span></template>
                            <el-empty v-if="!myCreativesList.length" description="暂无提交记录" :image-size="50" />
                            <el-table v-else :data="myCreativesList" stripe size="small">
                                <el-table-column prop="name" label="素材名称" min-width="120" />
                                <el-table-column label="类型" width="80">
                                    <template #default="{ row }">{{ row.type }}</template>
                                </el-table-column>
                                <el-table-column label="所属活动" min-width="140">
                                    <template #default="{ row }">{{ row.campaign?.name || '-' }}</template>
                                </el-table-column>
                                <el-table-column label="审核状态" width="100" align="center">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.status === 'pending'" size="small" type="warning">待审核</el-tag>
                                        <el-tag v-else-if="row.status === 'rejected'" size="small" type="danger">已驳回</el-tag>
                                        <el-tag v-else size="small" type="success">已通过</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="驳回原因" width="140">
                                    <template #default="{ row }"><span class="text-xs text-red-500">{{ row.review_notes || '-' }}</span></template>
                                </el-table-column>
                                <el-table-column label="提交时间" width="150">
                                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </div>
                </el-tab-pane>

                <!-- Tab 4: 关系链 -->
                <el-tab-pane label="🔗 关系链" name="tree" :disabled="!myAgentId">
                    <el-row :gutter="16" v-if="myAgentId">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">⬆ 上级推广员</span></template>
                                <div v-loading="loadingUpline">
                                    <div v-if="uplineList.length" class="space-y-2">
                                        <div v-for="(agent, i) in uplineList" :key="i"
                                            class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                                            <el-tag size="small" round>L{{ agent.level || (i + 1) }}</el-tag>
                                            <span class="text-sm">#{{ agent.id }} {{ agent.agent_code || agent.name || '-' }}</span>
                                            <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                        </div>
                                    </div>
                                    <el-empty v-else description="暂无上级，您是顶级推广员" :image-size="50" />
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="font-semibold">⬇ 下级团队</span>
                                    <el-tag size="small" type="success" class="ml-2">{{ downlineList.length }} 人</el-tag>
                                </template>
                                <div v-loading="loadingDownline">
                                    <div v-if="downlineList.length" class="space-y-2">
                                        <div v-for="(agent, i) in downlineList" :key="i"
                                            class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                                            <el-tag size="small" round>L{{ agent.level || 1 }}</el-tag>
                                            <span class="text-sm">#{{ agent.id }} {{ agent.agent_code || agent.name || '-' }}</span>
                                            <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                        </div>
                                    </div>
                                    <el-empty v-else description="暂无下级，开始推广吧！" :image-size="50" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>
            </el-tabs>

            <!-- 推广素材弹窗（按类型分组Tab） -->
            <el-dialog v-model="showCreativeDialog" :title="`推广素材 - ${currentCreativeCampaign?.name || ''}`" width="700px">
                <div v-loading="currentCreativeCampaign && loadingCreatives[currentCreativeCampaign.id]">
                    <template v-if="currentCreativeCampaign && campaignCreatives[currentCreativeCampaign.id]?.length">
                        <!-- 类型分类 Tab -->
                        <div class="creative-type-tabs">
                            <span :class="['type-tab', { active: creativeTab === 'all' }]" @click="creativeTab = 'all'">全部 ({{ campaignCreatives[currentCreativeCampaign.id].length }})</span>
                            <span v-for="(label, type) in creativeTypeLabels" :key="type"
                                :class="['type-tab', { active: creativeTab === type }]"
                                @click="creativeTab = type">
                                {{ label }} ({{ countByType(type) }})
                            </span>
                        </div>
                        <!-- 素材网格 -->
                        <div class="creative-grid">
                            <div v-for="c in filteredCreatives(creativeTab)" :key="c.id" class="creative-item">
                                <div v-if="c.type === 'image' || c.type === 'banner'" class="creative-preview">
                                    <el-image v-if="c.image_url" :src="c.image_url" :alt="c.name" class="w-full rounded" style="max-height:130px;object-fit:cover"
                                        fit="cover" :preview-src-list="[c.image_url]" preview-teleported />
                                    <div v-else class="no-img">暂无图片</div>
                                </div>
                                <div v-else-if="c.type === 'qr_code' && c.image_url" class="creative-preview">
                                    <el-image :src="c.image_url" :alt="c.name" class="w-full rounded" style="max-height:130px;object-fit:contain"
                                        fit="contain" :preview-src-list="[c.image_url]" preview-teleported />
                                </div>
                                <div v-else class="creative-type-icon">{{ creativeTypeLabels[c.type] || c.type }}</div>
                                <div class="creative-name">{{ c.name }}</div>
                                <div v-if="c.content" class="creative-desc">{{ c.content.substring(0, 60) }}{{ c.content.length > 60 ? '...' : '' }}</div>
                                <div class="creative-actions">
                                    <el-button size="small" type="primary" plain :loading="generatingCreativeId === c.id" @click="generateCreativeLink(c)">生成链接</el-button>
                                    <el-button v-if="creativeLinks[c.id]" size="small" type="success" @click="copyCreativeLink(c.id)">复制链接</el-button>
                                    <el-button v-if="c.image_url" size="small" @click="copyText(c.image_url)">复制图片</el-button>
                                </div>
                                <div v-if="creativeLinks[c.id]" class="mt-1">
                                    <el-input :model-value="creativeLinks[c.id]" readonly size="small">
                                        <template #append><el-button size="small" @click="copyCreativeLink(c.id)">复制</el-button></template>
                                    </el-input>
                                </div>
                            </div>
                            <el-empty v-if="!filteredCreatives(creativeTab).length" description="暂无此类素材" :image-size="50" />
                        </div>
                    </template>
                    <el-empty v-else-if="currentCreativeCampaign && !loadingCreatives[currentCreativeCampaign.id]" description="暂无推广素材" />
                </div>
            </el-dialog>

            <!-- 商品推广链接弹窗 -->
            <el-dialog v-model="showGeneratedLinks" title="商品推广链接" width="520px">
                <div v-for="(link, i) in generatedLinks" :key="i" class="mb-2 p-3 rounded bg-gray-50">
                    <div class="text-sm font-medium mb-1">{{ link.product_name }} - {{ link.sku_name }}</div>
                    <div class="flex gap-2">
                        <el-input :model-value="link.link" readonly size="small" class="flex-1" />
                        <el-button size="small" @click="copyText(link.link)">复制</el-button>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">推广码: {{ link.referral_code }} | 佣金率: {{ link.commission_rate }}% | 佣金: {{ formatMoney(link.commission_amount) }}</div>
                </div>
                <template #footer><el-button @click="showGeneratedLinks = false">关闭</el-button></template>
            </el-dialog>
        </div>
    </div>
</template>

<style scoped>
.affiliate-page { max-width: 1200px; margin: 0 auto; padding: 0 4px; }

/* 页头 + 紧凑统计行 */
.header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.header-title h1 { margin: 0; }
.header-stats { display: flex; gap: 12px; }
.mini-stat { display: flex; flex-direction: column; align-items: center; padding: 6px 14px; background: #f5f7fa; border-radius: 8px; min-width: 60px; }
.mini-num { font-size: 16px; font-weight: 700; }
.mini-label { font-size: 11px; color: #909399; margin-top: 1px; }

/* Tab 主容器 */
.main-tabs { min-height: 400px; }
.campaign-card { position: relative; }
.stat-card-sm { border-radius: 6px; padding: 4px; }
.stat-card-sm .stat-label { font-size: 12px; color: #909399; }
.stat-card-sm .stat-val { font-size: 20px; font-weight: 700; margin-top: 2px; }

/* 商品网格 - 阿里妈妈联盟风格 */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px; }
.product-card { border: 1px solid #e4e7ed; border-radius: 8px; padding: 12px; background: #fff; transition: box-shadow .2s; }
.product-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.product-header { display: flex; gap: 10px; margin-bottom: 8px; }
.product-img { width: 64px; height: 64px; object-fit: cover; border-radius: 6px; flex-shrink: 0; background: #f5f7fa; }
.product-info { flex: 1; min-width: 0; }
.product-name { font-size: 14px; font-weight: 600; color: #303133; line-height: 1.3; }
.product-meta { font-size: 12px; color: #909399; margin-top: 2px; }
.product-price-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-top: 1px solid #f2f3f5; margin-bottom: 8px; }
.product-price { font-size: 16px; font-weight: 700; color: #f56c6c; }
.product-commission { font-size: 12px; color: #606266; }

/* 推广样式 */
.promo-formats { border: 1px solid #ebeef5; border-radius: 6px; overflow: hidden; }
.format-tabs { display: flex; background: #f5f7fa; border-bottom: 1px solid #ebeef5; }
.format-tab { flex: 1; text-align: center; padding: 6px 4px; font-size: 12px; cursor: pointer; color: #909399; transition: all .2s; border-bottom: 2px solid transparent; }
.format-tab:hover { color: #409eff; }
.format-tab.active { color: #409eff; border-bottom-color: #409eff; background: #fff; font-weight: 600; }
.format-content { padding: 10px; }
.format-result { font-size: 12px; color: #606266; word-break: break-all; padding: 6px 8px; background: #f5f7fa; border-radius: 4px; margin-bottom: 8px; }
.format-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.format-img-preview { position: relative; width: 100%; height: 100px; background: #f5f7fa; border-radius: 4px; overflow: hidden; margin-bottom: 8px; cursor: pointer; }
.format-img-preview .preview-img { width: 100%; height: 100%; object-fit: cover; }
.format-img-preview .preview-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.3); color: #fff; font-size: 13px; opacity: 0; transition: opacity .2s; }
.format-img-preview:hover .preview-overlay { opacity: 1; }
.format-qr-placeholder { padding: 20px; text-align: center; }
.format-qr-area { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.qr-img { width: 100px; height: 100px; border-radius: 4px; }

.text-success { color: #67c23a; }
.text-primary { color: #409eff; }
.text-amber-500 { color: #f59e0b; }
.flex-1 { flex: 1; }
.h-full { height: 100%; }

/* 素材弹窗 - 类型Tab */
.creative-type-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.type-tab { padding: 4px 14px; font-size: 12px; border-radius: 14px; cursor: pointer; color: #606266; background: #f5f7fa; border: 1px solid #e4e7ed; transition: all .2s; }
.type-tab:hover { color: #409eff; border-color: #409eff; }
.type-tab.active { color: #fff; background: #409eff; border-color: #409eff; }
.creative-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.creative-item { border: 1px solid #e4e7ed; border-radius: 8px; padding: 12px; background: #fafafa; }
.creative-preview { margin-bottom: 8px; border-radius: 6px; overflow: hidden; }
.creative-preview .no-img { height: 80px; display: flex; align-items: center; justify-content: center; background: #f0f2f5; color: #c0c4cc; font-size: 12px; border-radius: 4px; }
.creative-type-icon { height: 60px; display: flex; align-items: center; justify-content: center; background: #ecf5ff; color: #409eff; font-size: 13px; font-weight: 600; border-radius: 6px; margin-bottom: 8px; }
.creative-name { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
.creative-desc { font-size: 11px; color: #909399; margin-bottom: 6px; }
.creative-actions { display: flex; gap: 4px; flex-wrap: wrap; }
</style>
