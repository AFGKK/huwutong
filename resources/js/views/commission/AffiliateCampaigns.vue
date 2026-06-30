<script setup>
import { ref, onMounted, reactive } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/affiliate.js'
import { uploadFile, getUploadedFiles } from '../../api/cloudUpload.js'

const loading = ref(false)
const campaigns = ref([])
const pagination = ref({ total: 0, current_page: 1, per_page: 20 })
const activeTab = ref('campaigns')
const filters = ref({ status: '', type: '', search: '' })

// 看板数据
const dashboardData = ref(null)

// 活动对话框
const campaignDialog = ref(false)
const editingCampaign = ref(false)
const campaignForm = reactive({
    name: '', slug: '', description: '', status: 'draft', type: 'referral',
    starts_at: '', ends_at: '',
    reward_first: 0, reward_renewal: 0, reward_upgrade: 0,
    budget_total: null, max_participants: null,
    target_audience: [], terms: [],
})
const savingCampaign = ref(false)

// 素材对话框
const creativeDialog = ref(false)
const creativeCampaignId = ref(null)
const creativesData = ref([])
const creativeForm = reactive({
    type: 'link', name: '', url: '', content: '', image_url: '',
    utm_params: { utm_source: 'affiliate', utm_medium: '', utm_campaign: '' },
})
const savingCreative = ref(false)

// 图片上传
const uploadingImage = ref(false)
const imagePickerDialog = ref(false)
const uploadedFiles = ref([])
const pickerLoading = ref(false)

// 素材统计
const creativeStatsData = ref([])
const creativeStatsDialog = ref(false)

// 多级关系
const treeDialog = ref(false)
const treeForm = reactive({ parent_agent_id: null, child_agent_id: null, level: 1, rate: 10 })
const uplineData = ref(null)
const downlineData = ref(null)
const uplineAgentId = ref('')
const downlineAgentId = ref('')
const savingTree = ref(false)

// 点击日志
const clickLogs = ref([])
const clickPagination = ref({ total: 0, current_page: 1, per_page: 20 })
const clickLoading = ref(false)
const clickFilters = ref({ campaign_id: '', agent_id: '', converted: '' })

// 预算充值
const depositDialog = ref(false)
const depositCampaign = ref(null)
const depositForm = reactive({ amount: null, payment_method: 'mock_instant' })
const depositing = ref(false)
const depositResult = ref(null)

const paymentMethods = [
    { value: 'mock_instant', label: '模拟支付（即时到账）', icon: '⚡' },
    { value: 'wechat', label: '微信支付', icon: '💚' },
    { value: 'alipay', label: '支付宝', icon: '💙' },
    { value: 'stripe', label: 'Stripe', icon: '🔴' },
    { value: 'paypal', label: 'PayPal', icon: '🔵' },
]

const statusOptions = [
    { value: 'draft', label: '草稿' },
    { value: 'active', label: '进行中' },
    { value: 'paused', label: '已暂停' },
    { value: 'completed', label: '已结束' },
]
const typeOptions = [
    { value: 'referral', label: '推荐返佣' },
    { value: 'commission', label: '佣金加成' },
    { value: 'reward', label: '奖励计划' },
    { value: 'rebate', label: '返现活动' },
]
const creativeTypeOptions = [
    { value: 'banner', label: '横幅' },
    { value: 'landing_page', label: '落地页' },
    { value: 'link', label: '推广链接' },
    { value: 'coupon', label: '优惠券' },
    { value: 'qr_code', label: '二维码' },
]
const statusColors = { draft: 'info', active: 'success', paused: 'warning', completed: '' }
const statusLabels = { draft: '草稿', active: '进行中', paused: '已暂停', completed: '已结束' }
const typeLabels = { referral: '推荐返佣', commission: '佣金加成', reward: '奖励计划', rebate: '返现活动' }
const creativeTypeLabels = { banner: '横幅', landing_page: '落地页', link: '推广链接', coupon: '优惠券', qr_code: '二维码' }

function previewImage(url) {
    if (!url) return
    window.open(url, '_blank')
}

async function loadDashboard() {
    try {
        const res = await api.dashboard()
        dashboardData.value = res.data.data
    } catch (e) {}
}

