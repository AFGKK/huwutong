<template>
    <div class="bundle-manager-page">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_bundles }}</div>
                    <div class="stat-label">套餐总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.active_bundles }}</div>
                    <div class="stat-label">已上架</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_purchases }}</div>
                    <div class="stat-label">总购买数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ formatMoney(stats.total_revenue) }}</div>
                    <div class="stat-label">总收入</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Tabs -->
        <el-tabs v-model="activeTab" @tab-change="loadData">
            <el-tab-pane label="套餐管理" name="bundles">
                <!-- 操作栏 -->
                <el-card class="search-card">
                    <el-row :gutter="16">
                        <el-col :span="6">
                            <el-input v-model="filters.search" placeholder="搜索套餐名称" clearable @clear="loadList" @keyup.enter="loadList" />
                        </el-col>
                        <el-col :span="18" style="text-align: right">
                            <el-button type="primary" @click="showCreateDialog">
                                <el-icon><Plus /></el-icon> 新建套餐
                            </el-button>
                            <el-button @click="loadList">刷新</el-button>
                        </el-col>
                    </el-row>
                </el-card>

                <!-- 套餐列表 -->
                <el-card class="table-card">
                    <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
                        <el-table-column prop="name" label="套餐名称" min-width="200">
                            <template #default="{ row }">
                                <div class="bundle-name">
                                    <span>{{ row.name }}</span>
                                    <el-tag v-if="row.is_featured" type="warning" size="small" effect="dark">精选</el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="原价" width="130">
                            <template #default="{ row }">
                                <span class="original-price">{{ formatMoney(row.original_price) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="套餐价" width="130">
                            <template #default="{ row }">
                                <span class="bundle-price">{{ formatMoney(row.bundle_price) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="折扣" width="80">
                            <template #default="{ row }">
                                <el-tag type="danger" size="small">{{ row.discount_percent }}%</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="周期" width="100">
                            <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                        </el-table-column>
                        <el-table-column label="库存" width="80">
                            <template #default="{ row }">{{ row.stock !== null ? row.stock : '不限' }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '上架' : '下架' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="包含" min-width="200">
                            <template #default="{ row }">
                                <div class="bundle-items">
                                    <el-tag v-for="item in row.items" :key="item.id" size="small" style="margin: 1px">
                                        {{ item.name }} x{{ item.quantity }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" type="primary" @click="showEditDialog(row)">编辑</el-button>
                                <el-button size="small" @click="showPurchaseDialog(row)">购买</el-button>
                                <el-popconfirm title="确认删除此套餐？" @confirm="handleDelete(row)">
                                    <template #reference>
                                        <el-button size="small" type="danger">删除</el-button>
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

            <el-tab-pane label="购买记录" name="purchases">
                <el-card class="search-card">
                    <el-row :gutter="16">
                        <el-col :span="6">
                            <el-input v-model="purchaseFilters.customer_id" placeholder="客户ID" clearable @clear="loadPurchases" @keyup.enter="loadPurchases" />
                        </el-col>
                        <el-col :span="4">
                            <el-select v-model="purchaseFilters.status" placeholder="状态" clearable @change="loadPurchases" style="width: 100%">
                                <el-option label="已完成" value="completed" />
                                <el-option label="已退款" value="refunded" />
                            </el-select>
                        </el-col>
                        <el-col :span="14" style="text-align: right">
                            <el-button @click="loadPurchases">刷新</el-button>
                        </el-col>
                    </el-row>
                </el-card>
                <el-card class="table-card">
                    <el-table :data="purchaseList" v-loading="loadingPurchases" border stripe>
                        <el-table-column prop="order_no" label="订单号" width="200" />
                        <el-table-column label="套餐" width="200">
                            <template #default="{ row }">{{ row.bundle?.name || '#' + row.product_bundle_id }}</template>
                        </el-table-column>
                        <el-table-column label="客户" width="150">
                            <template #default="{ row }">{{ row.customer?.name || '客户#' + row.customer_id }}</template>
                        </el-table-column>
                        <el-table-column label="实付金额" width="130">
                            <template #default="{ row }">{{ formatMoney(row.paid_amount) }}</template>
                        </el-table-column>
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'completed' ? 'success' : 'warning'" size="small">
                                    {{ row.status === 'completed' ? '已完成' : '已退款' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="购买时间" width="170">{{ row.purchased_at || row.created_at }}</el-table-column>
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
            :title="editingId ? '编辑套餐' : '新建套餐'"
            width="700px"
            :close-on-click-modal="false"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="套餐名称" prop="name">
                            <el-input v-model="form.name" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="URL标识">
                            <el-input v-model="form.slug" placeholder="留空自动生成" maxlength="255" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="套餐价" prop="bundle_price">
                            <el-input-number v-model="form.bundle_price" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="货币">
                            <el-select v-model="form.currency" style="width: 100%">
                                <el-option label="CNY ¥" value="CNY" />
                                <el-option label="USD $" value="USD" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="计费周期">
                            <el-select v-model="form.billing_period" style="width: 100%">
                                <el-option label="月付" value="monthly" />
                                <el-option label="年付" value="yearly" />
                                <el-option label="一次性" value="one_time" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="库存">
                            <el-input-number v-model="form.stock" :min="0" placeholder="0=不限" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="每人限购">
                            <el-input-number v-model="form.max_purchase_per_user" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="封面图">
                    <el-input v-model="form.image" placeholder="图片URL" maxlength="500" />
                </el-form-item>
                <el-form-item label="发布设置">
                    <el-checkbox v-model="form.is_active">上架</el-checkbox>
                    <el-checkbox v-model="form.is_featured">精选</el-checkbox>
                </el-form-item>

                <el-divider>套餐包含项目</el-divider>
                <div class="items-section">
                    <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
                        <el-row :gutter="8">
                            <el-col :span="7">
                                <el-input v-model="item.name" placeholder="项目名称" size="small" />
                            </el-col>
                            <el-col :span="5">
                                <el-input-number v-model="item.original_price" :min="0" :precision="2" placeholder="原价" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="4">
                                <el-input-number v-model="item.discount_percent" :min="0" :max="100" placeholder="折扣%" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="3">
                                <el-input-number v-model="item.quantity" :min="1" placeholder="数量" size="small" style="width: 100%" />
                            </el-col>
                            <el-col :span="3">
                                <el-select v-model="item.type" size="small" style="width: 100%">
                                    <el-option label="Plan" value="plan" />
                                    <el-option label="产品" value="product" />
                                    <el-option label="附加" value="addon" />
                                </el-select>
                            </el-col>
                            <el-col :span="2" style="text-align: right">
                                <el-button type="danger" :icon="Delete" size="small" circle @click="removeItem(idx)" />
                            </el-col>
                        </el-row>
                    </div>
                    <el-button type="primary" size="small" @click="addItem">+ 添加项目</el-button>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">
                    {{ editingId ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 购买对话框 -->
        <el-dialog v-model="purchaseDialogVisible" title="购买套餐" width="400px">
    <p>套餐：<strong>{{ purchasingBundle?.name }}</strong></p>
    <p>价格：<strong class="text-primary">{{ formatMoney(purchasingBundle?.bundle_price) }}</strong></p>
            <el-form :model="purchaseForm">
                <el-form-item label="客户ID">
                    <el-input-number v-model="purchaseForm.customer_id" :min="1" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="purchaseDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="purchasing" @click="submitPurchase">确认购买</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Delete } from '@element-plus/icons-vue'
import {
    getBundleList, getBundleStats, getBundleDetail,
    createBundle, updateBundle, deleteBundle,
    getBundlePurchases, purchaseBundle,
} from '@/api/bundles'

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

const rules = {
    name: [{ required: true, message: '请输入套餐名称', trigger: 'blur' }],
    bundle_price: [{ required: true, message: '请输入套餐价', trigger: 'blur' }],
}

const periodLabels = { monthly: '月付', yearly: '年付', one_time: '一次性' }
function periodLabel(p) { return periodLabels[p] || p }

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
    } catch { ElMessage.error('加载失败') }
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
    } catch { ElMessage.error('加载失败') }
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
    } catch { ElMessage.error('加载详情失败') }
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
            ElMessage.success('保存成功')
        } else {
            await createBundle(data)
            ElMessage.success('创建成功')
        }
        dialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally {
        submitting.value = false
    }
}

async function handleDelete(row) {
    try {
        await deleteBundle(row.id)
        ElMessage.success('已删除')
        loadList()
        loadStats()
    } catch { ElMessage.error('删除失败') }
}

function showPurchaseDialog(row) {
    purchasingBundle.value = row
    purchaseForm.value = { customer_id: null }
    purchaseDialogVisible.value = true
}

async function submitPurchase() {
    if (!purchaseForm.value.customer_id) {
        ElMessage.warning('请输入客户ID')
        return
    }
    purchasing.value = true
    try {
        await purchaseBundle(purchasingBundle.value.id, purchaseForm.value.customer_id)
        ElMessage.success('购买成功')
        purchaseDialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '购买失败')
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
.text-primary { color: #409eff; }
</style>
