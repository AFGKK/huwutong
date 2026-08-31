<template>
    <div class="portal-orders-page">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.orders_title') }}</h2>
                <p class="text-muted">{{ $t('portal.orders_subtitle') }}</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="never">
                    <div class="stat-card"><div class="stat-value">{{ orders.length }}</div><div class="stat-label">{{ $t('portal.orders_all') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="never" style="border-top:3px solid #e6a23c">
                    <div class="stat-card"><div class="stat-value" style="color:#e6a23c">{{ pendingCount }}</div><div class="stat-label">{{ $t('portal.inv_pending') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="never" style="border-top:3px solid #67c23a">
                    <div class="stat-card"><div class="stat-value" style="color:#67c23a">{{ paidCount }}</div><div class="stat-label">{{ $t('portal.orders_completed') }}</div></div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="never" style="border-top:3px solid #909399">
                    <div class="stat-card"><div class="stat-value" style="color:#909399">{{ cancelledCount }}</div><div class="stat-label">{{ $t('portal.orders_cancelled_refund') }}</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4 filter-card">
            <el-form :inline="!isMobile" size="small" class="orders-filter-form">
                <el-form-item :label="$t('portal.status')">
                    <el-select v-model="filters.status" clearable :placeholder="$t('portal.all')" style="width:120px" @change="loadOrders">
                        <el-option :label="$t('portal.inv_pending')" value="pending" />
                        <el-option :label="$t('portal.order_paid')" value="paid" />
                        <el-option :label="$t('portal.inv_cancelled')" value="cancelled" />
                        <el-option :label="$t('portal.inv_refunded')" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadOrders">{{ $t('portal.query') }}</el-button>
                </el-form-item>
                <el-form-item>
                    <el-button @click="$router.push('/portal/shop')">{{ $t('portal.continue_shopping') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 订单列表 -->
        <el-card shadow="never" v-loading="loading">
            <el-empty v-if="!loading && !orders.length" :description="$t('portal.no_orders')">
                <el-button type="primary" @click="$router.push('/portal/shop')">{{ $t('portal.go_shop') }}</el-button>
            </el-empty>

            <template v-for="order in orders" :key="order.id">
            <div class="order-card">
                <div class="order-header">
                    <div class="order-meta">
                        <span class="order-no">{{ $t('portal.order_n', { no: order.order_no || order.id }) }}</span>
                        <span class="order-date">{{ order.created_at }}</span>
                    </div>
                    <div class="order-status">
                        <el-tag :type="statusType(order.status)" size="small">{{ statusLabel(order.status) }}</el-tag>
                    </div>
                </div>

                <div class="order-items">
                    <div v-for="item in (order.items || [])" :key="item.id" class="order-item">
                        <div class="item-info">
                            <span class="item-name">{{ item.name || item.product_name || $t('portal.product_item') }}</span>
                            <span class="item-qty">×{{ item.quantity || 1 }}</span>
                        </div>
                        <span class="item-price">¥{{ Number(item.unit_price || item.price || 0).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="order-footer">
                    <div class="order-total">
                        {{ $t('portal.total_label') }} <strong class="total-price">¥{{ Number(order.final_amount || order.total_amount || 0).toFixed(2) }}</strong>
                    </div>
                    <div class="order-actions">
                        <el-button size="small" @click="viewDetail(order)">{{ $t('portal.view_detail') }}</el-button>
                        <el-button size="small" :loading="contactingSeller" @click="contactSeller(order)">{{ $t('portal.contact_seller') }}</el-button>
                        <el-button v-if="order.status === 'pending'" size="small" type="danger" plain @click="handleCancel(order)">{{ $t('portal.cancel_order') }}</el-button>
                        <el-button v-if="order.status === 'paid'" size="small" type="warning" plain @click="openRefundDialog(order)">{{ $t('portal.request_refund') }}</el-button>
                        <el-button v-if="order.status === 'paid'" size="small" type="primary" plain @click="goToLicense">{{ $t('portal.view_licenses') }}</el-button>
                    </div>
                </div>
            </div>
            </template>

            <div class="mt-4 flex-center" v-if="total > perPage">
                <el-pagination
                    v-model:current-page="page"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="loadOrders"
                    background
                    small
                />
            </div>
        </el-card>

        <!-- 详情抽屉 -->
        <el-drawer v-model="drawerVisible" :title="drawerTitle" :size="isMobile ? '100%' : '500px'">
            <template v-if="currentOrder">
                <el-descriptions :column="isMobile ? 1 : 2" border size="small">
                    <el-descriptions-item :label="$t('portal.order_no')">{{ currentOrder.order_no || currentOrder.id }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.status')">
                        <el-tag :type="statusType(currentOrder.status)" size="small">{{ statusLabel(currentOrder.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.goods_total')">¥{{ Number(currentOrder.total_amount || 0).toFixed(2) }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.paid_amount')">¥{{ Number(currentOrder.final_amount || 0).toFixed(2) }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.pay_method')">{{ currentOrder.payment_method || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.created_at')">{{ currentOrder.created_at }}</el-descriptions-item>
                    <el-descriptions-item :label="$t('portal.paid_at')">{{ currentOrder.paid_at || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-4">
                    <el-button type="primary" :loading="contactingSeller" @click="contactSeller(currentOrder)">{{ $t('portal.contact_seller') }}</el-button>
                </div>

                <h4 class="mt-4">{{ $t('portal.order_items') }}</h4>
                <div class="table-scroll-wrap">
                <el-table :data="currentOrder.items || []" stripe size="small">
                    <el-table-column prop="name" :label="$t('portal.product_item')" />
                    <el-table-column prop="quantity" :label="$t('portal.qty')" width="60" />
                    <el-table-column prop="unit_price" :label="$t('portal.unit_price')" width="100">
                        <template #default="{ row }">¥{{ Number(row.unit_price || 0).toFixed(2) }}</template>
                    </el-table-column>
                    <el-table-column prop="subtotal" :label="$t('portal.subtotal')" width="100">
                        <template #default="{ row }">¥{{ Number(row.subtotal || (row.unit_price || 0) * (row.quantity || 1) || 0).toFixed(2) }}</template>
                    </el-table-column>
                </el-table>
                </div>

                <h4 class="mt-4">{{ $t('portal.deliveries') }}</h4>
                <div v-if="currentOrder.deliveries?.length">
                    <el-timeline>
                        <el-timeline-item
                            v-for="d in currentOrder.deliveries"
                            :key="d.id"
                            :timestamp="d.sent_at || d.created_at"
                            :type="d.status === 'delivered' ? 'success' : 'primary'"
                        >
                            <p>{{ deliveryTypeLabel(d.delivery_type) }} — {{ d.delivery_channel }}</p>
                            <div v-if="d.content" style="font-size:12px;color:#909399;margin-top:4px">
                                <template v-if="Array.isArray(d.content)">
                                    <div v-for="(item, idx) in d.content" :key="idx" class="mb-2" style="border-left:2px solid #e4e7ed;padding-left:8px;">
                                        <template v-for="(v, k) in item" :key="k">
                                            <div v-if="k === 'deliverables' && Array.isArray(v)" class="mt-1">
                                                <div class="font-medium" style="font-size:11px;">{{ $t('portal.deliverables') }}</div>
                                                <div v-for="(dItem, dIdx) in v" :key="dIdx" class="ml-2 mt-1" style="border-left:2px solid #e4e7ed;padding-left:8px;">
                                                    <p v-for="(dv, dk) in dItem" :key="dk"><span class="font-medium">{{ contentTypeLabel(dk) }}:</span> {{ dv ?? '-' }}</p>
                                                </div>
                                            </div>
                                            <p v-else><span class="font-medium">{{ contentTypeLabel(k) }}:</span> {{ v ?? '-' }}</p>
                                        </template>
                                    </div>
                                </template>
                                <template v-else-if="typeof d.content === 'object'">
                                    <template v-for="(val, key) in d.content" :key="key">
                                        <div v-if="key === 'deliverables' && Array.isArray(val)" class="mt-1">
                                            <div class="font-medium" style="font-size:11px;">{{ $t('portal.deliverables') }}</div>
                                            <div v-for="(item, idx) in val" :key="idx" class="ml-2 mt-1" style="border-left:2px solid #e4e7ed;padding-left:8px;">
                                                <p v-for="(v, k) in item" :key="k"><span class="font-medium">{{ contentTypeLabel(k) }}:</span> {{ v ?? '-' }}</p>
                                            </div>
                                        </div>
                                        <p v-else><span class="font-medium">{{ contentTypeLabel(key) }}:</span> {{ val ?? '-' }}</p>
                                    </template>
                                </template>
                                <template v-else-if="isJsonContent(d.content)">
                                    <div v-for="(item, idx) in parseContent(d.content)" :key="idx" class="mb-2" style="border-left:2px solid #e4e7ed;padding-left:8px;">
                                        <template v-for="(v, k) in item" :key="k">
                                            <div v-if="k === 'deliverables' && Array.isArray(v)" class="mt-1">
                                                <div class="font-medium" style="font-size:11px;">{{ $t('portal.deliverables') }}</div>
                                                <div v-for="(dItem, dIdx) in v" :key="dIdx" class="ml-2 mt-1" style="border-left:2px solid #e4e7ed;padding-left:8px;">
                                                    <p v-for="(dv, dk) in dItem" :key="dk"><span class="font-medium">{{ contentTypeLabel(dk) }}:</span> {{ dv ?? '-' }}</p>
                                                </div>
                                            </div>
                                            <p v-else><span class="font-medium">{{ contentTypeLabel(k) }}:</span> {{ v ?? '-' }}</p>
                                        </template>
                                    </div>
                                </template>
                                <p v-else>{{ d.content }}</p>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
                <el-empty v-else :description="$t('portal.no_deliveries')" :image-size="60" />
            </template>
        </el-drawer>

        <el-dialog v-model="refundDialog.visible" :title="$t('portal.request_refund')" :width="isMobile ? '92%' : '480px'">
            <el-form label-position="top">
                <el-form-item :label="$t('portal.refund_reason')" required>
                    <el-input v-model="refundDialog.reason" type="textarea" :rows="4" maxlength="500" :placeholder="$t('portal.refund_reason_ph')" />
                </el-form-item>
                <el-form-item :label="$t('portal.refund_notes')">
                    <el-input v-model="refundDialog.notes" type="textarea" :rows="2" maxlength="2000" :placeholder="$t('portal.optional')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="refundDialog.visible = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="refundDialog.submitting" @click="submitRefund">{{ $t('portal.submit_request') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useResponsive } from '@/composables/useResponsive'
import shopApi from '@/api/shop'
import apiClient from '@/api/client'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const { isMobile } = useResponsive()
const router = useRouter()

const loading = ref(false)
const orders = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)

const drawerVisible = ref(false)
const currentOrder = ref(null)

const refundDialog = reactive({
    visible: false,
    submitting: false,
    orderId: null,
    reason: '',
    notes: '',
})

const filters = reactive({ status: '' })

const pendingCount = computed(() => orders.value.filter(o => o.status === 'pending').length)
const paidCount = computed(() => orders.value.filter(o => o.status === 'paid').length)
const cancelledCount = computed(() => orders.value.filter(o => ['cancelled', 'refunded', 'refunding'].includes(o.status)).length)

const drawerTitle = computed(() => {
    if (!currentOrder.value) return ''
    return t('portal.order_n', { no: currentOrder.value.order_no || currentOrder.value.id || '' })
})

function statusType(status) {
    return { pending: 'warning', paid: 'success', cancelled: 'info', refunding: 'danger', refunded: 'danger' }[status] || 'info'
}
function statusLabel(status) {
    const map = {
        pending: t('portal.inv_pending'),
        paid: t('portal.orders_completed'),
        cancelled: t('portal.inv_cancelled'),
        refunding: t('portal.order_refunding'),
        refunded: t('portal.inv_refunded'),
    }
    return map[status] || status
}
function deliveryTypeLabel(type) {
    const map = {
        license_key: 'License Key',
        download_link: t('portal.dl_download_link'),
        activation_code: t('portal.dl_activation_code'),
        api_key: 'API Key',
        file_package: t('portal.dl_file_package'),
        service_activation: t('portal.dl_service_activation'),
    }
    return map[type] || type
}

function isJsonContent(str) {
    if (typeof str !== 'string') return false;
    try { JSON.parse(str); return true; } catch { return false; }
}

function parseContent(str) {
    try { return JSON.parse(str); } catch { return {}; }
}

function contentTypeLabel(key) {
    const map = {
        license_id: 'License ID',
        license_key: 'License Key',
        activated: t('portal.ct_activated'),
        activated_at: t('portal.ct_activated_at'),
        delivered_at: t('portal.ct_delivered_at'),
        order_id: t('portal.order_no'),
        name: t('portal.ct_name'),
        type: t('portal.type'),
        content: t('portal.ct_content'),
        category: t('portal.category'),
        file_url: t('portal.ct_file_url'),
        file_size: t('portal.ct_file_size'),
        mime_type: t('portal.ct_mime_type'),
        description: t('portal.description'),
        original_name: t('portal.ct_original_name'),
    };
    return map[key] || key;
}

async function loadOrders() {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filters.status) params.status = filters.status
        const res = await shopApi.listOrders(params)
        const items = res.data?.data || []
        orders.value = Array.isArray(items) ? items : (items.data || [])
        total.value = res.data?.meta?.total || orders.value.length
    } catch {
        ElMessage.error(t('portal.orders_load_failed'))
    } finally { loading.value = false }
}

async function viewDetail(order) {
    try {
        const res = await shopApi.getOrder(order.id)
        currentOrder.value = res.data?.data || res.data || order
        drawerVisible.value = true
    } catch {
        currentOrder.value = order
        drawerVisible.value = true
    }
}

const contactingSeller = ref(false)
async function contactSeller(order) {
    if (!order?.id || contactingSeller.value) return
    contactingSeller.value = true
    try {
        const res = await apiClient.post('/user-chat/order-inquiry', { order_id: order.id })
        const conv = res.data?.data?.conversation
        ElMessage.success(t('portal.order_inquiry_sent'))
        if (conv?.id) {
            router.push({ name: 'UserChat', query: { conv: String(conv.id) } })
        } else {
            router.push({ name: 'UserChat' })
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.order_inquiry_failed'))
    } finally {
        contactingSeller.value = false
    }
}

async function handleCancel(order) {
    try {
        await ElMessageBox.confirm(t('portal.cancel_order_confirm'), t('actions.confirm'), { type: 'warning' })
        await shopApi.cancelOrder(order.id)
        ElMessage.success(t('portal.order_cancelled'))
        loadOrders()
    } catch { /* ignore */ }
}

function goToLicense() {
    window.location.href = '/portal/licenses'
}

function openRefundDialog(order) {
    refundDialog.orderId = order.id
    refundDialog.reason = ''
    refundDialog.notes = ''
    refundDialog.visible = true
}

async function submitRefund() {
    if (!refundDialog.reason.trim()) {
        ElMessage.warning(t('portal.refund_reason_required'))
        return
    }
    refundDialog.submitting = true
    try {
        await shopApi.requestRefund({
            order_id: refundDialog.orderId,
            reason: refundDialog.reason,
            notes: refundDialog.notes || undefined,
        })
        ElMessage.success(t('portal.refund_submitted'))
        refundDialog.visible = false
        loadOrders()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('portal.submit_failed'))
    } finally {
        refundDialog.submitting = false
    }
}

onMounted(loadOrders)
</script>

<style scoped>
.portal-orders-page { padding: 20px; max-width: 900px; margin: 0 auto; min-width: 0; overflow-x: clip; }
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 22px; font-weight: bold; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 13px; }
.flex-center { display: flex; justify-content: center; }
.order-card { border: 1px solid #e4e7ed; border-radius: 8px; padding: 16px; margin-bottom: 12px; background: #fff; }
.order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.order-meta { display: flex; gap: 12px; align-items: center; }
.order-no { font-weight: 600; font-size: 14px; }
.order-date { font-size: 12px; color: #909399; }
.order-items { border-top: 1px solid #f0f0f0; padding-top: 8px; }
.order-item { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.item-name { color: #303133; }
.item-qty { color: #909399; margin-left: 4px; }
.item-price { color: #303133; }
.order-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 8px; border-top: 1px solid #f0f0f0; flex-wrap: wrap; gap: 12px; }
.total-price { color: #f56c6c; font-size: 16px; }
.order-actions { display: flex; gap: 8px; flex-wrap: wrap; }

@media (max-width: 768px) {
    .portal-orders-page { padding: 12px; }
    .order-header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .order-meta { flex-direction: column; align-items: flex-start; gap: 4px; }
    .order-footer { flex-direction: column; align-items: stretch; }
    .order-actions { width: 100%; }
    .order-actions .el-button { flex: 1; min-width: 0; margin-left: 0 !important; }
    .orders-filter-form :deep(.el-form-item) { margin-right: 0; width: 100%; }
    .orders-filter-form :deep(.el-select) { width: 100% !important; }
}
</style>
