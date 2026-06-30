<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/promotions.js'

const activeTab = ref('promotions')
const loading = ref(false)
const stats = ref(null)

// ── 促销活动 ──
const promotions = ref([])
const promoPagination = ref({ total: 0, current_page: 1 })
const promoDialog = ref(false)
const promoForm = ref(emptyPromoForm())
const editPromoId = ref(null)

// ── 企业年框合同 ──
const contracts = ref([])
const contractPagination = ref({ total: 0, current_page: 1 })
const contractDialog = ref(false)
const contractForm = ref({ name: '', customer_id: null, total_value: null, currency: 'CNY', discount_rate: 0, start_date: '', end_date: '', licensed_items: [], notes: '' })
const contractDetail = ref(null)
const contractDetailVisible = ref(false)

// ── 优惠券 ──
const coupons = ref([])
const couponPagination = ref({ total: 0, current_page: 1 })
const couponDialog = ref(false)
const couponForm = ref({ code: '', name: '', type: 'percentage', value: null, usage_limit: null, starts_at: '', expires_at: null, applicable_plans: [] })

function emptyPromoForm() {
    return { name: '', type: 'flash_sale', description: '', discount_type: 'percentage', discount_value: null, max_discount: null, min_order_amount: null, usage_limit: null, usage_limit_per_customer: null, budget: null, starts_at: '', ends_at: null }
}

const typeOptions = [
    { value: 'flash_sale', label: '限时秒杀' },
    { value: 'bulk_discount', label: '批量折扣' },
    { value: 'bundle', label: '捆绑销售' },
    { value: 'x_for_y', label: '买X送Y' },
    { value: 'free_gift', label: '赠送礼品' },
    { value: 'tiered', label: '阶梯优惠' },
]

async function loadStats() {
    try { const res = await api.stats(); stats.value = res.data.data } catch (e) {}
}

