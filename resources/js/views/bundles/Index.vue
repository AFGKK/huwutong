<template>
    <div class="bundle-manager-page">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_bundles }}</div>
                    <div class="stat-label">{{ t('bundles_page.stat_total_bundles') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.active_bundles }}</div>
                    <div class="stat-label">{{ t('bundles_page.stat_active') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_purchases }}</div>
                    <div class="stat-label">{{ t('bundles_page.stat_total_purchases') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_revenue) }}</div>
                    <div class="stat-label">{{ t('bundles_page.stat_total_revenue') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" @tab-change="loadData">
            <el-tab-pane :label="t('bundles_page.tab_bundles')" name="bundles">
                <!-- 操作栏 -->
                <el-card class="search-card">
                    <el-row :gutter="16">
                        <el-col :span="6">
                            <el-input v-model="filters.search" :placeholder="t('bundles_page.search_ph')" clearable @clear="loadList" @keyup.enter="loadList" />
                        </el-col>
                        <el-col :span="18" style="text-align: right">
                            <el-button type="primary" @click="showCreateDialog">
                                <el-icon><Plus /></el-icon> {{ t('bundles_page.create_bundle') }}
                            </el-button>
                            <el-button @click="loadList">{{ t('bundles_page.refresh') }}</el-button>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- 套餐列表 -->
                <el-card class="table-card">
                    <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
                        <el-table-column prop="name" :label="t('bundles_page.col_name')" min-width="200">
                            <template #default="{ row }">
                                <div class="bundle-name">
                                    <span>{{ row.name }}</span>
                                    <el-tag v-if="row.is_featured" type="warning" size="small" effect="dark">{{ t('products_page.featured_yes') }}</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_original_price')" width="130">
                            <template #default="{ row }">
                                <span class="original-price">{{ formatMoney(row.original_price) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_bundle_price')" width="130">
                            <template #default="{ row }">
                                <span class="bundle-price">{{ formatMoney(row.bundle_price) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_discount')" width="80">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">{{ row.discount_percent }}%</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_period')" width="100">
                            <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_stock')" width="80">
                            <template #default="{ row }">{{ row.stock !== null ? row.stock : t('billing_page.unlimited') }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? t('bundles_page.status_listed') : t('bundles_page.status_unlisted') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_includes')" min-width="200">
                            <template #default="{ row }">
                                <div class="bundle-items">
                                    <el-tag v-for="item in row.items" :key="item.id" size="small" style="margin: 1px">
                                        {{ item.name }} x{{ item.quantity }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="showEditDialog(row)">{{ t('actions.edit') }}</el-button>
                                <el-button size="small" @click="showPurchaseDialog(row)">{{ t('bundles_page.btn_purchase') }}</el-button>
                                <el-popconfirm :title="t('bundles_page.delete_confirm')" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="currentPage"
                            :page-size="perPage"
                            :total="total"
                            layout="total, prev, pager, next"
                            @current-change="loadList"
                        />
                    </div>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('bundles_page.tab_purchases')" name="purchases">
                <el-card class="search-card">
                    <el-row :gutter="16">
                        <el-col :span="6">
                            <el-input v-model="purchaseFilters.customer_id" :placeholder="t('bundles_page.customer_id_ph')" clearable @clear="loadPurchases" @keyup.enter="loadPurchases" />
                        </el-col>
                        <el-col :span="4">
                            <el-select v-model="purchaseFilters.status" :placeholder="t('billing_page.col_status')" clearable @change="loadPurchases" style="width: 100%">
                                <el-option
                                    v-for="opt in purchaseStatusOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-col>
                        <el-col :span="14" style="text-align: right">
                            <el-button @click="loadPurchases">{{ t('bundles_page.refresh') }}</el-button>
                        </el-col>
                    </el-row>
                </el-card>
                <el-card class="table-card">
                    <el-table :data="purchaseList" v-loading="loadingPurchases" border stripe>
                        <el-table-column prop="order_no" :label="t('bundles_page.col_order_no')" width="200" />
                        <el-table-column :label="t('bundles_page.col_bundle')" width="200">
                            <template #default="{ row }">{{ row.bundle?.name || '#' + row.product_bundle_id }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_customer')" width="150">
                            <template #default="{ row }">{{ row.customer?.name || t('bundles_page.customer_fallback', { id: row.customer_id }) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_paid_amount')" width="130">
                            <template #default="{ row }">{{ formatMoney(row.paid_amount) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('billing_page.col_status')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'completed' ? 'success' : 'warning'" size="small">
                                    {{ purchaseStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('bundles_page.col_purchased_at')" width="170">
                            <template #default="{ row }">{{ row.purchased_at || row.created_at }}</template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap">
                        <el-pagination
                            v-model:current-page="purchasePage"
                            :page-size="purchasePerPage"
                            :total="purchaseTotal"
                            layout="total, prev, pager, next"
                            @current-change="loadPurchases"
                        />
                    </div>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 编辑/新建对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? t('bundles_page.edit_title') : t('bundles_page.create_title')"
            width="700px"
            :close-on-click-modal="false"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('bundles_page.field_name')" prop="name">
                            <el-input v-model="form.name" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('bundles_page.field_slug')">
                            <el-input v-model="form.slug" :placeholder="t('bundles_page.slug_ph')" maxlength="255" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="t('bundles_page.field_bundle_price')" prop="bundle_price">
                            <el-input-number v-model="form.bundle_price" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('bundles_page.field_currency')">
                            <el-select v-model="form.currency" style="width: 100%">
                                <el-option
                                    v-for="opt in currencyOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('bundles_page.field_billing_period')">
                            <el-select v-model="form.billing_period" style="width: 100%">
                                <el-option
                                    v-for="opt in billingPeriodOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('bundles_page.field_stock')">
                            <el-input-number v-model="form.stock" :min="0" :placeholder="t('bundles_page.stock_ph')" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('bundles_page.field_max_purchase')">
                            <el-input-number v-model="form.max_purchase_per_user" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('bundles_page.field_description')">
                    <el-input v-model="form.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item :label="t('bundles_page.field_image')">
                    <el-input v-model="form.image" :placeholder="t('bundles_page.image_ph')" maxlength="500" />
                </el-form-item>
                <el-form-item :label="t('bundles_page.publish_settings')">
                    <el-checkbox v-model="form.is_active">{{ t('bundles_page.checkbox_listed') }}</el-checkbox>
                    <el-checkbox v-model="form.is_featured">{{ t('bundles_page.checkbox_featured') }}</el-checkbox>
                </el-form-item>

                <el-divider>{{ t('bundles_page.items_divider') }}</el-divider>
                <div class="items-section">
                    <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
                        <el-row :gutter="8">
                            <el-col :span="7">
                                <el-input v-model="item.name" :placeholder="t('bundles_page.item_name_ph')" size="small" />
                            </el-col>
                            <el-col :span="5">
                                <el-input-number v-model="item.original_price" :min="0" :precision="2" :placeholder="t('bundles_page.item_original_price_ph')" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="4">
                                <el-input-number v-model="item.discount_percent" :min="0" :max="100" :placeholder="t('bundles_page.item_discount_ph')" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="3">
                                <el-input-number v-model="item.quantity" :min="1" :placeholder="t('bundles_page.item_quantity_ph')" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="3">
                                <el-select v-model="item.type" size="small" style="width: 100%">
                                    <el-option
                                        v-for="opt in itemTypeOptions"
                                        :key="opt.value"
                                        :label="opt.label"
                                        :value="opt.value"
                                    />
                                </el-select>
                            </el-col>
                            <el-col :span="2" style="text-align: right">
                                <el-button type="danger" :icon="Delete" size="small" circle @click="removeItem(idx)" />
                            </el-col>
                        </el-row>
                    </div>
                    <el-button type="primary" size="small" @click="addItem">+ {{ t('bundles_page.add_item') }}</el-button>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 购买对话框 -->
        <el-dialog v-model="purchaseDialogVisible" :title="t('bundles_page.purchase_title')" width="400px">
            <p>{{ t('bundles_page.purchase_bundle_label') }} <strong>{{ purchasingBundle?.name }}</strong></p>
            <p>{{ t('bundles_page.purchase_price_label') }} <strong class="text-primary">{{ formatMoney(purchasingBundle?.bundle_price) }}</strong></p>
            <el-form :model="purchaseForm">
                <el-form-item :label="t('bundles_page.field_customer_id')">
                    <el-input-number v-model="purchaseForm.customer_id" :min="1" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="purchaseDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="purchasing" @click="submitPurchase">{{ t('bundles_page.confirm_purchase') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus, Delete } from '@element-plus/icons-vue'
import {
    getBundleList, getBundleStats, getBundleDetail,
    createBundle, updateBundle, deleteBundle,
    getBundlePurchases, purchaseBundle,
} from '@/api/bundles'

const { t } = useI18n()

const activeTab = ref('bundles')
const loading = ref(false)
const loadingPurchases = ref(false)
const submitting = ref(false)
const purchasing = ref(false)
const stats = ref({ total_bundles: 0, active_bundles: 0, total_purchases: 0, total_revenue: 0 })
const list = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const total = ref(0)
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)

const filters = ref({ search: '' })

const purchaseList = ref([])
const purchasePage = ref(1)
const purchasePerPage = ref(15)
const purchaseTotal = ref(0)
const purchaseFilters = ref({ customer_id: '', status: '' })

const purchaseDialogVisible = ref(false)
const purchasingBundle = ref(null)
const purchaseForm = ref({ customer_id: null })

const emptyForm = () => ({
    name: '', slug: '', description: '', image: '',
    bundle_price: 0, currency: 'CNY', billing_period: 'monthly',
    stock: null, max_purchase_per_user: 1,
    is_active: true, is_featured: false,
    items: [],
})

const form = ref(emptyForm())

const periodLabels = computed(() => ({
    monthly: t('shop_page.cycle_monthly'),
    yearly: t('shop_page.cycle_yearly'),
    one_time: t('shop_page.cycle_onetime'),
}))

const billingPeriodOptions = computed(() => [
    { value: 'monthly', label: periodLabels.value.monthly },
    { value: 'yearly', label: periodLabels.value.yearly },
    { value: 'one_time', label: periodLabels.value.one_time },
])

const currencyOptions = computed(() => [
    { value: 'CNY', label: t('bundles_page.currency_cny') },
    { value: 'USD', label: t('bundles_page.currency_usd') },
])

const itemTypeOptions = computed(() => [
    { value: 'plan', label: t('bundles_page.item_type_plan') },
    { value: 'product', label: t('bundles_page.item_type_product') },
    { value: 'addon', label: t('bundles_page.item_type_addon') },
])

const purchaseStatusLabels = computed(() => ({
    completed: t('bundles_page.purchase_status_completed'),
    refunded: t('bundles_page.purchase_status_refunded'),
}))

const purchaseStatusOptions = computed(() => [
    { value: 'completed', label: purchaseStatusLabels.value.completed },
    { value: 'refunded', label: purchaseStatusLabels.value.refunded },
])

const rules = computed(() => ({
    name: [{ required: true, message: t('bundles_page.name_required'), trigger: 'blur' }],
    bundle_price: [{ required: true, message: t('bundles_page.price_required'), trigger: 'blur' }],
}))

function periodLabel(p) { return periodLabels.value[p] || p }

function purchaseStatusLabel(status) {
    return purchaseStatusLabels.value[status] || status
}

function formatMoney(val) {
    if (val === undefined || val === null) return '-'
    const num = typeof val === 'number' ? val.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : val
    return `¥${num}`
}

async function loadData() {
    if (activeTab.value === 'bundles') {
        loadStats()
        loadList()
    } else {
        loadPurchases()
    }
}

async function loadStats() {
    try {
        const res = await getBundleStats()
        stats.value = res.data || res
    } catch { /* ignore */ }
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value, ...filters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getBundleList(params)
        list.value = res.data?.data || res.data || []
        total.value = res.data?.total || res.total || 0
    } catch { ElMessage.error(t('messages.load_failed')) }
    finally { loading.value = false }
}

async function loadPurchases() {
    loadingPurchases.value = true
    try {
        const params = { page: purchasePage.value, per_page: purchasePerPage.value, ...purchaseFilters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getBundlePurchases(params)
        purchaseList.value = res.data?.data || res.data || []
        purchaseTotal.value = res.data?.total || res.total || 0
    } catch { ElMessage.error(t('messages.load_failed')) }
    finally { loadingPurchases.value = false }
}

function addItem() {
    form.value.items.push({ name: '', original_price: 0, discount_percent: 0, quantity: 1, type: 'plan', itemable_type: '', itemable_id: null })
}

function removeItem(idx) {
    form.value.items.splice(idx, 1)
}

function showCreateDialog() {
    editingId.value = null
    form.value = emptyForm()
    addItem()
    dialogVisible.value = true
}

async function showEditDialog(row) {
    editingId.value = row.id
    try {
        const res = await getBundleDetail(row.id)
        const data = res.data || res
        form.value = {
            name: data.name,
            slug: data.slug || '',
            description: data.description || '',
            image: data.image || '',
            bundle_price: data.bundle_price,
            currency: data.currency,
            billing_period: data.billing_period,
            stock: data.stock,
            max_purchase_per_user: data.max_purchase_per_user,
            is_active: data.is_active,
            is_featured: data.is_featured,
            items: (data.items || []).map(it => ({
                name: it.name,
                original_price: it.original_price,
                discount_percent: it.discount_percent,
                quantity: it.quantity,
                type: it.type || 'plan',
                itemable_type: it.itemable_type || '',
                itemable_id: it.itemable_id || null,
            })),
        }
        if (!form.value.items.length) addItem()
        dialogVisible.value = true
    } catch { ElMessage.error(t('bundles_page.detail_load_failed')) }
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
        const data = {
            ...form.value,
            stock: form.value.stock > 0 ? form.value.stock : null,
        }
        if (editingId.value) {
            await updateBundle(editingId.value, data)
            ElMessage.success(t('bundles_page.update_ok'))
        } else {
            await createBundle(data)
            ElMessage.success(t('bundles_page.create_ok'))
        }
        dialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        submitting.value = false
    }
}

async function handleDelete(row) {
    try {
        await deleteBundle(row.id)
        ElMessage.success(t('bundles_page.deleted_ok'))
        loadList()
        loadStats()
    } catch { ElMessage.error(t('bundles_page.delete_failed')) }
}

function showPurchaseDialog(row) {
    purchasingBundle.value = row
    purchaseForm.value = { customer_id: null }
    purchaseDialogVisible.value = true
}

async function submitPurchase() {
    if (!purchaseForm.value.customer_id) {
        ElMessage.warning(t('bundles_page.customer_id_required'))
        return
    }
    purchasing.value = true
    try {
        await purchaseBundle(purchasingBundle.value.id, purchaseForm.value.customer_id)
        ElMessage.success(t('bundles_page.purchase_ok'))
        purchaseDialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('bundles_page.purchase_failed'))
    } finally {
        purchasing.value = false
    }
}

onMounted(() => {
    loadData()
})
</script>

<style scoped>
.bundle-manager-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; margin-bottom: 10px; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.search-card { margin-bottom: 16px; }
.table-card { margin-bottom: 20px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.bundle-name { display: flex; align-items: center; gap: 8px; }
.original-price { text-decoration: line-through; color: #909399; }
.bundle-price { color: #e6a23c; font-weight: bold; font-size: 15px; }
.bundle-items { display: flex; flex-wrap: wrap; gap: 2px; }
.items-section { margin-bottom: 16px; }
.item-row { margin-bottom: 8px; padding: 8px; background: #fafafa; border-radius: 4px; }
.text-primary { color: #0f172a; }
</style>
