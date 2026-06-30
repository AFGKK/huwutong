<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/plan.js'
import planApi from '../../api/plan.js'
import productApi from '../../api/product.js'

const loading = ref(false)
const activeTab = ref('plans')
const plans = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const search = ref('')
const filterActive = ref('')
const filterBadge = ref('')
const products = ref([])

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
    return { popular: '⭐最受欢迎', best_value: '🏆最佳价值', hot: '🔥热销', new: '🆕新品' }[badge] || badge
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

const planRules = {
    name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }, { max: 255, message: '名称不能超过255个字符', trigger: 'blur' }],
    slug: [{ max: 255, message: '标识不能超过255个字符', trigger: 'blur' }],
}

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
    if (!valid) { ElMessage.warning('请填写必要信息'); return }
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
            ElMessage.success('套餐已更新')
        } else {
            await api.create(data)
            ElMessage.success('套餐已创建')
        }
        planDialog.value = false
        loadPlans()
    } catch (e) { ElMessage.error('操作失败') }
    finally { planSubmitting.value = false }
}

async function deletePlan(plan) {
    try {
        await api.destroy(plan.id)
        ElMessage.success('已删除')
        loadPlans()
    } catch (e) { ElMessage.error('删除失败') }
}

async function togglePlanActive(row) {
    try {
        await api.update(row.id, { is_active: !row.is_active })
        ElMessage.success(row.is_active ? '已停用' : '已启用')
        loadPlans()
    } catch { ElMessage.error('操作失败') }
}

function addFeature() {
    const text = planForm.value.featureInput?.trim()
    if (!text) { ElMessage.warning('请输入功能描述'); return }
    if (planForm.value.features.includes(text)) { ElMessage.warning('该功能已存在'); return }
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
            ElMessage.success('已更新')
        } else {
            await api.createBundleRule(bundleForm.value)
            ElMessage.success('捆绑规则已创建')
        }
        bundleDialog.value = false
        loadBundles()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deleteBundle(id) {
    try {
        await ElMessageBox.confirm('确定删除此捆绑规则？')
        await api.deleteBundleRule(id)
        ElMessage.success('已删除')
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
            ElMessage.success('已更新')
        } else {
            await api.createUpgradePath(pathForm.value)
            ElMessage.success('升级路径已创建')
        }
        pathDialog.value = false
        loadPaths()
    } catch (e) { ElMessage.error('操作失败') }
}

async function deletePath(id) {
    try {
        await ElMessageBox.confirm('确定删除此升级路径？')
        await api.deleteUpgradePath(id)
        ElMessage.success('已删除')
        loadPaths()
    } catch (e) {}
}

// 升级计算器
const calcDialog = ref(false)
const calcForm = ref({ from_plan_id: '', to_plan_id: '', billing_period: 'monthly' })
const calcResult = ref(null)