async function loadPromotions(page = 1) {
    loading.value = true
    try {
        const res = await api.list({ page, per_page: 15 })
        const d = res.data.data
        promotions.value = d?.data || d || []
        promoPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function loadContracts(page = 1) {
    loading.value = true
    try {
        const res = await api.listContracts({ page, per_page: 15 })
        const d = res.data.data
        contracts.value = d?.data || d || []
        contractPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function loadCoupons(page = 1) {
    loading.value = true
    try {
        const res = await api.listCoupons({ page, per_page: 15 })
        const d = res.data.data
        coupons.value = d?.data || d || []
        couponPagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

function openPromoForm() { editPromoId.value = null; promoForm.value = emptyPromoForm(); promoDialog.value = true }
async function savePromotion() {
    try {
        await api.create(promoForm.value)
        ElMessage.success('促销活动已创建')
        promoDialog.value = false; loadPromotions(promoPagination.value.current_page); loadStats()
    } catch (e) { ElMessage.error('保存失败') }
}
async function publishPromotion(promo) {
    try { await api.publish(promo.id); ElMessage.success('已发布'); loadPromotions(promoPagination.value.current_page) }
    catch (e) { ElMessage.error('发布失败') }
}
async function pausePromotion(promo) {
    try { await api.pause(promo.id); ElMessage.success('已暂停'); loadPromotions(promoPagination.value.current_page) }
    catch (e) { ElMessage.error('操作失败') }
}

function openContractForm() { contractForm.value = { name: '', customer_id: null, total_value: null, currency: 'CNY', discount_rate: 0, start_date: '', end_date: '', licensed_items: [], notes: '' }; contractDialog.value = true }
async function saveContract() {
    try {
        await api.createContract(contractForm.value)
        ElMessage.success('合同已创建')
        contractDialog.value = false; loadContracts(contractPagination.value.current_page)
    } catch (e) { ElMessage.error('保存失败') }
}
async function showContractDetail(contract) {
    try { const res = await api.showContract(contract.id); contractDetail.value = res.data.data; contractDetailVisible.value = true }
    catch (e) {}
}
async function approveContract(contract, status) {
    try { await api.approveContract(contract.id, { status }); ElMessage.success(status === 'approved' ? '已批准' : '已拒绝'); loadContracts(contractPagination.value.current_page) }
    catch (e) { ElMessage.error('操作失败') }
}

function openCouponForm() { couponForm.value = { code: '', name: '', type: 'percentage', value: null, usage_limit: null, starts_at: '', expires_at: null, applicable_plans: [] }; couponDialog.value = true }
async function saveCoupon() {
    try {
        await api.createCoupon(couponForm.value)
        ElMessage.success('优惠券已创建')
        couponDialog.value = false; loadCoupons(couponPagination.value.current_page)
    } catch (e) { ElMessage.error('保存失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }
function fmtDateShort(d) { return d ? new Date(d).toLocaleDateString('zh-CN') : '-' }

onMounted(() => { loadStats(); loadPromotions(); loadContracts(); loadCoupons() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>商业运营</el-breadcrumb-item>
            <el-breadcrumb-item>促销系统</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">进行中促销</div><div class="stat-value">{{ stats.active || 0 }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">总折扣额</div><div class="stat-value">{{ (stats.total_discount_given || 0).toLocaleString() }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">活跃合同</div><div class="stat-value">{{ stats.active_contracts ?? 0 }}</div></el-card></el-col>
            <el-col :span="6"><el-card shadow="never"><div class="stat-label">可用优惠券</div><div class="stat-value">{{ stats.active_coupons || 0 }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="never">
            <el-tabs v-model="activeTab">
                <!-- 促销活动 -->
                <el-tab-pane label="促销活动" name="promotions">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-400">共 {{ promotions.length }} 个</span>
                        <el-button type="primary" @click="openPromoForm()">新建促销</el-button>
                    </div>
                    <el-table :data="promotions" v-loading="loading && activeTab === 'promotions'" stripe>
                        <el-table-column prop="name" label="名称" min-width="160" />
                        <el-table-column label="类型" width="100"><template #default="{ row }">{{ typeOptions.find(t => t.value === row.type)?.label || row.type }}</template></el-table-column>
                        <el-table-column label="折扣" width="100"><template #default="{ row }">{{ row.discount_type === 'percentage' ? (row.discount_value||0) + '%' : row.discount_type === 'fixed_amount' ? '¥' + (row.discount_value||0) : row.discount_type }}</template></el-table-column>
                        <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'paused' ? 'warning' : row.status === 'expired' ? 'danger' : 'info'" size="small">{{ {draft:'草稿',active:'活跃',paused:'已暂停',expired:'已过期',cancelled:'已取消'}[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="开始" width="120"><template #default="{ row }">{{ fmtDateShort(row.starts_at) }}</template></el-table-column>
                        <el-table-column label="结束" width="120"><template #default="{ row }">{{ row.ends_at ? fmtDateShort(row.ends_at) : '不限' }}</template></el-table-column>
                        <el-table-column label="使用/限制" width="100"><template #default="{ row }">{{ row.usage_count }}{{ row.usage_limit ? '/'+row.usage_limit : '' }}</template></el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openPromoForm()">编辑</el-button>
                                <el-button v-if="row.status === 'draft' || row.status === 'paused'" size="small" type="success" @click="publishPromotion(row)">发布</el-button>
                                <el-button v-if="row.status === 'active'" size="small" type="warning" @click="pausePromotion(row)">暂停</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="promoPagination.current_page" :page-size="15" :total="promoPagination.total" layout="prev,pager,next,total" @current-change="loadPromotions" /></div>

                    <el-dialog v-model="promoDialog" title="新建促销活动" width="600px">
                        <el-form :model="promoForm" label-width="120px">
                            <el-form-item label="名称"><el-input v-model="promoForm.name" /></el-form-item>
                            <el-form-item label="促销类型"><el-select v-model="promoForm.type" class="w-full"><el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" /></el-select></el-form-item>
                            <el-form-item label="描述"><el-input v-model="promoForm.description" type="textarea" :rows="2" /></el-form-item>
                            <el-row :gutter="12">
                                <el-col :span="8"><el-form-item label="折扣类型"><el-select v-model="promoForm.discount_type"><el-option label="百分比" value="percentage" /><el-option label="固定金额" value="fixed_amount" /><el-option label="免费" value="free" /></el-select></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="折扣值"><el-input v-model.number="promoForm.discount_value" type="number" /></el-form-item></el-col>
                                <el-col :span="8"><el-form-item label="最大折扣"><el-input v-model.number="promoForm.max_discount" type="number" placeholder="可选" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="最低金额"><el-input v-model.number="promoForm.min_order_amount" type="number" placeholder="可选" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="预算"><el-input v-model.number="promoForm.budget" type="number" placeholder="可选" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="使用上限"><el-input v-model.number="promoForm.usage_limit" type="number" placeholder="可选" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="每人上限"><el-input v-model.number="promoForm.usage_limit_per_customer" type="number" placeholder="可选" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="开始时间"><el-date-picker v-model="promoForm.starts_at" type="datetime" class="w-full" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="结束时间"><el-date-picker v-model="promoForm.ends_at" type="datetime" class="w-full" placeholder="可选" /></el-form-item></el-col>
                            </el-row>
                        </el-form>
                        <template #footer><el-button @click="promoDialog = false">取消</el-button><el-button type="primary" @click="savePromotion">创建</el-button></template>
                    </el-dialog>
                </el-tab-pane>

                <!-- 企业年框合同 -->
                <el-tab-pane label="企业年框合同" name="contracts">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-400">共 {{ contracts.length }} 份</span>
                        <el-button type="primary" @click="openContractForm()">新建合同</el-button>
                    </div>
                    <el-table :data="contracts" v-loading="loading && activeTab === 'contracts'" stripe>
                        <el-table-column prop="contract_number" label="编号" width="140" />
                        <el-table-column prop="name" label="名称" min-width="150" />
                        <el-table-column label="客户" width="120"><template #default="{ row }">{{ row.customer?.name || '-' }}</template></el-table-column>
                        <el-table-column label="金额" width="110"><template #default="{ row }">¥{{ row.total_value?.toLocaleString() }}</template></el-table-column>
                        <el-table-column label="折扣" width="60"><template #default="{ row }">{{ row.discount_rate }}%</template></el-table-column>
                        <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'expired' ? 'danger' : row.status === 'pending_approval' ? 'warning' : 'info'" size="small">{{ {draft:'草稿',pending_approval:'待审批',active:'活跃',expired:'已过期',terminated:'已终止'}[row.status] }}</el-tag></template></el-table-column>
                        <el-table-column label="期限" width="180"><template #default="{ row }">{{ fmtDateShort(row.start_date) }} ~ {{ fmtDateShort(row.end_date) }}</template></el-table-column>
                        <el-table-column label="操作" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="showContractDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'draft'" size="small" @click="openContractForm()">编辑</el-button>
                                <el-button v-if="row.status === 'pending_approval'" size="small" type="success" @click="approveContract(row, 'approved')">批准</el-button>
                                <el-button v-if="row.status === 'pending_approval'" size="small" type="danger" @click="approveContract(row, 'rejected')">拒绝</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="contractPagination.current_page" :page-size="15" :total="contractPagination.total" layout="prev,pager,next,total" @current-change="loadContracts" /></div>

                    <el-dialog v-model="contractDetailVisible" title="合同详情" width="700px">
                        <div v-if="contractDetail">
                            <el-descriptions :column="2" border size="small">
                                <el-descriptions-item label="编号">{{ contractDetail.contract_number }}</el-descriptions-item>
                                <el-descriptions-item label="名称">{{ contractDetail.name }}</el-descriptions-item>
                                <el-descriptions-item label="客户">{{ contractDetail.customer?.name }}</el-descriptions-item>
                                <el-descriptions-item label="总金额">¥{{ contractDetail.total_value?.toLocaleString() }}</el-descriptions-item>
                                <el-descriptions-item label="折扣率">{{ contractDetail.discount_rate }}%</el-descriptions-item>
                                <el-descriptions-item label="状态">{{ contractDetail.status }}</el-descriptions-item>
                                <el-descriptions-item label="有效期">{{ fmtDateShort(contractDetail.start_date) }} ~ {{ fmtDateShort(contractDetail.end_date) }}</el-descriptions-item>
                                <el-descriptions-item label="自动续签">{{ contractDetail.auto_renew ? '是' : '否' }}</el-descriptions-item>
                            </el-descriptions>
                            <div v-if="contractDetail.licensed_items?.length" class="mt-3"><el-divider>授权项</el-divider><div v-for="(item, i) in contractDetail.licensed_items" :key="i" class="mb-1 text-sm">{{ item.name }} x{{ item.quantity }} @ ¥{{ item.unit_price }}</div></div>
                        </div>
                    </el-dialog>

                    <el-dialog v-model="contractDialog" title="新建企业年框合同" width="600px">
                        <el-form :model="contractForm" label-width="120px">
                            <el-form-item label="名称"><el-input v-model="contractForm.name" /></el-form-item>
                            <el-form-item label="客户 ID"><el-input v-model.number="contractForm.customer_id" type="number" /></el-form-item>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="合同金额"><el-input v-model.number="contractForm.total_value" type="number" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="折扣率 %"><el-input v-model.number="contractForm.discount_rate" type="number" /></el-form-item></el-col>
                            </el-row>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="开始日期"><el-date-picker v-model="contractForm.start_date" type="date" class="w-full" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="结束日期"><el-date-picker v-model="contractForm.end_date" type="date" class="w-full" /></el-form-item></el-col>
                            </el-row>
                            <el-form-item label="备注"><el-input v-model="contractForm.notes" type="textarea" :rows="2" /></el-form-item>
                        </el-form>
                        <template #footer><el-button @click="contractDialog = false">取消</el-button><el-button type="primary" @click="saveContract">创建</el-button></template>
                    </el-dialog>
                </el-tab-pane>

                <!-- 优惠券管理 -->
                <el-tab-pane label="优惠券管理" name="coupons">
                    <div class="flex justify-between mb-3">
                        <span class="text-sm text-gray-400">共 {{ coupons.length }} 张</span>
                        <el-button type="primary" @click="openCouponForm()">新建优惠券</el-button>
                    </div>
                    <el-table :data="coupons" v-loading="loading && activeTab === 'coupons'" stripe>
                        <el-table-column prop="code" label="优惠码" width="120" />
                        <el-table-column prop="name" label="名称" min-width="140" />
                        <el-table-column label="类型" width="90"><template #default="{ row }">{{ {percentage:'百分比',fixed_amount:'固定金额',free_trial:'免费试用'}[row.type] || row.type }}</template></el-table-column>
                        <el-table-column label="值" width="80"><template #default="{ row }">{{ row.type === 'percentage' ? row.value+'%' : '¥'+row.value }}</template></el-table-column>
                        <el-table-column label="状态" width="70"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                        <el-table-column label="使用/上限" width="90"><template #default="{ row }">{{ row.usage_count || 0 }}{{ row.usage_limit ? '/'+row.usage_limit : '' }}</template></el-table-column>
                        <el-table-column label="有效期" width="180"><template #default="{ row }">{{ fmtDateShort(row.starts_at) }} ~ {{ row.expires_at ? fmtDateShort(row.expires_at) : '不限' }}</template></el-table-column>
                        <el-table-column label="优先级" width="60"><template #default="{ row }">{{ row.priority || 0 }}</template></el-table-column>
                    </el-table>
                    <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="couponPagination.current_page" :page-size="15" :total="couponPagination.total" layout="prev,pager,next,total" @current-change="loadCoupons" /></div>

                    <el-dialog v-model="couponDialog" title="新建优惠券" width="500px">
                        <el-form :model="couponForm" label-width="110px">
                            <el-form-item label="优惠码"><el-input v-model="couponForm.code" placeholder="留空自动生成" /></el-form-item>
                            <el-form-item label="名称"><el-input v-model="couponForm.name" /></el-form-item>
                            <el-form-item label="类型"><el-select v-model="couponForm.type"><el-option label="百分比" value="percentage" /><el-option label="固定金额" value="fixed_amount" /><el-option label="免费试用" value="free_trial" /></el-select></el-form-item>
                            <el-form-item label="值"><el-input v-model.number="couponForm.value" type="number" /></el-form-item>
                            <el-form-item label="使用上限"><el-input v-model.number="couponForm.usage_limit" type="number" placeholder="可选" /></el-form-item>
                            <el-row :gutter="12">
                                <el-col :span="12"><el-form-item label="开始时间"><el-date-picker v-model="couponForm.starts_at" type="datetime" class="w-full" /></el-form-item></el-col>
                                <el-col :span="12"><el-form-item label="到期时间"><el-date-picker v-model="couponForm.expires_at" type="datetime" class="w-full" placeholder="可选" /></el-form-item></el-col>
                            </el-row>
                        </el-form>
                        <template #footer><el-button @click="couponDialog = false">取消</el-button><el-button type="primary" @click="saveCoupon">创建</el-button></template>
                    </el-dialog>
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
</style>