async function loadCampaigns(page = 1) {
    loading.value = true
    try {
        const params = { page, per_page: 20 }
        if (filters.value.status) params.status = filters.value.status
        if (filters.value.type) params.type = filters.value.type
        if (filters.value.search) Object.assign(params, { search: filters.value.search })
        const res = await api.campaigns(params)
        const d = res.data.data
        campaigns.value = d?.data || d || []
        pagination.value = {
            total: d?.total || 0,
            current_page: d?.current_page || page,
            per_page: d?.per_page || 20,
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

async function loadCreatives(campaignId) {
    try {
        const res = await api.creatives(campaignId)
        creativesData.value = res.data.data || []
    } catch (e) {}
}

async function loadClickLogs(page = 1) {
    clickLoading.value = true
    try {
        const params = { page, per_page: 20 }
        if (clickFilters.value.campaign_id) params.campaign_id = clickFilters.value.campaign_id
        if (clickFilters.value.agent_id) params.agent_id = clickFilters.value.agent_id
        if (clickFilters.value.converted !== '') params.converted = clickFilters.value.converted
        const res = await api.clickLogs(params)
        const d = res.data.data
        clickLogs.value = d?.data || d || []
        clickPagination.value = {
            total: d?.total || 0,
            current_page: d?.current_page || page,
            per_page: d?.per_page || 20,
        }
    } catch (e) {
        console.error(e)
    } finally {
        clickLoading.value = false
    }
}

// ─── 活动操作 ───

function openCreateCampaign() {
    editingCampaign.value = false
    Object.assign(campaignForm, {
        name: '', slug: '', description: '', status: 'draft', type: 'referral',
        starts_at: '', ends_at: '',
        reward_first: 0, reward_renewal: 0, reward_upgrade: 0,
        budget_total: null, max_participants: null,
        target_audience: [], terms: [],
    })
    campaignDialog.value = true
}

function openEditCampaign(c) {
    editingCampaign.value = true
    Object.assign(campaignForm, {
        name: c.name, slug: c.slug || '', description: c.description || '',
        status: c.status, type: c.type,
        starts_at: c.starts_at || '', ends_at: c.ends_at || '',
        reward_first: c.reward_first || 0, reward_renewal: c.reward_renewal || 0,
        reward_upgrade: c.reward_upgrade || 0,
        budget_total: c.budget_total, max_participants: c.max_participants,
        target_audience: c.target_audience || [],
        terms: c.terms || [],
    })
    campaignForm._id = c.id
    campaignDialog.value = true
}

async function saveCampaign() {
    savingCampaign.value = true
    try {
        const data = { ...campaignForm }
        delete data._id
        if (editingCampaign.value) {
            await api.updateCampaign(campaignForm._id, data)
            ElMessage.success('活动已更新')
        } else {
            await api.createCampaign(data)
            ElMessage.success('活动已创建')
        }
        campaignDialog.value = false
        loadCampaigns(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        savingCampaign.value = false
    }
}

async function refreshCampaign(id) {
    try {
        await api.refreshCampaign(id)
        ElMessage.success('已刷新')
        loadCampaigns(pagination.value.current_page)
    } catch (e) {
        ElMessage.error('刷新失败')
    }
}

// ─── 素材操作 ───

const editingCreative = ref(false)

function openCreateCreative(campaign) {
    creativeCampaignId.value = campaign.id
    editingCreative.value = false
    Object.assign(creativeForm, {
        type: 'link', name: '', url: '', content: '', image_url: '',
        is_active: true,
        utm_params: { utm_source: 'affiliate', utm_medium: '', utm_campaign: campaign.slug || campaign.name },
    })
    creativeDialog.value = true
    loadCreatives(campaign.id)
}

async function handleUpload(file) {
    uploadingImage.value = true
    try {
        const res = await uploadFile(file, 'screenshot')
        let url = res.data?.data?.url || res.data?.url || ''
        // 本地开发时替换域名
        if (url && url.startsWith('http')) {
            try {
                const u = new URL(url)
                url = u.pathname + u.search
            } catch (e) {}
        } else if (url && !url.startsWith('/')) {
            url = '/' + url
        }
        creativeForm.image_url = url
        ElMessage.success('图片已上传')
    } catch (e) {
        ElMessage.error('上传失败')
    } finally {
        uploadingImage.value = false
    }
    return false // Prevent default upload
}

async function openImagePicker() {
    imagePickerDialog.value = true
    pickerLoading.value = true
    try {
        const res = await getUploadedFiles({ type: 'screenshot', per_page: 50 })
        const d = res.data?.data
        uploadedFiles.value = d?.data || d || []
    } catch (e) {
        ElMessage.error('加载文件列表失败')
    } finally {
        pickerLoading.value = false
    }
}

function pickImage(file) {
    let url = file.url || file.file_url || ''
    if (url && url.startsWith('http')) {
        try { const u = new URL(url); url = u.pathname + u.search } catch (e) {}
    } else if (url && !url.startsWith('/')) {
        url = '/' + url
    }
    creativeForm.image_url = url
    imagePickerDialog.value = false
}

function openEditCreative(c) {
    creativeCampaignId.value = c.campaign_id
    editingCreative.value = true
    Object.assign(creativeForm, {
        type: c.type, name: c.name, url: c.url || '', content: c.content || '',
        image_url: c.image_url || '', is_active: c.is_active ?? true,
        utm_params: c.utm_params || { utm_source: 'affiliate', utm_medium: '', utm_campaign: '' },
    })
    creativeForm._id = c.id
    creativeDialog.value = true
    loadCreatives(c.campaign_id)
}

async function saveCreative() {
    savingCreative.value = true
    try {
        const data = { ...creativeForm }
        delete data._id
        if (editingCreative.value) {
            await api.updateCreative(creativeCampaignId.value, creativeForm._id, data)
            ElMessage.success('素材已更新')
        } else {
            await api.createCreative(creativeCampaignId.value, data)
            ElMessage.success('素材已添加')
        }
        creativeDialog.value = false
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        savingCreative.value = false
    }
}

async function deleteCreative(c) {
    try {
        await ElMessageBox.confirm('确定删除素材「' + c.name + '」？', '确认删除')
        await api.destroyCreative(c.campaign_id, c.id)
        ElMessage.success('素材已删除')
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error('删除失败')
    }
}

async function toggleCreative(c) {
    try {
        await api.updateCreative(c.campaign_id, c.id, { is_active: !c.is_active })
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

async function openCreativeStats(campaignId) {
    try {
        const res = await api.creativeStats(campaignId)
        creativeStatsData.value = res.data.data || []
        creativeStatsDialog.value = true
    } catch (e) {}
}

// ─── 多级关系 ───

async function loadUpline() {
    if (!uplineAgentId.value) { ElMessage.warning('请输入代理商ID'); return }
    try {
        const res = await api.upline(uplineAgentId.value)
        uplineData.value = res.data.data
    } catch (e) { ElMessage.error('加载上级链失败') }
}

async function loadDownline() {
    if (!downlineAgentId.value) { ElMessage.warning('请输入代理商ID'); return }
    try {
        const res = await api.downline(downlineAgentId.value)
        downlineData.value = res.data.data
    } catch (e) { ElMessage.error('加载下级链失败') }
}

async function saveTree() {
    savingTree.value = true
    try {
        await api.buildTree({ ...treeForm })
        ElMessage.success('关系已建立')
        treeDialog.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '建立关系失败')
    } finally {
        savingTree.value = false
    }
}

function formatMoney(v) { return v != null ? '¥' + Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '¥0.00' }
function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }
function fmtShortDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN') : '-' }

function openDeposit(campaign) {
    depositCampaign.value = campaign
    depositForm.amount = null
    depositDialog.value = true
}

async function submitDeposit() {
    if (!depositForm.amount || depositForm.amount <= 0) {
        ElMessage.warning('请输入充值金额')
        return
    }
    depositing.value = true
    depositResult.value = null
    try {
        const res = await api.depositBudget(depositCampaign.value.id, depositForm.amount, depositForm.payment_method)
        depositResult.value = res.data?.data
        if (res.data?.data?.status === 'completed') {
            ElMessage.success(res.data?.data?.message || '充值成功')
            loadCampaigns(pagination.value.current_page)
            loadDashboard()
        } else if (res.data?.data?.payment_url) {
            ElMessage.success('支付订单已创建，正在跳转...')
            window.open(res.data.data.payment_url, '_blank')
        } else {
            ElMessage.success('支付订单已创建')
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || '充值失败')
    } finally {
        depositing.value = false
    }
}

onMounted(() => {
    loadDashboard()
    loadCampaigns()
    loadClickLogs()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>商业管理</el-breadcrumb-item>
            <el-breadcrumb-item>联盟推广</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 看板统计 -->
        <el-row :gutter="16" class="mb-5" v-if="dashboardData">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">活动总数</div>
                    <div class="stat-value">{{ dashboardData.total_campaigns || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">活跃活动</div>
                    <div class="stat-value text-success">{{ dashboardData.active_campaigns || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">代理商参与</div>
                    <div class="stat-value">{{ dashboardData.active_agents || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">累计点击</div>
                    <div class="stat-value">{{ dashboardData.total_clicks || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">转化次数</div>
                    <div class="stat-value text-success">{{ dashboardData.total_conversions || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">转化率</div>
                    <div class="stat-value text-primary">
                        {{ dashboardData.total_clicks > 0 ? ((dashboardData.total_conversions / dashboardData.total_clicks) * 100).toFixed(1) : 0 }}%
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 主要标签页 -->
        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- ── 活动管理 ── -->
                <el-tab-pane label="活动管理" name="campaigns">
                    <div class="flex gap-3 mb-4 flex-wrap items-center justify-between">
                        <div class="flex gap-3">
                            <el-select v-model="filters.status" placeholder="按状态" clearable style="width:120px" @change="loadCampaigns(1)">
                                <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                            </el-select>
                            <el-select v-model="filters.type" placeholder="按类型" clearable style="width:120px" @change="loadCampaigns(1)">
                                <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                            </el-select>
                        </div>
                        <el-button type="primary" @click="openCreateCampaign">创建活动</el-button>
                    </div>

                    <el-table :data="campaigns" v-loading="loading" stripe>
                        <el-table-column prop="name" label="活动名称" min-width="160" />
                        <el-table-column prop="slug" label="标识" width="120" />
                        <el-table-column label="类型" width="100">
                            <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="statusColors[row.status] || 'info'" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" min-width="200">
                            <template #default="{ row }">
                                <span class="text-xs text-gray-500">{{ fmtShortDate(row.starts_at) }} ~ {{ fmtShortDate(row.ends_at) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="奖励" width="140" align="right">
                            <template #default="{ row }">
                                <div class="text-xs">首单: {{ formatMoney(row.reward_first) }}</div>
                                <div class="text-xs">续费: {{ formatMoney(row.reward_renewal) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="预算" min-width="190">
                            <template #default="{ row }">
                                <div v-if="row.budget_total" class="text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">已存</span>
                                        <span class="font-semibold text-success">{{ formatMoney(row.budget_deposited || 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">已用</span>
                                        <span>{{ formatMoney(row.budget_used || 0) }}</span>
                                    </div>
                                    <el-progress :percentage="row.budget_deposited > 0 ? Math.min((row.budget_used || 0) / row.budget_deposited * 100, 100) : 0"
                                        :stroke-width="6" :status="(row.budget_used || 0) >= (row.budget_deposited || 0) ? 'exception' : ''" />
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-gray-400">总额 {{ formatMoney(row.budget_total) }}</span>
                                        <el-button size="small" text type="primary" @click="openDeposit(row)">充值</el-button>
                                    </div>
                                </div>
                                <span v-else class="text-gray-400 text-xs">无限</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="参与/转化" width="100" align="center">
                            <template #default="{ row }">{{ row.participant_count || 0 }}/{{ row.conversion_count || 0 }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="240" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openEditCampaign(row)">编辑</el-button>
                                <el-button size="small" @click="openCreateCreative(row)">素材</el-button>
                                <el-button size="small" @click="openCreativeStats(row.id)">统计</el-button>
                                <el-button size="small" @click="refreshCampaign(row.id)">刷新</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page" :total="pagination.total"
                            layout="prev, pager, next, total" @current-change="loadCampaigns" />
                    </div>
                </el-tab-pane>

                <!-- ── 多级关系链 ── -->
                <el-tab-pane label="多级关系链" name="tree">
                    <el-row :gutter="24">
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">建立上下级关系</span></template>
                                <el-form>
                                    <el-form-item label="上级代理商ID"><el-input v-model.number="treeForm.parent_agent_id" type="number" /></el-form-item>
                                    <el-form-item label="下级代理商ID"><el-input v-model.number="treeForm.child_agent_id" type="number" /></el-form-item>
                                    <el-form-item label="层级"><el-select v-model="treeForm.level"><el-option :value="1" label="一级(直接)" /><el-option :value="2" label="二级(间接)" /></el-select></el-form-item>
                                    <el-form-item label="分成比例(%)"><el-input-number v-model="treeForm.rate" :min="0" :max="100" :precision="2" /></el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="savingTree" @click="saveTree">建立关系</el-button>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">查询上级链</span></template>
                                <div class="flex gap-2 mb-3">
                                    <el-input v-model.number="uplineAgentId" placeholder="代理商ID" type="number" />
                                    <el-button @click="loadUpline">查询</el-button>
                                </div>
                                <div v-if="uplineData">
                                    <div v-for="(agent, i) in uplineData" :key="i" class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded">
                                        <el-tag>L{{ agent.level || (i + 1) }}</el-tag>
                                        <span>#{{ agent.id }} {{ agent.agent_code }}</span>
                                        <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                    </div>
                                    <el-empty v-if="!uplineData.length" description="无上级链" />
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">查询下级链</span></template>
                                <div class="flex gap-2 mb-3">
                                    <el-input v-model.number="downlineAgentId" placeholder="代理商ID" type="number" />
                                    <el-button @click="loadDownline">查询</el-button>
                                </div>
                                <div v-if="downlineData">
                                    <div v-for="(agent, i) in downlineData" :key="i" class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded">
                                        <el-tag>L{{ agent.level || (i + 1) }}</el-tag>
                                        <span>#{{ agent.id }} {{ agent.agent_code }}</span>
                                        <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                    </div>
                                    <el-empty v-if="!downlineData.length" description="无下级链" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- ── 点击日志 ── -->
                <el-tab-pane label="点击日志" name="clicks">
                    <div class="flex gap-3 mb-4 flex-wrap">
                        <el-input v-model="clickFilters.campaign_id" placeholder="活动ID" style="width:120px" />
                        <el-input v-model="clickFilters.agent_id" placeholder="代理商ID" style="width:120px" />
                        <el-select v-model="clickFilters.converted" placeholder="转化状态" clearable style="width:130px" @change="loadClickLogs(1)">
                            <el-option label="已转化" value="1" />
                            <el-option label="未转化" value="0" />
                        </el-select>
                        <el-button @click="loadClickLogs(1)">搜索</el-button>
                    </div>

                    <el-table :data="clickLogs" v-loading="clickLoading" stripe>
                        <el-table-column prop="id" label="ID" width="70" />
                        <el-table-column label="代理商" width="100">
                            <template #default="{ row }">{{ row.agent?.agent_code || row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column label="活动" width="100">
                            <template #default="{ row }">{{ row.campaign?.name || row.campaign_id }}</template>
                        </el-table-column>
                        <el-table-column prop="referral_code" label="推广码" width="110" />
                        <el-table-column prop="ip_address" label="IP" width="120" />
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

                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="clickPagination.current_page"
                            :page-size="clickPagination.per_page" :total="clickPagination.total"
                            layout="prev, pager, next, total" @current-change="loadClickLogs" />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- ── 活动编辑对话框 ── -->
        <el-dialog v-model="campaignDialog" :title="editingCampaign ? '编辑活动' : '创建活动'" width="750px">
            <el-form :model="campaignForm" label-width="110px">
                <el-form-item label="活动名称"><el-input v-model="campaignForm.name" /></el-form-item>
                <el-form-item label="标识(slug)"><el-input v-model="campaignForm.slug" placeholder="唯一标识" /></el-form-item>
                <el-form-item label="描述"><el-input v-model="campaignForm.description" type="textarea" :rows="2" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item label="类型"><el-select v-model="campaignForm.type" style="width:100%">
                            <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select></el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="状态"><el-select v-model="campaignForm.status" style="width:100%">
                            <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select></el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="总预算"><el-input-number v-model="campaignForm.budget_total" :min="0" :precision="2" style="width:100%" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="开始时间"><el-date-picker v-model="campaignForm.starts_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="结束时间"><el-date-picker v-model="campaignForm.ends_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item label="首单奖励"><el-input-number v-model="campaignForm.reward_first" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="续费奖励"><el-input-number v-model="campaignForm.reward_renewal" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="升级奖励"><el-input-number v-model="campaignForm.reward_upgrade" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item label="最大参与"><el-input-number v-model="campaignForm.max_participants" :min="0" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="campaignDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingCampaign" @click="saveCampaign">保存</el-button>
            </template>
        </el-dialog>

        <!-- ── 素材管理对话框 ── -->
        <el-dialog v-model="creativeDialog" :title="editingCreative ? '编辑素材' : '推广素材管理'" width="700px">
            <div class="mb-4">
                <div class="font-semibold mb-2 flex items-center justify-between">
                    <span>已有素材 ({{ creativesData.length }})</span>
                </div>
                <el-table :data="creativesData" stripe size="small">
                    <el-table-column label="预览" width="60">
                        <template #default="{ row }">
                            <img v-if="row.image_url" :src="row.image_url" class="creative-preview" @click="previewImage(row.image_url)" />
                            <span v-else class="text-xs text-gray-400">无图</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" label="名称" min-width="100" />
                    <el-table-column label="类型" width="70">
                        <template #default="{ row }">{{ creativeTypeLabels[row.type] || row.type }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="70">
                        <template #default="{ row }">
                            <el-switch :model-value="!!row.is_active" size="small" @change="toggleCreative(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="click_count" label="点击" width="55" align="center" />
                    <el-table-column prop="conversion_count" label="转化" width="55" align="center" />
                    <el-table-column label="操作" width="90" align="center">
                        <template #default="{ row }">
                            <el-button size="small" text @click="openEditCreative(row)">编辑</el-button>
                            <el-popconfirm title="确定删除?" @confirm="deleteCreative(row)">
                                <template #reference>
                                    <el-button size="small" text type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <el-divider>{{ editingCreative ? '编辑素材' : '添加新素材' }}</el-divider>
            <el-form :model="creativeForm" label-width="100px">
                <el-form-item label="素材类型"><el-select v-model="creativeForm.type" style="width:200px">
                    <el-option v-for="o in creativeTypeOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select></el-form-item>
                <el-form-item label="名称"><el-input v-model="creativeForm.name" placeholder="素材名称" /></el-form-item>
                <el-form-item label="URL"><el-input v-model="creativeForm.url" placeholder="推广链接URL" /></el-form-item>
                <el-form-item label="内容"><el-input v-model="creativeForm.content" type="textarea" :rows="2" placeholder="素材描述内容" /></el-form-item>
                <el-form-item label="图片">
                    <div class="flex items-center gap-2" style="width:100%">
                        <el-input v-model="creativeForm.image_url" placeholder="素材图片地址" class="flex-1" />
                        <el-upload :show-file-list="false" :before-upload="handleUpload" accept="image/*">
                            <el-button size="small" :loading="uploadingImage">上传</el-button>
                        </el-upload>
                        <el-button size="small" @click="openImagePicker">选择</el-button>
                        <img v-if="creativeForm.image_url" :src="creativeForm.image_url" class="creative-preview-lg" @click="previewImage(creativeForm.image_url)" />
                    </div>
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="creativeForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="creativeDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingCreative" @click="saveCreative">{{ editingCreative ? '保存' : '添加' }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 图片选择器对话框 ── -->
        <el-dialog v-model="imagePickerDialog" title="选择图片" width="650px">
            <div v-loading="pickerLoading">
                <el-empty v-if="!uploadedFiles.length && !pickerLoading" description="暂无已上传图片" />
                <el-row :gutter="12" v-else>
                    <el-col :span="8" v-for="f in uploadedFiles" :key="f.id" class="mb-3">
                        <el-card shadow="hover" :body-style="{ padding: '8px' }" class="picker-card" @click="pickImage(f)">
                            <img :src="f.thumbnail_url || f.url || f.file_url" class="picker-img" />
                            <div class="text-xs text-gray-400 mt-1 truncate">{{ f.original_name || f.filename }}</div>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
            <template #footer>
                <el-button @click="imagePickerDialog = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- ── 充值对话框 ── -->
        <el-dialog v-model="depositDialog" title="预算充值" width="500px" :close-on-click-modal="!depositing">
            <template v-if="!depositResult">
                <el-alert title="充值说明" type="info" :closable="false" class="mb-4">
                    <template #default>
                        <div class="text-sm">充值金额将存入活动预算账户，结算佣金时将从中扣减。</div>
                        <div class="text-sm mt-1">选择「模拟支付」即时到账；选择真实支付方式需完成支付后到账。</div>
                    </template>
                </el-alert>
                <el-form v-if="depositCampaign" label-width="100px">
                    <el-form-item label="活动名称">
                        <span class="text-sm">{{ depositCampaign.name }}</span>
                    </el-form-item>
                    <el-form-item label="当前余额">
                        <span class="text-sm">
                            {{ formatMoney((depositCampaign.budget_deposited || 0) - (depositCampaign.budget_used || 0)) }}
                            (已存 {{ formatMoney(depositCampaign.budget_deposited || 0) }} / 总预算 {{ formatMoney(depositCampaign.budget_total) }})
                        </span>
                    </el-form-item>
                    <el-form-item label="充值金额">
                        <el-input-number v-model="depositForm.amount" :min="0.01" :precision="2"
                            :max="depositCampaign.budget_total - (depositCampaign.budget_deposited || 0)"
                            style="width:100%" />
                    </el-form-item>
                    <el-form-item label="支付方式">
                        <el-radio-group v-model="depositForm.payment_method">
                            <el-radio v-for="pm in paymentMethods" :key="pm.value" :value="pm.value" class="payment-radio">
                                {{ pm.icon }} {{ pm.label }}
                            </el-radio>
                        </el-radio-group>
                    </el-form-item>
                </el-form>
            </template>
            <template v-else>
                <el-result :icon="depositResult.status === 'completed' ? 'success' : 'info'"
                    :title="depositResult.status === 'completed' ? '支付成功' : '订单已创建'"
                    :sub-title="`¥${depositResult.amount}${depositResult.status === 'completed' ? ' 已存入活动预算' : '，请完成支付'}`">
                    <template #extra>
                        <div class="text-xs text-gray-400">
                            <div>交易流水号: {{ depositResult.topup_id || '-' }}</div>
                            <div>支付方式: {{ paymentMethods.find(p => p.value === depositResult.payment_method)?.label || depositResult.payment_method }}</div>
                            <div v-if="depositResult.status === 'completed'">充值后余额: {{ formatMoney(depositResult.remaining || 0) }}</div>
                            <div v-if="depositResult.payment_url">
                                <el-button type="primary" size="small" class="mt-2" @click="window.open(depositResult.payment_url, '_blank')">
                                    去支付
                                </el-button>
                            </div>
                        </div>
                    </template>
                </el-result>
            </template>
            <template #footer>
                <el-button v-if="!depositResult" @click="depositDialog = false">取消</el-button>
                <el-button v-if="!depositResult" type="primary" :loading="depositing" @click="submitDeposit">确认支付</el-button>
                <el-button v-else type="primary" @click="depositDialog = false; depositResult = null">完成</el-button>
            </template>
        </el-dialog>

        <!-- ── 素材统计对话框 ── -->
        <el-dialog v-model="creativeStatsDialog" title="素材转化统计" width="600px">
            <el-table :data="creativeStatsData" stripe>
                <el-table-column prop="name" label="素材名称" min-width="140" />
                <el-table-column prop="type" label="类型" width="80" />
                <el-table-column prop="click_count" label="点击" width="70" align="center" />
                <el-table-column prop="conversion_count" label="转化" width="70" align="center" />
                <el-table-column label="转化率" width="80" align="center">
                    <template #default="{ row }">
                        {{ row.click_count > 0 ? ((row.conversion_count / row.click_count) * 100).toFixed(1) : 0 }}%
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-primary { color: #409eff; }
.creative-preview { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; cursor: pointer; }
.creative-preview-lg { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
.picker-card { cursor: pointer; transition: transform .15s; }
.picker-card:hover { transform: scale(1.03); }
.picker-img { width: 100%; height: 100px; object-fit: cover; border-radius: 4px; }
.payment-radio { display: flex; margin-bottom: 6px; }
.flex-1 { flex: 1; }
</style>