async function calculateUpgrade() {
    try {
        const res = await api.calculateUpgrade(calcForm.value)
        calcResult.value = res.data.data
    } catch (e) { ElMessage.error('计算失败') }
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

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

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
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>计费</el-breadcrumb-item>
            <el-breadcrumb-item>套餐系统</el-breadcrumb-item>
        </el-breadcrumb>

        <el-tabs v-model="activeTab">
            <!-- Tab 1: 套餐列表 -->
            <el-tab-pane label="套餐列表" name="plans">
                <el-card shadow="never" class="mb-3">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center flex-wrap gap-2">
                            <el-input v-model="search" placeholder="搜索名称 / Slug / 描述" clearable style="width:200px" @keyup.enter="loadPlans()" />
                            <el-select v-model="filterActive" clearable placeholder="状态" style="width:100px" @change="loadPlans()">
                                <el-option label="活跃" :value="true" />
                                <el-option label="停用" :value="false" />
                            </el-select>
                            <el-select v-model="filterBadge" clearable placeholder="徽章" style="width:120px" @change="loadPlans()">
                                <el-option label="⭐ 最受欢迎" value="popular" />
                                <el-option label="🏆 最佳价值" value="best_value" />
                                <el-option label="🔥 热销" value="hot" />
                                <el-option label="🆕 新品" value="new" />
                            </el-select>
                            <el-button type="primary" @click="loadPlans()">搜索</el-button>
                            <el-button text @click="search='';filterActive='';filterBadge='';loadPlans()">重置</el-button>
                        </div>
                        <el-button type="primary" @click="openPlanDialog()">+ 新增套餐</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="plans" v-loading="loading" stripe>
                        <el-table-column label="名称" min-width="160" prop="name" sortable="custom">
                            <template #default="{ row }">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ row.name }}</span>
                                    <el-tag v-if="row.badge" :type="getBadgeType(row.badge)" size="small" effect="dark">{{ getBadgeLabel(row.badge) }}</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="Slug" width="120"><template #default="{ row }"><code>{{ row.slug }}</code></template></el-table-column>
                        <el-table-column label="定价" min-width="180">
                            <template #default="{ row }">
                                <div class="price-cell">
                                    <span class="price-item" v-if="row.price_monthly > 0">月 ¥{{ row.price_monthly }}</span>
                                    <span class="price-item" v-if="row.price_quarterly > 0">季 ¥{{ row.price_quarterly }}</span>
                                    <span class="price-item" v-if="row.price_semi_annually > 0">半年 ¥{{ row.price_semi_annually }}</span>
                                    <span class="price-item" v-if="row.price_yearly > 0">年 ¥{{ row.price_yearly }}</span>
                                    <span v-if="!row.price_monthly && !row.price_quarterly && !row.price_semi_annually && !row.price_yearly" class="text-muted">免费</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="试用" width="60" align="center"><template #default="{ row }">{{ row.trial_days ? row.trial_days+'天' : '-' }}</template></el-table-column>
                        <el-table-column label="产品" min-width="120">
                            <template #default="{ row }">
                                <span v-if="row.product_id">{{ getProductName(row.product_id) || 'ID:'+row.product_id }}</span>
                                <span v-else class="text-muted">通用</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80" align="center">
                            <template #default="{ row }">
                                <el-tooltip :content="row.is_active ? '已上架' : '已停用'" placement="top">
                                    <el-switch :model-value="!!row.is_active" size="small" @click="togglePlanActive(row)" />
                                </el-tooltip>
                            </template>
                        </el-table-column>
                        <el-table-column label="排序" width="55" align="center"><template #default="{ row }">{{ row.sort_order }}</template></el-table-column>
                        <el-table-column label="操作" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="openPlanDialog(row)">编辑</el-button>
                                <el-popconfirm title="确定删除此套餐？" @confirm="deletePlan(row)">
                                    <template #reference><el-button size="small" type="danger">删除</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="20" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadPlans" /></div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 2: 捆绑规则 -->
            <el-tab-pane label="捆绑规则" name="bundles">
                <el-card shadow="never" class="mb-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-400">设置套餐捆绑销售规则</span>
                        <el-button type="primary" size="small" @click="openBundleForm()">+ 新增捆绑</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="bundleRules" stripe>
                        <el-table-column label="主套餐" width="160"><template #default="{ row }">{{ row.parent_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="被捆绑套餐" width="160"><template #default="{ row }">{{ row.included_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="类型" width="100"><template #default="{ row }"><el-tag size="small">{{ {optional:'可选',required:'必选',upgrade:'升级'}[row.type] }}</el-tag></template></el-table-column>
                        <el-table-column label="折扣%" width="80"><template #default="{ row }">{{ row.discount_percent }}%</template></el-table-column>
                        <el-table-column label="固定扣减" width="100"><template #default="{ row }">{{ row.fixed_discount ? '¥'+row.fixed_discount : '-' }}</template></el-table-column>
                        <el-table-column label="排序" width="60"><template #default="{ row }">{{ row.sort_order }}</template></el-table-column>
                        <el-table-column label="状态" width="70"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template></el-table-column>
                        <el-table-column label="操作" width="140">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openBundleForm(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteBundle(row.id)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 3: 升级路径 -->
            <el-tab-pane label="升级路径" name="paths">
                <el-card shadow="never" class="mb-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-400">设置套餐间的升降级折算规则</span>
                        <el-button type="primary" size="small" @click="openPathForm()">+ 新增路径</el-button>
                    </div>
                </el-card>
                <el-card shadow="never">
                    <el-table :data="upgradePaths" stripe>
                        <el-table-column label="从套餐" width="160"><template #default="{ row }">{{ row.from_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="到套餐" width="160"><template #default="{ row }">{{ row.to_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="折算比例" width="100"><template #default="{ row }">{{ (row.proration_ratio * 100) + '%' }}</template></el-table-column>
                        <el-table-column label="额外费用" width="100"><template #default="{ row }">{{ row.additional_fee ? '¥'+row.additional_fee : '-' }}</template></el-table-column>
                        <el-table-column label="允许降级" width="90"><template #default="{ row }"><el-tag :type="row.allow_downgrade ? 'warning' : 'info'" size="small">{{ row.allow_downgrade ? '是' : '否' }}</el-tag></template></el-table-column>
                        <el-table-column label="状态" width="70"><template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template></el-table-column>
                        <el-table-column label="操作" width="140">
                            <template #default="{ row }">
                                <el-button size="small" text @click="openPathForm(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deletePath(row.id)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>

            <!-- Tab 4: 升级计算器 -->
            <el-tab-pane label="升级计算器" name="calculator">
                <el-card shadow="never">
                    <el-form :model="calcForm" label-width="120px" class="w-96">
                        <el-form-item label="源套餐ID"><el-input-number v-model="calcForm.from_plan_id" :min="1" /></el-form-item>
                        <el-form-item label="目标套餐ID"><el-input-number v-model="calcForm.to_plan_id" :min="1" /></el-form-item>
                        <el-form-item label="计费周期"><el-select v-model="calcForm.billing_period"><el-option label="月付" value="monthly" /><el-option label="季付" value="quarterly" /><el-option label="半年付" value="semi_annually" /><el-option label="年付" value="yearly" /></el-select></el-form-item>
                        <el-form-item><el-button type="primary" @click="calculateUpgrade">计算</el-button></el-form-item>
                    </el-form>
                    <div v-if="calcResult" class="mt-4 p-4 bg-gray-50 rounded">
                        <p><strong>类型：</strong><el-tag :type="calcResult.type === 'upgrade' ? 'success' : calcResult.type === 'downgrade' ? 'warning' : 'info'">{{ {upgrade:'升级',downgrade:'降级',crossgrade:'平级'}[calcResult.type] }}</el-tag></p>
                        <p><strong>原价：</strong>¥{{ calcResult.from_price }}</p>
                        <p><strong>新价：</strong>¥{{ calcResult.to_price }}</p>
                        <p><strong>剩余价值抵扣：</strong>¥{{ calcResult.credit }}</p>
                        <p><strong>需补差价：</strong>¥{{ calcResult.charge }}</p>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- Tab 5: 升级日志 -->
            <el-tab-pane label="升级日志" name="logs">
                <el-card shadow="never">
                    <el-table :data="upgradeLogs" stripe>
                        <el-table-column label="类型" width="80"><template #default="{ row }"><el-tag :type="row.type === 'upgrade' ? 'success' : row.type === 'downgrade' ? 'warning' : 'info'" size="small">{{ {upgrade:'升级',downgrade:'降级',crossgrade:'平级'}[row.type] }}</el-tag></template></el-table-column>
                        <el-table-column label="原套餐" width="140"><template #default="{ row }">{{ row.from_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="新套餐" width="140"><template #default="{ row }">{{ row.to_plan?.name || '-' }}</template></el-table-column>
                        <el-table-column label="抵扣" width="80"><template #default="{ row }">¥{{ row.credit }}</template></el-table-column>
                        <el-table-column label="补差价" width="80"><template #default="{ row }">¥{{ row.charge }}</template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag type="success">{{ row.status }}</el-tag></template></el-table-column>
                        <el-table-column label="操作人" width="100"><template #default="{ row }">{{ row.operator?.name || '-' }}</template></el-table-column>
                        <el-table-column label="时间" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 套餐编辑对话框 -->
        <el-dialog v-model="planDialog" :title="planEditing ? '编辑套餐' : '新增套餐'" width="780px" :close-on-click-modal="false">
            <el-form ref="planFormRef" :model="planForm" :rules="planRules" label-width="110px" size="default">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="名称" prop="name">
                            <el-input v-model="planForm.name" maxlength="255" placeholder="如：专业版" @input="onNameInput" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="标识(Slug)" prop="slug">
                            <el-input v-model="planForm.slug" placeholder="留空自动生成" maxlength="255">
                                <template #append><el-button text @click="planForm.slug = autoSlug(planForm.name)">🔄</el-button></template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="planForm.description" type="textarea" :rows="2" maxlength="500" placeholder="套餐简短描述，显示在定价页" />
                </el-form-item>

                <div class="form-section-title">💳 定价</div>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="月付(¥)"><el-input-number v-model="planForm.price_monthly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="季付(¥)"><el-input-number v-model="planForm.price_quarterly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="半年付(¥)"><el-input-number v-model="planForm.price_semi_annually" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="年付(¥)"><el-input-number v-model="planForm.price_yearly" :min="0" :precision="2" style="width:100%" /></el-form-item></el-col>
                </el-row>

                <div class="form-section-title">⚙️ 配置</div>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="关联产品">
                            <el-select v-model="planForm.product_id" clearable placeholder="不关联（通用套餐）" style="width:100%" filterable>
                                <el-option v-for="p in products" :key="p.id" :label="p.name + (p.slug ? ' ('+p.slug+')' : '')" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="币种">
                            <el-select v-model="planForm.currency" style="width:100%">
                                <el-option label="CNY" value="CNY" />
                                <el-option label="USD" value="USD" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="试用天数">
                            <el-input-number v-model="planForm.trial_days" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="排序">
                            <el-input-number v-model="planForm.sort_order" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="徽章">
                            <el-select v-model="planForm.badge" clearable style="width:100%" placeholder="无">
                                <el-option label="⭐ 最受欢迎" value="popular" />
                                <el-option label="🏆 最佳价值" value="best_value" />
                                <el-option label="🔥 热销" value="hot" />
                                <el-option label="🆕 新品" value="new" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item label="活跃"><el-switch v-model="planForm.is_active" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item label="公开可见"><el-switch v-model="planForm.is_public" /></el-form-item></el-col>
                </el-row>

                <div class="form-section-title">� 限制配置</div>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item label="API Key 上限">
                            <el-input-number v-model="planForm.limits.max_api_keys" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">-1 表示无限制</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最大产品数">
                            <el-input-number v-model="planForm.limits.max_products" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">-1 表示无限制</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="团队成员数">
                            <el-input-number v-model="planForm.limits.team_members" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">-1 表示无限制</div>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item label="API 速率限制">
                            <el-input-number v-model="planForm.limits.api_rate_limit" :min="0" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">每分钟请求数</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="最大激活数">
                            <el-input-number v-model="planForm.limits.max_activations" :min="-1" style="width:100%" />
                            <div style="font-size:11px;color:#909399;margin-top:2px">-1 表示无限制</div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="form-section-title">�📋 功能清单</div>
                <el-form-item label="功能列表">
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
                        <div style="font-size:12px;color:#909399;margin-bottom:6px" v-if="!planForm.features.length">暂无功能，请在下方添加</div>
                        <div class="feature-input-row">
                            <el-input
                                v-model="planForm.featureInput"
                                placeholder="输入功能描述，按回车添加"
                                size="default"
                                style="flex:1"
                                @keydown="featureKeydown"
                            />
                            <el-button type="primary" size="default" @click="addFeature" :disabled="!planForm.featureInput?.trim()">添加</el-button>
                        </div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="planDialog = false">取消</el-button>
                <el-button type="primary" :loading="planSubmitting" @click="submitPlan">{{ planEditing ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>

        <!-- 捆绑规则对话框 -->
        <el-dialog v-model="bundleDialog" :title="bundleEditing ? '编辑捆绑规则' : '新增捆绑规则'" width="450px">
            <el-form :model="bundleForm" label-width="110px">
                <el-form-item label="主套餐ID"><el-input-number v-model="bundleForm.parent_plan_id" :min="1" /></el-form-item>
                <el-form-item label="被捆绑套餐ID"><el-input-number v-model="bundleForm.included_plan_id" :min="1" /></el-form-item>
                <el-form-item label="类型"><el-select v-model="bundleForm.type" class="w-full"><el-option label="可选" value="optional" /><el-option label="必选" value="required" /><el-option label="升级" value="upgrade" /></el-select></el-form-item>
                <el-form-item label="折扣%"><el-input-number v-model="bundleForm.discount_percent" :min="0" :max="100" /></el-form-item>
                <el-form-item label="固定扣减(¥)"><el-input-number v-model="bundleForm.fixed_discount" :min="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="bundleForm.sort_order" :min="0" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="bundleForm.is_active" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="bundleDialog = false">取消</el-button><el-button type="primary" @click="submitBundle">{{ bundleEditing ? '保存' : '创建' }}</el-button></template>
        </el-dialog>

        <!-- 升级路径对话框 -->
        <el-dialog v-model="pathDialog" :title="pathEditing ? '编辑升级路径' : '新增升级路径'" width="450px">
            <el-form :model="pathForm" label-width="110px">
                <el-form-item label="源套餐ID"><el-input-number v-model="pathForm.from_plan_id" :min="1" /></el-form-item>
                <el-form-item label="目标套餐ID"><el-input-number v-model="pathForm.to_plan_id" :min="1" /></el-form-item>
                <el-form-item label="折算比例"><el-input-number v-model="pathForm.proration_ratio" :min="0" :max="1" :step="0.1" /></el-form-item>
                <el-form-item label="额外费用(¥)"><el-input-number v-model="pathForm.additional_fee" :min="0" /></el-form-item>
                <el-form-item label="允许降级"><el-switch v-model="pathForm.allow_downgrade" /></el-form-item>
                <el-form-item label="启用"><el-switch v-model="pathForm.is_active" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="pathDialog = false">取消</el-button><el-button type="primary" @click="submitPath">{{ pathEditing ? '保存' : '创建' }}</el-button></template>
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
    background: #409eff; border-radius: 2px; flex-shrink: 0;
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
</style>
