<script setup>
import { ref, onMounted, reactive, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/affiliate.js'
import { uploadFile, getUploadedFiles, deleteUploadedFile } from '../../api/cloudUpload.js'

const { t, locale } = useI18n()
const ns = 'affiliate_campaigns_page'
const ct = 'my_affiliate_page'

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
    billing_mode: 'cpa',
    starts_at: '', ends_at: '',
    reward_first: 0, reward_renewal: 0, reward_upgrade: 0,
    budget_total: null, max_participants: null,
    cost_per_click: null, cost_per_impression: null,
    target_audience: [], terms: [],
})
const savingCampaign = ref(false)

// AI 佣金推荐
const aiLoading = ref(false)
const aiRecommendation = ref(null)
const aiDialogVisible = ref(false)

async function getAiRecommendation() {
    if (!campaignForm.type) {
        ElMessage.warning(t(`${ns}.messages.select_type_first`))
        return
    }
    aiLoading.value = true
    try {
        const res = await api.aiRecommendRate({
            campaign_type: campaignForm.type,
            product_price: campaignForm.reward_first || undefined,
        })
        aiRecommendation.value = res.data
        ElMessage.success(t(`${ns}.messages.ai_rate_suggested`, { rate: res.data.suggested_rate }))
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.ai_recommend_failed`) + ': ' + (e.response?.data?.message || e.message))
    } finally {
        aiLoading.value = false
    }
}

function showAiAnalysis() {
    aiDialogVisible.value = true
}

// 素材对话框
const creativeDialog = ref(false)
const creativeCampaignId = ref(null)
const creativesData = ref([])
const creativeForm = reactive({
    type: 'link', name: '', url: '', content: '', image_url: '',
    commission_amount: null, commission_rate: null,
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

// 推广审核
const pendingCreatives = ref([])
const pendingCount = ref(0)
const loadingPending = ref(false)
const reviewingId = ref(null)
const reviewingAgentId = ref(null)
const pendingAgentsList = ref([])
const pendingAgentsCount = ref(0)
const loadingPendingAgents = ref(false)
const reviewStatus = ref('pending')

const paymentMethods = computed(() => [
    { value: 'mock_instant', label: t(`${ns}.payment_methods.mock_instant`) },
    { value: 'wechat', label: t(`${ns}.payment_methods.wechat`) },
    { value: 'alipay', label: t(`${ns}.payment_methods.alipay`) },
    { value: 'stripe', label: t(`${ns}.payment_methods.stripe`) },
    { value: 'paypal', label: t(`${ns}.payment_methods.paypal`) },
])

const statusOptions = computed(() => [
    { value: 'draft', label: t(`${ns}.status.draft`) },
    { value: 'active', label: t(`${ns}.status.active`) },
    { value: 'paused', label: t(`${ns}.status.paused`) },
    { value: 'completed', label: t(`${ns}.status.completed`) },
])
const typeOptions = computed(() => [
    { value: 'referral', label: t(`${ct}.campaign_types.referral`) },
    { value: 'commission', label: t(`${ct}.campaign_types.commission`) },
    { value: 'reward', label: t(`${ct}.campaign_types.reward`) },
    { value: 'rebate', label: t(`${ct}.campaign_types.rebate`) },
])
const billingModeOptions = computed(() => [
    { value: 'cpa', label: t(`${ns}.billing_modes.cpa`) },
    { value: 'cpc', label: t(`${ns}.billing_modes.cpc`) },
    { value: 'cpm', label: t(`${ns}.billing_modes.cpm`) },
])
const billingModeLabels = computed(() => ({
    cpa: t(`${ns}.billing_modes.cpa_short`),
    cpc: t(`${ns}.billing_modes.cpc_short`),
    cpm: t(`${ns}.billing_modes.cpm_short`),
}))
const creativeTypeOptions = computed(() => [
    { value: 'banner', label: t(`${ct}.creative_types.banner`) },
    { value: 'image', label: t(`${ct}.creative_types.image`) },
    { value: 'video', label: t(`${ct}.creative_types.video`) },
    { value: 'text', label: t(`${ct}.creative_types.text`) },
    { value: 'landing_page', label: t(`${ct}.creative_types.landing_page`) },
    { value: 'link', label: t(`${ct}.creative_types.link`) },
    { value: 'coupon', label: t(`${ct}.creative_types.coupon`) },
    { value: 'qr_code', label: t(`${ct}.creative_types.qr_code`) },
])
const statusColors = { draft: 'info', active: 'success', paused: 'warning', completed: '' }
const statusLabels = computed(() => ({
    draft: t(`${ns}.status.draft`),
    active: t(`${ns}.status.active`),
    paused: t(`${ns}.status.paused`),
    completed: t(`${ns}.status.completed`),
}))
const typeLabels = computed(() => ({
    referral: t(`${ct}.campaign_types.referral`),
    commission: t(`${ct}.campaign_types.commission`),
    reward: t(`${ct}.campaign_types.reward`),
    rebate: t(`${ct}.campaign_types.rebate`),
}))
const creativeTypeLabels = computed(() => ({
    banner: t(`${ct}.creative_types.banner`),
    image: t(`${ct}.creative_types.image`),
    video: t(`${ct}.creative_types.video`),
    text: t(`${ct}.creative_types.text`),
    landing_page: t(`${ct}.creative_types.landing_page`),
    link: t(`${ct}.creative_types.link`),
    coupon: t(`${ct}.creative_types.coupon`),
    qr_code: t(`${ct}.creative_types.qr_code`),
}))
const treeLevelOptions = computed(() => [
    { value: 1, label: t(`${ns}.tree.level_direct`) },
    { value: 2, label: t(`${ns}.tree.level_indirect`) },
])

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
        billing_mode: 'cpa',
        starts_at: '', ends_at: '',
        reward_first: 0, reward_renewal: 0, reward_upgrade: 0,
        budget_total: null, max_participants: null,
        cost_per_click: null, cost_per_impression: null,
        platform_share_rate: 0,
        target_audience: [], terms: [],
    })
    campaignDialog.value = true
}

function openEditCampaign(c) {
    editingCampaign.value = true
    Object.assign(campaignForm, {
        name: c.name, slug: c.slug || '', description: c.description || '',
        status: c.status, type: c.type,
        billing_mode: c.billing_mode || 'cpa',
        starts_at: c.starts_at || '', ends_at: c.ends_at || '',
        reward_first: c.reward_first || 0, reward_renewal: c.reward_renewal || 0,
        reward_upgrade: c.reward_upgrade || 0,
        budget_total: c.budget_total, max_participants: c.max_participants,
        cost_per_click: c.cost_per_click, cost_per_impression: c.cost_per_impression,
        platform_share_rate: c.platform_share_rate ?? 0,
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
            ElMessage.success(t(`${ns}.messages.campaign_updated`))
        } else {
            await api.createCampaign(data)
            ElMessage.success(t(`${ns}.messages.campaign_created`))
        }
        campaignDialog.value = false
        loadCampaigns(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.save_failed`))
    } finally {
        savingCampaign.value = false
    }
}

