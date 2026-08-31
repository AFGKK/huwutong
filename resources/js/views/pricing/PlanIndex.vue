<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/plan.js'
import productApi from '../../api/product.js'

const { t, locale } = useI18n()

const loading = ref(false)
const activeTab = ref('plans')
const plans = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const search = ref('')
const filterActive = ref('')
const filterBadge = ref('')
const products = ref([])

const badgeOptionKeys = ['popular', 'best_value', 'hot', 'new']
const billingPeriodKeys = ['monthly', 'quarterly', 'semi_annually', 'yearly']
const bundleTypeKeys = ['optional', 'required', 'upgrade']
const changeTypeKeys = ['upgrade', 'downgrade', 'crossgrade']

const badgeOptions = computed(() =>
    badgeOptionKeys.map((value) => ({
        value,
        label: t(`plan_index_page.badges.${value}`),
    }))
)

const billingPeriodOptions = computed(() =>
    billingPeriodKeys.map((value) => ({
        value,
        label: t(`plan_index_page.billing_periods.${value}`),
    }))
)

const bundleTypeOptions = computed(() =>
    bundleTypeKeys.map((value) => ({
        value,
        label: t(`plan_index_page.bundle_types.${value}`),
    }))
)

const planRules = computed(() => ({
    name: [
        { required: true, message: t('plan_index_page.validation.name_required'), trigger: 'blur' },
        { max: 255, message: t('plan_index_page.validation.name_max'), trigger: 'blur' },
    ],
    slug: [{ max: 255, message: t('plan_index_page.validation.slug_max'), trigger: 'blur' }],
}))

async function loadProducts() {
    try {
        const res = await productApi.list({ per_page: 500, fields: 'id,name,slug' })
        const d = res.data.data
        products.value = d?.data || d || []
    } catch { products.value = [] }
}

