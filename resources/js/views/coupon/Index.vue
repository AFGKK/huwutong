<template>
    <div class="coupon-management">
        <div class="page-header">
            <div>
                <h2>优惠券管理</h2>
                <p class="text-muted">创建和管理优惠券、查看使用统计数据</p>
            </div>
            <el-button type="primary" @click="openCreateDialog">
                <el-icon><Plus /></el-icon> 新建优惠券
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.total }}</div>
                        <div class="stat-label">优惠券总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #67c23a;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #67c23a;">{{ stats.active }}</div>
                        <div class="stat-label">生效中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #e6a23c;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #e6a23c;">{{ stats.total_redemptions || 0 }}</div>
                        <div class="stat-label">总使用次数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top: 3px solid #f56c6c;">
                    <div class="stat-card">
                        <div class="stat-value" style="color: #f56c6c;">¥{{ formatPrice(stats.total_discount || 0) }}</div>
                        <div class="stat-label">总优惠金额</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small" @keyup.enter="doSearch">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width: 120px">
                        <el-option label="生效中" value="active" />
                        <el-option label="已过期" value="expired" />
                        <el-option label="已停用" value="disabled" />
                        <el-option label="待生效" value="pending" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="filters.type" clearable placeholder="全部" style="width: 120px">
                        <el-option label="百分比" value="percentage" />
                        <el-option label="固定金额" value="fixed" />
                        <el-option label="免费试用" value="free_trial" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="优惠码/名称" clearable style="width: 180px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 优惠券列表 -->
        <el-card shadow="never">
            <el-table :data="coupons" v-loading="loading" stripe>
                <el-table-column prop="code" label="优惠码" width="140">
                    <template #default="{ row }">
                        <el-tag effect="dark" size="small">{{ row.code }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="名称" width="140" />
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">{{ typeLabel(row.type) }}</template>
                </el-table-column>
                <el-table-column prop="value" label="优惠值" width="100">
                    <template #default="{ row }">
                        {{ row.type === 'percentage' ? `${row.value}%` : `¥${row.value}` }}
                    </template>
                </el-table-column>
                <el-table-column label="使用次数" width="110">
                    <template #default="{ row }">
                        {{ row.usage_count || 0 }}/{{ row.usage_limit || '∞' }}
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row)" size="small">{{ statusLabel(row) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="有效期" width="200">
                    <template #default="{ row }">
                        {{ row.starts_at ? row.starts_at.substring(0, 10) : '-' }} ~
                        {{ row.expires_at ? row.expires_at.substring(0, 10) : '永久' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button type="primary" link size="small" @click="openEditDialog(row)">编辑</el-button>
                        <el-button type="danger" link size="small" @click="viewRedemptions(row)">使用记录</el-button>
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
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑优惠券' : '新建优惠券'" width="600px">
            <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
                <el-form-item label="优惠码" prop="code">
                    <el-input v-model="form.code" placeholder="唯一优惠码，如 SUMMER2026" :disabled="isEdit">
                        <template #append>
                            <el-button @click="generateCode">随机生成</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item label="名称" prop="name">
                    <el-input v-model="form.name" placeholder="如：夏季大促八折" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="form.description" type="textarea" :rows="2" placeholder="内部备注说明" />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="类型" prop="type">
                            <el-select v-model="form.type" style="width:100%">
                                <el-option label="百分比折扣" value="percentage" />
                                <el-option label="固定金额" value="fixed" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="优惠值" prop="value">
                            <el-input v-model.number="form.value" :min="0" type="number">
                                <template #append>{{ form.type === 'percentage' ? '%' : '¥' }}</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="最低订单额">
                            <el-input v-model.number="form.minimum_order_amount" :min="0" type="number" placeholder="0 不限制">
                                <template #append>¥</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="最高折扣">
                            <el-input v-model.number="form.maximum_discount" :min="0" type="number" placeholder="0 不限制">
                                <template #append>¥</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="总使用限制">
                            <el-input v-model.number="form.usage_limit" :min="0" type="number" placeholder="0 不限制" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="每人限制">
                            <el-input v-model.number="form.usage_limit_per_user" :min="0" type="number" placeholder="0 不限制" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="生效时间" prop="starts_at">
                            <el-date-picker v-model="form.starts_at" type="datetime" placeholder="立即生效" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="过期时间" prop="expires_at">
                            <el-date-picker v-model="form.expires_at" type="datetime" placeholder="永不过期" style="width:100%" value-format="YYYY-MM-DD HH:mm:ss" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="优惠券详情" width="500px">
            <template v-if="currentCoupon">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="优惠码">{{ currentCoupon.code }}</el-descriptions-item>
                    <el-descriptions-item label="名称">{{ currentCoupon.name }}</el-descriptions-item>
                    <el-descriptions-item label="类型">{{ typeLabel(currentCoupon.type) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(currentCoupon)" size="small">{{ statusLabel(currentCoupon) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="优惠值">{{ currentCoupon.type === 'percentage' ? `${currentCoupon.value}%` : `¥${currentCoupon.value}` }}</el-descriptions-item>
                    <el-descriptions-item label="使用次数">{{ currentCoupon.usage_count || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="最低订单额">¥{{ currentCoupon.minimum_order_amount || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="最高折扣">¥{{ currentCoupon.maximum_discount || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="生效时间">{{ currentCoupon.starts_at || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="过期时间">{{ currentCoupon.expires_at || '永久' }}</el-descriptions-item>
                </el-descriptions>
                <div v-if="currentCoupon.description" class="mt-4">
                    <strong>描述：</strong>
                    <p>{{ currentCoupon.description }}</p>
                </div>
            </template>
        </el-dialog>

        <!-- 使用记录对话框 -->
        <el-dialog v-model="redemptionVisible" :title="`使用记录 - ${currentCoupon?.code || ''}`" width="700px">
            <el-table :data="redemptions" v-loading="redemptionLoading" stripe size="small">
                <el-table-column prop="customer_id" label="客户ID" width="80" />
                <el-table-column prop="order_id" label="订单号" width="80" />
                <el-table-column prop="original_amount" label="原金额" width="100">
                    <template #default="{ row }">¥{{ row.original_amount }}</template>
                </el-table-column>
                <el-table-column prop="discount_amount" label="优惠额" width="100">
                    <template #default="{ row }">-¥{{ row.discount_amount }}</template>
                </el-table-column>
                <el-table-column prop="final_amount" label="实付" width="100">
                    <template #default="{ row }">¥{{ row.final_amount }}</template>
                </el-table-column>
                <el-table-column prop="created_at" label="使用时间" width="170" />
            </el-table>
            <el-empty v-if="!redemptionLoading && !redemptions.length" description="暂无使用记录" />
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import billingApi from '@/api/billing'

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
    starts_at: '',
    expires_at: '',
})

const rules = {
    code: [{ required: true, message: '请输入优惠码', trigger: 'blur' }],
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
    value: [{ required: true, type: 'number', min: 0.01, message: '请输入有效的优惠值', trigger: 'blur' }],
}

function typeLabel(type) {
    return { percentage: '百分比', fixed: '固定金额', free_trial: '免费试用' }[type] || type
}

function statusLabel(coupon) {
    const now = new Date()
    if (coupon.status === 'disabled') return '已停用'
    if (coupon.expires_at && new Date(coupon.expires_at) < now) return '已过期'
    if (coupon.starts_at && new Date(coupon.starts_at) > now) return '待生效'
    return '生效中'
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
        ElMessage.error('加载优惠券列表失败')
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
    form.starts_at = ''
    form.expires_at = ''
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
        starts_at: row.starts_at || '',
        expires_at: row.expires_at || '',
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
            ElMessage.success('优惠券已更新')
        } else {
            await billingApi.createCoupon(form)
            ElMessage.success('优惠券已创建')
        }
        dialogVisible.value = false
        loadCoupons()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
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

onMounted(() => {
    loadStats()
    loadCoupons()
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