async function refreshCampaign(id) {
    try {
        await api.refreshCampaign(id)
        ElMessage.success(t(`${ns}.messages.refreshed`))
        loadCampaigns(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.refresh_failed`))
    }
}

// ─── 素材操作 ───

const editingCreative = ref(false)

function openCreateCreative(campaign) {
    creativeCampaignId.value = campaign.id
    editingCreative.value = false
    Object.assign(creativeForm, {
        type: 'link', name: '', url: '', content: '', image_url: '',
        is_active: true, commission_amount: null, commission_rate: null,
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
        ElMessage.success(t(`${ns}.messages.image_uploaded`))
    } catch (e) {
        ElMessage.error(t(`${ns}.messages.upload_failed`))
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
        ElMessage.error(t(`${ns}.messages.load_files_failed`))
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
async function deletePickerImage(file) {
    const name = file.original_name || file.filename || ''
    try {
        await ElMessageBox.confirm(
            t(`${ns}.dialogs.delete_image`, { name }),
            t(`${ns}.dialogs.confirm_delete`),
            { confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel'), type: 'warning' },
        )
        await deleteUploadedFile(file.id)
        ElMessage.success(t(`${ns}.messages.image_deleted`))
        uploadedFiles.value = uploadedFiles.value.filter(f => f.id !== file.id)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t(`${ns}.messages.delete_failed`))
    }
}
function openEditCreative(c) {
    creativeCampaignId.value = c.campaign_id
    editingCreative.value = true
    Object.assign(creativeForm, {
        type: c.type, name: c.name, url: c.url || '', content: c.content || '',
        image_url: c.image_url || '', is_active: c.is_active ?? true,
        commission_amount: c.commission_amount ?? null, commission_rate: c.commission_rate ?? null,
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
            ElMessage.success(t(`${ns}.messages.creative_updated`))
        } else {
            await api.createCreative(creativeCampaignId.value, data)
            ElMessage.success(t(`${ns}.messages.creative_added`))
        }
        creativeDialog.value = false
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.save_failed`))
    } finally {
        savingCreative.value = false
    }
}

async function deleteCreative(c) {
    try {
        await ElMessageBox.confirm(
            t(`${ns}.dialogs.delete_creative`, { name: c.name }),
            t(`${ns}.dialogs.confirm_delete`),
            { confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel') },
        )
        await api.destroyCreative(c.campaign_id, c.id)
        ElMessage.success(t(`${ns}.messages.creative_deleted`))
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t(`${ns}.messages.delete_failed`))
    }
}

async function reviewCreative(c, action) {
    const notes = action === 'rejected'
        ? await ElMessageBox.prompt(
            t(`${ns}.dialogs.reject_reason_prompt`),
            t(`${ns}.dialogs.reject_creative`),
            { inputType: 'textarea' },
        ).then(r => r.value).catch(() => null)
        : ''
    if (action === 'rejected' && notes === null) return
    try {
        await api.reviewCreative(c.campaign_id, c.id, action, notes)
        ElMessage.success(action === 'approved' ? t(`${ns}.messages.creative_approved`) : t(`${ns}.messages.creative_rejected`))
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    }
}

async function loadPending() {
    loadingPending.value = true
    try {
        const res = await api.pendingCreatives({ status: reviewStatus.value })
        const d = res.data?.data || res.data
        pendingCreatives.value = d?.data || d || []
        pendingCount.value = reviewStatus.value === 'pending' ? pendingCreatives.value.length : 0
    } catch (e) {
        pendingCreatives.value = []
        if (reviewStatus.value === 'pending') pendingCount.value = 0
    } finally {
        loadingPending.value = false
    }
}

async function loadPendingAgents() {
    loadingPendingAgents.value = true
    try {
        const res = await api.pendingAgents()
        const body = res.data || {}
        pendingAgentsList.value = Array.isArray(body.data) ? body.data : []
        pendingAgentsCount.value = body.meta?.total ?? pendingAgentsList.value.length
    } catch (e) {
        pendingAgentsList.value = []
        pendingAgentsCount.value = 0
    } finally {
        loadingPendingAgents.value = false
    }
}

async function reviewAgent(agent, action) {
    let notes = ''
    if (action === 'rejected') {
        try {
            notes = await ElMessageBox.prompt(
                t(`${ns}.dialogs.reject_reason_prompt`),
                t(`${ns}.dialogs.reject_agent`),
                { inputType: 'textarea', confirmButtonText: t(`${ns}.dialogs.confirm_reject`), cancelButtonText: t('actions.cancel') },
            ).then(r => r.value)
        } catch { return }
    }
    reviewingAgentId.value = agent.id
    try {
        await api.reviewAgent(agent.id, action, notes)
        ElMessage.success(action === 'approved' ? t(`${ns}.messages.agent_approved`) : t(`${ns}.messages.agent_rejected`))
        pendingAgentsList.value = pendingAgentsList.value.filter(x => x.id !== agent.id)
        pendingAgentsCount.value = pendingAgentsList.value.length
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    } finally {
        reviewingAgentId.value = null
    }
}

async function resubmitCreative(c) {
    try {
        await ElMessageBox.confirm(
            t(`${ns}.dialogs.resubmit_creative`, { name: c.name }),
            t(`${ns}.dialogs.resubmit`),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel') },
        )
        reviewingId.value = c.id
        await api.resubmitCreative(c.id)
        ElMessage.success(t(`${ns}.messages.resubmitted`))
        pendingCreatives.value = pendingCreatives.value.filter(x => x.id !== c.id)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('messages.failed'))
    } finally {
        reviewingId.value = null
    }
}

async function quickReview(c, action) {
    let notes = ''
    if (action === 'rejected') {
        try {
            notes = await ElMessageBox.prompt(
                t(`${ns}.dialogs.reject_reason_prompt`),
                t(`${ns}.dialogs.reject_creative`),
                { inputType: 'textarea', confirmButtonText: t(`${ns}.dialogs.confirm_reject`), cancelButtonText: t('actions.cancel') },
            ).then(r => r.value)
        } catch { return }
    }
    reviewingId.value = c.id
    try {
        await api.reviewCreative(c.campaign_id, c.id, action, notes)
        ElMessage.success(action === 'approved' ? t(`${ns}.messages.reviewed_approved`) : t(`${ns}.messages.reviewed_rejected`))
        pendingCreatives.value = pendingCreatives.value.filter(x => x.id !== c.id)
        pendingCount.value = pendingCreatives.value.length
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    } finally {
        reviewingId.value = null
    }
}