// 套餐列表
async function loadPlans(page = 1) {
    loading.value = true
    try {
        const params = { page, per_page: 20 }
        if (search.value) params.search = search.value
        if (filterActive.value !== '') params['filter.is_active'] = filterActive.value
        if (filterBadge.value) params['filter.badge'] = filterBadge.value
        const res = await api.list(params)
        const d = res.data.data
        plans.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

function getBadgeType(badge) {
    return { popular: 'warning', best_value: 'success', hot: 'danger', new: 'info' }[badge] || 'info'
}

function getBadgeLabel(badge) {
    if (!badge) return badge
    const key = `plan_index_page.badges.${badge}`
    const label = t(key)
    return label !== key ? label : badge
}

function bundleTypeLabel(type) {
    const key = `plan_index_page.bundle_types.${type}`
    const label = t(key)
    return label !== key ? label : type
}

function changeTypeLabel(type) {
    const key = `plan_index_page.change_types.${type}`
    const label = t(key)
    return label !== key ? label : type
}

function getProductName(productId) {
    const p = products.value.find(p => p.id === productId)
    return p ? p.name : ''
}

// 套餐列表
const planDialog = ref(false)
const planEditing = ref(null)
const planSubmitting = ref(false)
const planFormRef = ref(null)
const defaultLimits = { max_api_keys: 0, max_products: 0, team_members: 0, api_rate_limit: 0, max_activations: 0 }
const planForm = ref({ name: '', slug: '', description: '', price_monthly: 0, price_quarterly: 0, price_semi_annually: 0, price_yearly: 0, currency: 'CNY', trial_days: 0, sort_order: 0, badge: '', is_active: true, is_public: true, features: [], featureInput: '', limits: { ...defaultLimits }, product_id: null })

function autoSlug(name) {
    return (name || '').toLowerCase()
        .replace(/[^a-z0-9\u4e00-\u9fa5]+/g, '-')
        .replace(/^-|-$/g, '')
        .substring(0, 255)
}

let nameTimer = null
function onNameInput() {
    if (nameTimer) clearTimeout(nameTimer)
    if (planEditing.value) return // 编辑时不改 slug
    nameTimer = setTimeout(() => {
        if (!planForm.value.slug || planForm.value.slug === autoSlug(planForm.value.name.replace(/./g, '')) || planForm.value.slug === planForm.value.name) {
            planForm.value.slug = autoSlug(planForm.value.name)
        }
    }, 300)
}

function openPlanDialog(plan = null) {
    planEditing.value = plan
    if (plan) {
        const planLimits = plan.limits
        planForm.value = {
            name: plan.name || '', slug: plan.slug || '', description: plan.description || '',
            price_monthly: Number(plan.price_monthly) || 0, price_quarterly: Number(plan.price_quarterly) || 0,
            price_semi_annually: Number(plan.price_semi_annually) || 0, price_yearly: Number(plan.price_yearly) || 0,
            currency: plan.currency || 'CNY', trial_days: Number(plan.trial_days) || 0, sort_order: Number(plan.sort_order) || 0,
            badge: plan.badge || '', is_active: plan.is_active ?? true, is_public: plan.is_public ?? true,
            features: Array.isArray(plan.features) ? [...plan.features] : [], featureInput: '',
            limits: {
                max_api_keys: planLimits?.max_api_keys ?? 0,
                max_products: planLimits?.max_products ?? 0,
                team_members: planLimits?.team_members ?? 0,
                api_rate_limit: planLimits?.api_rate_limit ?? 0,
                max_activations: planLimits?.max_activations ?? 0,
            },
            product_id: plan.product_id || null,
        }
    } else {
        planForm.value = { name: '', slug: '', description: '', price_monthly: 0, price_quarterly: 0, price_semi_annually: 0, price_yearly: 0, currency: 'CNY', trial_days: 0, sort_order: 0, badge: '', is_active: true, is_public: true, features: [], featureInput: '', limits: { ...defaultLimits }, product_id: null }
    }
    planDialog.value = true
}

async function submitPlan() {
    const valid = await planFormRef.value?.validate().catch(() => false)
    if (!valid) { ElMessage.warning(t('plan_index_page.messages.fill_required')); return }
    planSubmitting.value = true
    try {
        const slug = planForm.value.slug || autoSlug(planForm.value.name)
        const data = {
            name: planForm.value.name,
            slug: slug,
            description: planForm.value.description || undefined,
            price_monthly: Number(planForm.value.price_monthly) || 0,
            price_quarterly: Number(planForm.value.price_quarterly) || 0,
            price_semi_annually: Number(planForm.value.price_semi_annually) || 0,
            price_yearly: Number(planForm.value.price_yearly) || 0,
            currency: planForm.value.currency || 'CNY',
            trial_days: Number(planForm.value.trial_days) || 0,
            sort_order: Number(planForm.value.sort_order) || 0,
            badge: planForm.value.badge || undefined,
            is_active: planForm.value.is_active,
            is_public: planForm.value.is_public,
            features: planForm.value.features || [],
            limits: planForm.value.limits,
            product_id: planForm.value.product_id || undefined,
        }
        if (planEditing.value) {
            await api.update(planEditing.value.id, data)
            ElMessage.success(t('plan_index_page.messages.plan_updated'))
        } else {
            await api.create(data)
            ElMessage.success(t('plan_index_page.messages.plan_created'))
        }
        planDialog.value = false
        loadPlans()
    } catch (e) { ElMessage.error(t('messages.failed')) }
    finally { planSubmitting.value = false }
}

async function deletePlan(plan) {
    try {
        await api.destroy(plan.id)
        ElMessage.success(t('plan_index_page.messages.deleted'))
        loadPlans()
    } catch (e) { ElMessage.error(t('plan_index_page.messages.delete_failed')) }
}

async function togglePlanActive(row) {
    try {
        await api.update(row.id, { is_active: !row.is_active })
        ElMessage.success(row.is_active ? t('plan_index_page.messages.deactivated') : t('plan_index_page.messages.activated'))
        loadPlans()
    } catch { ElMessage.error(t('messages.failed')) }
}

function addFeature() {
    const text = planForm.value.featureInput?.trim()
    if (!text) { ElMessage.warning(t('plan_index_page.messages.enter_feature')); return }
    if (planForm.value.features.includes(text)) { ElMessage.warning(t('plan_index_page.messages.feature_exists')); return }
    planForm.value.features.push(text)
    planForm.value.featureInput = ''
}

function removeFeature(index) {
    planForm.value.features.splice(index, 1)
}

function featureKeydown(e) {
    if (e.key === 'Enter') { e.preventDefault(); addFeature() }
}

// 捆绑规则
const bundleRules = ref([])
const bundleDialog = ref(false)
const bundleForm = ref({ parent_plan_id: '', included_plan_id: '', type: 'optional', discount_percent: 0, fixed_discount: null, sort_order: 0, is_active: true })
const bundleEditing = ref(null)

async function loadBundles() {
    try {
        const res = await api.bundleRules()
        const d = res.data.data
        bundleRules.value = d?.data || d || []
    } catch (e) {}
}

function openBundleForm(rule = null) {
    bundleEditing.value = rule
    if (rule) {
        bundleForm.value = { ...rule }
    } else {
        bundleForm.value = { parent_plan_id: '', included_plan_id: '', type: 'optional', discount_percent: 0, fixed_discount: null, sort_order: 0, is_active: true }
    }
    bundleDialog.value = true
}

async function submitBundle() {
    try {
        if (bundleEditing.value) {
            await api.updateBundleRule(bundleEditing.value.id, bundleForm.value)
            ElMessage.success(t('plan_index_page.messages.updated'))
        } else {
            await api.createBundleRule(bundleForm.value)
            ElMessage.success(t('plan_index_page.messages.bundle_created'))
        }
        bundleDialog.value = false
        loadBundles()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deleteBundle(id) {
    try {
        await ElMessageBox.confirm(t('plan_index_page.confirm.delete_bundle'))
        await api.deleteBundleRule(id)
        ElMessage.success(t('plan_index_page.messages.deleted'))
        loadBundles()
    } catch (e) {}
}

// 升级路径
const upgradePaths = ref([])
const pathDialog = ref(false)
const pathForm = ref({ from_plan_id: '', to_plan_id: '', proration_ratio: 0.5, additional_fee: 0, allow_downgrade: false, is_active: true })
const pathEditing = ref(null)

async function loadPaths() {
    try {
        const res = await api.upgradePaths()
        const d = res.data.data
        upgradePaths.value = d?.data || d || []
    } catch (e) {}
}

function openPathForm(path = null) {
    pathEditing.value = path
    if (path) {
        pathForm.value = { ...path }
    } else {
        pathForm.value = { from_plan_id: '', to_plan_id: '', proration_ratio: 0.5, additional_fee: 0, allow_downgrade: false, is_active: true }
    }
    pathDialog.value = true
}

async function submitPath() {
    try {
        if (pathEditing.value) {
            await api.updateUpgradePath(pathEditing.value.id, pathForm.value)
            ElMessage.success(t('plan_index_page.messages.updated'))
        } else {
            await api.createUpgradePath(pathForm.value)
            ElMessage.success(t('plan_index_page.messages.path_created'))
        }
        pathDialog.value = false
        loadPaths()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

async function deletePath(id) {
    try {
        await ElMessageBox.confirm(t('plan_index_page.confirm.delete_path'))
        await api.deleteUpgradePath(id)
        ElMessage.success(t('plan_index_page.messages.deleted'))
        loadPaths()
    } catch (e) {}
}

// 升级计算器
const calcForm = ref({ from_plan_id: '', to_plan_id: '', billing_period: 'monthly' })
const calcResult = ref(null)

async function calculateUpgrade() {
    try {
        const res = await api.calculateUpgrade(calcForm.value)
        calcResult.value = res.data.data
    } catch (e) { ElMessage.error(t('plan_index_page.messages.calc_failed')) }
}

// 升级日志
const upgradeLogs = ref([])

async function loadLogs() {
    try {
        const res = await api.upgradeLogs()
        const d = res.data.data
        upgradeLogs.value = d?.data || d || []
    } catch (e) {}
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'
    return new Date(d).toLocaleString(loc)
}

onMounted(() => {
    loadPlans()
    loadBundles()
    loadPaths()
    loadLogs()
    loadProducts()
})
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin.group.billing') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('admin.menu.plans') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <el-tabs v-model="activeTab">
            <!-- Tab 1: 套餐列表 -->
            <el-tab-pane :label="t('plan_index_page.tabs.plans')" name="plans">
                <el-card shadow="never" class="mb-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center flex-wrap gap-2">
                            <el-input v-model="search" :placeholder="t('plan_index_page.plans.search_ph')" clearable style="width:200px" @keyup.enter="loadPlans()" />
                            <el-select v-model="filterActive" clearable :placeholder="t('plan_index_page.plans.filter_status')" style="width:100px" @change="loadPlans()">
                                <el-option :label="t('plan_index_page.status.active')" :value="true" />
                                <el-option :label="t('plan_index_page.status.inactive')" :value="false" />
                            </el-select>
                            <el-select v-model="filterBadge" clearable :placeholder="t('plan_index_page.plans.filter_badge')" style="width:120px" @change="loadPlans()">
                                <el-option v-for="opt in badgeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                            <el-button type="primary" @click="loadPlans()">{{ t('actions.search') }}</el-button>
                            <el-button text @click="search='';filterActive='';filterBadge='';loadPlans()">{{ t('actions.reset') }}</el-button>
                        </div>
                        <el-button type="primary" @click="openPlanDialog()">+ {{ t('plan_index_page.plans.add_btn') }}</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="plans" v-loading="loading" stripe>
                        <el-table-column :label="t('plan_index_page.cols.name')" min-width="160" prop="name" sortable="custom">
                            <template #default="{ row }">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ row.name }}</span>
                                    <el-tag v-if="row.badge" :type="getBadgeType(row.badge)" size="small" effect="dark">{{ getBadgeLabel(row.badge) }}</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.slug')" width="120"><template #default="{ row }"><code>{{ row.slug }}</code></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.pricing')" min-width="180">
                            <template #default="{ row }">
                                <div class="price-cell">
                                    <span class="price-item" v-if="row.price_monthly > 0">{{ t('plan_index_page.pricing.monthly') }} ¥{{ row.price_monthly }}</span>
                                    <span class="price-item" v-if="row.price_quarterly > 0">{{ t('plan_index_page.pricing.quarterly') }} ¥{{ row.price_quarterly }}</span>
                                    <span class="price-item" v-if="row.price_semi_annually > 0">{{ t('plan_index_page.pricing.semi_annually') }} ¥{{ row.price_semi_annually }}</span>
                                    <span class="price-item" v-if="row.price_yearly > 0">{{ t('plan_index_page.pricing.yearly') }} ¥{{ row.price_yearly }}</span>
                                    <span v-if="!row.price_monthly && !row.price_quarterly && !row.price_semi_annually && !row.price_yearly" class="text-muted">{{ t('plan_index_page.pricing.free') }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.trial')" width="60" align="center"><template #default="{ row }">{{ row.trial_days ? t('plan_index_page.trial_days', { n: row.trial_days }) : '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.product')" min-width="120">
                            <template #default="{ row }">
                                <span v-if="row.product_id">{{ getProductName(row.product_id) || t('plan_index_page.product.id_prefix', { id: row.product_id }) }}</span>
                                <span v-else class="text-muted">{{ t('plan_index_page.product.universal') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.status')" width="80" align="center">
                            <template #default="{ row }">
                                <el-tooltip :content="row.is_active ? t('plan_index_page.status.listed') : t('plan_index_page.status.deactivated')" placement="top">
                                    <el-switch :model-value="!!row.is_active" size="small" @click="togglePlanActive(row)" />
                                </el-tooltip>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.sort')" width="55" align="center"><template #default="{ row }">{{ row.sort_order }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.actions')" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="openPlanDialog(row)">{{ t('actions.edit') }}</el-button>
                                <el-popconfirm :title="t('plan_index_page.confirm.delete_plan')" @confirm="deletePlan(row)">
                                    <template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="20" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadPlans" /></div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 2: 捆绑规则 -->
            <el-tab-pane :label="t('plan_index_page.tabs.bundles')" name="bundles">
                <el-card shadow="never" class="mb-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-400">{{ t('plan_index_page.bundles.hint') }}</span>
                        <el-button type="primary" size="small" @click="openBundleForm()">+ {{ t('plan_index_page.bundles.add_btn') }}</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="bundleRules" stripe>
                        <el-table-column :label="t('plan_index_page.cols.parent_plan')" width="160"><template #default="{ row }">{{ row.parent_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.included_plan')" width="160"><template #default="{ row }">{{ row.included_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.type')" width="100"><template #default="{ row }"><el-tag size="small">{{ bundleTypeLabel(row.type) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.discount_pct')" width="80"><template #default="{ row }">{{ row.discount_percent }}%</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.fixed_discount')" width="100"><template #default="{ row }">{{ row.fixed_discount ? '¥'+row.fixed_discount : '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.sort')" width="60"><template #default="{ row }">{{ row.sort_order }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.status')" width="70"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('plan_index_page.status.enabled') : t('plan_index_page.status.disabled') }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.actions')" width="140">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openBundleForm(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deleteBundle(row.id)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 升级路径 -->
            <el-tab-pane :label="t('plan_index_page.tabs.paths')" name="paths">
                <el-card shadow="never" class="mb-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-400">{{ t('plan_index_page.paths.hint') }}</span>
                        <el-button type="primary" size="small" @click="openPathForm()">+ {{ t('plan_index_page.paths.add_btn') }}</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="upgradePaths" stripe>
                        <el-table-column :label="t('plan_index_page.cols.from_plan')" width="160"><template #default="{ row }">{{ row.from_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.to_plan')" width="160"><template #default="{ row }">{{ row.to_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.proration_ratio')" width="100"><template #default="{ row }">{{ (row.proration_ratio * 100) + '%' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.additional_fee')" width="100"><template #default="{ row }">{{ row.additional_fee ? '¥'+row.additional_fee : '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.allow_downgrade')" width="90"><template #default="{ row }"><el-tag :type="row.allow_downgrade ? 'warning' : 'info'" size="small">{{ row.allow_downgrade ? t('plan_index_page.yes') : t('plan_index_page.no') }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.status')" width="70"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('plan_index_page.status.enabled') : t('plan_index_page.status.disabled') }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.actions')" width="140">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openPathForm(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" text type="danger" @click="deletePath(row.id)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 4: 升级计算器 -->
            <el-tab-pane :label="t('plan_index_page.tabs.calculator')" name="calculator">
                <el-card shadow="never">
                    <el-form :model="calcForm" label-width="120px" class="w-96">
                        <el-form-item :label="t('plan_index_page.calculator.source_plan_id')"><el-input-number v-model="calcForm.from_plan_id" :min="1" /></el-form-item>
                        <el-form-item :label="t('plan_index_page.calculator.target_plan_id')"><el-input-number v-model="calcForm.to_plan_id" :min="1" /></el-form-item>
                        <el-form-item :label="t('plan_index_page.calculator.billing_period')"><el-select v-model="calcForm.billing_period"><el-option v-for="opt in billingPeriodOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                        <el-form-item><el-button type="primary" @click="calculateUpgrade">{{ t('plan_index_page.calculator.calculate') }}</el-button></el-form-item>
                    </el-form>
                    <div v-if="calcResult" class="mt-4 p-4 bg-gray-50 rounded">
                        <p><strong>{{ t('plan_index_page.calculator.result_type') }}：</strong><el-tag :type="calcResult.type === 'upgrade' ? 'success' : calcResult.type === 'downgrade' ? 'warning' : 'info'">{{ changeTypeLabel(calcResult.type) }}</el-tag></p>
                        <p><strong>{{ t('plan_index_page.calculator.original_price') }}：</strong>¥{{ calcResult.from_price }}</p>
                        <p><strong>{{ t('plan_index_page.calculator.new_price') }}：</strong>¥{{ calcResult.to_price }}</p>
                        <p><strong>{{ t('plan_index_page.calculator.credit') }}：</strong>¥{{ calcResult.credit }}</p>
                        <p><strong>{{ t('plan_index_page.calculator.charge') }}：</strong>¥{{ calcResult.charge }}</p>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 5: 升级日志 -->
            <el-tab-pane :label="t('plan_index_page.tabs.logs')" name="logs">
                <el-card shadow="never">
                    <el-table :data="upgradeLogs" stripe>
                        <el-table-column :label="t('plan_index_page.cols.type')" width="80"><template #default="{ row }"><el-tag :type="row.type === 'upgrade' ? 'success' : row.type === 'downgrade' ? 'warning' : 'info'" size="small">{{ changeTypeLabel(row.type) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.original_plan')" width="140"><template #default="{ row }">{{ row.from_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.new_plan')" width="140"><template #default="{ row }">{{ row.to_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.credit')" width="80"><template #default="{ row }">¥{{ row.credit }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.charge')" width="80"><template #default="{ row }">¥{{ row.charge }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.status')" width="80"><template #default="{ row }"><el-tag type="success">{{ row.status }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.operator')" width="100"><template #default="{ row }">{{ row.operator?.name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('plan_index_page.cols.time')" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 套餐编辑对话框 -->
        <el-dialog v-model="planDialog" :title="planEditing ? t('plan_index_page.dialog.edit_plan') : t('plan_index_page.dialog.create_plan')" width="780px" :close-on-click-modal="false">
            <el-form ref="planFormRef" :model="planForm" :rules="planRules" label-width="110px" size="default">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.name')" prop="name">
                            <el-input v-model="planForm.name" maxlength="255" :placeholder="t('plan_index_page.form.name_ph')" @input="onNameInput" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.slug')" prop="slug">
                            <el-input v-model="planForm.slug" :placeholder="t('plan_index_page.form.slug_ph')" maxlength="255">
                                <template #append><el-button text @click="planForm.slug = autoSlug(planForm.name)">{{ t('plan_index_page.form.regenerate_slug') }}</el-button></template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('plan_index_page.form.description')">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" maxlength="500" :placeholder="t('plan_index_page.form.description_ph')" />
                </el-form-item>

                <div class="form-section-title">{{ t('plan_index_page.sections.pricing') }}</div>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('plan_index_page.pricing.monthly_pay')"><el-input-number v-model="planForm.price_monthly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('plan_index_page.pricing.quarterly_pay')"><el-input-number v-model="planForm.price_quarterly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('plan_index_page.pricing.semi_annually_pay')"><el-input-number v-model="planForm.price_semi_annually" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('plan_index_page.pricing.yearly_pay')"><el-input-number v-model="planForm.price_yearly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>

                <div class="form-section-title">{{ t('plan_index_page.sections.config') }}</div>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.linked_product')">
                            <el-select v-model="planForm.product_id" clearable :placeholder="t('plan_index_page.form.linked_product_ph')" style="width:100%" filterable>
                                <el-option v-for="p in products" :key="p.id" :label="p.name + (p.slug ? ' ('+p.slug+')' : '')" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.currency')">
                            <el-select v-model="planForm.currency" style="width:100%">
                                <el-option label="CNY" value="CNY" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.trial_days')">
                            <el-input-number v-model="planForm.trial_days" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.sort_order')">
                            <el-input-number v-model="planForm.sort_order" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('plan_index_page.form.badge')">
                            <el-select v-model="planForm.badge" clearable style="width:100%" :placeholder="t('plan_index_page.badges.none')">
                                <el-option v-for="opt in badgeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item :label="t('plan_index_page.form.is_active')"><el-switch v-model="planForm.is_active" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('plan_index_page.form.is_public')"><el-switch v-model="planForm.is_public" /></el-form-item></el-col>
                </el-row>

                <div class="form-section-title">{{ t('plan_index_page.sections.limits') }}</div>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="t('plan_index_page.form.max_api_keys')">
                            <el-input-number v-model="planForm.limits.max_api_keys" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">{{ t('plan_index_page.hints.unlimited') }}</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('plan_index_page.form.max_products')">
                            <el-input-number v-model="planForm.limits.max_products" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">{{ t('plan_index_page.hints.unlimited') }}</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('plan_index_page.form.team_members')">
                            <el-input-number v-model="planForm.limits.team_members" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">{{ t('plan_index_page.hints.unlimited') }}</div>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="t('plan_index_page.form.api_rate_limit')">
                            <el-input-number v-model="planForm.limits.api_rate_limit" :min="0" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">{{ t('plan_index_page.hints.rate_limit') }}</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('plan_index_page.form.max_activations')">
                            <el-input-number v-model="planForm.limits.max_activations" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">{{ t('plan_index_page.hints.unlimited') }}</div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="form-section-title">{{ t('plan_index_page.sections.features') }}</div>
                <el-form-item :label="t('plan_index_page.form.feature_list')">
                    <div style="width:100%">
                        <div v-if="planForm.features.length" class="feature-tags">
                            <el-tag
                                v-for="(f, i) in planForm.features"
                                :key="i"
                                closable
                                :disable-transitions="false"
                                size="default"
                                style="margin:0 6px 6px 0"
                                @close="removeFeature(i)"
                            >
                                {{ f }}
                            </el-tag>
                        </div>
                        <div style="font-size:12px;color:#909399;margin-bottom:6px" v-if="!planForm.features.length">{{ t('plan_index_page.form.no_features') }}</div>
                        <div class="feature-input-row">
                            <el-input
                                v-model="planForm.featureInput"
                                :placeholder="t('plan_index_page.form.feature_ph')"
                                size="default"
                                style="flex:1"
                                @keydown="featureKeydown"
                            />
                            <el-button type="primary" size="default" @click="addFeature" :disabled="!planForm.featureInput?.trim()">{{ t('plan_index_page.form.add_feature') }}</el-button>
                        </div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="planDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="planSubmitting" @click="submitPlan">{{ planEditing ? t('actions.save') : t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 捆绑规则对话框 -->
        <el-dialog v-model="bundleDialog" :title="bundleEditing ? t('plan_index_page.dialog.edit_bundle') : t('plan_index_page.dialog.create_bundle')" width="450px">
            <el-form :model="bundleForm" label-width="110px">
                <el-form-item :label="t('plan_index_page.form.parent_plan_id')"><el-input-number v-model="bundleForm.parent_plan_id" :min="1" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.included_plan_id')"><el-input-number v-model="bundleForm.included_plan_id" :min="1" /></el-form-item>
                <el-form-item :label="t('plan_index_page.cols.type')"><el-select v-model="bundleForm.type" class="w-full"><el-option v-for="opt in bundleTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                <el-form-item :label="t('plan_index_page.form.discount_percent')"><el-input-number v-model="bundleForm.discount_percent" :min="0" :max="100" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.fixed_discount_yuan')"><el-input-number v-model="bundleForm.fixed_discount" :min="0" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.sort_order')"><el-input-number v-model="bundleForm.sort_order" :min="0" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.enable')"><el-switch v-model="bundleForm.is_active" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="bundleDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitBundle">{{ bundleEditing ? t('actions.save') : t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 升级路径对话框 -->
        <el-dialog v-model="pathDialog" :title="pathEditing ? t('plan_index_page.dialog.edit_path') : t('plan_index_page.dialog.create_path')" width="450px">
            <el-form :model="pathForm" label-width="110px">
                <el-form-item :label="t('plan_index_page.form.from_plan_id')"><el-input-number v-model="pathForm.from_plan_id" :min="1" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.to_plan_id')"><el-input-number v-model="pathForm.to_plan_id" :min="1" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.proration_ratio')"><el-input-number v-model="pathForm.proration_ratio" :min="0" :max="1" :step="0.1" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.additional_fee_yuan')"><el-input-number v-model="pathForm.additional_fee" :min="0" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.allow_downgrade')"><el-switch v-model="pathForm.allow_downgrade" /></el-form-item>
                <el-form-item :label="t('plan_index_page.form.enable')"><el-switch v-model="pathForm.is_active" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="pathDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitPath">{{ pathEditing ? t('actions.save') : t('actions.create') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.w-60 { width: 240px; }
.w-96 { width: 384px; }
.form-section-title {
    font-size: 14px; font-weight: 600; color: #1f2937;
    padding-bottom: 8px; margin: 16px 0 12px;
    border-bottom: 1px solid #e5e7eb;
    display: flex; align-items: center; gap: 6px;
}
.form-section-title::before {
    content: ''; display: inline-block; width: 3px; height: 14px;
    background: #0f172a; border-radius: 2px; flex-shrink: 0;
}
.feature-tags {
    display: flex; flex-wrap: wrap; align-items: center;
    padding: 8px; background: #fafafa; border-radius: 6px;
    min-height: 42px; border: 1px solid #e5e7eb; margin-bottom: 8px;
}
.feature-input-row {
    display: flex; gap: 8px; align-items: center;
}
.price-cell {
    display: flex; flex-wrap: wrap; gap: 4px 8px; line-height: 1.4;
}
.price-item {
    font-size: 12px; background: #f5f7fa; padding: 1px 6px; border-radius: 3px; white-space: nowrap;
}
.text-muted { color: #c0c4cc; }

@media (max-width: 768px) {
    .w-96 { width: 100%; }
    :deep(.el-tabs__nav-scroll) { overflow-x: auto; }
    :deep(.el-table) { display: block; overflow-x: auto; }
    :deep(.el-dialog) {
        width: calc(100vw - 32px) !important;
        max-width: 100%;
    }
    :deep(.el-form-item__label) {
        width: 100% !important;
        text-align: left;
        justify-content: flex-start;
    }
    :deep(.el-form-item__content) {
        margin-left: 0 !important;
    }
    .flex.items-center.justify-between {
        flex-direction: column;
        align-items: stretch !important;
        gap: 12px;
    }
    .flex.items-center.flex-wrap.gap-2 {
        width: 100%;
    }
    .flex.items-center.flex-wrap.gap-2 :deep(.el-input),
    .flex.items-center.flex-wrap.gap-2 :deep(.el-select) {
        width: 100% !important;
    }
}
</style>
