<template>
    <div class="coupon-management">
        <div class="page-header">
            <div>
                <h2>{{ t('coupon_page.title') }}</h2>
                <p class="text-muted">{{ t('coupon_page.subtitle') }}</p>
            </div>
            <el-button type="primary" @click="openCreateDialog">
                <el-icon><Plus /></el-icon> {{ t('coupon_page.buttons.create') }}
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total }}</div>
                        <div class="stat-label">{{ t('coupon_page.stats.total') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #67c23a;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a;">{{ stats.active }}</div>
                        <div class="stat-label">{{ t('coupon_page.stats.active') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #e6a23c;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c;">{{ stats.total_redemptions || 0 }}</div>
                        <div class="stat-label">{{ t('coupon_page.stats.total_redemptions') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #f56c6c;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c;">¥{{ formatPrice(stats.total_discount || 0) }}</div>
                        <div class="stat-label">{{ t('coupon_page.stats.total_discount') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small" @keyup.enter="doSearch">
                <el-form-item :label="t('coupon_page.filters.status')">
                    <el-select v-model="filters.status" clearable :placeholder="t('coupon_page.placeholders.all')" style="width: 120px">
                        <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('coupon_page.filters.type')">
                    <el-select v-model="filters.type" clearable :placeholder="t('coupon_page.placeholders.all')" style="width: 120px">
                        <el-option v-for="opt in typeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('coupon_page.filters.search')">
                    <el-input v-model="filters.search" :placeholder="t('coupon_page.placeholders.search')" clearable style="width: 180px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 批量操作 -->
        <div class="mb-4" v-if="selectedIds.length > 0">
            <el-alert :title="t('coupon_page.selected_count', { n: selectedIds.length })" type="info" show-icon :closable="false">
                <template #action>
                    <el-button size="small" type="success" @click="batchToggleStatus('active')">{{ t('coupon_page.buttons.batch_enable') }}</el-button>
                    <el-button size="small" type="warning" @click="batchToggleStatus('disabled')">{{ t('coupon_page.buttons.batch_disable') }}</el-button>
                    <el-button size="small" @click="selectedIds = []">{{ t('coupon_page.buttons.clear_selection') }}</el-button>
                </template>
            </el-alert>
        </div>

        <!-- 优惠券列表 -->
        <el-card shadow="never">
            <el-table :data="coupons" v-loading="loading" stripe @selection-change="onSelectionChange">
                <el-table-column type="selection" width="40" />
                <el-table-column prop="code" :label="t('coupon_page.cols.code')" width="140">
                    <template #default="{ row }">
                        <el-tag effect="dark" size="small">{{ row.code }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="t('coupon_page.cols.name')" width="140" />
                <el-table-column prop="type" :label="t('coupon_page.cols.type')" width="100">
                    <template #default="{ row }">{{ typeLabel(row.type) }}</template>
                </el-table-column>
                <el-table-column prop="value" :label="t('coupon_page.cols.value')" width="100">
                    <template #default="{ row }">
                        {{ row.type === 'percentage' ? `${row.value}%` : `¥${row.value}` }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('coupon_page.cols.usage_count')" width="110">
                    <template #default="{ row }">
                        {{ row.usage_count || 0 }}/{{ row.usage_limit || '∞' }}
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('coupon_page.cols.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row)" size="small">{{ statusLabel(row) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('coupon_page.cols.validity')" width="200">
                    <template #default="{ row }">
                        {{ row.starts_at ? row.starts_at.substring(0, 10) : '-' }} ~
                        {{ row.expires_at ? row.expires_at.substring(0, 10) : t('coupon_page.never_expires') }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('coupon_page.cols.actions')" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">{{ t('coupon_page.buttons.detail') }}</el-button>
                        <el-button type="primary" link size="small" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button type="primary" link size="small" @click="viewRedemptions(row)">{{ t('coupon_page.buttons.records') }}</el-button>
                        <el-button v-if="row.status === 'active'" type="warning" link size="small" @click="toggleStatus(row, 'disabled')">{{ t('actions.disable') }}</el-button>
                        <el-button v-else type="success" link size="small" @click="toggleStatus(row, 'active')">{{ t('actions.enable') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex-center" v-if="total > perPage">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @size-change="loadCoupons"
                    @current-change="loadCoupons"
                />
            </div>
        </el-card>

        <!-- 新建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? t('coupon_page.dialogs.edit') : t('coupon_page.dialogs.create')" width="600px">
            <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
                <el-form-item :label="t('coupon_page.cols.code')" prop="code">
                    <el-input v-model="form.code" :placeholder="t('coupon_page.placeholders.code')" :disabled="isEdit">
                        <template #append>
                            <el-button @click="generateCode">{{ t('coupon_page.buttons.generate_code') }}</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="t('coupon_page.cols.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('coupon_page.placeholders.name')" />
                </el-form-item>
                <el-form-item :label="t('coupon_page.cols.description')">
                    <el-input v-model="form.description" type="textarea" :rows="2" :placeholder="t('coupon_page.placeholders.description')" />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.cols.type')" prop="type">
                            <el-select v-model="form.type" style="width:100%">
                                <el-option v-for="opt in formTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.cols.value')" prop="value">
                            <el-input v-model.number="form.value" :min="0" type="number">
                                <template #append>{{ form.type === 'percentage' ? '%' : '¥' }}</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.min_order')">
                            <el-input v-model.number="form.minimum_order_amount" :min="0" type="number" :placeholder="t('coupon_page.placeholders.no_limit')">
                                <template #append>¥</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.max_discount')">
                            <el-input v-model.number="form.maximum_discount" :min="0" type="number" :placeholder="t('coupon_page.placeholders.no_limit')">
                                <template #append>¥</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.usage_limit')">
                            <el-input v-model.number="form.usage_limit" :min="0" type="number" :placeholder="t('coupon_page.placeholders.no_limit')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.usage_limit_per_user')">
                            <el-input v-model.number="form.usage_limit_per_user" :min="0" type="number" :placeholder="t('coupon_page.placeholders.no_limit')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.cols.starts_at')" prop="starts_at">
                            <el-date-picker v-model="form.starts_at" type="datetime" :placeholder="t('coupon_page.placeholders.starts_at')" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.cols.expires_at')" prop="expires_at">
                            <el-date-picker v-model="form.expires_at" type="datetime" :placeholder="t('coupon_page.placeholders.expires_at')" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <!-- 适用限制 -->
                <el-form-item :label="t('coupon_page.form.applicable_plans')">
                    <el-select v-model="form.applicable_plans" multiple clearable :placeholder="t('coupon_page.placeholders.all_plans')" style="width:100%" :loading="loadingPlans">
                        <el-option v-for="p in planOptions" :key="p.id" :label="p.name" :value="p.slug" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('coupon_page.form.applicable_products')">
                    <el-select v-model="form.applicable_products" multiple clearable :placeholder="t('coupon_page.placeholders.all_products')" style="width:100%" :loading="loadingProducts" value-key="id">
                        <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('coupon_page.form.applicable_periods')">
                    <el-select v-model="form.applicable_billing_periods" multiple clearable :placeholder="t('coupon_page.placeholders.all_periods')" style="width:100%">
                        <el-option v-for="opt in billingPeriodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <!-- 高级设置 -->
                <el-divider>{{ t('coupon_page.form.advanced') }}</el-divider>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.priority')">
                            <el-input-number v-model="form.priority" :min="0" :max="999" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('coupon_page.form.budget')">
                            <el-input v-model.number="form.budget" :min="0" type="number" :placeholder="t('coupon_page.placeholders.no_limit')">
                                <template #append>¥</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('coupon_page.form.stack_options')">
                    <el-checkbox v-model="form.is_redeemable_with_other_coupons" :label="t('coupon_page.form.redeemable_with_other')" border />
                    <el-checkbox v-model="form.is_stackable" :label="t('coupon_page.form.stackable')" border class="ml-2" />
                    <el-checkbox v-model="form.auto_apply" :label="t('coupon_page.form.auto_apply')" border class="ml-2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('coupon_page.dialogs.detail')" width="500px">
            <template v-if="currentCoupon">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('coupon_page.cols.code')">{{ currentCoupon.code }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.name')">{{ currentCoupon.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.type')">{{ typeLabel(currentCoupon.type) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.status')">
                        <el-tag :type="statusType(currentCoupon)" size="small">{{ statusLabel(currentCoupon) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.value')">{{ currentCoupon.type === 'percentage' ? `${currentCoupon.value}%` : `¥${currentCoupon.value}` }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.usage_count')">{{ currentCoupon.usage_count || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.form.min_order')">¥{{ currentCoupon.minimum_order_amount || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.form.max_discount')">¥{{ currentCoupon.maximum_discount || 0 }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.starts_at')">{{ currentCoupon.starts_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('coupon_page.cols.expires_at')">{{ currentCoupon.expires_at || t('coupon_page.never_expires') }}</el-descriptions-item>
                </el-descriptions>
                <div v-if="currentCoupon.description" class="mt-4">
                    <strong>{{ t('coupon_page.description_prefix') }}</strong>
                    <p>{{ currentCoupon.description }}</p>
                </div>
            </template>
        </el-dialog>

        <!-- 使用记录对话框 -->
        <el-dialog v-model="redemptionVisible" :title="t('coupon_page.dialogs.redemption_title', { code: currentCoupon?.code || '' })" width="700px">
            <el-table :data="redemptions" v-loading="redemptionLoading" stripe size="small">
                <el-table-column prop="customer_id" :label="t('coupon_page.cols.customer_id')" width="80" />
                <el-table-column prop="order_id" :label="t('coupon_page.cols.order_id')" width="80" />
                <el-table-column prop="original_amount" :label="t('coupon_page.cols.original_amount')" width="100">
                    <template #default="{ row }">¥{{ row.original_amount }}</template>
                </el-table-column>
                <el-table-column prop="discount_amount" :label="t('coupon_page.cols.discount_amount')" width="100">
                    <template #default="{ row }">-¥{{ row.discount_amount }}</template>
                </el-table-column>
                <el-table-column prop="final_amount" :label="t('coupon_page.cols.final_amount')" width="100">
                    <template #default="{ row }">¥{{ row.final_amount }}</template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('coupon_page.cols.used_at')" width="170" />
            </el-table>
            <el-empty v-if="!redemptionLoading && !redemptions.length" :description="t('coupon_page.no_redemptions')" />
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import billingApi from '@/api/billing'
import productApi from '@/api/product'

const { t } = useI18n()

const loading = ref(false)
const coupons = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const stats = ref({ total: 0, active: 0, total_redemptions: 0, total_discount: 0 })

const dialogVisible = ref(false)
const isEdit = ref(false)
const editId = ref(null)
const formRef = ref(null)
const saving = ref(false)

const detailVisible = ref(false)
const currentCoupon = ref(null)

const redemptionVisible = ref(false)
const redemptions = ref([])
const redemptionLoading = ref(false)

const selectedIds = ref([])

const planOptions = ref([])
const productOptions = ref([])
const loadingPlans = ref(false)
const loadingProducts = ref(false)

const filters = reactive({
    status: '',
    type: '',
    search: '',
})

const form = reactive({
    code: '',
    name: '',
    description: '',
    type: 'percentage',
    value: 0,
    minimum_order_amount: 0,
    maximum_discount: 0,
    usage_limit: 0,
    usage_limit_per_user: 0,
    applicable_plans: [],
    applicable_products: [],
    applicable_billing_periods: [],
    starts_at: '',
    expires_at: '',
    is_redeemable_with_other_coupons: false,
    is_stackable: false,
    auto_apply: false,
    priority: 0,
    budget: 0,
})

const typeLabels = computed(() => ({
    percentage: t('coupon_page.types.percentage'),
    fixed_amount: t('coupon_page.types.fixed_amount'),
    free_trial: t('coupon_page.types.free_trial'),
}))

const statusLabels = computed(() => ({
    disabled: t('coupon_page.status.disabled'),
    expired: t('coupon_page.status.expired'),
    pending: t('coupon_page.status.pending'),
    active: t('coupon_page.status.active'),
}))

const statusFilterOptions = computed(() => [
    { value: 'active', label: statusLabels.value.active },
    { value: 'expired', label: statusLabels.value.expired },
    { value: 'disabled', label: statusLabels.value.disabled },
    { value: 'pending', label: statusLabels.value.pending },
])

const typeFilterOptions = computed(() => [
    { value: 'percentage', label: typeLabels.value.percentage },
    { value: 'fixed_amount', label: typeLabels.value.fixed_amount },
    { value: 'free_trial', label: typeLabels.value.free_trial },
])

const formTypeOptions = computed(() => [
    { value: 'percentage', label: t('coupon_page.types.percentage_discount') },
    { value: 'fixed_amount', label: typeLabels.value.fixed_amount },
    { value: 'free_trial', label: typeLabels.value.free_trial },
])

const billingPeriodOptions = computed(() => [
    { value: 'monthly', label: t('coupon_page.billing_periods.monthly') },
    { value: 'quarterly', label: t('coupon_page.billing_periods.quarterly') },
    { value: 'semi_annually', label: t('coupon_page.billing_periods.semi_annually') },
    { value: 'yearly', label: t('coupon_page.billing_periods.yearly') },
])

const rules = computed(() => ({
    code: [{ required: true, message: t('coupon_page.validation.code_required'), trigger: 'blur' }],
    name: [{ required: true, message: t('coupon_page.validation.name_required'), trigger: 'blur' }],
    type: [{ required: true, message: t('coupon_page.validation.type_required'), trigger: 'change' }],
    value: [{ required: true, type: 'number', min: 0.01, message: t('coupon_page.validation.value_required'), trigger: 'blur' }],
}))

function typeLabel(type) {
    return typeLabels.value[type] || type
}

function statusLabel(coupon) {
    const now = new Date()
    if (coupon.status === 'disabled') return statusLabels.value.disabled
    if (coupon.expires_at && new Date(coupon.expires_at) < now) return statusLabels.value.expired
    if (coupon.starts_at && new Date(coupon.starts_at) > now) return statusLabels.value.pending
    return statusLabels.value.active
}

function statusType(coupon) {
    const now = new Date()
    if (coupon.status === 'disabled') return 'info'
    if (coupon.expires_at && new Date(coupon.expires_at) < now) return 'danger'
    if (coupon.starts_at && new Date(coupon.starts_at) > now) return 'warning'
    return 'success'
}

function formatPrice(v) { return (Number(v) || 0).toFixed(2) }

function generateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
    let code = ''
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    form.code = code
}

async function loadStats() {
    try {
        const res = await billingApi.getCouponStats()
        stats.value = res.data?.data || res.data || stats.value
    } catch { /* ignore */ }
}

async function loadCoupons() {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filters.status) params.status = filters.status
        if (filters.type) params.type = filters.type
        if (filters.search) params.search = filters.search

        const res = await billingApi.getCoupons(params)
        const d = res.data?.data || res.data || []
        coupons.value = d.data || (Array.isArray(d) ? d : [])
        total.value = d.total || coupons.value.length
    } catch (e) {
        ElMessage.error(t('coupon_page.messages.load_failed'))
    } finally {
        loading.value = false
    }
}

function doSearch() { page.value = 1; loadCoupons() }
function resetFilters() {
    filters.status = ''
    filters.type = ''
    filters.search = ''
    doSearch()
}

function openCreateDialog() {
    isEdit.value = false
    editId.value = null
    form.code = ''
    form.name = ''
    form.description = ''
    form.type = 'percentage'
    form.value = 0
    form.minimum_order_amount = 0
    form.maximum_discount = 0
    form.usage_limit = 0
    form.usage_limit_per_user = 0
    form.applicable_plans = []
    form.applicable_products = []
    form.applicable_billing_periods = []
    form.starts_at = ''
    form.expires_at = ''
    form.is_redeemable_with_other_coupons = false
    form.is_stackable = false
    form.auto_apply = false
    form.priority = 0
    form.budget = 0
    dialogVisible.value = true
}

function openEditDialog(row) {
    isEdit.value = true
    editId.value = row.id
    Object.assign(form, {
        code: row.code,
        name: row.name,
        description: row.description || '',
        type: row.type,
        value: row.value,
        minimum_order_amount: row.minimum_order_amount || 0,
        maximum_discount: row.maximum_discount || 0,
        usage_limit: row.usage_limit || 0,
        usage_limit_per_user: row.usage_limit_per_user || 0,
        applicable_plans: row.applicable_plans || [],
        applicable_products: row.applicable_products ? row.applicable_products.map(Number) : [],
        applicable_billing_periods: row.applicable_billing_periods || [],
        starts_at: row.starts_at || '',
        expires_at: row.expires_at || '',
        is_redeemable_with_other_coupons: row.is_redeemable_with_other_coupons ?? false,
        is_stackable: row.is_stackable ?? false,
        auto_apply: row.auto_apply ?? false,
        priority: row.priority ?? 0,
        budget: row.budget || 0,
    })
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        if (isEdit.value) {
            await billingApi.updateCoupon(editId.value, form)
            ElMessage.success(t('coupon_page.messages.updated'))
        } else {
            await billingApi.createCoupon(form)
            ElMessage.success(t('coupon_page.messages.created'))
        }
        dialogVisible.value = false
        loadCoupons()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('coupon_page.messages.save_failed'))
    } finally {
        saving.value = false
    }
}

function viewDetail(row) {
    currentCoupon.value = row
    detailVisible.value = true
}

async function viewRedemptions(row) {
    currentCoupon.value = row
    redemptionVisible.value = true
    redemptionLoading.value = true
    try {
        const res = await billingApi.getCouponRedemptions(row.id)
        const d = res.data?.data || res.data || []
        redemptions.value = Array.isArray(d) ? d : (d.data || [])
    } catch {
        redemptions.value = []
    } finally {
        redemptionLoading.value = false
    }
}

/** 切换单个优惠券状态 */
async function toggleStatus(row, newStatus) {
    try {
        await billingApi.updateCoupon(row.id, { status: newStatus })
        ElMessage.success(newStatus === 'active' ? t('coupon_page.messages.enabled') : t('coupon_page.messages.disabled'))
        loadCoupons()
        loadStats()
    } catch (e) {
        ElMessage.error(t('coupon_page.messages.toggle_failed'))
    }
}

/** 批量切换状态 */
async function batchToggleStatus(newStatus) {
    try {
        const promises = selectedIds.value.map(id => billingApi.updateCoupon(id, { status: newStatus }))
        await Promise.all(promises)
        const msgKey = newStatus === 'active' ? 'coupon_page.messages.batch_enabled' : 'coupon_page.messages.batch_disabled'
        ElMessage.success(t(msgKey, { n: selectedIds.value.length }))
        selectedIds.value = []
        loadCoupons()
        loadStats()
    } catch (e) {
        ElMessage.error(t('coupon_page.messages.batch_failed'))
    }
}

/** 选择变更 */
function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id)
}

/** 加载方案列表 */
async function loadPlans() {
    loadingPlans.value = true
    try {
        const res = await billingApi.getPlans({ per_page: 999 })
        const d = res.data?.data || res.data || []
        planOptions.value = d.data || (Array.isArray(d) ? d : [])
    } catch { /* ignore */ }
    finally { loadingPlans.value = false }
}

/** 加载产品列表 */
async function loadProducts() {
    loadingProducts.value = true
    try {
        const res = await productApi.list({ per_page: 999 })
        const d = res.data?.data || res.data || []
        productOptions.value = d.data || (Array.isArray(d) ? d : [])
    } catch { /* ignore */ }
    finally { loadingProducts.value = false }
}

onMounted(() => {
    loadStats()
    loadCoupons()
    loadPlans()
    loadProducts()
})
</script>

<style scoped>
.coupon-management { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0 0 4px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.flex-center { display: flex; justify-content: center; }
.text-muted { color: #909399; font-size: 13px; }
</style>