async function toggleCreative(c) {
    try {
        await api.updateCreative(c.campaign_id, c.id, { is_active: !c.is_active })
        loadCreatives(creativeCampaignId.value)
    } catch (e) {
        ElMessage.error(t('messages.failed'))
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
    if (!uplineAgentId.value) { ElMessage.warning(t(`${ns}.messages.enter_agent_id`)); return }
    try {
        const res = await api.upline(uplineAgentId.value)
        uplineData.value = res.data.data
    } catch (e) { ElMessage.error(t(`${ns}.messages.load_upline_failed`)) }
}

async function loadDownline() {
    if (!downlineAgentId.value) { ElMessage.warning(t(`${ns}.messages.enter_agent_id`)); return }
    try {
        const res = await api.downline(downlineAgentId.value)
        downlineData.value = res.data.data
    } catch (e) { ElMessage.error(t(`${ns}.messages.load_downline_failed`)) }
}

async function saveTree() {
    savingTree.value = true
    try {
        await api.buildTree({ ...treeForm })
        ElMessage.success(t(`${ns}.messages.relation_created`))
        treeDialog.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${ns}.messages.relation_failed`))
    } finally {
        savingTree.value = false
    }
}

function formatMoney(v) {
    return v != null
        ? '¥' + Number(v).toLocaleString(locale.value, { minimumFractionDigits: 2 })
        : '¥0.00'
}
function fmtDate(d) { return d ? new Date(d).toLocaleString(locale.value) : '-' }
function fmtShortDate(d) { return d ? new Date(d).toLocaleDateString(locale.value) : '-' }

function openDeposit(campaign) {
    depositCampaign.value = campaign
    depositForm.amount = null
    depositDialog.value = true
}

async function submitDeposit() {
    if (!depositForm.amount || depositForm.amount <= 0) {
        ElMessage.warning(t(`${ns}.messages.enter_deposit_amount`))
        return
    }
    depositing.value = true
    depositResult.value = null
    try {
        const res = await api.depositBudget(depositCampaign.value.id, depositForm.amount, depositForm.payment_method)
        depositResult.value = res.data?.data
        if (res.data?.data?.status === 'completed') {
            ElMessage.success(res.data?.data?.message || t(`${ns}.messages.deposit_success`))
            loadCampaigns(pagination.value.current_page)
            loadDashboard()
        } else if (res.data?.data?.payment_url) {
            ElMessage.success(t(`${ns}.messages.payment_redirect`))
            window.open(res.data.data.payment_url, '_blank')
        } else {
            ElMessage.success(t(`${ns}.messages.payment_created`))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t(`${ns}.messages.deposit_failed`))
    } finally {
        depositing.value = false
    }
}

onMounted(() => {
    loadDashboard()
    loadCampaigns()
    loadClickLogs()
    loadPending()
    loadPendingAgents()
})

watch(activeTab, (tab) => {
    if (tab === 'review') {
        loadPending()
        loadPendingAgents()
    }
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
        <el-row :gutter="16" class="mb-5" v-if="dashboardData">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.total_campaigns`) }}</div>
                    <div class="stat-value">{{ dashboardData.total_campaigns || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.active_campaigns`) }}</div>
                    <div class="stat-value text-success">{{ dashboardData.active_campaigns || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.active_agents`) }}</div>
                    <div class="stat-value">{{ dashboardData.active_agents || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.total_clicks`) }}</div>
                    <div class="stat-value">{{ dashboardData.total_clicks || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.total_conversions`) }}</div>
                    <div class="stat-value text-success">{{ dashboardData.total_conversions || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-label">{{ t(`${ns}.stats.conversion_rate`) }}</div>
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
                <el-tab-pane :label="t(`${ns}.tabs.campaigns`)" name="campaigns">
                    <div class="flex gap-3 mb-4 flex-wrap items-center justify-between">
                        <div class="flex gap-3">
                            <el-select v-model="filters.status" :placeholder="t(`${ns}.filters.by_status`)" clearable style="width:120px" @change="loadCampaigns(1)">
                                <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                            </el-select>
                            <el-select v-model="filters.type" :placeholder="t(`${ns}.filters.by_type`)" clearable style="width:120px" @change="loadCampaigns(1)">
                                <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                            </el-select>
                        </div>
                        <el-button type="primary" @click="openCreateCampaign">{{ t(`${ns}.buttons.create_campaign`) }}</el-button>
                    </div>

                    <el-table :data="campaigns" v-loading="loading" stripe>
                        <el-table-column prop="name" :label="t(`${ns}.cols.campaign_name`)" min-width="160" />
                        <el-table-column prop="slug" :label="t(`${ns}.cols.slug`)" width="120" />
                        <el-table-column :label="t(`${ns}.cols.type`)" width="100">
                            <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.billing`)" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" type="info">{{ billingModeLabels[row.billing_mode] || row.billing_mode?.toUpperCase() || 'CPA' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.status`)" width="80">
                            <template #default="{ row }">
                                <el-tag :type="statusColors[row.status] || 'info'" size="small">{{ statusLabels[row.status] || row.status }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.time`)" min-width="200">
                            <template #default="{ row }">
                                <span class="text-xs text-gray-500">{{ fmtShortDate(row.starts_at) }} ~ {{ fmtShortDate(row.ends_at) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.reward`)" width="140" align="right">
                            <template #default="{ row }">
                                <div class="text-xs">{{ t(`${ns}.reward.first_order`) }}: {{ formatMoney(row.reward_first) }}</div>
                                <div class="text-xs">{{ t(`${ns}.reward.renewal`) }}: {{ formatMoney(row.reward_renewal) }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.budget`)" min-width="190">
                            <template #default="{ row }">
                                <div v-if="row.budget_total" class="text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">{{ t(`${ns}.budget.deposited`) }}</span>
                                        <span class="font-semibold text-success">{{ formatMoney(row.budget_deposited || 0) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-500">{{ t(`${ns}.budget.used`) }}</span>
                                        <span>{{ formatMoney(row.budget_used || 0) }}</span>
                                    </div>
                                    <el-progress :percentage="row.budget_deposited > 0 ? Math.min((row.budget_used || 0) / row.budget_deposited * 100, 100) : 0"
                                        :stroke-width="6" :status="(row.budget_used || 0) >= (row.budget_deposited || 0) ? 'exception' : ''" />
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-gray-400">{{ t(`${ns}.budget.total`) }} {{ formatMoney(row.budget_total) }}</span>
                                        <el-button size="small" text type="primary" @click="openDeposit(row)">{{ t(`${ns}.buttons.deposit`) }}</el-button>
                                    </div>
                                </div>
                                <span v-else class="text-gray-400 text-xs">{{ t(`${ns}.empty.unlimited`) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.participants_conversions`)" width="100" align="center">
                            <template #default="{ row }">{{ row.participant_count || 0 }}/{{ row.conversion_count || 0 }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.actions`)" width="240" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openEditCampaign(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" @click="openCreateCreative(row)">{{ t(`${ns}.buttons.creatives`) }}</el-button>
                                <el-button size="small" @click="openCreativeStats(row.id)">{{ t(`${ns}.buttons.stats`) }}</el-button>
                                <el-button size="small" @click="refreshCampaign(row.id)">{{ t(`${ns}.buttons.refresh`) }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="flex justify-center mt-4">
                        <el-pagination v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page" :total="pagination.total"
                            layout="prev, pager, next, total" @current-change="loadCampaigns" />
                    </div>
                </el-tab-pane>

                <!-- ── 推广审核 ── -->
                <el-tab-pane name="review">
                    <template #label>
                        <span>{{ t(`${ns}.tabs.review`) }} <el-tag v-if="(pendingCount + pendingAgentsCount) > 0" size="small" type="danger" style="margin-left:4px">{{ pendingCount + pendingAgentsCount }}</el-tag></span>
                    </template>
                    <div class="flex gap-3 mb-4">
                        <el-radio-group v-model="reviewStatus" @change="loadPending">
                            <el-radio-button value="pending">{{ t(`${ns}.review.pending`) }}</el-radio-button>
                            <el-radio-button value="rejected">{{ t(`${ns}.review.rejected`) }}</el-radio-button>
                        </el-radio-group>
                    </div>
                    <div v-loading="loadingPending">
                        <el-empty v-if="!pendingCreatives.length && !loadingPending" :description="reviewStatus === 'pending' ? t(`${ns}.review.empty_pending`) : t(`${ns}.review.empty_rejected`)" :image-size="60" />
                        <el-table v-else :data="pendingCreatives" stripe size="small">
                            <el-table-column :label="t(`${ns}.cols.preview`)" width="60">
                                <template #default="{ row }">
                                    <img v-if="row.image_url" :src="row.image_url" class="creative-preview" />
                                    <span v-else class="text-xs text-gray-400">{{ row.type }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="name" :label="t(`${ns}.cols.creative_name`)" min-width="120" />
                            <el-table-column :label="t(`${ns}.cols.campaign`)" min-width="150">
                                <template #default="{ row }">{{ row.campaign?.name || '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${ns}.cols.submitter`)" width="120">
                                <template #default="{ row }">{{ row.creator?.name || row.creator?.email || t(`${ns}.empty.unknown`) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${ns}.cols.submitted_at`)" width="160">
                                <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                            </el-table-column>
                            <el-table-column v-if="reviewStatus === 'rejected'" :label="t(`${ns}.cols.reject_reason`)" min-width="150">
                                <template #default="{ row }"><span class="text-xs text-red-500">{{ row.review_notes || '-' }}</span></template>
                            </el-table-column>
                            <el-table-column :label="t(`${ns}.cols.actions`)" width="220" align="center">
                                <template #default="{ row }">
                                    <template v-if="reviewStatus === 'pending'">
                                        <el-button size="small" type="success" :loading="reviewingId === row.id" @click="quickReview(row, 'approved')">{{ t('actions.approve') }}</el-button>
                                        <el-button size="small" type="danger" :loading="reviewingId === row.id" @click="quickReview(row, 'rejected')">{{ t('actions.reject') }}</el-button>
                                    </template>
                                    <template v-else>
                                        <el-button size="small" type="primary" :loading="reviewingId === row.id" @click="resubmitCreative(row)">{{ t(`${ns}.buttons.resubmit`) }}</el-button>
                                    </template>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>

                    <!-- 推广员申请审核 -->
                    <el-divider style="margin:12px 0" />
                    <div class="font-semibold mb-3" style="font-size:13px;color:#303133">
                        {{ t(`${ns}.review.agent_review`) }}
                        <el-tag v-if="pendingAgentsCount > 0" size="small" type="warning" style="margin-left:6px">{{ pendingAgentsCount }}</el-tag>
                    </div>
                    <div v-loading="loadingPendingAgents">
                        <el-empty v-if="!pendingAgentsList.length && !loadingPendingAgents" :description="t(`${ns}.review.empty_agents`)" :image-size="50" />
                        <el-table v-else :data="pendingAgentsList" stripe size="small">
                            <el-table-column prop="agent_code" :label="t(`${ns}.cols.agent_code`)" width="120" />
                            <el-table-column :label="t(`${ns}.cols.applicant`)" width="150">
                                <template #default="{ row }">{{ row.user?.name || row.user?.email || '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${ns}.cols.applied_at`)" width="160">
                                <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${ns}.cols.actions`)" width="200" align="center">
                                <template #default="{ row }">
                                    <el-button size="small" type="success" :loading="reviewingAgentId === row.id" @click="reviewAgent(row, 'approved')">{{ t('actions.approve') }}</el-button>
                                    <el-button size="small" type="danger" :loading="reviewingAgentId === row.id" @click="reviewAgent(row, 'rejected')">{{ t('actions.reject') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-tab-pane>

                <!-- ── 多级关系链 ── -->
                <el-tab-pane :label="t(`${ns}.tabs.tree`)" name="tree">
                    <el-row :gutter="24">
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.tree.build_title`) }}</span></template>
                                <el-form>
                                    <el-form-item :label="t(`${ns}.form.parent_agent_id`)"><el-input v-model.number="treeForm.parent_agent_id" type="number" /></el-form-item>
                                    <el-form-item :label="t(`${ns}.form.child_agent_id`)"><el-input v-model.number="treeForm.child_agent_id" type="number" /></el-form-item>
                                    <el-form-item :label="t(`${ns}.form.level`)"><el-select v-model="treeForm.level"><el-option v-for="o in treeLevelOptions" :key="o.value" :value="o.value" :label="o.label" /></el-select></el-form-item>
                                    <el-form-item :label="t(`${ns}.form.share_rate`)"><el-input-number v-model="treeForm.rate" :min="0" :max="100" :precision="2" /></el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" :loading="savingTree" @click="saveTree">{{ t(`${ns}.buttons.build_relation`) }}</el-button>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.tree.upline_title`) }}</span></template>
                                <div class="flex gap-2 mb-3">
                                    <el-input v-model.number="uplineAgentId" :placeholder="t(`${ns}.placeholders.agent_id`)" type="number" />
                                    <el-button @click="loadUpline">{{ t(`${ns}.buttons.query`) }}</el-button>
                                </div>
                                <div v-if="uplineData">
                                    <div v-for="(agent, i) in uplineData" :key="i" class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded">
                                        <el-tag>L{{ agent.level || (i + 1) }}</el-tag>
                                        <span>#{{ agent.id }} {{ agent.agent_code }}</span>
                                        <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                    </div>
                                    <el-empty v-if="!uplineData.length" :description="t(`${ns}.tree.no_upline`)" />
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never">
                                <template #header><span class="font-semibold">{{ t(`${ns}.tree.downline_title`) }}</span></template>
                                <div class="flex gap-2 mb-3">
                                    <el-input v-model.number="downlineAgentId" :placeholder="t(`${ns}.placeholders.agent_id`)" type="number" />
                                    <el-button @click="loadDownline">{{ t(`${ns}.buttons.query`) }}</el-button>
                                </div>
                                <div v-if="downlineData">
                                    <div v-for="(agent, i) in downlineData" :key="i" class="flex items-center gap-2 mb-2 p-2 bg-gray-50 rounded">
                                        <el-tag>L{{ agent.level || (i + 1) }}</el-tag>
                                        <span>#{{ agent.id }} {{ agent.agent_code }}</span>
                                        <span class="text-xs text-gray-400">{{ agent.rate || '-' }}%</span>
                                    </div>
                                    <el-empty v-if="!downlineData.length" :description="t(`${ns}.tree.no_downline`)" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-tab-pane>

                <!-- ── 点击日志 ── -->
                <el-tab-pane :label="t(`${ns}.tabs.clicks`)" name="clicks">
                    <!-- 统计卡片 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-label">{{ t(`${ns}.stats.total_clicks_impressions`) }}</div>
                                <div class="stat-value">{{ clickPagination.total }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-label">{{ t(`${ns}.stats.total_conversions`) }}</div>
                                <div class="stat-value text-success">{{ clickLogs.filter(r => r.converted).length }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-label">{{ t(`${ns}.stats.total_commission`) }}</div>
                                <div class="stat-value text-primary">{{ formatMoney(clickLogs.reduce((s, r) => s + (r.commission_amount || 0), 0)) }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="never" class="stat-card">
                                <div class="stat-label">{{ t(`${ns}.stats.estimated_budget`) }}</div>
                                <div class="stat-value text-warning">{{ formatMoney(clickLogs.reduce((s, r) => s + (r.campaign?.billing_mode === 'cpc' ? (r.campaign?.cost_per_click || 0) : (r.commission_amount || 0)), 0)) }}</div>
                            </el-card>
                        </el-col>
                    </el-row>
                    <div class="flex gap-3 mb-4 flex-wrap">
                        <el-input v-model="clickFilters.campaign_id" :placeholder="t(`${ns}.placeholders.campaign_id`)" style="width:120px" />
                        <el-input v-model="clickFilters.agent_id" :placeholder="t(`${ns}.placeholders.agent_id`)" style="width:120px" />
                        <el-select v-model="clickFilters.converted" :placeholder="t(`${ns}.filters.conversion_status`)" clearable style="width:130px" @change="loadClickLogs(1)">
                            <el-option :label="t(`${ns}.filters.converted`)" value="1" />
                            <el-option :label="t(`${ns}.filters.not_converted`)" value="0" />
                        </el-select>
                        <el-button @click="loadClickLogs(1)">{{ t('actions.search') }}</el-button>
                    </div>

                    <el-table :data="clickLogs" v-loading="clickLoading" stripe>
                        <el-table-column prop="id" :label="t(`${ns}.cols.id`)" width="70" />
                        <el-table-column :label="t(`${ns}.cols.agent`)" width="100">
                            <template #default="{ row }">{{ row.agent?.agent_code || row.agent_id }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.campaign`)" width="120">
                            <template #default="{ row }">{{ row.campaign?.name || row.campaign_id }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.billing_mode`)" width="100">
                            <template #default="{ row }">
                                <el-tag size="small" type="info">{{ billingModeLabels[row.campaign?.billing_mode] || (row.campaign?.billing_mode?.toUpperCase()) || 'CPA' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="referral_code" :label="t(`${ns}.cols.referral_code`)" width="110" />
                        <el-table-column prop="ip_address" :label="t(`${ns}.cols.ip`)" width="120" />
                        <el-table-column :label="t(`${ns}.cols.converted`)" width="70" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ row.converted ? t(`${ct}.bool.yes`) : t(`${ct}.bool.no`) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.cost`)" width="100" align="right">
                            <template #default="{ row }">
                                <span v-if="row.campaign?.billing_mode === 'cpc'">{{ t(`${ct}.data.per_click`, { amount: formatMoney(row.campaign?.cost_per_click || 0) }) }}</span>
                                <span v-else-if="row.campaign?.billing_mode === 'cpm'">{{ t(`${ct}.data.per_thousand`, { amount: formatMoney(row.campaign?.cost_per_impression || 0) }) }}</span>
                                <span v-else>{{ formatMoney(row.commission_amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="commission_amount" :label="t(`${ns}.cols.commission`)" width="100" align="right">
                            <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t(`${ns}.cols.time`)" width="160">
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
        <el-dialog v-model="campaignDialog" :title="editingCampaign ? t(`${ns}.dialogs.edit_campaign`) : t(`${ns}.dialogs.create_campaign`)" width="750px">
            <el-form :model="campaignForm" label-width="110px">
                <el-form-item :label="t(`${ns}.form.campaign_name`)"><el-input v-model="campaignForm.name" /></el-form-item>
                <el-form-item :label="t(`${ns}.form.slug`)"><el-input v-model="campaignForm.slug" :placeholder="t(`${ns}.placeholders.unique_slug`)" /></el-form-item>
                <el-form-item :label="t(`${ns}.form.description`)"><el-input v-model="campaignForm.description" type="textarea" :rows="2" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="t(`${ns}.form.type`)"><el-select v-model="campaignForm.type" style="width:100%">
                            <el-option v-for="o in typeOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select></el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${ns}.form.billing_mode`)"><el-select v-model="campaignForm.billing_mode" style="width:100%">
                            <el-option v-for="o in billingModeOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select></el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${ns}.form.status`)"><el-select v-model="campaignForm.status" style="width:100%">
                            <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select></el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t(`${ns}.form.budget_total`)"><el-input-number v-model="campaignForm.budget_total" :min="0" :precision="2" style="width:100%" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t(`${ns}.form.starts_at`)"><el-date-picker v-model="campaignForm.starts_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t(`${ns}.form.ends_at`)"><el-date-picker v-model="campaignForm.ends_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item :label="t(`${ns}.form.reward_first`)"><el-input-number v-model="campaignForm.reward_first" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t(`${ns}.form.reward_renewal`)"><el-input-number v-model="campaignForm.reward_renewal" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t(`${ns}.form.reward_upgrade`)"><el-input-number v-model="campaignForm.reward_upgrade" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t(`${ns}.form.max_participants`)"><el-input-number v-model="campaignForm.max_participants" :min="0" /></el-form-item>
                <!-- CPC/CPM 价格字段 -->
                <el-form-item :label="t(`${ns}.form.cost_per_click`)" v-if="campaignForm.billing_mode === 'cpc'">
                    <el-input-number v-model="campaignForm.cost_per_click" :min="0" :precision="2" style="width:200px" :placeholder="t(`${ns}.placeholders.per_click_cost`)" />
                </el-form-item>
                <el-form-item :label="t(`${ns}.form.cost_per_impression`)" v-if="campaignForm.billing_mode === 'cpm'">
                    <el-input-number v-model="campaignForm.cost_per_impression" :min="0" :precision="2" style="width:200px" :placeholder="t(`${ns}.placeholders.per_impression_cost`)" />
                </el-form-item>
                <el-form-item :label="t(`${ns}.form.platform_share`)">
                    <el-input-number v-model="campaignForm.platform_share_rate" :min="0" :max="100" :precision="2" style="width:160px" />
                    <span style="font-size:12px;color:#909399;margin-left:6px">% &nbsp; {{ t(`${ns}.form.platform_share_hint`) }}</span>
                </el-form-item>

                <!-- AI 佣金推荐 -->
                <el-divider content-position="left" style="margin:12px 0">
                    <el-tag size="small" type="warning" effect="plain">{{ t(`${ns}.ai.section_tag`) }}</el-tag>
                </el-divider>
                <el-form-item :label="t(`${ns}.ai.suggestion`)">
                    <div class="flex items-start gap-3 w-full">
                        <el-button size="small" type="warning" :loading="aiLoading" @click="getAiRecommendation" :disabled="!campaignForm.type">
                            {{ t(`${ns}.buttons.get_ai_rate`) }}
                        </el-button>
                        <div v-if="aiRecommendation" class="ai-rec-result flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <span class="text-sm font-semibold text-warning">
                                    {{ t(`${ns}.ai.suggested_rate`) }}: <span class="text-lg">{{ aiRecommendation.suggested_rate }}%</span>
                                </span>
                                <span v-if="aiRecommendation.confidence" class="text-xs text-gray-400">
                                    {{ t(`${ns}.ai.confidence`) }}: {{ aiRecommendation.confidence }}%
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mb-2">{{ aiRecommendation.reason }}</div>
                            <div class="flex gap-2">
                                <el-button size="small" @click="campaignForm.reward_first = Math.round(campaignForm.reward_first || 0 * aiRecommendation.suggested_rate / 100)" v-if="(campaignForm.reward_first ?? 0) > 0">
                                    {{ t(`${ns}.buttons.apply_reward`) }}
                                </el-button>
                                <el-tooltip :content="t(`${ns}.ai.view_detail_tooltip`)" placement="top">
                                    <el-button size="small" text @click="showAiAnalysis">
                                        {{ t(`${ns}.buttons.view_details`) }}
                                    </el-button>
                                </el-tooltip>
                            </div>
                        </div>
                        <el-empty v-else-if="!campaignForm.type" :description="t(`${ns}.ai.select_type_first`)" :image-size="36" class="flex-1" />
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="campaignDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingCampaign" @click="saveCampaign">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- ── AI 推荐详情对话框 ── -->
        <el-dialog v-model="aiDialogVisible" :title="t(`${ns}.ai.detail_title`)" width="560px">
            <div v-if="aiRecommendation" class="ai-detail-content">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t(`${ns}.ai.suggested_rate`)" :span="2">
                        <span class="text-warning font-bold text-lg">{{ aiRecommendation.suggested_rate }}%</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${ns}.ai.rate_range`)">
                        {{ aiRecommendation.rate_range?.min }}% ~ {{ aiRecommendation.rate_range?.max }}%
                        <span class="text-gray-400 text-xs ml-2">{{ t(`${ns}.ai.recommended`) }}: {{ aiRecommendation.rate_range?.recommended }}%</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${ns}.ai.confidence`)">
                        <el-progress :percentage="aiRecommendation.confidence" :stroke-width="12"
                            :status="aiRecommendation.confidence >= 70 ? 'success' : aiRecommendation.confidence >= 40 ? 'warning' : 'exception'"
                            :format="() => aiRecommendation.confidence + '%'" style="width:120px" />
                    </el-descriptions-item>
                </el-descriptions>

                <div class="mt-4">
                    <h4 class="font-semibold mb-2" style="font-size:13px">{{ t(`${ns}.ai.reason_title`) }}</h4>
                    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded">{{ aiRecommendation.reason }}</p>
                </div>

                <div v-if="aiRecommendation.details" class="mt-4">
                    <h4 class="font-semibold mb-2" style="font-size:13px">{{ t(`${ns}.ai.detail_analysis`) }}</h4>
                    <div class="space-y-2 text-sm">
                        <div v-if="aiRecommendation.details.price_benchmark" class="bg-blue-50 p-3 rounded">
                            <div class="font-medium text-blue-700 mb-1">{{ t(`${ns}.ai.price_benchmark`) }}</div>
                            <div class="text-blue-600">{{ aiRecommendation.details.price_benchmark.reason }}</div>
                        </div>
                        <div v-if="aiRecommendation.details.type_benchmark" class="bg-purple-50 p-3 rounded">
                            <div class="font-medium text-purple-700 mb-1">{{ t(`${ns}.ai.type_benchmark`) }}</div>
                            <div class="text-purple-600">{{ aiRecommendation.details.type_benchmark.reason }}</div>
                        </div>
                        <div v-if="aiRecommendation.details.history" class="bg-green-50 p-3 rounded">
                            <div class="font-medium text-green-700 mb-1">{{ t(`${ns}.ai.history`) }}</div>
                            <div class="text-green-600 mb-1">{{ aiRecommendation.details.history.reason }}</div>
                            <div class="text-xs text-green-500">
                                {{ t(`${ns}.ai.sample`) }}: {{ aiRecommendation.details.history.sample_size }} |
                                {{ t(`${ns}.ai.conversion`) }}: {{ aiRecommendation.details.history.total_conversions }} |
                                {{ t(`${ns}.stats.conversion_rate`) }}: {{ aiRecommendation.details.history.conversion_rate }}%
                            </div>
                        </div>
                        <div v-if="aiRecommendation.details.seasonal_factor && aiRecommendation.details.seasonal_factor !== 1.0" class="bg-orange-50 p-3 rounded">
                            <div class="font-medium text-orange-700 mb-1">{{ t(`${ns}.ai.seasonal`) }}</div>
                            <div class="text-orange-600">{{ t(`${ns}.ai.factor`) }}: {{ aiRecommendation.details.seasonal_factor }}x</div>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>

        <!-- ── 素材管理对话框 ── -->
        <el-dialog v-model="creativeDialog" :title="editingCreative ? t(`${ns}.creatives.edit_title`) : t(`${ns}.creatives.manage_title`)" width="700px">
            <div class="mb-4">
                <div class="font-semibold mb-2 flex items-center justify-between">
                    <span>{{ t(`${ns}.creatives.existing_count`, { count: creativesData.length }) }}</span>
                </div>
                <el-table :data="creativesData" stripe size="small">
                    <el-table-column :label="t(`${ns}.cols.preview`)" width="60">
                        <template #default="{ row }">
                            <img v-if="row.image_url" :src="row.image_url" class="creative-preview" @click="previewImage(row.image_url)" />
                            <span v-else class="text-xs text-gray-400">{{ t(`${ns}.empty.no_image`) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="t(`${ns}.cols.name`)" min-width="100" />
                    <el-table-column :label="t(`${ns}.cols.type`)" width="70">
                        <template #default="{ row }">{{ creativeTypeLabels[row.type] || row.type }}</template>
                    </el-table-column>
                    <el-table-column :label="t(`${ns}.cols.status`)" width="70">
                        <template #default="{ row }">
                            <el-switch :model-value="!!row.is_active" size="small" @change="toggleCreative(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="click_count" :label="t(`${ns}.cols.clicks`)" width="55" align="center" />
                    <el-table-column prop="conversion_count" :label="t(`${ns}.cols.conversions`)" width="55" align="center" />
                    <el-table-column :label="t(`${ns}.cols.commission`)" width="90" align="center">
                        <template #default="{ row }">
                            <span v-if="row.commission_amount != null" style="color:#e6a23c;font-size:12px">¥{{ row.commission_amount }}</span>
                            <span v-else-if="row.commission_rate != null" style="color:#e6a23c;font-size:12px">{{ row.commission_rate }}%</span>
                            <span v-else style="color:#909399;font-size:11px">{{ t(`${ns}.creatives.default_commission`) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${ns}.cols.review`)" width="70" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.status === 'pending'" size="small" type="warning">{{ t(`${ct}.creative_status.pending`) }}</el-tag>
                            <el-tag v-else-if="row.status === 'rejected'" size="small" type="danger">{{ t(`${ct}.creative_status.rejected`) }}</el-tag>
                            <el-tag v-else size="small" type="success">{{ t(`${ct}.creative_status.approved`) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${ns}.cols.actions`)" width="100" align="center">
                        <template #default="{ row }">
                            <el-button size="small" text @click="openEditCreative(row)">{{ t('actions.edit') }}</el-button>
                            <el-popconfirm :title="t(`${ns}.dialogs.delete_confirm_short`)" @confirm="deleteCreative(row)">
                                <template #reference>
                                    <el-button size="small" text type="danger">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
            <el-divider>{{ editingCreative ? t(`${ns}.creatives.edit_section`) : t(`${ns}.creatives.add_section`) }}</el-divider>
            <el-form :model="creativeForm" label-width="100px">
                <el-form-item :label="t(`${ns}.form.creative_type`)"><el-select v-model="creativeForm.type" style="width:200px">
                    <el-option v-for="o in creativeTypeOptions" :key="o.value" :label="o.label" :value="o.value" />
                </el-select></el-form-item>
                <el-form-item :label="t(`${ns}.form.name`)"><el-input v-model="creativeForm.name" :placeholder="t(`${ns}.placeholders.creative_name`)" /></el-form-item>
                <el-form-item :label="t(`${ns}.form.url`)"><el-input v-model="creativeForm.url" :placeholder="t(`${ns}.placeholders.promo_url`)" /></el-form-item>
                <el-form-item :label="t(`${ns}.form.content`)"><el-input v-model="creativeForm.content" type="textarea" :rows="2" :placeholder="t(`${ns}.placeholders.creative_content`)" /></el-form-item>
                <el-form-item :label="t(`${ns}.form.image`)">
                    <div class="flex items-center gap-2" style="width:100%">
                        <el-input v-model="creativeForm.image_url" :placeholder="t(`${ns}.placeholders.image_url`)" class="flex-1" />
                        <el-upload :show-file-list="false" :before-upload="handleUpload" accept="image/*">
                            <el-button size="small" :loading="uploadingImage">{{ t('actions.upload') }}</el-button>
                        </el-upload>
                        <el-button size="small" @click="openImagePicker">{{ t(`${ns}.buttons.select_image`) }}</el-button>
                        <img v-if="creativeForm.image_url" :src="creativeForm.image_url" class="creative-preview-lg" @click="previewImage(creativeForm.image_url)" />
                    </div>
                </el-form-item>
                <el-form-item :label="t(`${ns}.form.enabled`)">
                    <el-switch v-model="creativeForm.is_active" />
                </el-form-item>
                <el-divider style="margin:8px 0" />
                <div class="text-xs text-gray-400 mb-2 font-semibold">{{ t(`${ns}.creatives.commission_settings`) }}</div>
                <el-form-item :label="t(`${ns}.form.commission_amount`)">
                    <el-input-number v-model="creativeForm.commission_amount" :min="0" :precision="2" style="width:160px" :placeholder="t(`${ns}.placeholders.commission_per_conversion`)" clearable />
                    <span style="font-size:12px;color:#909399;margin-left:6px">{{ t(`${ns}.creatives.yuan`) }}</span>
                </el-form-item>
                <el-form-item :label="t(`${ns}.form.commission_rate`)">
                    <el-input-number v-model="creativeForm.commission_rate" :min="0" :max="100" :precision="2" style="width:160px" :placeholder="t(`${ns}.placeholders.commission_percent`)" clearable />
                    <span style="font-size:12px;color:#909399;margin-left:6px">%</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="creativeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingCreative" @click="saveCreative">{{ editingCreative ? t('actions.save') : t(`${ns}.buttons.add`) }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 图片选择器对话框 ── -->
        <el-dialog v-model="imagePickerDialog" :title="t(`${ns}.creatives.picker_title`)" width="650px">
            <div v-loading="pickerLoading">
                <el-empty v-if="!uploadedFiles.length && !pickerLoading" :description="t(`${ns}.creatives.empty_uploads`)" />
                <el-row :gutter="12" v-else>
                    <el-col :span="8" v-for="f in uploadedFiles" :key="f.id" class="mb-3">
                        <el-card shadow="hover" :body-style="{ padding: '8px' }" class="picker-card">
                            <div class="picker-img-wrap" @click="pickImage(f)">
                                <img :src="f.thumbnail_url || f.url || f.file_url" class="picker-img" />
                            </div>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-xs text-gray-400 truncate" style="max-width:calc(100% - 26px)">{{ f.original_name || f.filename }}</span>
                                <el-tooltip :content="t(`${ns}.creatives.delete_image_tooltip`)" placement="top">
                                    <el-button size="small" text type="danger" circle @click.stop="deletePickerImage(f)" style="min-width:20px;height:20px;padding:0;font-size:12px">×</el-button>
                                </el-tooltip>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
            <template #footer>
                <el-button @click="imagePickerDialog = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 充值对话框 ── -->
        <el-dialog v-model="depositDialog" :title="t(`${ns}.deposit.title`)" width="500px" :close-on-click-modal="!depositing">
            <template v-if="!depositResult">
                <el-alert :title="t(`${ns}.deposit.notice_title`)" type="info" :closable="false" class="mb-4">
                    <template #default>
                        <div class="text-sm">{{ t(`${ns}.deposit.notice_line1`) }}</div>
                        <div class="text-sm mt-1">{{ t(`${ns}.deposit.notice_line2`) }}</div>
                    </template>
                </el-alert>
                <el-form v-if="depositCampaign" label-width="100px">
                    <el-form-item :label="t(`${ns}.form.campaign_name`)">
                        <span class="text-sm">{{ depositCampaign.name }}</span>
                    </el-form-item>
                    <el-form-item :label="t(`${ns}.form.current_balance`)">
                        <span class="text-sm">
                            {{ formatMoney((depositCampaign.budget_deposited || 0) - (depositCampaign.budget_used || 0)) }}
                            ({{ t(`${ns}.deposit.balance_hint`, { deposited: formatMoney(depositCampaign.budget_deposited || 0), total: formatMoney(depositCampaign.budget_total) }) }})
                        </span>
                    </el-form-item>
                    <el-form-item :label="t(`${ns}.form.deposit_amount`)">
                        <el-input-number v-model="depositForm.amount" :min="0.01" :precision="2"
                            :max="depositCampaign.budget_total - (depositCampaign.budget_deposited || 0)"
                            style="width:100%" />
                    </el-form-item>
                    <el-form-item :label="t(`${ns}.form.payment_method`)">
                        <el-radio-group v-model="depositForm.payment_method">
                            <el-radio v-for="pm in paymentMethods" :key="pm.value" :value="pm.value" class="payment-radio">
                                {{ pm.label }}
                            </el-radio>
                        </el-radio-group>
                    </el-form-item>
                </el-form>
            </template>
            <template v-else>
                <el-result :icon="depositResult.status === 'completed' ? 'success' : 'info'"
                    :title="depositResult.status === 'completed' ? t(`${ns}.deposit.success_title`) : t(`${ns}.deposit.order_created_title`)"
                    :sub-title="`¥${depositResult.amount}${depositResult.status === 'completed' ? t(`${ns}.deposit.deposited_subtitle`) : t(`${ns}.deposit.complete_payment_subtitle`)}`">
                    <template #extra>
                        <div class="text-xs text-gray-400">
                            <div>{{ t(`${ns}.deposit.transaction_id`) }}: {{ depositResult.topup_id || '-' }}</div>
                            <div>{{ t(`${ns}.form.payment_method`) }}: {{ paymentMethods.find(p => p.value === depositResult.payment_method)?.label || depositResult.payment_method }}</div>
                            <div v-if="depositResult.status === 'completed'">{{ t(`${ns}.deposit.balance_after`) }}: {{ formatMoney(depositResult.remaining || 0) }}</div>
                            <div v-if="depositResult.payment_url">
                                <el-button type="primary" size="small" class="mt-2" @click="window.open(depositResult.payment_url, '_blank')">
                                    {{ t(`${ns}.buttons.go_pay`) }}
                                </el-button>
                            </div>
                        </div>
                    </template>
                </el-result>
            </template>
            <template #footer>
                <el-button v-if="!depositResult" @click="depositDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button v-if="!depositResult" type="primary" :loading="depositing" @click="submitDeposit">{{ t(`${ns}.buttons.confirm_pay`) }}</el-button>
                <el-button v-else type="primary" @click="depositDialog = false; depositResult = null">{{ t(`${ns}.buttons.done`) }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 素材统计对话框 ── -->
        <el-dialog v-model="creativeStatsDialog" :title="t(`${ns}.creatives.stats_title`)" width="600px">
            <el-table :data="creativeStatsData" stripe>
                <el-table-column prop="name" :label="t(`${ns}.cols.creative_name`)" min-width="140" />
                <el-table-column prop="type" :label="t(`${ns}.cols.type`)" width="80" />
                <el-table-column prop="click_count" :label="t(`${ns}.cols.clicks`)" width="70" align="center" />
                <el-table-column prop="conversion_count" :label="t(`${ns}.cols.conversions`)" width="70" align="center" />
                <el-table-column :label="t(`${ns}.cols.conversion_rate`)" width="80" align="center">
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
.text-primary { color: #0f172a; }
.creative-preview { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; cursor: pointer; }
.creative-preview-lg { width: 48px; height: 48px; object-fit: cover; border-radius: 4px; cursor: pointer; flex-shrink: 0; }
.picker-card { cursor: pointer; transition: transform .15s; }
.picker-card:hover { transform: scale(1.03); }
.picker-img { width: 100%; height: 100px; object-fit: cover; border-radius: 4px; }
.payment-radio { display: flex; margin-bottom: 6px; }
.flex-1 { flex: 1; }
</style>
