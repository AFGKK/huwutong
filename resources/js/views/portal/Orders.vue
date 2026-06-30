<template>
    <div class="portal-orders-page">
        <div class="page-header">
            <div>
                <h2>我的订单</h2>
                <p class="text-muted">查看您的购买记录和订单状态</p>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-card"><div class="stat-value">{{ orders.length }}</div><div class="stat-label">全部订单</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top:3px solid #e6a23c">
                    <div class="stat-card"><div class="stat-value" style="color:#e6a23c">{{ pendingCount }}</div><div class="stat-label">待支付</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top:3px solid #67c23a">
                    <div class="stat-card"><div class="stat-value" style="color:#67c23a">{{ paidCount }}</div><div class="stat-label">已完成</div></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" style="border-top:3px solid #909399">
                    <div class="stat-card"><div class="stat-value" style="color:#909399">{{ cancelledCount }}</div><div class="stat-label">已取消/退款</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" size="small">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width:120px" @change="loadOrders">
                        <el-option label="待支付" value="pending" />
                        <el-option label="已支付" value="paid" />
                        <el-option label="已取消" value="cancelled" />
                        <el-option label="已退款" value="refunded" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadOrders">查询</el-button>
                </el-form-item>
                <el-form-item>
                    <el-button @click="$router.push('/portal/shop')">继续购物</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 订单列表 -->
        <el-card shadow="never" v-loading="loading">
            <el-empty v-if="!loading && !orders.length" description="暂无订单记录">
                <el-button type="primary" @click="$router.push('/portal/shop')">去商店看看</el-button>
            </el-empty>

            <template v-for="order in orders" :key="order.id">
            <div class="order-card">
                <div class="order-header">
                    <div class="order-meta">
                        <span class="order-no">订单 #{{ order.order_no || order.id }}</span>
                        <span class="order-date">{{ order.created_at }}</span>
                    </div>
                    <div class="order-status">
                        <el-tag :type="statusType(order.status)" size="small">{{ statusLabel(order.status) }}</el-tag>
                    </div>
                </div>

                <div class="order-items">
                    <div v-for="item in (order.items || [])" :key="item.id" class="order-item">
                        <div class="item-info">
                            <span class="item-name">{{ item.name || item.product_name || '商品' }}</span>
                            <span class="item-qty">×{{ item.quantity || 1 }}</span>
                        </div>
                        <span class="item-price">¥{{ Number(item.unit_price || item.price || 0).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="order-footer">
                    <div class="order-total">
                        合计: <strong class="total-price">¥{{ Number(order.final_amount || order.total_amount || 0).toFixed(2) }}</strong>
                    </div>
                    <div class="order-actions">
                        <el-button size="small" @click="viewDetail(order)">查看详情</el-button>
                        <el-button v-if="order.status === 'pending'" size="small" type="danger" plain @click="handleCancel(order)">取消订单</el-button>
                        <el-button v-if="order.status === 'paid'" size="small" type="primary" plain @click="goToLicense">查看 License</el-button>
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
        <el-drawer v-model="drawerVisible" :title="`订单 #${currentOrder?.order_no || currentOrder?.id || ''}`" size="500px">
            <template v-if="currentOrder">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="订单号">{{ currentOrder.order_no || currentOrder.id }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(currentOrder.status)" size="small">{{ statusLabel(currentOrder.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="商品总额">¥{{ Number(currentOrder.total_amount || 0).toFixed(2) }}</el-descriptions-item>
                    <el-descriptions-item label="实付金额">¥{{ Number(currentOrder.final_amount || 0).toFixed(2) }}</el-descriptions-item>
                    <el-descriptions-item label="支付方式">{{ currentOrder.payment_method || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ currentOrder.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="支付时间">{{ currentOrder.paid_at || '-' }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="mt-4">订单明细</h4>
                <el-table :data="currentOrder.items || []" stripe size="small">
                    <el-table-column prop="name" label="商品" />
                    <el-table-column prop="quantity" label="数量" width="60" />
                    <el-table-column prop="unit_price" label="单价" width="100">
                        <template #default="{ row }">¥{{ Number(row.unit_price || 0).toFixed(2) }}</template>
                    </el-table-column>
                    <el-table-column prop="subtotal" label="小计" width="100">
                        <template #default="{ row }">¥{{ Number(row.subtotal || (row.unit_price || 0) * (row.quantity || 1) || 0).toFixed(2) }}</template>
                    </el-table-column>
                </el-table>

                <h4 class="mt-4">发货记录</h4>
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
                                                <div class="font-medium" style="font-size:11px;">交付物：</div>
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
                                            <div class="font-medium" style="font-size:11px;">交付物：</div>
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
                                                <div class="font-medium" style="font-size:11px;">交付物：</div>
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
                <el-empty v-else description="暂无发货记录" :image-size="60" />
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import shopApi from '@/api/shop'

const loading = ref(false)
const orders = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)

const drawerVisible = ref(false)
const currentOrder = ref(null)

const filters = reactive({ status: '' })

const pendingCount = computed(() => orders.value.filter(o => o.status === 'pending').length)
const paidCount = computed(() => orders.value.filter(o => o.status === 'paid').length)
const cancelledCount = computed(() => orders.value.filter(o => ['cancelled', 'refunded', 'refunding'].includes(o.status)).length)

function statusType(status) {
    return { pending: 'warning', paid: 'success', cancelled: 'info', refunding: 'danger', refunded: 'danger' }[status] || 'info'
}
function statusLabel(status) {
    return { pending: '待支付', paid: '已完成', cancelled: '已取消', refunding: '退款中', refunded: '已退款' }[status] || status
}
function deliveryTypeLabel(t) {
    return { license_key: 'License Key', download_link: '下载链接', activation_code: '激活码', api_key: 'API Key', file_package: '文件包', service_activation: '服务激活' }[t] || t
}

function isJsonContent(str) {
    if (typeof str !== 'string') return false;
    try { JSON.parse(str); return true; } catch { return false; }
}

function parseContent(str) {
    try { return JSON.parse(str); } catch { return {}; }
}

function contentTypeLabel(key) {
    const labels = {
        license_id: 'License ID',
        license_key: 'License Key',
        activated: '激活状态',
        activated_at: '激活时间',
        delivered_at: '交付时间',
        order_id: '订单号',
        deliverables: '交付物',
        name: '名称',
        type: '类型',
        content: '内容',
        category: '分类',
        file_url: '文件链接',
        file_size: '文件大小',
        mime_type: '文件类型',
        description: '描述',
        original_name: '原始文件名',
    };
    return labels[key] || key;
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
        ElMessage.error('加载订单失败')
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

async function handleCancel(order) {
    try {
        await ElMessageBox.confirm('确定取消此订单？', '确认', { type: 'warning' })
        await shopApi.cancelOrder(order.id)
        ElMessage.success('订单已取消')
        loadOrders()
    } catch { /* ignore */ }
}

function goToLicense() {
    window.location.href = '/portal/licenses'
}

onMounted(loadOrders)
</script>

<style scoped>
.portal-orders-page { padding: 20px; max-width: 900px; margin: 0 auto; }
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
.order-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 8px; border-top: 1px solid #f0f0f0; }
.total-price { color: #f56c6c; font-size: 16px; }
.order-actions { display: flex; gap: 8px; }
</style>
